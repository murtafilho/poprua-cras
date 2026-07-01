<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SobreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sobre_requires_authentication(): void
    {
        $this->get(route('sobre.index'))->assertRedirect(route('login'));
    }

    public function test_sobre_renders_creditos(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('sobre.index'))
            ->assertOk()
            ->assertViewIs('sobre.index')
            ->assertSee('Desenvolvimento e responsabilidade técnica')
            ->assertSee('Roberto Murta')
            ->assertSee('rluciano@pbh.gov.br')
            ->assertSee('Gerência de Informação da Fiscalização — GINFI')
            ->assertSee('Cássio Soares Martins')
            ->assertSee('ginfi@pbh.gov.br');
    }
}
