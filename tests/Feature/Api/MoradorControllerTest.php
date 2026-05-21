<?php

namespace Tests\Feature\Api;

use App\Models\Morador;
use App\Models\MoradorHistorico;
use App\Models\Ponto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MoradorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $tipoAbordagemId;

    private int $resultadoAcaoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->tipoAbordagemId = DB::table('tipo_abordagem')->insertGetId(['tipo' => 'Rotina']);
        $this->resultadoAcaoId = DB::table('resultados_acoes')->insertGetId(['resultado' => 'Orientacao']);
    }

    public function test_index_returns_paginated_moradores(): void
    {
        Morador::factory()->count(25)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/moradores');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
            ]);

        $this->assertCount(20, $response->json('data'));
        $this->assertEquals(25, $response->json('total'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/moradores');

        $response->assertUnauthorized();
    }

    public function test_index_filters_by_ponto_id(): void
    {
        $ponto = Ponto::factory()->create();
        Morador::factory()->create(['ponto_atual_id' => $ponto->id]);
        Morador::factory()->create(['ponto_atual_id' => null]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/moradores?ponto_id={$ponto->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_filters_by_search_term(): void
    {
        Morador::factory()->create(['nome_social' => 'Joao Pedro']);
        Morador::factory()->create(['nome_social' => 'Maria Clara']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/moradores?search=Joao');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Joao Pedro', $response->json('data.0.nome_social'));
    }

    public function test_index_filters_sem_ponto(): void
    {
        $ponto = Ponto::factory()->create();
        Morador::factory()->create(['ponto_atual_id' => $ponto->id]);
        Morador::factory()->create(['ponto_atual_id' => null]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/moradores?sem_ponto=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertNull($response->json('data.0.ponto_atual_id'));
    }

    public function test_buscar_searches_by_nome_social(): void
    {
        Morador::factory()->create(['nome_social' => 'Carlos Eduardo']);
        Morador::factory()->create(['nome_social' => 'Ana Maria']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/moradores/buscar?termo=Carlos');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertEquals('Carlos Eduardo', $response->json('0.nome_social'));
    }

    public function test_buscar_searches_by_apelido(): void
    {
        Morador::factory()->create(['apelido' => 'Pretinho', 'nome_social' => 'Fulano']);
        Morador::factory()->create(['apelido' => 'Loira', 'nome_social' => 'Sicrana']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/moradores/buscar?termo=Pretinho');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_buscar_requires_min_2_chars(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/moradores/buscar?termo=A');

        $response->assertUnprocessable();
    }

    public function test_buscar_requires_termo(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/moradores/buscar');

        $response->assertUnprocessable();
    }

    public function test_store_creates_morador_with_valid_data(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/moradores', [
                'nome_social' => 'Joao da Silva',
                'apelido' => 'Jota',
                'genero' => 'Homem cisgenero',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nome_social', 'Joao da Silva');

        $this->assertDatabaseHas('moradores', [
            'nome_social' => 'Joao da Silva',
            'apelido' => 'Jota',
        ]);
    }

    public function test_store_creates_morador_with_ponto_entry(): void
    {
        $ponto = Ponto::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/moradores', [
                'nome_social' => 'Maria com Ponto',
                'ponto_id' => $ponto->id,
            ]);

        $response->assertCreated();

        $morador = Morador::where('nome_social', 'Maria com Ponto')->first();
        $this->assertEquals($ponto->id, $morador->ponto_atual_id);

        $this->assertDatabaseHas('morador_historicos', [
            'morador_id' => $morador->id,
            'ponto_id' => $ponto->id,
        ]);
    }

    public function test_store_validates_required_nome_social(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/moradores', [
                'apelido' => 'Sem Nome',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['nome_social']);
    }

    public function test_store_validates_ponto_id_exists(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/moradores', [
                'nome_social' => 'Teste',
                'ponto_id' => 99999,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ponto_id']);
    }

    public function test_show_returns_morador_details(): void
    {
        $morador = Morador::factory()->create(['nome_social' => 'Detalhes Morador']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/moradores/{$morador->id}");

        $response->assertOk()
            ->assertJsonPath('nome_social', 'Detalhes Morador')
            ->assertJsonPath('id', $morador->id);
    }

    public function test_show_returns_404_for_nonexistent_morador(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/moradores/99999');

        $response->assertNotFound();
    }

    public function test_update_modifies_morador_data(): void
    {
        $morador = Morador::factory()->create(['nome_social' => 'Nome Antigo']);

        $response = $this->actingAs($this->user)
            ->putJson("/api/moradores/{$morador->id}", [
                'nome_social' => 'Nome Novo',
                'apelido' => 'Apelido Novo',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nome_social', 'Nome Novo');

        $this->assertDatabaseHas('moradores', [
            'id' => $morador->id,
            'nome_social' => 'Nome Novo',
            'apelido' => 'Apelido Novo',
        ]);
    }

    public function test_update_validates_nome_social_max_length(): void
    {
        $morador = Morador::factory()->create();

        $response = $this->actingAs($this->user)
            ->putJson("/api/moradores/{$morador->id}", [
                'nome_social' => str_repeat('A', 256),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['nome_social']);
    }

    public function test_destroy_soft_deletes_morador(): void
    {
        $morador = Morador::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/moradores/{$morador->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('moradores', ['id' => $morador->id]);
    }

    public function test_destroy_unlinks_ponto_and_registers_saida(): void
    {
        $ponto = Ponto::factory()->create();
        $morador = Morador::factory()->create(['ponto_atual_id' => $ponto->id]);
        MoradorHistorico::create([
            'morador_id' => $morador->id,
            'ponto_id' => $ponto->id,
            'data_entrada' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/moradores/{$morador->id}");

        $response->assertOk();
        $this->assertSoftDeleted('moradores', ['id' => $morador->id]);

        // ponto_atual_id should be nulled via registrarSaida
        $morador = Morador::withTrashed()->find($morador->id);
        $this->assertNull($morador->ponto_atual_id);
    }

    public function test_restore_recovers_soft_deleted_morador(): void
    {
        $morador = Morador::factory()->create();
        $morador->delete();

        $response = $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/restaurar");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('moradores', [
            'id' => $morador->id,
            'deleted_at' => null,
        ]);
    }

    public function test_restore_returns_422_if_not_trashed(): void
    {
        $morador = Morador::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/restaurar");

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_restore_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/moradores/99999/restaurar');

        $response->assertNotFound();
    }

    public function test_historico_returns_movement_history(): void
    {
        $ponto = Ponto::factory()->create();
        $morador = Morador::factory()->create(['ponto_atual_id' => $ponto->id]);

        MoradorHistorico::create([
            'morador_id' => $morador->id,
            'ponto_id' => $ponto->id,
            'data_entrada' => now()->subDays(5),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/moradores/{$morador->id}/historico");

        $response->assertOk()
            ->assertJsonStructure([
                'morador' => ['id', 'nome_social', 'apelido'],
                'historico',
            ]);

        $this->assertCount(1, $response->json('historico'));
    }

    public function test_entrada_registers_morador_entry_at_ponto(): void
    {
        $morador = Morador::factory()->create(['ponto_atual_id' => null]);
        $ponto = Ponto::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/entrada", [
                'ponto_id' => $ponto->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals($ponto->id, $morador->fresh()->ponto_atual_id);
        $this->assertDatabaseHas('morador_historicos', [
            'morador_id' => $morador->id,
            'ponto_id' => $ponto->id,
        ]);
    }

    public function test_entrada_validates_ponto_id_required(): void
    {
        $morador = Morador::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/entrada", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ponto_id']);
    }

    public function test_entrada_validates_ponto_exists(): void
    {
        $morador = Morador::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/entrada", [
                'ponto_id' => 99999,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ponto_id']);
    }

    public function test_saida_registers_morador_exit_from_ponto(): void
    {
        $ponto = Ponto::factory()->create();
        $morador = Morador::factory()->create(['ponto_atual_id' => $ponto->id]);

        MoradorHistorico::create([
            'morador_id' => $morador->id,
            'ponto_id' => $ponto->id,
            'data_entrada' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/saida");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull($morador->fresh()->ponto_atual_id);
    }

    public function test_transferir_moves_morador_between_pontos(): void
    {
        $pontoOrigem = Ponto::factory()->create();
        $pontoDestino = Ponto::factory()->create();
        $morador = Morador::factory()->create(['ponto_atual_id' => $pontoOrigem->id]);

        MoradorHistorico::create([
            'morador_id' => $morador->id,
            'ponto_id' => $pontoOrigem->id,
            'data_entrada' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/transferir", [
                'ponto_id' => $pontoDestino->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $morador->refresh();
        $this->assertEquals($pontoDestino->id, $morador->ponto_atual_id);

        // Old historico should be closed
        $historicoAntigo = MoradorHistorico::where('morador_id', $morador->id)
            ->where('ponto_id', $pontoOrigem->id)
            ->first();
        $this->assertNotNull($historicoAntigo->data_saida);

        // New historico should be open
        $historicoNovo = MoradorHistorico::where('morador_id', $morador->id)
            ->where('ponto_id', $pontoDestino->id)
            ->first();
        $this->assertNotNull($historicoNovo->data_entrada);
        $this->assertNull($historicoNovo->data_saida);
    }

    public function test_transferir_validates_ponto_id_required(): void
    {
        $morador = Morador::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/transferir", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ponto_id']);
    }

    public function test_por_ponto_returns_moradores_at_specific_ponto(): void
    {
        $ponto = Ponto::factory()->create();
        $outroPonto = Ponto::factory()->create();

        Morador::factory()->count(3)->create(['ponto_atual_id' => $ponto->id]);
        Morador::factory()->count(2)->create(['ponto_atual_id' => $outroPonto->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/pontos/{$ponto->id}/moradores");

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_arquivados_returns_only_soft_deleted_moradores(): void
    {
        Morador::factory()->count(2)->create();
        $deletados = Morador::factory()->count(3)->create();
        $deletados->each->delete();

        $response = $this->actingAs($this->user)
            ->getJson('/api/moradores/arquivados');

        $response->assertOk();
        $this->assertEquals(3, $response->json('total'));
    }

    public function test_arquivados_supports_search_filter(): void
    {
        $m1 = Morador::factory()->create(['nome_social' => 'Pedro Arquivado']);
        $m2 = Morador::factory()->create(['nome_social' => 'Ana Arquivada']);
        $m1->delete();
        $m2->delete();

        $response = $this->actingAs($this->user)
            ->getJson('/api/moradores/arquivados?search=Pedro');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }
}
