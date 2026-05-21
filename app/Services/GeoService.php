<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

class GeoService
{
    public function loadBairros(): array
    {
        return DB::table('geo_bairros')
            ->select('id', 'codigo', 'nome', 'area_km2', 'perimetro_m')
            ->selectRaw('ST_AsGeoJSON(geom)::json as geojson')
            ->whereNotNull('geom')
            ->get()
            ->map(fn ($b) => [
                'type' => 'Feature',
                'properties' => [
                    'id' => $b->id,
                    'codigo' => $b->codigo,
                    'nome' => $b->nome,
                    'area_km2' => $b->area_km2,
                    'perimetro_m' => $b->perimetro_m,
                ],
                'geometry' => json_decode($b->geojson),
            ])->all();
    }

    public function loadRegionais(): array
    {
        return DB::table('geo_regionais')
            ->select('id', 'codigo', 'sigla', 'nome', 'area_km2', 'perimetro_m')
            ->selectRaw('ST_AsGeoJSON(geom)::json as geojson')
            ->whereNotNull('geom')
            ->get()
            ->map(fn ($r) => [
                'type' => 'Feature',
                'properties' => [
                    'id' => $r->id,
                    'codigo' => $r->codigo,
                    'sigla' => $r->sigla,
                    'nome' => $r->nome,
                    'area_km2' => $r->area_km2,
                    'perimetro_m' => $r->perimetro_m,
                ],
                'geometry' => json_decode($r->geojson),
            ])->all();
    }

    public function loadLimite(): ?array
    {
        $limite = DB::table('geo_limite_municipio')
            ->select('id', 'area_km2', 'perimetro_m')
            ->selectRaw('ST_AsGeoJSON(geom)::json as geojson')
            ->whereNotNull('geom')
            ->first();

        if (! $limite) {
            return null;
        }

        return [[
            'type' => 'Feature',
            'properties' => [
                'id' => $limite->id,
                'area_km2' => $limite->area_km2,
                'perimetro_m' => $limite->perimetro_m,
            ],
            'geometry' => json_decode($limite->geojson),
        ]];
    }
}
