<?php

/*
 * Config do spatie/laravel-backup adaptada ao poprua-cras.
 *
 * MECANISMO montado pelo painel (frente backup-offsite). O DESTINO off-site e
 * decisao humana (guard #6): selecionado via env BACKUP_DISK (default 'local',
 * que NAO satisfaz o Gate 4). Para off-site, criar/usar um disco s3 ou sftp em
 * config/filesystems.php e setar BACKUP_DISK=s3 (+ AWS keys e bucket) ou =sftp.
 */

return [

    'backup' => [

        'name' => env('APP_NAME', 'poprua-cras'),

        'source' => [

            'files' => [
                'include' => [
                    base_path(),
                ],
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    storage_path(),
                ],
                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => null,
            ],

            'databases' => [
                // poprua-cras roda em PostgreSQL 17 + PostGIS (conexao 'pgsql').
                env('DB_CONNECTION', 'pgsql'),
            ],
        ],

        'database_dump_compressor' => Spatie\DbDumper\Compressors\GzipCompressor::class,

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,

            'filename_prefix' => 'poprua-cras-',

            // DESTINO off-site: decisao humana via env. 'local' = nao off-site (Gate 4 amarelo).
            'disks' => [
                env('BACKUP_DISK', 'local'),
            ],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',

        'tries' => 1,
        'retry_delay' => 0,
    ],

    'notifications' => [

        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', 'admin@example.com'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Backup poprua-cras'),
            ],
        ],
    ],

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'poprua-cras'),
            'disks' => [env('BACKUP_DISK', 'local')],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],

        'tries' => 1,
        'retry_delay' => 0,
    ],
];
