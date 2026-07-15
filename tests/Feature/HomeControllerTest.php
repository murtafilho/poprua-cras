<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_institutional_home_with_login(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('ADPF 976', false);
        $response->assertSee('PNPSR', false);
        $response->assertSee('Belo Horizonte', false);
        $response->assertSee('Entrar', false);
        $response->assertDontSee('href="'.route('mapa.index').'"', false);
    }

    public function test_root_redirects_to_home(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('home'));
    }

    public function test_authenticated_user_sees_launcher_shortcuts(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('ADPF 976', false);
        $response->assertSee('Mapa', false);
        $response->assertSee('Zeladorias', false);
        $response->assertSee('Minhas', false);
        $response->assertSee('Pontos', false);
        $response->assertSee('Pessoas', false);
        $response->assertSee('Dashboard', false);
        $response->assertSee(route('mapa.index'), false);
        $response->assertSee('Sobre o sistema', false);
        $response->assertSee(route('sobre.index'), false);
    }

    public function test_home_shows_brand_and_version(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(config('app.brand'), false);
        $response->assertSee('v'.config('app.version'), false);
    }
}
