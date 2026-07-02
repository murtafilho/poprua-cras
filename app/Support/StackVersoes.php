<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class StackVersoes
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $lock = json_decode(File::get(base_path('package-lock.json')), true);
        $npm = static function (string $name) use ($lock): string {
            $key = "node_modules/{$name}";

            return $lock['packages'][$key]['version'] ?? '?';
        };

        return [
            'laravel' => self::composerVersion('laravel/framework'),
            'breeze' => self::composerVersion('laravel/breeze'),
            'sanctum' => self::composerVersion('laravel/sanctum'),
            'permission' => self::composerVersion('spatie/laravel-permission'),
            'medialibrary' => self::composerVersion('spatie/laravel-medialibrary'),
            'activitylog' => self::composerVersion('spatie/laravel-activitylog'),
            'backup' => self::composerVersion('spatie/laravel-backup'),
            'dompdf' => self::composerVersion('barryvdh/laravel-dompdf'),
            'proj4php' => self::composerVersion('proj4php/proj4php'),
            'phpunit' => self::composerVersion('phpunit/phpunit'),
            'phpstan' => self::composerVersion('phpstan/phpstan'),
            'larastan' => self::composerVersion('larastan/larastan'),
            'pint' => self::composerVersion('laravel/pint'),
            'playwright' => $npm('@playwright/test'),
            'alpinejs' => $npm('alpinejs'),
            'leaflet' => $npm('leaflet'),
            'markercluster' => $npm('leaflet.markercluster'),
            'chartjs' => $npm('chart.js'),
            'flatpickr' => $npm('flatpickr'),
            'axios' => $npm('axios'),
            'vite' => $npm('vite'),
        ];
    }

    public static function composerVersion(string $package): string
    {
        $lock = json_decode(File::get(base_path('composer.lock')), true);
        foreach ($lock['packages'] as $pkg) {
            if (($pkg['name'] ?? '') === $package) {
                return ltrim($pkg['version'] ?? '?', 'v');
            }
        }

        return '?';
    }
}
