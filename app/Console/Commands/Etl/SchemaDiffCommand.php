<?php

namespace App\Console\Commands\Etl;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SchemaDiffCommand extends Command
{
    protected $signature = 'etl:schema-diff
        {--only= : Lista de tabelas separadas por virgula para limitar a comparacao}';

    protected $description = 'Pre-flight do ETL: compara schemas Geo x CRAS e aponta se etl/migrate.sql ainda esta correto';

    private const TARGET = 'pgsql';      // CRAS — fonte de verdade canonica

    private const SOURCE = 'pgsql_geo';  // Geo — origem unidirecional

    /**
     * Tabelas conhecidas que NAO entram no migrate.sql.
     * Listadas aqui para nao gerar ruido no relatorio.
     */
    private const IGNORED = [
        // PostGIS infra
        'spatial_ref_sys',
        // Laravel runtime — sessoes/queue/cache nao migram do Geo
        'cache', 'cache_locks', 'sessions',
        'jobs', 'job_batches', 'failed_jobs',
        'migrations',
        'password_resets', 'password_reset_tokens', 'personal_access_tokens',
        // Exclusivas do CRAS (seedadas via migrations/seeders)
        'membros_equipe', 'vistoria_participantes',
    ];

    /**
     * Divergencias esperadas conforme etl/migrate.sql.
     * Se schema-diff reportar algo diferente disso, ATUALIZAR migrate.sql.
     *
     * @var array<string, array{drop: string[], add: string[]}>
     */
    private const EXPECTED_DIVERGENCES = [
        'moradores' => ['drop' => ['fotografia'], 'add' => []],
        'pontos' => ['drop' => [], 'add' => ['deleted_at']],
        'vistorias' => ['drop' => [], 'add' => [
            'finalizada', 'finalizada_em', 'finalizada_por',
            'data_prevista_zeladoria', 'periodo_zeladoria',
            'houve_lavratura', 'tipo_protocolo',
            'cancelada', 'cancelada_em', 'cancelada_por',
        ]],
    ];

    public function handle(): int
    {
        $this->info('=== POPRUA ETL — Schema Diff (pre-flight) ===');
        $this->line('Destino: poprua-cras ('.self::TARGET.')  Origem: poprua-geo ('.self::SOURCE.')');
        $this->newLine();

        if (! $this->probeConnections()) {
            return self::FAILURE;
        }

        $only = $this->option('only')
            ? array_map('trim', explode(',', (string) $this->option('only')))
            : null;

        $cras = $this->readSchema(self::TARGET, $only);
        $geo = $this->readSchema(self::SOURCE, $only);
        $crasGeom = $this->readGeometryColumns(self::TARGET);
        $geoGeom = $this->readGeometryColumns(self::SOURCE);

        $unexpected = $this->report($cras, $geo, $crasGeom, $geoGeom);

        $this->newLine();
        if ($unexpected) {
            $this->error('Divergencias INESPERADAS encontradas — etl/migrate.sql provavelmente precisa ser atualizado.');

            return self::FAILURE;
        }
        $this->info('OK — divergencias batem com etl/migrate.sql. Migracao pronta para rodar.');

        return self::SUCCESS;
    }

    private function probeConnections(): bool
    {
        foreach ([self::TARGET => 'CRAS', self::SOURCE => 'Geo '] as $conn => $label) {
            try {
                $v = DB::connection($conn)->selectOne('SELECT version() as v')->v;
                $this->line("  ok  {$label}: ".substr((string) $v, 0, 55).'...');
            } catch (\Throwable $e) {
                $this->error("  X   {$label}: ".$e->getMessage());
                if ($conn === self::SOURCE) {
                    $this->newLine();
                    $this->warn('Conferir .env (ETL_SOURCE_*) e se docker network connect esta ativo:');
                    $this->warn('  sudo docker network connect poprua-geo_poprua-geo php84-poprua-cras');
                }

                return false;
            }
        }
        $this->newLine();

        return true;
    }

    /** @param array<int, string>|null $only
     *  @return array<string, array<string, string>> */
    private function readSchema(string $connection, ?array $only): array
    {
        // self::IGNORED e constante — sem risco de injection ao inline.
        $ignored = "'".implode("','", self::IGNORED)."'";
        $sql = "SELECT c.table_name, c.column_name, c.udt_name
                FROM information_schema.columns c
                JOIN information_schema.tables t
                  ON t.table_schema = c.table_schema
                 AND t.table_name = c.table_name
                WHERE c.table_schema = 'public'
                  AND t.table_type = 'BASE TABLE'
                  AND c.table_name NOT IN ({$ignored})";
        $bindings = [];
        if ($only) {
            $placeholders = implode(',', array_fill(0, count($only), '?'));
            $sql .= " AND c.table_name IN ({$placeholders})";
            $bindings = $only;
        }
        $sql .= ' ORDER BY c.table_name, c.ordinal_position';

        $rows = DB::connection($connection)->select($sql, $bindings);
        $out = [];
        foreach ($rows as $r) {
            $out[$r->table_name][$r->column_name] = $r->udt_name;
        }

        return $out;
    }

