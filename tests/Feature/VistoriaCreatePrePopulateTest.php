<?php

namespace Tests\Feature;

use App\Models\EquipeDia;
use App\Models\MembroEquipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VistoriaCreatePrePopulateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_de_vistoria_marca_checkbox_da_equipe_do_dia(): void
    {
        $user = User::factory()->create();
        $marcado = MembroEquipe::query()->create(['nome' => 'Marcado Hoje', 'equipe' => 'gcm', 'ativo' => true]);
        $naoMarcado = MembroEquipe::query()->create(['nome' => 'Outro Membro', 'equipe' => 'gcm', 'ativo' => true]);

        EquipeDia::create([
            'user_id' => $user->id,
            'data' => now()->toDateString(),
            'membro_equipe_id' => $marcado->id,
        ]);

        $html = $this->actingAs($user)
            ->get(route('vistorias.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="participantes\[\]"[^>]*value="'.$marcado->id.'"[^>]*checked/',
            $html,
            "membro {$marcado->id} (equipe do dia) deveria sair com checked"
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*name="participantes\[\]"[^>]*value="'.$naoMarcado->id.'"[^>]*checked/',
            $html,
            "membro {$naoMarcado->id} (NAO na equipe do dia) NAO deveria sair com checked"
        );
    }

    public function test_create_de_vistoria_sem_equipe_do_dia_nao_pre_marca_nada(): void
    {
        $user = User::factory()->create();
        $m = MembroEquipe::query()->create(['nome' => 'X', 'equipe' => 'gcm', 'ativo' => true]);

        $html = $this->actingAs($user)
            ->get(route('vistorias.create'))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*name="participantes\[\]"[^>]*value="'.$m->id.'"[^>]*checked/',
            $html
        );
    }
}
