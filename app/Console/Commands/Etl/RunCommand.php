<?php

namespace App\Console\Commands\Etl;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunCommand extends Command
{
    protected $signature = 'etl:run
        {--confirm : Confirma execucao real (sem isso, dry-run)}
        {--skip-backup : Pula o pg_dump (use APENAS se o CRAS estiver vazio)}';

    protected $description = 'Executa etl/migrate.sql via PDO (one-shot, idempotente via TRUNCATE)';

    /** Diretorio dos backups dentro do container PHP. */
    private const BACKUP_DIR = '/var/backups/poprua-cras';

    /** Tabelas migradas (para contagem pos-execucao). */
    private const REPORTED_TABLES = [
        'users', 'roles', 'permissions',
        'pontos', 'moradores', 'vistorias',
        'vistoria_fotos', 'morador_historicos', 'media',
        'endereco_atualizados',
        'geo_bairros', 'geo_regionais', 'geo_limite_municipio',
    ];

    public function handle(): int
    {
        $sqlPath = base_path('etl/migrate.sql');
        if (! file_exists($sqlPath)) {
            $this->error("Nao encontrei {$sqlPath}");

            return self::FAILURE;
        }

        $this->info('=== POPRUA ETL — run ===');
        $this->line("SQL: {$sqlPath}");
        $this->newLine();

        if (! $this->probeConnections()) {
            return self::FAILURE;
        }

        $geoPwd = (string) config('database.connections.pgsql_geo.password');
        if ($geoPwd === '') {
            $this->error('ETL_SOURCE_PASSWORD nao definido — FDW nao consegue autenticar no Geo.');

            return self::FAILURE;
        }

        if (! $this->option('confirm')) {
            $this->dryRunOutput();

            return self::SUCCESS;
        }

        if (! $this->option('skip-backup')) {
            if (! $this->runBackup()) {
                return self::FAILURE;
            }
        } else {
            $this->warn('Backup pulado (--skip-backup). Use APENAS em CRAS sem dados que importam.');
        }

        // Le, substitui placeholder e executa
        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            $this->error("Falha lendo {$sqlPath}");

            return self::FAILURE;
        }

        $escapedPwd = str_replace("'", "''", $geoPwd);
        $sql = str_replace('<<GEO_PWD>>', $escapedPwd, $sql);

        $this->info('Executando migrate.sql (PDO unprepared)...');
        $t0 = microtime(true);
        try {
            DB::connection('pgsql')->unprepared($sql);
        } catch (\Throwable $e) {
            $this->error('Falha: '.$e->getMessage());

            return self::FAILURE;
        }
        $dt = round(microtime(true) - $t0, 1);
        $this->info("  ok  migrate.sql concluido em {$dt}s");
        $this->newLine();

        $this->reportRowCounts();
        $this->newLine();
        $this->reportPostGisValidation();

