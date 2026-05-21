<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateMySqlToPostgres extends Command
{
    protected $signature = 'migrate:mysql-to-postgres {--table=* : Tabelas específicas para migrar}';

    protected $description = 'Migra dados do MySQL para PostgreSQL com suporte a PostGIS';

    private array $tables = [
        'users',
        'permissions',
        'roles',
        'role_has_permissions',
        'model_has_permissions',
        'model_has_roles',
        'caracteristica_abrigo',
        'tipo_abordagem',
        'tipo_abrigo_desmontado',
        'resultados_acoes',
        'encaminhamentos',
        'geo_bairros',
        'geo_regionais',
        'geo_limite_municipio',
        'endereco_atualizados',
        'pontos',
        'vistorias',
        'vistoria_fotos',
        'moradores',
        'morador_historicos',
    ];

    public function handle(): int
    {
        $tables = $this->option('table') ?: $this->tables;

        $this->info('Iniciando migração MySQL -> PostgreSQL');
        $this->newLine();

        foreach ($tables as $table) {
            $this->migrateTable($table);
        }

        $this->newLine();
        $this->info('Migração concluída!');

        return Command::SUCCESS;
    }

    private function migrateTable(string $table): void
    {
        $this->info("Migrando tabela: {$table}");

        try {
            $count = DB::connection('mysql')->table($table)->count();
            $this->line("  - {$count} registros encontrados");

            if ($count === 0) {
                $this->line('  - Pulando (tabela vazia)');

                return;
            }

            // Desabilitar triggers temporariamente
            DB::statement('SET session_replication_role = replica;');

            $bar = $this->output->createProgressBar($count);
            $bar->start();

            // Verificar se a tabela tem coluna id
            $hasId = DB::connection('mysql')
                ->select("SHOW COLUMNS FROM {$table} LIKE 'id'");

            // Processar em lotes ou cursor dependendo da estrutura
            if (! empty($hasId)) {
                DB::connection('mysql')->table($table)->orderBy('id')->chunk(1000, function ($rows) use ($table, $bar) {
                    $this->processRows($rows, $table, $bar);
                });
            } else {
                // Para tabelas sem id, usar get() com insert em lote
                $rows = DB::connection('mysql')->table($table)->get();
                $this->processRows($rows, $table, $bar);
            }

            $bar->finish();
            $this->newLine();

            // Atualizar geometrias PostGIS
            $this->updateGeometries($table);

            // Resetar sequence do PostgreSQL
            $this->resetSequence($table);

            // Reabilitar triggers
            DB::statement('SET session_replication_role = DEFAULT;');

            $this->line('  - Concluído!');

        } catch (\Exception $e) {
            $this->error('  - Erro: '.$e->getMessage());
        }
    }

    private function processRows($rows, string $table, $bar): void
    {
        $data = [];

        foreach ($rows as $row) {
            $rowArray = (array) $row;

            // Tratar dados espaciais
            if ($table === 'geo_bairros' || $table === 'geo_regionais' || $table === 'geo_limite_municipio') {
                $rowArray = $this->processGeoTable($rowArray);
            } elseif ($table === 'endereco_atualizados') {
                $rowArray = $this->processEnderecoTable($rowArray);
            } elseif ($table === 'pontos') {
                $rowArray = $this->processPontosTable($rowArray);
            }

            // Remover colunas que não existem no PostgreSQL
            unset($rowArray['geom']);

            $data[] = $rowArray;
            $bar->advance();
        }

        // Inserir no PostgreSQL em lotes de 1000
        foreach (array_chunk($data, 1000) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function processGeoTable(array $row): array
    {
        // Manter JSON geometry para depois converter
        return $row;
    }

    private function processEnderecoTable(array $row): array
    {
        return $row;
    }

    private function processPontosTable(array $row): array
    {
        return $row;
    }

    private function updateGeometries(string $table): void
    {
        $this->line('  - Atualizando geometrias PostGIS...');

        switch ($table) {
            case 'geo_bairros':
            case 'geo_regionais':
            case 'geo_limite_municipio':
                DB::statement("
                    UPDATE {$table}
                    SET geom = ST_SetSRID(ST_GeomFromGeoJSON(geometry::text), 4326)
                    WHERE geometry IS NOT NULL AND geom IS NULL
                ");
                break;

            case 'endereco_atualizados':
                DB::statement('
                    UPDATE endereco_atualizados
                    SET geom = ST_SetSRID(ST_MakePoint(lng::float, lat::float), 4326)
                    WHERE lat IS NOT NULL AND lng IS NOT NULL AND geom IS NULL
                ');
                break;

            case 'pontos':
                DB::statement('
                    UPDATE pontos
                    SET geom = ST_SetSRID(ST_MakePoint(lng::float, lat::float), 4326)
                    WHERE lat IS NOT NULL AND lng IS NOT NULL AND geom IS NULL
                ');
                break;
        }
    }

    private function resetSequence(string $table): void
    {
        try {
            $maxId = DB::table($table)->max('id') ?? 0;
            $sequence = "{$table}_id_seq";
            DB::statement("SELECT setval('{$sequence}', {$maxId}, true)");
        } catch (\Exception $e) {
            // Ignorar se não houver sequence
        }
    }
}
