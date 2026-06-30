<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Parametro;
use App\Models\VistoriaRascunho;
use App\Services\VistoriaRascunhoService;
use Illuminate\Console\Command;

class LimparRascunhosExpiradosCommand extends Command
{
    protected $signature = 'rascunhos:limpar {--dry-run : Listar sem excluir}';

    protected $description = 'Remove rascunhos de zeladoria sem atualização há mais de N dias';

    public function handle(VistoriaRascunhoService $service): int
    {
        $dias = (int) Parametro::get('rascunho_dias_expiracao', 30);

        if ($dias < 1) {
            $this->warn('Parâmetro rascunho_dias_expiracao inválido; nada a fazer.');

            return self::SUCCESS;
        }

        $limite = now()->subDays($dias);

        if ($this->option('dry-run')) {
            $count = VistoriaRascunho::query()
                ->where('updated_at', '<', $limite)
                ->count();

            $this->info("{$count} rascunho(s) expirado(s) (> {$dias} dias).");

            return self::SUCCESS;
        }

        $deleted = $service->limparExpirados($dias);

        $this->info("Removidos {$deleted} rascunho(s) com mais de {$dias} dias sem atualização.");

        return self::SUCCESS;
    }
}