        return self::SUCCESS;
    }

    private function probeConnections(): bool
    {
        try {
            DB::connection('pgsql')->select('SELECT 1');
            $this->line('  ok  CRAS alcancavel');
        } catch (\Throwable $e) {
            $this->error('CRAS inalcancavel: '.$e->getMessage());

            return false;
        }
        try {
            DB::connection('pgsql_geo')->select('SELECT 1');
            $this->line('  ok  Geo  alcancavel');
        } catch (\Throwable $e) {
            $this->error('Geo inalcancavel: '.$e->getMessage());
            $this->warn('Conferir docker network connect poprua-geo_poprua-geo php84-poprua-cras');

            return false;
        }
        $this->newLine();

        return true;
    }

    private function dryRunOutput(): void
    {
        $this->newLine();
        $this->info('DRY-RUN — nada foi executado. Para rodar:');
        $this->line('  php artisan etl:run --confirm');
        $this->newLine();
        $this->line('O que --confirm faz:');
        $this->line('  1. pg_dump do CRAS em '.self::BACKUP_DIR.'/<stamp>.dump');
        $this->line('  2. le etl/migrate.sql, substitui <<GEO_PWD>> pela senha do .env');
        $this->line('  3. DB::unprepared() executa tudo numa transacao (BEGIN..COMMIT)');
        $this->line('  4. TRUNCATE + FDW IMPORT + INSERTs ordenados por FK + reset de sequencias');
        $this->line('  5. imprime contagens por tabela e validacao PostGIS');
    }

    private function runBackup(): bool
    {
        $stamp = date('Y-m-d-Hi');
        $dump = self::BACKUP_DIR."/{$stamp}.dump";
        $this->info("Backup: {$dump}");

        shell_exec('mkdir -p '.escapeshellarg(self::BACKUP_DIR).' 2>&1');

        $cfg = config('database.connections.pgsql');
        $cmd = sprintf(
            'PGPASSWORD=%s pg_dump -Fc -h %s -p %s -U %s -d %s -f %s 2>&1',
            escapeshellarg((string) $cfg['password']),
            escapeshellarg((string) $cfg['host']),
            escapeshellarg((string) $cfg['port']),
            escapeshellarg((string) $cfg['username']),
            escapeshellarg((string) $cfg['database']),
            escapeshellarg($dump)
        );
        passthru($cmd, $exit);
        if ($exit !== 0) {
            $this->error('Backup falhou (exit '.$exit.'). Abortando.');

            return false;
        }
        $size = file_exists($dump) ? (int) filesize($dump) : 0;
        $this->info('  ok  backup gerado ('.$this->humanSize($size).')');
        $this->newLine();

        return true;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'K', 'M', 'G'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes = (int) ($bytes / 1024);
            $i++;
        }

        return $bytes.$units[$i];
    }

    private function reportRowCounts(): void
    {
        $this->info('Contagens pos-migracao:');
        $rows = [];
        foreach (self::REPORTED_TABLES as $t) {
            try {
                $n = (int) DB::connection('pgsql')->scalar("SELECT COUNT(*) FROM public.\"{$t}\"");
                $rows[] = [$t, $n];
            } catch (\Throwable $e) {
                $rows[] = [$t, 'ERRO: '.substr($e->getMessage(), 0, 40)];
            }
        }
        $this->table(['Tabela', 'Linhas'], $rows);
    }

    private function reportPostGisValidation(): void
    {
        $this->info('Validacao PostGIS:');
        $checks = [
            ['pontos srid != 4326',
                'SELECT COUNT(*) FROM public.pontos WHERE geom IS NOT NULL AND ST_SRID(geom) <> 4326'],
            ['pontos invalidos',
                'SELECT COUNT(*) FROM public.pontos WHERE geom IS NOT NULL AND NOT ST_IsValid(geom)'],
            ['geo_bairros invalidos',
                'SELECT COUNT(*) FROM public.geo_bairros WHERE geom IS NOT NULL AND NOT ST_IsValid(geom)'],
            ['geo_regionais invalidos',
                'SELECT COUNT(*) FROM public.geo_regionais WHERE geom IS NOT NULL AND NOT ST_IsValid(geom)'],
            ['endereco_atualizados invalidos',
                'SELECT COUNT(*) FROM public.endereco_atualizados WHERE geom IS NOT NULL AND NOT ST_IsValid(geom)'],
        ];
        $rows = [];
        $hasIssue = false;
        foreach ($checks as [$label, $sql]) {
            $n = (int) DB::connection('pgsql')->scalar($sql);
            $rows[] = [$label, $n];
            if ($n > 0) {
                $hasIssue = true;
            }
        }
        $this->table(['Check', 'Qtd'], $rows);
        if ($hasIssue) {
            $this->error('Existem geometrias problematicas — investigar antes do cutover.');
        } else {
            $this->info('Todas as geometrias OK.');
        }
    }
}
