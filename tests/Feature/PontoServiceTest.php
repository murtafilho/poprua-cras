<?php

namespace Tests\Feature;

use App\Models\Ponto;
use App\Services\EnderecoService;
use App\Services\PontoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PontoServiceTest extends TestCase
{
    use RefreshDatabase;

    private PontoService $service;

    private EnderecoService|Mockery\MockInterface $enderecoServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enderecoServiceMock = Mockery::mock(EnderecoService::class);
        $this->enderecoServiceMock->shouldReceive('vincularEnderecoAoPonto')->byDefault()->andReturn(true);

        $this->service = new PontoService($this->enderecoServiceMock);
    }

    public function test_creates_new_ponto_when_no_nearby_ponto_exists(): void
    {
        $result = $this->service->findOrCreateFromCoordinates(-19.9135, -43.9514);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('created', $result);
        $this->assertDatabaseHas('pontos', ['id' => $result['id'], 'lat' => -19.9135, 'lng' => -43.9514]);
    }

    public function test_returns_existing_ponto_when_one_exists_within_50m(): void
    {
        $ponto = Ponto::factory()->create(['lat' => -19.9135, 'lng' => -43.9514]);
        DB::statement(
            'UPDATE pontos SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
            [-43.9514, -19.9135, $ponto->id]
        );

        // ~10m away from the original point
        $result = $this->service->findOrCreateFromCoordinates(-19.91355, -43.95145);

        $this->assertEquals($ponto->id, $result['id']);
        $this->assertFalse($result['created']);
    }

    public function test_new_ponto_gets_geom_column_set(): void
    {
        $result = $this->service->findOrCreateFromCoordinates(-19.9135, -43.9514);

        $geom = DB::selectOne(
            'SELECT ST_AsText(geom) as wkt, ST_SRID(geom) as srid FROM pontos WHERE id = ?',
            [$result['id']]
        );

        $this->assertNotNull($geom->wkt);
        $this->assertEquals(4326, $geom->srid);
        $this->assertStringContainsString('-43.9514', $geom->wkt);
        $this->assertStringContainsString('-19.9135', $geom->wkt);
    }

    public function test_new_ponto_gets_linked_to_nearest_address_via_endereco_service(): void
    {
        $this->enderecoServiceMock
            ->shouldReceive('vincularEnderecoAoPonto')
            ->once()
            ->withArgs(function (int $pontoId, float $lat, float $lng, ?string $complemento) {
                return $lat === -19.9135 && $lng === -43.9514;
            })
            ->andReturn(true);

        $this->service->findOrCreateFromCoordinates(-19.9135, -43.9514);
    }

    public function test_complemento_is_passed_to_vincular_endereco_ao_ponto(): void
    {
        $this->enderecoServiceMock
            ->shouldReceive('vincularEnderecoAoPonto')
            ->once()
            ->withArgs(function (int $pontoId, float $lat, float $lng, ?string $complemento) {
                return $complemento === 'Perto do bar';
            })
            ->andReturn(true);

        $this->service->findOrCreateFromCoordinates(-19.9135, -43.9514, 'Perto do bar');
    }

    public function test_returns_created_true_for_new_ponto(): void
    {
        $result = $this->service->findOrCreateFromCoordinates(-19.9135, -43.9514);

        $this->assertTrue($result['created']);
    }

    public function test_returns_created_false_for_existing_ponto(): void
    {
        $ponto = Ponto::factory()->create(['lat' => -19.9135, 'lng' => -43.9514]);
        DB::statement(
            'UPDATE pontos SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
            [-43.9514, -19.9135, $ponto->id]
        );

        $result = $this->service->findOrCreateFromCoordinates(-19.9135, -43.9514);

        $this->assertFalse($result['created']);
    }

    public function test_does_not_create_duplicate_when_called_twice_for_same_location(): void
    {
        $result1 = $this->service->findOrCreateFromCoordinates(-19.9135, -43.9514);
        $result2 = $this->service->findOrCreateFromCoordinates(-19.9135, -43.9514);

        $this->assertEquals($result1['id'], $result2['id']);
        $this->assertTrue($result1['created']);
        $this->assertFalse($result2['created']);
        $this->assertEquals(1, DB::table('pontos')->where('lat', -19.9135)->where('lng', -43.9514)->count());
    }
}
