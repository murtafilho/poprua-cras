<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup (spatie/laravel-backup). Destino via env BACKUP_DISK.
// Rodado por cron do HOST uma vez/dia as 03:00 (/etc/cron.d/poprua-cras-backup ->
// `php artisan schedule:run`). Por isso TODAS as tarefas sao dailyAt('03:00'): so
// disparam quando o scheduler e invocado nesse minuto. Executam em sequencia:
// limpa antigos -> roda backup -> verifica saude.
Schedule::command('backup:clean')->dailyAt('03:00');
Schedule::command('backup:run')->dailyAt('03:00');
Schedule::command('backup:monitor')->dailyAt('03:00');
Schedule::command('rascunhos:limpar')->dailyAt('03:00');
Schedule::command('media:clean-orphaned')->dailyAt('03:00');
