<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GeoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_bairros_returns_geojson_feature_collection(): void
    {
        DB::table('geo_bairros')->insert([
            'codigo' => '001',
            'nome' => 'Centro',
            'area_km2' => 1.5,
            'perimetro_m' => 5000,
            'geometry' => json_encode(['type' => 'MultiPolygon', 'coordinates' => [[[[0, 0], [1, 0], [1, 1], [0, 0]]]]]),
            'geom' => DB::raw("ST_GeomFromGeoJSON('{\"type\":\"MultiPolygon\",\"coordinates\":[[[[0,0],[1,0],[1,1],[0,0]]]]}')"),
        ]);

        $response = $this->getJson('/api/geo/bairros');

        $response->assertOk()
            ->assertJsonStructure([
                'type',
                'features' => [['type', 'properties' => ['id', 'nome'], 'geometry']],
            ])
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('features.0.properties.nome', 'Centro');
    }

    public function test_bairros_response_is_cached(): void
    {
        DB::table('geo_bairros')->insert([
            'codigo' => '001',
            'nome' => 'Savassi',
            'area_km2' => 0.8,
            'perimetro_m' => 3000,
            'geometry' => json_encode(['type' => 'MultiPolygon', 'coordinates' => [[[[0, 0], [1, 0], [1, 1], [0, 0]]]]]),
            'geom' => DB::raw("ST_GeomFromGeoJSON('{\"type\":\"MultiPolygon\",\"coordinates\":[[[[0,0],[1,0],[1,1],[0,0]]]]}')"),
        ]);

        $this->getJson('/api/geo/bairros')->assertOk();
        $this->assertTrue(Cache::has('geo:bairros'));
    }

    public function test_regionais_returns_geojson(): void
    {
        DB::table('geo_regionais')->insert([
            'codigo' => '01',
            'sigla' => 'CS',
            'nome' => 'Centro-Sul',
            'area_km2' => 30.0,
            'perimetro_m' => 25000,
            'geometry' => json_encode(['type' => 'MultiPolygon', 'coordinates' => [[[[0, 0], [1, 0], [1, 1], [0, 0]]]]]),
            'geom' => DB::raw("ST_GeomFromGeoJSON('{\"type\":\"MultiPolygon\",\"coordinates\":[[[[0,0],[1,0],[1,1],[0,0]]]]}')"),
        ]);

        $response = $this->getJson('/api/geo/regionais');

        $response->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('features.0.properties.sigla', 'CS');
    }

    public function test_limite_municipio_returns_geojson(): void
    {
        DB::table('geo_limite_municipio')->insert([
            'area_km2' => 331.0,
            'perimetro_m' => 80000,
            'geometry' => json_encode(['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 0]]]]),
            'geom' => DB::raw("ST_GeomFromGeoJSON('{\"type\":\"Polygon\",\"coordinates\":[[[0,0],[1,0],[1,1],[0,0]]]}')"),
        ]);

        $response = $this->getJson('/api/geo/limite-municipio');

        $response->assertOk()
            ->assertJsonPath('type', 'FeatureCollection');
    }

    public function test_limite_municipio_returns_404_when_empty(): void
    {
        $this->getJson('/api/geo/limite-municipio')
            ->assertNotFound();
    }

    public function test_bairros_returns_empty_collection_when_no_data(): void
    {
        $response = $this->getJson('/api/geo/bairros');

        $response->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonCount(0, 'features');
    }

    public function test_bairros_returns_etag_header(): void
    {
        $response = $this->getJson('/api/geo/bairros');

        $response->assertOk()
            ->assertHeader('ETag')
            ->assertHeader('Cache-Control');
    }

    public function test_bairros_returns_304_on_matching_etag(): void
    {
        $first = $this->getJson('/api/geo/bairros');
        $etag = $first->headers->get('ETag');

        $second = $this->getJson('/api/geo/bairros', ['If-None-Match' => $etag]);

        $second->assertStatus(304);
    }
}
