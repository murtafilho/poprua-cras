<?php

namespace App\Console\Commands\Etl;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunCommand extends Command
{
    protected $signature = 'etl:run
        {--confirm : Confirma execucao real (sem isso, dry-run)}
        {--skip-backup : Pula o pg_dump (use APENAS se o CRAS estiver vazio)}
        {--from-dump : Restaura o .dump mais recente da raiz em vez de conectar ao Geo via FDW}';

    protected $description = 'Executa etl/migrate.sql via PDO (one-shot, idempotente via TRUNCATE)';

    private const BACKUP_DIR = '/var/backups/poprua-cras';

    private const TEMP_DB = 'poprua_geo_import';

    private const REPORTED_TABLES = [
        'users', 'model_has_roles',
        'pontos', 'moradores', 'vistorias',
        'vistoria_fotos', 'morador_historicos', 'media',
        'endereco_atualizados',
        'geo_bairros', 'geo_regionais', 'geo_limite_municipio',
    ];

    private bool $tempDbCreated = false;

    public function handle(): int
    {
        $sqlPath = base_path('etl/migrate.sql');
        if (! file_exists($sqlPath)) {
            $this->error("Nao encontrei {$sqlPath}");

            return self::FAILURE;
        }

        $fromDump = (bool) $this->option('from-dump');

        $this->info('=== POPRUA ETL — run ===');
        $this->line("SQL: {$sqlPath}");
        if ($fromDump) {
            $this->line('Modo: --from-dump (restaura .dump local)');
        }
        $this->newLine();

        if ($fromDump) {
            return $this->handleFromDump($sqlPath);
        }

        return $this->handleFromFdw($sqlPath);
    }

    private function handleFromFdw(string $sqlPath): int
    {
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
            $this->warn('Backup pulado (--skip-backup).');
        }

        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            $this->error("Falha lendo {$sqlPath}");

