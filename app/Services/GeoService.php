<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

class GeoService
{
    public const SRID = 4326;

    /** Expressão SQL: ST_SetSRID(ST_MakePoint(?, ?), 4326) */
    public static function sqlMakePoint(): string
    {
        return 'ST_SetSRID(ST_MakePoint(?, ?), '.self::SRID.')';
    }

    /** Filtro por distância em metros (operador padrão: <). */
    public static function sqlWithinDistance(string $column = 'geom', string $operator = '<'): string
    {
        return "ST_Distance({$column}::geography, ".self::sqlMakePoint()."::geography) {$operator} ?";
    }

    /** Ordenação k-NN por proximidade ao ponto. */
    public static function sqlKnnOrder(string $column = 'geom'): string
    {
        return "{$column} <-> ".self::sqlMakePoint();
    }

    /** Pré-filtro espacial com ST_Expand (graus). */
    public static function sqlExpandBounds(string $column = 'geom'): string
    {
        return "{$column} && ST_Expand(".self::sqlMakePoint().'::geometry, ?)';
    }

    /** Interseção com bounding box (west, south, east, north). */
    public static function sqlEnvelopeBounds(string $column = 'geom'): string
    {
        return "{$column} && ST_MakeEnvelope(?, ?, ?, ?, ".self::SRID.')';
    }

    /** Ordenação por distância crescente (sem limite). */
    public static function sqlDistanceOrder(string $column = 'geom'): string
    {
        return 'ST_Distance('.$column.'::geography, '.self::sqlMakePoint().'::geography)';
    }

    /** SELECT de distância em metros como alias distancia. */
    public static function sqlDistanceSelect(string $column = 'geom'): string
    {
        return 'ST_Distance('.$column.'::geography, '.self::sqlMakePoint().'::geography) as distancia';
    }

    public static function atualizarGeomPonto(int $pontoId, float $lng, float $lat): void
    {
        DB::statement(
            'UPDATE pontos SET geom = '.self::sqlMakePoint().' WHERE id = ?',
            [$lng, $lat, $pontoId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function loadBairros(): array
    {
        return $this->loadGeoJsonFeatures('geo_bairros', ['id', 'codigo', 'nome', 'area_km2', 'perimetro_m']);
    }

    /** @return array<int, array<string, mixed>> */
    public function loadRegionais(): array
    {
        return $this->loadGeoJsonFeatures('geo_regionais', ['id', 'codigo', 'sigla', 'nome', 'area_km2', 'perimetro_m']);
    }

    /** @return array<int, array<string, mixed>>|null */
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

        return [$this->toGeoJsonFeature($limite, ['id', 'area_km2', 'perimetro_m'])];
    }

    /**
     * @param  array<int, string>  $propertyKeys
     * @return array<int, array<string, mixed>>
     */
    private function loadGeoJsonFeatures(string $table, array $propertyKeys): array
    {
        return DB::table($table)
            ->select($propertyKeys)
            ->selectRaw('ST_AsGeoJSON(geom)::json as geojson')
            ->whereNotNull('geom')
            ->get()
            ->map(fn ($row) => $this->toGeoJsonFeature($row, $propertyKeys))
            ->all();
    }

    /**
     * @param  array<int, string>  $propertyKeys
     * @return array<string, mixed>
     */
    private function toGeoJsonFeature(object $row, array $propertyKeys): array
    {
        $properties = [];
        foreach ($propertyKeys as $key) {
            $properties[$key] = $row->{$key};
        }

        return [
            'type' => 'Feature',
            'properties' => $properties,
            'geometry' => json_decode($row->geojson),
        ];
    }
}