    /** @return array<string, array<string, array{srid: int, type: string}>> */
    private function readGeometryColumns(string $connection): array
    {
        try {
            $rows = DB::connection($connection)->select(
                "SELECT f_table_name AS t, f_geometry_column AS c, srid, type
                 FROM geometry_columns
                 WHERE f_table_schema = 'public'"
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[$r->t][$r->c] = ['srid' => (int) $r->srid, 'type' => $r->type];
        }

        return $out;
    }

    /**
     * Imprime o relatorio e retorna true se houver divergencias inesperadas
     * (que migrate.sql ainda nao prevê).
     *
     * @param  array<string, array<string, string>>  $cras
     * @param  array<string, array<string, string>>  $geo
     * @param  array<string, array<string, array{srid: int, type: string}>>  $crasGeom
     * @param  array<string, array<string, array{srid: int, type: string}>>  $geoGeom
     */
    private function report(array $cras, array $geo, array $crasGeom, array $geoGeom): bool
    {
        $crasTables = array_keys($cras);
        $geoTables = array_keys($geo);

        $onlyCras = array_values(array_diff($crasTables, $geoTables));
        $onlyGeo = array_values(array_diff($geoTables, $crasTables));
        $inBoth = array_values(array_intersect($crasTables, $geoTables));

        $this->info('Tabelas no escopo (ignorando infra): CRAS={'.count($crasTables).'}  Geo={'.count($geoTables).'}  em_ambos={'.count($inBoth).'}');
        $this->newLine();

        $unexpected = false;

        if ($onlyCras) {
            $this->warn('Tabelas so no CRAS (verificar se sao novas pos-fork ou se precisam ser adicionadas a IGNORED):');
            foreach ($onlyCras as $t) {
                $this->line("  + {$t}");
            }
            $unexpected = true;
        }

        if ($onlyGeo) {
            $this->warn('Tabelas so no Geo (verificar se devem entrar na IGNORED ou no migrate.sql):');
            foreach ($onlyGeo as $t) {
                $this->line("  - {$t}");
            }
            $unexpected = true;
        }

        foreach ($inBoth as $t) {
            $crasCols = array_keys($cras[$t]);
            $geoCols = array_keys($geo[$t]);
            $newInCras = array_values(array_diff($crasCols, $geoCols));
            $droppedInCras = array_values(array_diff($geoCols, $crasCols));
            $typeMismatch = [];
            foreach (array_intersect($crasCols, $geoCols) as $col) {
                if ($cras[$t][$col] !== $geo[$t][$col]) {
                    $typeMismatch[$col] = ['cras' => $cras[$t][$col], 'geo' => $geo[$t][$col]];
                }
            }

            if (! $newInCras && ! $droppedInCras && ! $typeMismatch) {
                continue;
            }

            $expected = self::EXPECTED_DIVERGENCES[$t] ?? ['drop' => [], 'add' => []];
            $unexpectedAdd = array_diff($newInCras, $expected['add']);
            $unexpectedDrop = array_diff($droppedInCras, $expected['drop']);
            $missingAdd = array_diff($expected['add'], $newInCras);
            $missingDrop = array_diff($expected['drop'], $droppedInCras);

            $tableLabel = ($unexpectedAdd || $unexpectedDrop || $missingAdd || $missingDrop || $typeMismatch)
                ? "<fg=red>{$t}</> (precisa atencao)"
                : "<fg=green>{$t}</> (bate com migrate.sql)";
            $this->line($tableLabel);

            if ($newInCras) {
                $this->line('  novas em CRAS: '.implode(', ', $newInCras));
            }
            if ($droppedInCras) {
                $this->line('  dropadas em CRAS: '.implode(', ', $droppedInCras));
            }
            foreach ($typeMismatch as $col => $t2) {
                $this->line("  tipo divergente em {$col}: CRAS={$t2['cras']} | Geo={$t2['geo']}");
                $unexpected = true;
            }
            if ($unexpectedAdd) {
                $this->error('  INESPERADO add: '.implode(', ', $unexpectedAdd));
                $unexpected = true;
            }
            if ($unexpectedDrop) {
                $this->error('  INESPERADO drop: '.implode(', ', $unexpectedDrop));
                $unexpected = true;
            }
            if ($missingAdd) {
                $this->error('  ESPERADA mas nao encontrada (add): '.implode(', ', $missingAdd));
                $unexpected = true;
            }
            if ($missingDrop) {
                $this->error('  ESPERADA mas nao encontrada (drop): '.implode(', ', $missingDrop));
                $unexpected = true;
            }
        }
        $this->newLine();

        // Geometrias
        $geomRows = [];
        foreach ($inBoth as $t) {
            foreach ($crasGeom[$t] ?? [] as $col => $info) {
                $g = $geoGeom[$t][$col] ?? null;
                $action = ($g && $g['srid'] !== $info['srid']) ? 'ST_Transform!' : 'ok';
                if ($action !== 'ok') {
                    $unexpected = true;
                }
                $geomRows[] = [$t, $col, "{$info['type']}/{$info['srid']}", $g ? "{$g['type']}/{$g['srid']}" : '-', $action];
            }
        }
        if ($geomRows) {
            $this->info('Geometrias PostGIS:');
            $this->table(['Tabela', 'Coluna', 'CRAS', 'Geo', 'Acao'], $geomRows);
        }

        return $unexpected;
    }
}
