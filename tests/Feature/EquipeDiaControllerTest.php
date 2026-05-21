<?php

namespace Tests\Feature;

use App\Models\EquipeDia;
use App\Models\MembroEquipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipeDiaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->get(route('equipe-dia.index'))
            ->assertRedirect(route('login'));
    }

    public function test_index_lista_membros_ativos_agrupados(): void
    {
        $user = User::factory()->create();
        MembroEquipe::query()->create(['nome' => 'Ana', 'equipe' => 'gcm', 'ativo' => true]);
        MembroEquipe::query()->create(['nome' => 'Pedro', 'equipe' => 'slu', 'ativo' => true]);
        MembroEquipe::query()->create(['nome' => 'Inativo', 'equipe' => 'gcm', 'ativo' => false]);

        $response = $this->actingAs($user)->get(route('equipe-dia.index'));

        $response->assertOk()
            ->assertSee('Ana')
            ->assertSee('Pedro')
            ->assertDontSee('Inativo');
    }

    public function test_store_grava_equipe_do_dia_para_user_logado(): void
    {
        $user = User::factory()->create();
        $m1 = MembroEquipe::query()->create(['nome' => 'A', 'equipe' => 'gcm', 'ativo' => true]);
        $m2 = MembroEquipe::query()->create(['nome' => 'B', 'equipe' => 'slu', 'ativo' => true]);

        $hoje = now()->toDateString();

        $this->actingAs($user)
            ->post(route('equipe-dia.store'), [
                'data' => $hoje,
                'participantes' => [$m1->id, $m2->id],
            ])
            ->assertRedirect();

        $this->assertEquals(
            [$m1->id, $m2->id],
            EquipeDia::doDia($user->id, $hoje)->orderBy('membro_equipe_id')->pluck('membro_equipe_id')->all()
        );
    }

    public function test_store_substitui_equipe_anterior_da_mesma_data(): void
    {
        $user = User::factory()->create();
        $m1 = MembroEquipe::query()->create(['nome' => 'A', 'equipe' => 'gcm', 'ativo' => true]);
        $m2 = MembroEquipe::query()->create(['nome' => 'B', 'equipe' => 'slu', 'ativo' => true]);
        $hoje = now()->toDateString();

        EquipeDia::create(['user_id' => $user->id, 'data' => $hoje, 'membro_equipe_id' => $m1->id]);
        $this->assertEquals(1, EquipeDia::doDia($user->id, $hoje)->count());

        $this->actingAs($user)
            ->post(route('equipe-dia.store'), [
                'data' => $hoje,
                'participantes' => [$m2->id],
            ])
            ->assertRedirect();

        $this->assertEquals([$m2->id], EquipeDia::doDia($user->id, $hoje)->pluck('membro_equipe_id')->all());
    }

    public function test_store_com_participantes_vazio_apaga_a_equipe(): void
    {
        $user = User::factory()->create();
        $m = MembroEquipe::query()->create(['nome' => 'A', 'equipe' => 'gcm', 'ativo' => true]);
        $hoje = now()->toDateString();
        EquipeDia::create(['user_id' => $user->id, 'data' => $hoje, 'membro_equipe_id' => $m->id]);

        $this->actingAs($user)
            ->post(route('equipe-dia.store'), ['data' => $hoje])
            ->assertRedirect();

        $this->assertEquals(0, EquipeDia::doDia($user->id, $hoje)->count());
    }

    public function test_equipe_de_um_user_nao_vaza_para_outro(): void
    {
        $coordA = User::factory()->create();
        $coordB = User::factory()->create();
        $m = MembroEquipe::query()->create(['nome' => 'X', 'equipe' => 'gcm', 'ativo' => true]);
        $hoje = now()->toDateString();

        EquipeDia::create(['user_id' => $coordA->id, 'data' => $hoje, 'membro_equipe_id' => $m->id]);

        $this->assertEquals(1, EquipeDia::doDia($coordA->id, $hoje)->count());
        $this->assertEquals(0, EquipeDia::doDia($coordB->id, $hoje)->count());
    }
}
