<?php

namespace Tests\Feature;

use App\Services\EnderecoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnderecoServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnderecoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnderecoService::class);
    }

    /**
     * Helper: inserts a row into endereco_atualizados with PostGIS geom.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function insertEndereco(array $overrides = []): int
    {
        $defaults = [
            'SIGLA_TIPO_LOGRADOURO' => 'R',
            'NOME_LOGRADOURO' => 'DOS TIMBIRAS',
            'NUMERO_IMOVEL' => '1000',
            'LETRA_IMOVEL' => null,
            'NOME_BAIRRO_POPULAR' => 'FUNCIONARIOS',
            'NOME_REGIONAL' => 'CENTRO-SUL',
            'CEP' => '30140-060',
            'lat' => -19.9245,
            'lng' => -43.9352,
        ];

        $data = array_merge($defaults, $overrides);
        $lat = $data['lat'];
        $lng = $data['lng'];

        // geom must be set via raw expression
        $data['geom'] = DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)");

        return DB::table('endereco_atualizados')->insertGetId($data);
    }

    /**
     * Helper: inserts a row into pontos with PostGIS geom.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function insertPonto(array $overrides = []): int
    {
        $defaults = [
            'lat' => -19.9245,
            'lng' => -43.9352,
            'complemento' => null,
            'endereco_atualizado_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $data = array_merge($defaults, $overrides);
        $lat = $data['lat'];
        $lng = $data['lng'];

        $data['geom'] = DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)");

        return DB::table('pontos')->insertGetId($data);
    }

    // ---------------------------------------------------------------
    // buscarEnderecoMaisProximo
    // ---------------------------------------------------------------

    public function test_buscar_endereco_mais_proximo_finds_nearest_within_radius(): void
    {
        // Reference point: Praca da Liberdade, BH
        $refLat = -19.9320;
        $refLng = -43.9380;

        // Insert two addresses — one close (~50m), one farther (~150m)
        $closeId = $this->insertEndereco([
            'NOME_LOGRADOURO' => 'DA LIBERDADE',
            'NUMERO_IMOVEL' => '100',
            'lat' => -19.93205,
            'lng' => -43.93805,
        ]);

        $farId = $this->insertEndereco([
            'NOME_LOGRADOURO' => 'GONÇALVES DIAS',
            'NUMERO_IMOVEL' => '200',
            'lat' => -19.9335,
            'lng' => -43.9390,
        ]);

        $result = $this->service->buscarEnderecoMaisProximo($refLat, $refLng);

        $this->assertNotNull($result);
        $this->assertEquals($closeId, $result->id);
        $this->assertEquals('DA LIBERDADE', $result->logradouro);
        $this->assertObjectHasProperty('distancia', $result);
        $this->assertLessThan(100, $result->distancia);
    }

    public function test_buscar_endereco_mais_proximo_returns_null_when_no_address_in_radius(): void
    {
        // Insert an address in BH
        $this->insertEndereco([
            'lat' => -19.9245,
            'lng' => -43.9352,
        ]);

        // Query from a distant point (~5km away), well beyond the 300m radius
        $result = $this->service->buscarEnderecoMaisProximo(-19.88, -43.90);

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // geocodificarEndereco — exact match (step 1)
    // ---------------------------------------------------------------

    public function test_geocodificar_exact_match_logradouro_numero_bairro(): void
    {
        $id = $this->insertEndereco([
            'NOME_LOGRADOURO' => 'DOS TIMBIRAS',
            'NUMERO_IMOVEL' => '1500',
            'NOME_BAIRRO_POPULAR' => 'FUNCIONARIOS',
            'lat' => -19.9250,
            'lng' => -43.9360,
        ]);

        $result = $this->service->geocodificarEndereco('DOS TIMBIRAS', '1500', 'FUNCIONARIOS');

        $this->assertNotNull($result);
        $this->assertEquals($id, $result->id);
        $this->assertEquals('1500', $result->numero);
        $this->assertEquals('FUNCIONARIOS', $result->bairro);
    }

    // ---------------------------------------------------------------
    // geocodificarEndereco — fallback step 2: logradouro + numero (no bairro)
    // ---------------------------------------------------------------

    public function test_geocodificar_fallback_logradouro_numero_sem_bairro(): void
    {
        $id = $this->insertEndereco([
            'NOME_LOGRADOURO' => 'AFONSO PENA',
            'NUMERO_IMOVEL' => '2000',
            'NOME_BAIRRO_POPULAR' => 'CENTRO',
            'lat' => -19.9200,
            'lng' => -43.9400,
        ]);

        // Search with a bairro that does not match — should skip step 1
        // but succeed at step 2 (logradouro + numero, any bairro)
        $result = $this->service->geocodificarEndereco('AFONSO PENA', '2000', 'BAIRRO INEXISTENTE');

        $this->assertNotNull($result);
        $this->assertEquals($id, $result->id);
    }

    // ---------------------------------------------------------------
    // geocodificarEndereco — fallback step 3/4: nearest number
    // ---------------------------------------------------------------

    public function test_geocodificar_fallback_nearest_number(): void
    {
        // Insert addresses at numbers 100, 500, 900
        $this->insertEndereco([
            'NOME_LOGRADOURO' => 'BAHIA',
            'NUMERO_IMOVEL' => '100',
            'NOME_BAIRRO_POPULAR' => 'CENTRO',
            'lat' => -19.9210,
            'lng' => -43.9410,
        ]);
        $id500 = $this->insertEndereco([
            'NOME_LOGRADOURO' => 'BAHIA',
            'NUMERO_IMOVEL' => '500',
            'NOME_BAIRRO_POPULAR' => 'CENTRO',
            'lat' => -19.9215,
            'lng' => -43.9415,
        ]);
        $this->insertEndereco([
            'NOME_LOGRADOURO' => 'BAHIA',
            'NUMERO_IMOVEL' => '900',
            'NOME_BAIRRO_POPULAR' => 'CENTRO',
            'lat' => -19.9220,
            'lng' => -43.9420,
        ]);

        // Search for number 480 — closest to 500
        $result = $this->service->geocodificarEndereco('BAHIA', '480', 'CENTRO');

        $this->assertNotNull($result);
        $this->assertEquals($id500, $result->id);
    }

    // ---------------------------------------------------------------
    // geocodificarEndereco — fallback step 5/6: any address on street
    // ---------------------------------------------------------------

    public function test_geocodificar_fallback_any_address_on_street(): void
    {
        $id = $this->insertEndereco([
            'NOME_LOGRADOURO' => 'ESPIRITO SANTO',
            'NUMERO_IMOVEL' => '300',
            'NOME_BAIRRO_POPULAR' => 'CENTRO',
            'lat' => -19.9230,
            'lng' => -43.9430,
        ]);

        // Search with no number — skips steps 1-4, falls to step 5/6
        $result = $this->service->geocodificarEndereco('ESPIRITO SANTO', null, 'CENTRO');

        $this->assertNotNull($result);
        $this->assertEquals($id, $result->id);
    }

    // ---------------------------------------------------------------
    // geocodificarEndereco — returns null when nothing found
    // ---------------------------------------------------------------

    public function test_geocodificar_returns_null_when_not_found(): void
    {
        // Insert an address that won't match the query
        $this->insertEndereco([
            'NOME_LOGRADOURO' => 'DOS TIMBIRAS',
            'NUMERO_IMOVEL' => '100',
        ]);

        $result = $this->service->geocodificarEndereco('LOGRADOURO INEXISTENTE QUE NAO EXISTE', '999', 'BAIRRO FICTICIO');

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // normalizarTexto — tested indirectly via geocodificar with accents
    // ---------------------------------------------------------------

    public function test_normalizar_texto_removes_accents_via_geocodificar(): void
    {
        $id = $this->insertEndereco([
            'NOME_LOGRADOURO' => 'PARAIBA',
            'NUMERO_IMOVEL' => '700',
            'NOME_BAIRRO_POPULAR' => 'FUNCIONARIOS',
            'lat' => -19.9260,
            'lng' => -43.9370,
        ]);

        // Input with accents — normalizarTexto should strip them before querying
        $result = $this->service->geocodificarEndereco('Paraíba', '700', 'Funcionários');

        $this->assertNotNull($result);
        $this->assertEquals($id, $result->id);
    }

    // ---------------------------------------------------------------
    // removerTipoLogradouro — tested indirectly via geocodificar
    // ---------------------------------------------------------------

    public function test_remover_tipo_logradouro_strips_prefix_via_geocodificar(): void
    {
        $id = $this->insertEndereco([
            'NOME_LOGRADOURO' => 'GUAICURUS',
            'NUMERO_IMOVEL' => '800',
            'NOME_BAIRRO_POPULAR' => 'CENTRO',
            'lat' => -19.9270,
            'lng' => -43.9380,
        ]);

        // Input with "RUA" prefix — removerTipoLogradouro should strip it
        $result = $this->service->geocodificarEndereco('RUA GUAICURUS', '800', 'CENTRO');

        $this->assertNotNull($result);
        $this->assertEquals($id, $result->id);
    }

    // ---------------------------------------------------------------
    // vincularEnderecoAoPonto
    // ---------------------------------------------------------------

    public function test_vincular_endereco_ao_ponto_links_address_and_generates_reference(): void
    {
        $enderecoLat = -19.9245;
        $enderecoLng = -43.9352;

        $enderecoId = $this->insertEndereco([
            'SIGLA_TIPO_LOGRADOURO' => 'R',
            'NOME_LOGRADOURO' => 'DOS TIMBIRAS',
            'NUMERO_IMOVEL' => '1000',
            'lat' => $enderecoLat,
            'lng' => $enderecoLng,
        ]);

        // Insert a ponto ~30m from the address (within 300m radius)
        $pontoLat = $enderecoLat + 0.0003;
        $pontoLng = $enderecoLng + 0.0003;
        $pontoId = $this->insertPonto([
            'lat' => $pontoLat,
            'lng' => $pontoLng,
        ]);

        $result = $this->service->vincularEnderecoAoPonto($pontoId, $pontoLat, $pontoLng);

        $this->assertTrue($result);

        $ponto = DB::table('pontos')->where('id', $pontoId)->first();
        $this->assertEquals($enderecoId, $ponto->endereco_atualizado_id);

        // Without user-provided complemento, the service generates a distance-based reference
        $this->assertNotNull($ponto->complemento);
        $this->assertStringContainsString('R', $ponto->complemento);
        $this->assertStringContainsString('DOS TIMBIRAS', $ponto->complemento);
        $this->assertStringContainsString('1000', $ponto->complemento);
        $this->assertMatchesRegularExpression('/^\d+m de /', $ponto->complemento);
    }

    public function test_vincular_endereco_ao_ponto_preserves_user_complemento(): void
    {
        $enderecoId = $this->insertEndereco([
            'lat' => -19.9245,
            'lng' => -43.9352,
        ]);

        $pontoId = $this->insertPonto([
            'lat' => -19.9246,
            'lng' => -43.9353,
        ]);

        $result = $this->service->vincularEnderecoAoPonto($pontoId, -19.9246, -43.9353, 'Em frente ao mercado');

        $this->assertTrue($result);

        $ponto = DB::table('pontos')->where('id', $pontoId)->first();
        $this->assertEquals($enderecoId, $ponto->endereco_atualizado_id);
        $this->assertEquals('Em frente ao mercado', $ponto->complemento);
    }

    public function test_vincular_endereco_ao_ponto_returns_false_when_no_nearby_address(): void
    {
        $pontoId = $this->insertPonto([
            'lat' => -19.88,
            'lng' => -43.90,
        ]);

        // No addresses inserted nearby
        $result = $this->service->vincularEnderecoAoPonto($pontoId, -19.88, -43.90);

        $this->assertFalse($result);

        $ponto = DB::table('pontos')->where('id', $pontoId)->first();
        $this->assertNull($ponto->endereco_atualizado_id);
    }
}
