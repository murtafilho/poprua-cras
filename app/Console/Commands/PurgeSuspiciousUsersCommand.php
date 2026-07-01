<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeSuspiciousUsersCommand extends Command
{
    protected $signature = 'users:purge-suspicious
        {--dry-run : Apenas lista os usuarios que seriam removidos}
        {--confirm : Executa a remocao (sem isso, dry-run implicito)}';

    protected $description = 'Remove contas de pentest/scanner criadas em jun/2026 (e claude.test@interno.local)';

    /**
     * IDs e padroes das contas injetadas em testes de seguranca (jun/2026).
     * Legitimos preservados: 26-28 (Iara, Raquel), 81 (Wendy).
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run') || ! $this->option('confirm');

        $ids = DB::table('users')
            ->where(function ($q) {
                $q->where('id', 25)
                    ->orWhereBetween('id', [29, 80]);
            })
            ->orderBy('id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('Nenhum usuario suspeito encontrado.');

            return self::SUCCESS;
        }

        $users = DB::table('users')->whereIn('id', $ids)->orderBy('id')->get(['id', 'name', 'email']);
        $vistorias = DB::table('vistorias')->whereIn('user_id', $ids)->count();

        $this->warn("Usuarios suspeitos: {$users->count()}");
        $this->table(['id', 'name', 'email'], $users->map(fn ($u) => [(string) $u->id, $u->name, $u->email])->all());
        $this->line("Vistorias a remover: {$vistorias}");

        if ($dryRun) {
            $this->comment('Dry-run — use --confirm para executar.');

            return self::SUCCESS;
        }

        if (! $this->option('no-interaction') && ! $this->confirm('Remover permanentemente estes usuarios e vistorias vinculadas?', false)) {
            return self::SUCCESS;
        }

        $count = $users->count();

        DB::transaction(function () use ($ids) {
            $idList = $ids->all();

            DB::table('vistorias')->whereIn('user_id', $idList)->delete();

            DB::table('model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->whereIn('model_id', $idList)
                ->delete();

            DB::table('model_has_permissions')
                ->where('model_type', 'App\Models\User')
                ->whereIn('model_id', $idList)
                ->delete();

            DB::table('sessions')->whereIn('user_id', $idList)->delete();

            DB::table('users')->whereIn('id', $idList)->delete();
        });

        $this->info("Removidos {$count} usuarios suspeitos.");

        return self::SUCCESS;
    }
}
