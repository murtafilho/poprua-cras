<?php

namespace Tests\Unit\Observers;

use App\Models\Ponto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PontoObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_geom_is_set_when_ponto_created_with_coordinates(): void
    {
        $ponto = Ponto::create([
            'lat' => -19.9135,
            'lng' => -43.9514,
            'numero' => '100',
        ]);

        $result = DB::selectOne('SELECT ST_AsText(geom) as wkt, ST_SRID(geom) as srid FROM pontos WHERE id = ?', [$ponto->id]);

        $this->assertNotNull($result->wkt);
        $this->assertEquals(4326, $result->srid);
        $this->assertStringContainsString('-43.9514', $result->wkt);
        $this->assertStringContainsString('-19.9135', $result->wkt);
    }

    public function test_geom_is_updated_when_coordinates_change(): void
    {
        $ponto = Ponto::create([
            'lat' => -19.9135,
            'lng' => -43.9514,
            'numero' => '100',
        ]);

        $ponto->update(['lat' => -19.92, 'lng' => -43.96]);

        $result = DB::selectOne('SELECT ST_AsText(geom) as wkt FROM pontos WHERE id = ?', [$ponto->id]);

        $this->assertStringContainsString('-43.96', $result->wkt);
        $this->assertStringContainsString('-19.92', $result->wkt);
    }

    public function test_geom_not_updated_when_non_coordinate_fields_change(): void
    {
        $ponto = Ponto::create([
            'lat' => -19.9135,
            'lng' => -43.9514,
            'numero' => '100',
        ]);

        $originalGeom = DB::selectOne('SELECT ST_AsText(geom) as wkt FROM pontos WHERE id = ?', [$ponto->id])->wkt;

        $ponto->update(['complemento' => 'Perto do bar']);

        $newGeom = DB::selectOne('SELECT ST_AsText(geom) as wkt FROM pontos WHERE id = ?', [$ponto->id])->wkt;

        $this->assertEquals($originalGeom, $newGeom);
    }
}
