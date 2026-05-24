<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapa_requer_autenticacao(): void
    {
        $this->get('/mapa')->assertRedirect('/login');
    }

    public function test_usuario_autenticado_ve_mapa(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/mapa')
            ->assertOk();
    }

    public function test_mapa_renderiza_view_correta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/mapa')
            ->assertOk()
            ->assertViewIs('mapa.index');
    }
}
