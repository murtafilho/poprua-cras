<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup off-site (spatie/laravel-backup). Destino via env BACKUP_DISK.
// Requer scheduler ativo: container queue/cron rodando `schedule:run` a cada minuto.
Schedule::command('backup:clean')->daily()->at('03:00')->onOneServer();
Schedule::command('backup:run')->daily()->at('03:15')->onOneServer();
Schedule::command('backup:monitor')->daily()->at('06:00')->onOneServer();