            return self::FAILURE;
        }

        $escapedPwd = str_replace("'", "''", $geoPwd);
        $sql = str_replace('<<GEO_PWD>>', $escapedPwd, $sql);

        return $this->executeMigrateSql($sql);
    }

    private function handleFromDump(string $sqlPath): int
    {
        try {
            DB::connection('pgsql')->select('SELECT 1');
            $this->line('  ok  CRAS alcancavel');
        } catch (\Throwable $e) {
            $this->error('CRAS inalcancavel: '.$e->getMessage());

            return self::FAILURE;
        }

        $dumpFile = $this->findLatestDump();
        if (! $dumpFile) {
            $this->error('Nenhum arquivo .dump encontrado na raiz do projeto.');
            $this->line('Esperado: poprua_geo-*.dump ou qualquer *.dump em '.base_path());

            return self::FAILURE;
        }

        $this->info("Dump encontrado: {$dumpFile}");
        $this->line('  tamanho: '.$this->humanSize((int) filesize($dumpFile)));
        $this->newLine();

        if (! $this->option('confirm')) {
            $this->dryRunDumpOutput($dumpFile);

            return self::SUCCESS;
        }

        if (! $this->option('skip-backup')) {
            if (! $this->runBackup()) {
                return self::FAILURE;
            }
        } else {
            $this->warn('Backup pulado (--skip-backup).');
        }

        if (! $this->ensureFdwExtension()) {
            return self::FAILURE;
        }

        if (! $this->restoreDumpToTempDb($dumpFile)) {
            return self::FAILURE;
        }

        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            $this->error("Falha lendo {$sqlPath}");
            $this->dropTempDb();

            return self::FAILURE;
        }

        $sql = $this->rewriteSqlForLocalDb($sql);

        $result = $this->executeMigrateSql($sql);

        $this->dropTempDb();

        return $result;
    }

    private function ensureFdwExtension(): bool
    {
        try {
            $exists = DB::connection('pgsql')->scalar(
                "SELECT 1 FROM pg_extension WHERE extname = 'postgres_fdw'"
            );
            if ($exists) {
                return true;
            }
        } catch (\Throwable) {
            // continua para tentar instalar
        }

        $this->info('Instalando extensao postgres_fdw (requer superuser)...');

        $cfg = config('database.connections.pgsql');
        $db = (string) $cfg['database'];
        $cmd = sprintf(
            'sudo -u postgres psql -d %s -c %s 2>&1',
            escapeshellarg($db),
            escapeshellarg('CREATE EXTENSION IF NOT EXISTS postgres_fdw; GRANT USAGE ON FOREIGN DATA WRAPPER postgres_fdw TO CURRENT_USER;')
        );
        exec($cmd, $lines, $exit);

        if ($exit !== 0) {
            $this->error('Falha ao instalar postgres_fdw: '.implode("\n", $lines));
            $this->line('Instale manualmente: sudo -u postgres psql -d '.$db.' -c "CREATE EXTENSION IF NOT EXISTS postgres_fdw;"');

            return false;
        }

        $this->info('  ok  postgres_fdw instalada');

        return true;
    }

    private function findLatestDump(): ?string
    {
        $pattern = base_path('*.dump');
        $files = glob($pattern);
        if (! $files) {
            return null;
        }
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    private function restoreDumpToTempDb(string $dumpFile): bool
    {
        $cfg = config('database.connections.pgsql');
        $host = (string) $cfg['host'];
        $port = (string) $cfg['port'];
        $user = (string) $cfg['username'];
        $pwd = (string) $cfg['password'];

        $this->info('Criando base temporaria '.self::TEMP_DB.'...');

        $envPrefix = "PGPASSWORD={$pwd}";

        $drop = sprintf(
            '%s dropdb -h %s -p %s -U %s --if-exists %s 2>&1',
            $envPrefix,
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg(self::TEMP_DB)
        );
        shell_exec($drop);

        $create = sprintf(
            '%s createdb -h %s -p %s -U %s -E UTF8 %s 2>&1',
            $envPrefix,
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg(self::TEMP_DB)
        );
        $output = shell_exec($create);
        if ($output !== null && $output !== '') {
            $this->warn("createdb output: {$output}");
        }
        $this->tempDbCreated = true;

        $postgis = sprintf(
            'sudo -u postgres psql -d %s -c %s 2>&1',
            escapeshellarg(self::TEMP_DB),
            escapeshellarg('CREATE EXTENSION IF NOT EXISTS postgis;')
        );
        exec($postgis, $pgLines, $pgExit);
        if ($pgExit !== 0) {
            $this->error('Falha ao instalar PostGIS em '.self::TEMP_DB.': '.implode("\n", $pgLines));
            $this->dropTempDb();

            return false;
        }
        $this->line('  ok  PostGIS instalado em '.self::TEMP_DB);

        $this->info('Restaurando dump em '.self::TEMP_DB.' (pode levar alguns minutos)...');

        $restore = sprintf(
            '%s pg_restore -h %s -p %s -U %s -d %s --no-owner --no-acl --jobs=2 %s 2>&1',
            $envPrefix,
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg(self::TEMP_DB),
            escapeshellarg($dumpFile)
        );
        $restoreOutput = '';
        exec($restore, $lines, $exit);
        $restoreOutput = implode("\n", $lines);

        // pg_restore retorna 1 para warnings (roles inexistentes, etc.) — aceitavel
        if ($exit > 1) {
            $this->error("pg_restore falhou (exit {$exit}):");
            $this->line($restoreOutput);
            $this->dropTempDb();

            return false;
        }

        if ($exit === 1) {
            $this->warn('pg_restore concluiu com warnings (normal — roles/grants do Geo nao existem local).');
        } else {
            $this->info('  ok  dump restaurado');
        }
        $this->newLine();

        return true;
    }

    private function rewriteSqlForLocalDb(string $sql): string
    {
        $cfg = config('database.connections.pgsql');
        $host = (string) $cfg['host'];
        $port = (string) $cfg['port'];
        $user = (string) $cfg['username'];
        $pwd = str_replace("'", "''", (string) $cfg['password']);

        // Substituir o bloco FDW para apontar para o banco local temporário
        $sql = preg_replace(
            "/CREATE SERVER geo_src FOREIGN DATA WRAPPER postgres_fdw\s+OPTIONS \([^)]+\);/",
            "CREATE SERVER geo_src FOREIGN DATA WRAPPER postgres_fdw\n    OPTIONS (host '{$host}', port '{$port}', dbname '".self::TEMP_DB."');",
            $sql
        );

        $sql = preg_replace(
            "/CREATE USER MAPPING FOR CURRENT_USER SERVER geo_src\s+OPTIONS \([^)]+\);/",
            "CREATE USER MAPPING FOR CURRENT_USER SERVER geo_src\n    OPTIONS (user '{$user}', password '{$pwd}');",
            $sql
        );

        return $sql;
    }

    private function dropTempDb(): void
    {
        if (! $this->tempDbCreated) {
            return;
        }

        $this->info('Removendo base temporaria '.self::TEMP_DB.'...');

        // Fechar conexões ativas antes de dropar
        $cfg = config('database.connections.pgsql');
        $pwd = (string) $cfg['password'];
        $host = (string) $cfg['host'];
        $port = (string) $cfg['port'];
        $user = (string) $cfg['username'];

        $terminate = sprintf(
            'PGPASSWORD=%s psql -h %s -p %s -U %s -d postgres -c %s 2>&1',
            escapeshellarg($pwd),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '".self::TEMP_DB."' AND pid <> pg_backend_pid()")
        );
        shell_exec($terminate);

        $drop = sprintf(
            'PGPASSWORD=%s dropdb -h %s -p %s -U %s --if-exists %s 2>&1',
            escapeshellarg($pwd),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg(self::TEMP_DB)
        );
        $output = shell_exec($drop);
        if ($output !== null && trim($output) !== '') {
            $this->warn("dropdb: {$output}");
        } else {
            $this->info('  ok  '.self::TEMP_DB.' removido');
        }
        $this->tempDbCreated = false;
    }

    private function executeMigrateSql(string $sql): int
    {
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

    private function dryRunDumpOutput(string $dumpFile): void
    {
        $this->newLine();
        $this->info('DRY-RUN (--from-dump) — nada foi executado. Para rodar:');
        $this->line('  php artisan etl:run --from-dump --confirm --skip-backup');
        $this->newLine();
        $this->line('O que --from-dump --confirm faz:');
        $this->line("  1. restaura {$dumpFile} em base temporaria '".self::TEMP_DB."'");
        $this->line('  2. reescreve o FDW no migrate.sql para apontar para localhost/'.self::TEMP_DB);
        $this->line('  3. DB::unprepared() executa tudo numa transacao (BEGIN..COMMIT)');
        $this->line('  4. TRUNCATE + FDW IMPORT + INSERTs ordenados por FK + reset de sequencias');
        $this->line('  5. remove a base temporaria '.self::TEMP_DB);
        $this->line('  6. imprime contagens por tabela e validacao PostGIS');
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
