<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateStackPdfCommand extends Command
{
    protected $signature = 'docs:stack-pdf {--output=docs/STACK_TECNOLOGICA.pdf}';

    protected $description = 'Gera PDF da stack tecnológica do SIZEM BH';

    public function handle(): int
    {
        $output = base_path($this->option('output'));
        File::ensureDirectoryExists(dirname($output));

        $versoes = $this->versoes();

        Pdf::loadView('docs.stack-tecnologica', [
            'geradoEm' => now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'versoes' => $versoes,
        ])
            ->setPaper('a4', 'portrait')
            ->save($output);

        $this->info("PDF gerado: {$output}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function versoes(): array
    {
        $lock = json_decode(File::get(base_path('package-lock.json')), true);
        $npm = static function (string $name) use ($lock): string {
            $key = "node_modules/{$name}";

            return $lock['packages'][$key]['version'] ?? '?';
        };

        return [
            'laravel' => $this->composerVersion('laravel/framework'),
            'breeze' => $this->composerVersion('laravel/breeze'),
            'sanctum' => $this->composerVersion('laravel/sanctum'),
            'permission' => $this->composerVersion('spatie/laravel-permission'),
            'medialibrary' => $this->composerVersion('spatie/laravel-medialibrary'),
            'activitylog' => $this->composerVersion('spatie/laravel-activitylog'),
            'backup' => $this->composerVersion('spatie/laravel-backup'),
            'dompdf' => $this->composerVersion('barryvdh/laravel-dompdf'),
            'proj4php' => $this->composerVersion('proj4php/proj4php'),
            'phpunit' => $this->composerVersion('phpunit/phpunit'),
            'phpstan' => $this->composerVersion('phpstan/phpstan'),
            'larastan' => $this->composerVersion('larastan/larastan'),
            'pint' => $this->composerVersion('laravel/pint'),
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

    private function composerVersion(string $package): string
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
