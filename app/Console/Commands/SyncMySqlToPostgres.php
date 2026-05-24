<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SyncMySqlToPostgres extends Command
{
    protected $signature = 'sync:mysql-to-postgres
                            {--modo=relatorio : relatorio, migrar ou rollback}
                            {--backup= : Índice do backup para rollback (0=mais recente)}';

    protected $description = 'Sincroniza registros faltantes do MySQL legado para PostgreSQL (pontos e vistorias)';

    private string $backupDir;

    /**
     * Colunas MySQL a descartar (desnormalizações ou removidas no v2).
     *
     * @var array<int, string>
     */
    private array $vistoriasColunasDescartar = [
        'conformidade',
        'estrutura_abrigo_provisorio',
        'e1',
        'e2',
        'e3',
        'e4',
        'resultado_acao',
        'tipo_abrigo_desmontado',
    ];

    public function handle(): int
    {
        $this->backupDir = storage_path('sync-backups');
        $modo = $this->option('modo');

        if ($modo === 'rollback') {
            return $this->rollback();
        }

        return $this->syncReport($modo === 'migrar');
    }

    private function syncReport(bool $executar): int
    {
        $this->info('========================================');
        $this->info(' POPRUA — Sync MySQL → PostgreSQL');
        $this->info(' Modo: '.($executar ? 'migrar' : 'relatorio'));
        $this->info(' '.now()->format('Y-m-d H:i:s'));
        $this->info('========================================');
        $this->newLine();

        // --- Comparar PONTOS ---
        $this->info('── PONTOS ──────────────────────────────');
        $mysqlPontosIds = DB::connection('mysql')->table('pontos')->pluck('id')->sort()->values();
        $pgPontosIds = DB::table('pontos')->pluck('id')->sort()->values();

        $pontosFaltantes = $mysqlPontosIds->diff($pgPontosIds)->values();
        $pontosExclusivos = $pgPontosIds->diff($mysqlPontosIds)->values();

        $this->line("  MySQL:      {$mysqlPontosIds->count()} registros");
        $this->line("  PostgreSQL: {$pgPontosIds->count()} registros");
        $this->line("  Faltantes no PG: {$pontosFaltantes->count()}");
        $this->line("  Exclusivos do PG: {$pontosExclusivos->count()}");

        if ($pontosFaltantes->isNotEmpty()) {
            $this->line('  IDs faltantes: '.$pontosFaltantes->take(20)->implode(', ').'...');
        }
        $this->newLine();

        // --- Comparar VISTORIAS ---
        $this->info('── VISTORIAS ───────────────────────────');
        $mysqlVistIds = DB::connection('mysql')->table('vistorias')->pluck('id')->sort()->values();
        $pgVistIds = DB::table('vistorias')->pluck('id')->sort()->values();

        $vistFaltantes = $mysqlVistIds->diff($pgVistIds)->values();
        $vistExclusivos = $pgVistIds->diff($mysqlVistIds)->values();

        $this->line("  MySQL:      {$mysqlVistIds->count()} registros");
        $this->line("  PostgreSQL: {$pgVistIds->count()} registros");
        $this->line("  Faltantes no PG: {$vistFaltantes->count()}");
        $this->line("  Exclusivos do PG: {$vistExclusivos->count()}");

        if ($vistFaltantes->isNotEmpty()) {
            $this->line("  IDs faltantes: {$vistFaltantes->first()} → {$vistFaltantes->last()}");
            $datas = DB::connection('mysql')->table('vistorias')
                ->whereIn('id', $vistFaltantes->all())
                ->selectRaw('MIN(created_at) as primeiro, MAX(created_at) as ultimo')
                ->first();
            $this->line("  Período: {$datas->primeiro} → {$datas->ultimo}");
        }
        $this->newLine();

        // --- Apenas relatório ---
        if (! $executar) {
            $this->info('── RESUMO ──────────────────────────────');
            $this->line("  Pontos a migrar:    {$pontosFaltantes->count()}");
            $this->line("  Vistorias a migrar: {$vistFaltantes->count()}");
            $this->newLine();
            $this->line('  Para executar: php artisan sync:mysql-to-postgres --modo=migrar');
            $this->line('  Para reverter: php artisan sync:mysql-to-postgres --modo=rollback');

            return Command::SUCCESS;
        }

        // --- Backup ---
        $timestamp = now()->format('Ymd_His');
        $backupPath = "{$this->backupDir}/sync-{$timestamp}";
        File::ensureDirectoryExists($backupPath);

        $this->info('── BACKUP PRÉ-MIGRAÇÃO ─────────────────');
        $this->line("  Diretório: {$backupPath}");

        $seqPontos = DB::selectOne('SELECT last_value FROM pontos_id_seq')->last_value;
        $seqVist = DB::selectOne('SELECT last_value FROM vistorias_id_seq')->last_value;

        File::put("{$backupPath}/pontos_seq.txt", $seqPontos);
        File::put("{$backupPath}/vistorias_seq.txt", $seqVist);
        File::put("{$backupPath}/pontos_ids.txt", $pontosFaltantes->implode(','));
        File::put("{$backupPath}/vistorias_ids.txt", $vistFaltantes->implode(','));
        File::put("{$backupPath}/metadata.txt", implode("\n", [
            'Data: '.now()->format('Y-m-d H:i:s'),
            "Pontos a migrar: {$pontosFaltantes->count()}",
            "Vistorias a migrar: {$vistFaltantes->count()}",
            "PG pontos antes: {$pgPontosIds->count()}",
            "PG vistorias antes: {$pgVistIds->count()}",
            "Seq pontos antes: {$seqPontos}",
            "Seq vistorias antes: {$seqVist}",
        ]));

        // Dump completo via pg_dump
        $pgDump = 'PGPASSWORD=poprua_secret pg_dump -h pg17-poprua-cras -p 5432 -U poprua_cras poprua_cras';
        exec("{$pgDump} -t pontos --data-only > {$backupPath}/pontos_backup.sql 2>/dev/null");
        exec("{$pgDump} -t vistorias --data-only > {$backupPath}/vistorias_backup.sql 2>/dev/null");

        $this->line("  Sequences: pontos={$seqPontos}, vistorias={$seqVist}");
        $this->line('  Dumps SQL salvos');
        $this->line('  Backup concluído!');
        $this->newLine();

        // --- Validação de FKs ---
        $this->info('── VALIDAÇÃO DE FKs ────────────────────');
        $erroFk = false;

        if ($vistFaltantes->isNotEmpty()) {
            // ponto_id
            $pontoIdsVist = DB::connection('mysql')->table('vistorias')
                ->whereIn('id', $vistFaltantes->all())
                ->whereNotNull('ponto_id')
                ->distinct()
                ->pluck('ponto_id');

            $pontosFaltantesFK = $pontoIdsVist->diff(DB::table('pontos')->pluck('id'));
            if ($pontosFaltantesFK->isNotEmpty()) {
                $this->error("  ERRO: ponto_id não encontrados no PG: {$pontosFaltantesFK->implode(', ')}");
                $erroFk = true;
            } else {
                $this->line('  ponto_id: OK');
            }

            // tipo_abordagem
            $tipoIds = DB::connection('mysql')->table('vistorias')
                ->whereIn('id', $vistFaltantes->all())
                ->whereNotNull('tipo_abordagem_id')
                ->distinct()
                ->pluck('tipo_abordagem_id');
            $tiposFaltantes = $tipoIds->diff(DB::table('tipo_abordagem')->pluck('id'));
            if ($tiposFaltantes->isNotEmpty()) {
                $this->error("  ERRO: tipo_abordagem_id não encontrados: {$tiposFaltantes->implode(', ')}");
                $erroFk = true;
            } else {
                $this->line('  tipo_abordagem_id: OK');
            }

            // encaminhamentos (e1-e4)
            $encamIds = collect();
            foreach (['e1_id', 'e2_id', 'e3_id', 'e4_id'] as $col) {
                $ids = DB::connection('mysql')->table('vistorias')
                    ->whereIn('id', $vistFaltantes->all())
                    ->whereNotNull($col)
                    ->distinct()
                    ->pluck($col);
                $encamIds = $encamIds->merge($ids);
            }
            $encamFaltantes = $encamIds->unique()->diff(DB::table('encaminhamentos')->pluck('id'));
            if ($encamFaltantes->isNotEmpty()) {
                $this->error("  ERRO: encaminhamento_id não encontrados: {$encamFaltantes->implode(', ')}");
                $erroFk = true;
            } else {
                $this->line('  encaminhamentos (e1-e4): OK');
            }
        }

        if ($erroFk) {
            $this->newLine();
            $this->error('  ABORTANDO: FKs faltantes. Backup preservado em: '.$backupPath);

            return Command::FAILURE;
        }

        $this->line('  Todas as FKs validadas!');
        $this->newLine();

        // --- Migrar PONTOS ---
        if ($pontosFaltantes->isNotEmpty()) {
            $this->info("── MIGRANDO PONTOS ({$pontosFaltantes->count()}) ──────────");

            DB::statement('SET session_replication_role = replica;');

            $bar = $this->output->createProgressBar($pontosFaltantes->count());
            $bar->start();

            // endereco_id do MySQL NÃO é copiado (sistema de endereçamento diferente)
            // endereco_atualizado_id será preenchido depois pelo vincular-enderecos
            DB::connection('mysql')->table('pontos')
                ->whereIn('id', $pontosFaltantes->all())
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($bar) {
                    $data = [];
                    foreach ($rows as $row) {
                        $data[] = [
                            'id' => $row->id,
                            'numero' => $row->numero,
                            'caracteristica_abrigo_id' => $row->caracteristica_abrigo_id,
                            'complemento' => $row->complemento,
                            'endereco_atualizado_id' => null, // será vinculado depois
                            'lat' => $row->lat,
                            'lng' => $row->lng,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ];
                        $bar->advance();
                    }
                    DB::table('pontos')->insert($data);
                });

            $bar->finish();
            $this->newLine();

            // Geometrias PostGIS
            DB::statement('
                UPDATE pontos
                SET geom = ST_SetSRID(ST_MakePoint(lng::float, lat::float), 4326)
                WHERE lat IS NOT NULL AND lng IS NOT NULL AND geom IS NULL
            ');

            // Sequence
            DB::statement("SELECT setval('pontos_id_seq', (SELECT MAX(id) FROM pontos), true)");

            DB::statement('SET session_replication_role = DEFAULT;');

            $pgPontosDepois = DB::table('pontos')->count();
            $this->line("  PG antes: {$pgPontosIds->count()} → depois: {$pgPontosDepois}");
            $this->line('  Pontos migrados!');
            $this->newLine();

            // Vincular ao sistema de endereçamento v2
            $this->info('── VINCULANDO ENDEREÇOS ────────────────');
            $this->line('  Executando pontos:vincular-enderecos...');
            $this->call('pontos:vincular-enderecos', ['--no-interaction' => true]);
            $this->newLine();
        }

        // --- Migrar VISTORIAS ---
        if ($vistFaltantes->isNotEmpty()) {
            $this->info("── MIGRANDO VISTORIAS ({$vistFaltantes->count()}) ─────────");

            DB::statement('SET session_replication_role = replica;');

            $bar = $this->output->createProgressBar($vistFaltantes->count());
            $bar->start();

            DB::connection('mysql')->table('vistorias')
                ->whereIn('id', $vistFaltantes->all())
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($bar) {
                    $data = [];
                    foreach ($rows as $row) {
                        $data[] = [
                            'id' => $row->id,
                            'data_abordagem' => $row->data_abordagem,
                            'nomes_pessoas' => $row->nomes_pessoas,
                            'quantidade_pessoas' => $row->quantidade_pessoas,
                            'tipo_abordagem_id' => $row->tipo_abordagem_id,
                            'casal' => (bool) ($row->casal ?? false),
                            'qtd_casais' => 0,
                            'classificacao' => $row->classificacao,
                            'num_reduzido' => (bool) ($row->num_reduzido ?? false),
                            'catador_reciclados' => (bool) ($row->catador_reciclados ?? false),
                            'resistencia' => (bool) ($row->resistencia ?? false),
                            'fixacao_antiga' => (bool) ($row->fixacao_antiga ?? false),
                            'excesso_objetos' => (bool) ($row->excesso_objetos ?? false),
                            'trafico_ilicitos' => isset($row->trafico_ilicitos) ? (bool) $row->trafico_ilicitos : null,
                            // menores_idosos → crianca_adolescente
                            'crianca_adolescente' => (bool) ($row->menores_idosos ?? false),
                            'idosos' => false,
                            'gestante' => false,
                            'lgbtqiapn' => false,
                            'cena_uso_caracterizada' => false,
                            'qtd_abrigos_provisorios' => 0,
                            'abrigos_tipos' => null,
                            'deficiente' => isset($row->deficiente) ? (bool) $row->deficiente : null,
                            'agrupamento_quimico' => isset($row->agrupamento_quimico) ? (bool) $row->agrupamento_quimico : null,
                            'saude_mental' => isset($row->saude_mental) ? (bool) $row->saude_mental : null,
                            'animais' => isset($row->animais) ? (bool) $row->animais : null,
                            'qtd_animais' => 0,
                            'conducao_forcas_seguranca' => false,
                            'conducao_forcas_observacao' => null,
                            'apreensao_fiscal' => false,
                            'auto_fiscalizacao_aplicado' => false,
                            'auto_fiscalizacao_numero' => null,
                            'e1_id' => $row->e1_id,
                            'e2_id' => $row->e2_id,
                            'e3_id' => $row->e3_id,
                            'e4_id' => $row->e4_id,
                            'material_apreendido' => $row->material_apreendido,
                            'material_descartado' => $row->material_descartado,
                            'tipo_abrigo_desmontado_id' => $row->tipo_abrigo_desmontado_id,
                            'qtd_kg' => $row->qtd_kg,
                            'resultado_acao_id' => $row->resultado_acao_id,
                            'movimento_migratorio' => $row->movimento_migratorio,
                            'observacao' => $row->observacao,
                            'ponto_id' => $row->ponto_id,
                            'user_id' => null,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ];
                        $bar->advance();
                    }
                    DB::table('vistorias')->insert($data);
                });

            $bar->finish();
            $this->newLine();

            // Sequence
            DB::statement("SELECT setval('vistorias_id_seq', (SELECT MAX(id) FROM vistorias), true)");

            DB::statement('SET session_replication_role = DEFAULT;');

            $pgVistDepois = DB::table('vistorias')->count();
            $this->line("  PG antes: {$pgVistIds->count()} → depois: {$pgVistDepois}");
            $this->line('  Vistorias migradas!');
            $this->newLine();
        }

        // --- Relatório final ---
        $this->info('── RELATÓRIO FINAL ─────────────────────');
        $this->table(
            ['Tabela', 'MySQL', 'PG antes', 'PG depois'],
            [
                ['pontos', $mysqlPontosIds->count(), $pgPontosIds->count(), DB::table('pontos')->count()],
                ['vistorias', $mysqlVistIds->count(), $pgVistIds->count(), DB::table('vistorias')->count()],
            ]
        );
        $this->newLine();
        $this->line("  Backup em: {$backupPath}");
        $this->line('  Para reverter: php artisan sync:mysql-to-postgres --modo=rollback');
        $this->newLine();
        $this->info('  Sincronização concluída!');

        return Command::SUCCESS;
    }

    private function rollback(): int
    {
        $this->info('========================================');
        $this->info(' POPRUA — Rollback de sincronização');
        $this->info(' '.now()->format('Y-m-d H:i:s'));
        $this->info('========================================');
        $this->newLine();

        if (! File::isDirectory($this->backupDir)) {
            $this->error('Nenhum backup encontrado.');

            return Command::FAILURE;
        }

        $backups = collect(File::directories($this->backupDir))
            ->filter(fn ($d) => str_contains(basename($d), 'sync-'))
            ->sortDesc()
            ->values();

        if ($backups->isEmpty()) {
            $this->error('Nenhum backup encontrado.');

            return Command::FAILURE;
        }

        // Listar backups
        $this->info('── BACKUPS DISPONÍVEIS ─────────────────');
        foreach ($backups as $i => $path) {
            $meta = File::exists("{$path}/metadata.txt") ? File::get("{$path}/metadata.txt") : 'sem metadata';
            $this->line("  [{$i}] ".basename($path));
            collect(explode("\n", $meta))->each(fn ($l) => $this->line("      {$l}"));
            $this->newLine();
        }

        $indice = (int) ($this->option('backup') ?? 0);
        $backupPath = $backups[$indice] ?? null;

        if (! $backupPath) {
            $this->error("Backup índice {$indice} não encontrado.");

            return Command::FAILURE;
        }

        $this->info('── RESTAURANDO: '.basename($backupPath).' ────');
        $this->newLine();

        // Desabilitar triggers
        DB::statement('SET session_replication_role = replica;');

        // Rollback vistorias (primeiro, por causa da FK ponto_id)
        $vistIds = trim(File::get("{$backupPath}/vistorias_ids.txt"));
        if (! empty($vistIds)) {
            $ids = array_filter(explode(',', $vistIds));
            $count = count($ids);
            $this->line("  Removendo {$count} vistorias...");
            DB::table('vistorias')->whereIn('id', $ids)->delete();

            $seqVist = trim(File::get("{$backupPath}/vistorias_seq.txt"));
            if (! empty($seqVist)) {
                DB::statement("SELECT setval('vistorias_id_seq', {$seqVist}, true)");
                $this->line("  Sequence vistorias restaurada para {$seqVist}");
            }
        } else {
            $this->line('  Nenhuma vistoria para restaurar.');
        }

        // Rollback pontos
        $pontoIds = trim(File::get("{$backupPath}/pontos_ids.txt"));
        if (! empty($pontoIds)) {
            $ids = array_filter(explode(',', $pontoIds));
            $count = count($ids);
            $this->line("  Removendo {$count} pontos...");
            DB::table('pontos')->whereIn('id', $ids)->delete();

            $seqPontos = trim(File::get("{$backupPath}/pontos_seq.txt"));
            if (! empty($seqPontos)) {
                DB::statement("SELECT setval('pontos_id_seq', {$seqPontos}, true)");
                $this->line("  Sequence pontos restaurada para {$seqPontos}");
            }
        } else {
            $this->line('  Nenhum ponto para restaurar.');
        }

        DB::statement('SET session_replication_role = DEFAULT;');

        $this->newLine();
        $this->info('── VERIFICAÇÃO PÓS-ROLLBACK ────────────');
        $this->table(
            ['Tabela', 'Contagem PG'],
            [
                ['pontos', DB::table('pontos')->count()],
                ['vistorias', DB::table('vistorias')->count()],
            ]
        );

        $this->newLine();
        $this->line("  Dump SQL disponível em: {$backupPath}/");
        $this->info('  Rollback concluído!');

        return Command::SUCCESS;
    }
}
