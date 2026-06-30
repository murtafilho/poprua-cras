<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Ponto;
use App\Models\User;
use App\Models\VistoriaRascunho;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VistoriaRascunhoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tipo_abordagem')->insertGetId(['tipo' => 'Rotina']);
        DB::table('resultados_acoes')->insertGetId(['resultado' => 'Orientação']);

        $this->user = User::factory()->create();
    }

    public function test_patch_saves_rascunho_by_ponto(): void
    {
        $ponto = Ponto::factory()->create();

        $response = $this->actingAs($this->user)->patchJson('/api/vistorias/rascunho', [
            'ponto_id' => $ponto->id,
            'lat' => -19.91,
            'lng' => -43.94,
            'etapa_atual' => 2,
            'payload' => [
                'data_abordagem' => '2026-06-24T10:00',
                'observacao' => 'Teste rascunho',
            ],
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('vistorias_rascunhos', [
            'user_id' => $this->user->id,
            'ponto_id' => $ponto->id,
            'etapa_atual' => 2,
            'context_key' => 'ponto:'.$ponto->id,
        ]);
    }

    public function test_get_retrieves_rascunho_for_context(): void
    {
        $ponto = Ponto::factory()->create();

        VistoriaRascunho::query()->create([
            'user_id' => $this->user->id,
            'ponto_id' => $ponto->id,
            'context_key' => 'ponto:'.$ponto->id,
            'payload' => ['observacao' => 'Salvo'],
            'etapa_atual' => 1,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/vistorias/rascunho?ponto_id='.$ponto->id);

        $response->assertOk()
            ->assertJsonPath('rascunho.payload.observacao', 'Salvo')
            ->assertJsonPath('rascunho.etapa_atual', 1);
    }

    public function test_get_returns_null_when_no_rascunho(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/vistorias/rascunho?lat=-19.913500&lng=-43.951400')
            ->assertOk()
            ->assertJsonPath('rascunho', null);
    }

    public function test_delete_discards_rascunho(): void
    {
        $ponto = Ponto::factory()->create();

        VistoriaRascunho::query()->create([
            'user_id' => $this->user->id,
            'ponto_id' => $ponto->id,
            'context_key' => 'ponto:'.$ponto->id,
            'payload' => [],
            'etapa_atual' => 0,
        ]);

        $this->actingAs($this->user)
            ->deleteJson('/api/vistorias/rascunho?ponto_id='.$ponto->id)
            ->assertOk();

        $this->assertDatabaseMissing('vistorias_rascunhos', [
            'user_id' => $this->user->id,
            'context_key' => 'ponto:'.$ponto->id,
        ]);
    }

    public function test_store_vistoria_deletes_rascunho(): void
    {
        $ponto = Ponto::factory()->create();

        VistoriaRascunho::query()->create([
            'user_id' => $this->user->id,
            'ponto_id' => $ponto->id,
            'context_key' => 'ponto:'.$ponto->id,
            'payload' => [],
            'etapa_atual' => 3,
        ]);

        $tipoId = DB::table('tipo_abordagem')->value('id');
        $resultadoId = DB::table('resultados_acoes')->value('id');

        $this->actingAs($this->user)->post(route('vistorias.store'), [
            'ponto_id' => $ponto->id,
            'lat' => $ponto->lat,
            'lng' => $ponto->lng,
            'data_abordagem' => now()->format('Y-m-d\TH:i'),
            'tipo_abordagem_id' => $tipoId,
            'resultado_acao_id' => $resultadoId,
        ])->assertRedirect();

        $this->assertDatabaseMissing('vistorias_rascunhos', [
            'user_id' => $this->user->id,
            'context_key' => 'ponto:'.$ponto->id,
        ]);
    }

    public function test_rascunho_requires_authentication(): void
    {
        $this->patchJson('/api/vistorias/rascunho', [
            'etapa_atual' => 0,
            'payload' => [],
        ])->assertUnauthorized();
    }

    public function test_patch_upserts_same_context(): void
    {
        $ponto = Ponto::factory()->create();

        $this->actingAs($this->user)->patchJson('/api/vistorias/rascunho', [
            'ponto_id' => $ponto->id,
            'etapa_atual' => 0,
            'payload' => ['observacao' => 'v1'],
        ])->assertOk();

        $this->actingAs($this->user)->patchJson('/api/vistorias/rascunho', [
            'ponto_id' => $ponto->id,
            'etapa_atual' => 1,
            'payload' => ['observacao' => 'v2'],
        ])->assertOk();

        $this->assertEquals(1, VistoriaRascunho::query()->where('user_id', $this->user->id)->count());

        $this->actingAs($this->user)
            ->getJson('/api/vistorias/rascunho?ponto_id='.$ponto->id)
            ->assertJsonPath('rascunho.payload.observacao', 'v2')
            ->assertJsonPath('rascunho.etapa_atual', 1);
    }
}
