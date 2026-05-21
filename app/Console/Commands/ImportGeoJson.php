<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;

class ImportGeoJson extends Command
{
    protected $signature = 'geo:import {--fresh : Limpa as tabelas antes de importar}';

    protected $description = 'Importa arquivos GeoJSON de bairros, regionais e limite do município';

    private Proj4php $proj4;

    private Proj $utmProj;

    private Proj $wgs84Proj;

    public function handle(): int
    {
        $this->proj4 = new Proj4php;
        $this->utmProj = new Proj('EPSG:31983', $this->proj4); // UTM zona 23S - SIRGAS 2000
        $this->wgs84Proj = new Proj('EPSG:4326', $this->proj4); // WGS84

        if ($this->option('fresh')) {
            $this->info('Limpando tabelas...');
            DB::table('geo_bairros')->truncate();
            DB::table('geo_regionais')->truncate();
            DB::table('geo_limite_municipio')->truncate();
        }

        $this->importBairros();
        $this->importRegionais();
        $this->importLimiteMunicipio();

        $this->info('Importação concluída!');

        return Command::SUCCESS;
    }

    private function importBairros(): void
    {
        $this->info('Importando bairros...');
        $data = json_decode(file_get_contents(public_path('BAIRRO_POPULAR.json')), true);

        $bar = $this->output->createProgressBar(count($data['features']));

        foreach ($data['features'] as $feature) {
            $geometry = $this->convertGeometry($feature['geometry']);

            DB::table('geo_bairros')->insert([
                'codigo' => $feature['properties']['CODIGO'],
                'nome' => $feature['properties']['NOME'],
                'area_km2' => $feature['properties']['AREA_KM2'],
                'perimetro_m' => $feature['properties']['PERIMETR_M'],
                'geometry' => json_encode($geometry),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Bairros importados: '.count($data['features']));
    }

    private function importRegionais(): void
    {
        $this->info('Importando regionais...');
        $data = json_decode(file_get_contents(public_path('REGIONAL.json')), true);

        $bar = $this->output->createProgressBar(count($data['features']));

        foreach ($data['features'] as $feature) {
            $geometry = $this->convertGeometry($feature['geometry']);

            DB::table('geo_regionais')->insert([
                'codigo' => $feature['properties']['COD_REG'],
                'sigla' => $feature['properties']['SIGLA'],
                'nome' => $feature['properties']['NOME'],
                'area_km2' => $feature['properties']['AREA_KM2'],
                'perimetro_m' => $feature['properties']['PERIMETR_M'],
                'geometry' => json_encode($geometry),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Regionais importadas: '.count($data['features']));
    }

    private function importLimiteMunicipio(): void
    {
        $this->info('Importando limite do município...');
        $data = json_decode(file_get_contents(public_path('LIMITE_MUNICIPIO.json')), true);

        foreach ($data['features'] as $feature) {
            $geometry = $this->convertGeometry($feature['geometry']);

            DB::table('geo_limite_municipio')->insert([
                'area_km2' => $feature['properties']['AREA_KM2'],
                'perimetro_m' => $feature['properties']['PERIMETR_M'],
                'geometry' => json_encode($geometry),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info('Limite do município importado');
    }

    private function convertGeometry(array $geometry): array
    {
        $converted = [
            'type' => $geometry['type'],
            'coordinates' => $this->convertCoordinates($geometry['coordinates'], $geometry['type']),
        ];

        return $converted;
    }

    private function convertCoordinates(array $coords, string $type): array
    {
        return match ($type) {
            'Point' => $this->convertPoint($coords),
            'LineString', 'MultiPoint' => array_map(fn ($c) => $this->convertPoint($c), $coords),
            'Polygon', 'MultiLineString' => array_map(fn ($ring) => array_map(fn ($c) => $this->convertPoint($c), $ring), $coords),
            'MultiPolygon' => array_map(fn ($polygon) => array_map(fn ($ring) => array_map(fn ($c) => $this->convertPoint($c), $ring), $polygon), $coords),
            default => $coords,
        };
    }

    private function convertPoint(array $coords): array
    {
        $point = new Point($coords[0], $coords[1], $this->utmProj);
        $converted = $this->proj4->transform($this->wgs84Proj, $point);

        return [round($converted->x, 6), round($converted->y, 6)];
    }
}
