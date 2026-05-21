<?php

namespace App\Console\Commands;

use App\Services\GeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmCacheCommand extends Command
{
    protected $signature = 'cache:warm {--force : Força regeneração mesmo se cache existir}';

    protected $description = 'Aquece o cache com dados estáticos (geo, etc.)';

    private const TTL = 86400;

    public function handle(GeoService $geoService): int
    {
        $this->info('Aquecendo cache...');
        $start = microtime(true);

        $items = [
            'geo:bairros' => fn () => $geoService->loadBairros(),
            'geo:regionais' => fn () => $geoService->loadRegionais(),
            'geo:limite-municipio' => fn () => $geoService->loadLimite(),
        ];

        foreach ($items as $key => $loader) {
            if ($this->option('force')) {
                Cache::forget($key);
            }

            if (Cache::has($key) && ! $this->option('force')) {
                $this->line("  <comment>SKIP</comment>  {$key} (ja em cache)");

                continue;
            }

            $this->line("  <info>LOAD</info>  {$key}...");
            $t = microtime(true);
            Cache::remember($key, self::TTL, $loader);
            $ms = round((microtime(true) - $t) * 1000);
            $this->line("  <info>OK</info>    {$key} ({$ms}ms)");
        }

        $total = round((microtime(true) - $start) * 1000);
        $this->info("Cache aquecido em {$total}ms.");

        return 0;
    }
}
