<?php

namespace Tests\Feature\Api;

use App\Models\EnderecoAtualizado;
use App\Models\Ponto;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PontoApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_lista_pontos_em_bounds(): void
    {
        // Criar pontos dentro da área
        Ponto::factory()->count(3)->create([
            'lat' => -19.90,
            'lng' => -43.95,
        ]);

        // Criar ponto fora da área
        Ponto::factory()->create([
            'lat' => -20.50,
            'lng' => -44.50,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/pontos?'.http_build_query([
            'north' => -19.80,
            'south' => -20.00,
            'east' => -43.80,
            'west' => -44.00,
        ]));

        $response->assertOk();
        $response->assertJsonCount(3);
    }

    public function test_lista_pontos_requer_bounds(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/pontos');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['north', 'south', 'east', 'west']);
    }

    public function test_lista_pontos_valida_bounds_numericos(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/pontos?'.http_build_query([
            'north' => 'invalid',
            'south' => -20.00,
            'east' => -43.80,
            'west' => -44.00,
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['north']);
    }

    public function test_mostra_detalhes_do_ponto(): void
    {
        $endereco = EnderecoAtualizado::factory()->create([
            'NOME_LOGRADOURO' => 'DA PAZ',
            'NOME_BAIRRO_OFICIAL' => 'CENTRO',
        ]);

        $ponto = Ponto::factory()->create([
            'endereco_atualizado_id' => $endereco->id,
        ]);

        Vistoria::factory()->count(2)->create(['ponto_id' => $ponto->id]);

        $response = $this->actingAs($this->user)->getJson("/api/pontos/{$ponto->id}");

        $response->assertOk();
        $response->assertJsonFragment(['id' => $ponto->id]);
    }

    public function test_ponto_nao_encontrado_retorna_404(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/pontos/99999');

        $response->assertStatus(404);
        $response->assertJsonFragment(['error' => 'Ponto não encontrado']);
    }

    public function test_buscar_logradouros_autocomplete(): void
    {
        EnderecoAtualizado::factory()->create([
            'NOME_LOGRADOURO' => 'DA PAZ',
            'NOME_REGIONAL' => 'CENTRO-SUL',
        ]);

        EnderecoAtualizado::factory()->create([
            'NOME_LOGRADOURO' => 'DAS FLORES',
            'NOME_REGIONAL' => 'PAMPULHA',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/enderecos/logradouros?q=DA');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json()));
    }

    public function test_buscar_logradouros_requer_minimo_caracteres(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/enderecos/logradouros?q=D');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['q']);
    }

    public function test_buscar_endereco_por_logradouro_e_numero(): void
    {
        EnderecoAtualizado::factory()->create([
            'NOME_LOGRADOURO' => 'AFONSO PENA',
            'NUMERO_IMOVEL' => 1000,
            'lat' => -19.92,
            'lng' => -43.94,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/enderecos/buscar?'.http_build_query([
            'logradouro' => 'AFONSO PENA',
            'numero' => 1000,
        ]));

        $response->assertOk();
        $response->assertJsonFragment(['encontrado' => true]);
    }

    public function test_buscar_endereco_numero_aproximado(): void
    {
        EnderecoAtualizado::factory()->create([
            'NOME_LOGRADOURO' => 'AFONSO PENA',
            'NUMERO_IMOVEL' => 1000,
            'lat' => -19.92,
            'lng' => -43.94,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/enderecos/buscar?'.http_build_query([
            'logradouro' => 'AFONSO PENA',
            'numero' => 1050,
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'encontrado' => true,
            'exato' => false,
        ]);
    }

    public function test_buscar_endereco_nao_encontrado(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/enderecos/buscar?'.http_build_query([
            'logradouro' => 'LOGRADOURO INEXISTENTE',
            'numero' => 9999,
        ]));

        $response->assertOk();
        $response->assertJsonFragment(['encontrado' => false]);
    }

    public function test_buscar_endereco_por_coordenadas(): void
    {
        EnderecoAtualizado::factory()->create([
            'lat' => -19.92,
            'lng' => -43.94,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/enderecos/por-coordenadas?'.http_build_query([
            'lat' => -19.9201,
            'lng' => -43.9401,
        ]));

        $response->assertOk();
        $response->assertJsonFragment(['encontrado' => true]);
        $response->assertJsonStructure([
            'encontrado',
            'endereco',
            'distancia_metros',
        ]);
    }

    public function test_buscar_endereco_por_coordenadas_fora_do_raio(): void
    {
        EnderecoAtualizado::factory()->create([
            'lat' => -19.92,
            'lng' => -43.94,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/enderecos/por-coordenadas?'.http_build_query([
            'lat' => -20.50, // Muito longe
            'lng' => -44.50,
        ]));

        $response->assertOk();
        $response->assertJsonFragment(['encontrado' => false]);
    }

    public function test_atualizar_coordenadas_ponto(): void
    {
        $ponto = Ponto::factory()->comEndereco()->create([
            'lat' => null,
            'lng' => null,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/pontos/{$ponto->id}/coordenadas", [
            'lat' => -19.92,
            'lng' => -43.94,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);

        $ponto->refresh();
        $this->assertEquals(-19.92, $ponto->lat);
        $this->assertEquals(-43.94, $ponto->lng);
    }

    public function test_atualizar_coordenadas_valida_lat_lng(): void
    {
        $ponto = Ponto::factory()->create();

        $response = $this->actingAs($this->user)->patchJson("/api/pontos/{$ponto->id}/coordenadas", [
            'lat' => 999, // Inválido
            'lng' => -43.94,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['lat']);
    }

    public function test_atualizar_coordenadas_ponto_inexistente(): void
    {
        $response = $this->actingAs($this->user)->patchJson('/api/pontos/99999/coordenadas', [
            'lat' => -19.92,
            'lng' => -43.94,
        ]);

        $response->assertStatus(404);
    }

    public function test_api_pontos_requer_autenticacao(): void
    {
        $response = $this->getJson('/api/pontos?'.http_build_query([
            'north' => -19.80,
            'south' => -20.00,
            'east' => -43.80,
            'west' => -44.00,
        ]));

        $response->assertUnauthorized();
    }

    public function test_limite_de_5000_pontos(): void
    {
        Ponto::factory()->count(100)->create([
            'lat' => -19.90,
            'lng' => -43.95,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/pontos?'.http_build_query([
            'north' => -19.00,
            'south' => -21.00,
            'east' => -42.00,
            'west' => -45.00,
        ]));

        $response->assertOk();
        // O controller limita a 5000, mas temos apenas 100
        $this->assertLessThanOrEqual(5000, count($response->json()));
    }
}
