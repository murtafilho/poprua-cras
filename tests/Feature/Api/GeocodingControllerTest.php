<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GeocodingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function insertEndereco(array $overrides = []): void
    {
        $defaults = [
            'SIGLA_TIPO_LOGRADOURO' => 'RUA',
            'NOME_LOGRADOURO' => 'AFONSO PENA',
            'NUMERO_IMOVEL' => '1000',
            'NOME_BAIRRO_POPULAR' => 'Centro',
            'NOME_REGIONAL' => 'CENTRO-SUL',
            'CEP' => '30130000',
            'lat' => -19.9191,
            'lng' => -43.9386,
        ];

        $data = array_merge($defaults, $overrides);
        $data['geom'] = DB::raw("ST_SetSRID(ST_MakePoint({$data['lng']}, {$data['lat']}), 4326)");

        DB::table('endereco_atualizados')->insert($data);
    }

    public function test_geocode_requires_authentication(): void
    {
        $this->postJson('/api/geocode', ['logradouro' => 'AFONSO PENA'])
            ->assertUnauthorized();
    }

    public function test_geocode_validates_logradouro_required(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/geocode', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('logradouro');
    }

    public function test_geocode_finds_address_in_local_database(): void
    {
        $this->insertEndereco();

        $response = $this->actingAs($this->user)
            ->postJson('/api/geocode', ['logradouro' => 'AFONSO PENA', 'numero' => '1000']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('source', 'endereco_atualizado')
            ->assertJsonStructure(['lat', 'lng', 'display_name', 'address']);
    }

    public function test_geocode_returns_address_details(): void
    {
        $this->insertEndereco();

        $response = $this->actingAs($this->user)
            ->postJson('/api/geocode', ['logradouro' => 'AFONSO PENA']);

        $response->assertOk()
            ->assertJsonPath('address.logradouro', 'AFONSO PENA')
            ->assertJsonPath('address.bairro', 'Centro');
    }

    public function test_geocode_not_found_falls_back_to_nominatim(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/geocode', [
                'logradouro' => 'RUA INEXISTENTE XYZ123',
                'cidade' => 'Belo Horizonte',
            ]);

        $response->assertJsonStructure(['success']);
    }

    public function test_geocode_response_never_contains_stack_trace(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/geocode', [
                'logradouro' => 'AFONSO PENA',
                'cidade' => 'Belo Horizonte',
            ]);

        $json = $response->json();
        $this->assertArrayNotHasKey('trace', $json);
        $this->assertArrayNotHasKey('file', $json);
        $this->assertArrayNotHasKey('line', $json);
    }
}
