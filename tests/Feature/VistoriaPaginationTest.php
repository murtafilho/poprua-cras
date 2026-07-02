<?php

namespace Tests\Feature;

use App\Models\Ponto;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Testes de paginacao e filtros da listagem de vistorias
 */
class VistoriaPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::create(['name' => 'participar de equipes vistoria']);
        Role::create(['name' => 'agente']);

        $this->user = User::factory()->create(['ativo' => true]);
        $this->user->assignRole('agente');
    }

    /**
     * Testa se a paginacao esta funcionando corretamente
     */
    public function test_paginacao_listagem_vistorias(): void
    {
        $ponto = Ponto::factory()->create();

        // Criar 15 vistorias
        for ($i = 0; $i < 15; $i++) {
            Vistoria::factory()->create([
                'ponto_id' => $ponto->id,
                'user_id' => $this->user->id,
                'data_abordagem' => now()->subDays($i),
            ]);
        }

        // Pagina 1 (default 5 por pagina)
        $response = $this->actingAs($this->user)
            ->get(route('vistorias.index'));

        $response->assertOk()
            ->assertSee('pagination-bar')
            ->assertSee('15'); // Total de registros

        // Verificar que temos paginacao (link para pagina 2)
        $response->assertSee('page=2');
    }

    /**
     * Testa alteracao de itens por pagina
     */
    public function test_alterar_itens_por_pagina(): void
    {
        $ponto = Ponto::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            Vistoria::factory()->create([
                'ponto_id' => $ponto->id,
                'user_id' => $this->user->id,
                'data_abordagem' => now()->subDays($i),
            ]);
        }

        // 10 por pagina
        $response = $this->actingAs($this->user)
            ->get(route('vistorias.index', ['per_page' => 10]));

        $response->assertOk()
            ->assertSee('20'); // Total

        // 25 por pagina
        $response = $this->actingAs($this->user)
            ->get(route('vistorias.index', ['per_page' => 25]));

        $response->assertOk()
            ->assertSee('20'); // Total (mostra todos em uma pagina)
    }

    /**
     * Testa filtro por supervisor
     */
    public function test_filtro_por_supervisor(): void
    {
        $ponto = Ponto::factory()->create();
        $outroUser = User::factory()->create(['ativo' => true]);

        // 3 vistorias do usuario atual
        for ($i = 0; $i < 3; $i++) {
            Vistoria::factory()->create([
                'ponto_id' => $ponto->id,
                'user_id' => $this->user->id,
                'data_abordagem' => now()->subDays($i),
            ]);
        }

        // 2 vistorias de outro usuario
        for ($i = 0; $i < 2; $i++) {
            Vistoria::factory()->create([
                'ponto_id' => $ponto->id,
                'user_id' => $outroUser->id,
                'data_abordagem' => now()->subDays($i + 10),
            ]);
        }

        // Filtrar pelo usuario atual
        $response = $this->actingAs($this->user)
            ->get(route('vistorias.index', ['supervisor' => $this->user->id]));

        $response->assertOk()
            ->assertSee('3'); // Total filtrado
    }

    /**
     * Testa filtro por data
     */
    public function test_filtro_por_data(): void
    {
        $ponto = Ponto::factory()->create();

        // Vistoria hoje
        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'data_abordagem' => now(),
        ]);

        // Vistoria ontem
        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'data_abordagem' => now()->subDay(),
        ]);

        // Vistoria de 3 dias atras
        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'data_abordagem' => now()->subDays(3),
        ]);

        // Filtrar apenas hoje e ontem
        $response = $this->actingAs($this->user)
            ->get(route('vistorias.index', [
                'data_inicio' => now()->subDay()->toDateString(),
                'data_fim' => now()->toDateString(),
            ]));

        $response->assertOk()
            ->assertSee('2'); // Total filtrado
    }

    /**
     * Testa validacao de per_page
     */
    public function test_validacao_per_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('vistorias.index', ['per_page' => 'abc']))
            ->assertSessionHasErrors('per_page');

        $this->actingAs($this->user)
            ->get(route('vistorias.index', ['per_page' => 200]))
            ->assertSessionHasErrors('per_page');
    }
}
