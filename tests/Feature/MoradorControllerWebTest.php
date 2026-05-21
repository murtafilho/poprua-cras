<?php

namespace Tests\Feature;

use App\Models\Morador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MoradorControllerWebTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tipo_abordagem')->insert(['tipo' => 'Rotina']);
        DB::table('resultados_acoes')->insert(['resultado' => 'Orientação']);
        $this->user = User::factory()->create();
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('moradores.index'))->assertRedirect(route('login'));
    }

    public function test_index_renders_with_moradores(): void
    {
        Morador::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->get(route('moradores.index'))
            ->assertOk()
            ->assertViewHas('moradores');
    }

    public function test_index_filters_by_search(): void
    {
        Morador::factory()->create(['nome_social' => 'João Silva']);
        Morador::factory()->create(['nome_social' => 'Maria Santos']);

        $response = $this->actingAs($this->user)
            ->get(route('moradores.index', ['buscar' => 'João']));

        $response->assertOk();
        $response->assertSee('João Silva');
    }

    public function test_show_displays_morador_details(): void
    {
        $morador = Morador::factory()->create(['nome_social' => 'Carlos Teste']);

        $this->actingAs($this->user)
            ->get(route('moradores.show', $morador))
            ->assertOk()
            ->assertSee('Carlos Teste');
    }

    public function test_create_renders_form(): void
    {
        $this->actingAs($this->user)
            ->get(route('moradores.create'))
            ->assertOk();
    }

    public function test_store_creates_morador(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('moradores.store'), [
                'nome_social' => 'Novo Morador',
                'genero' => 'Homem cisgenero',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('moradores', ['nome_social' => 'Novo Morador']);
    }

    public function test_store_validates_required_nome(): void
    {
        $this->actingAs($this->user)
            ->post(route('moradores.store'), [])
            ->assertSessionHasErrors('nome_social');
    }

    public function test_edit_renders_form(): void
    {
        $morador = Morador::factory()->create();

        $this->actingAs($this->user)
            ->get(route('moradores.edit', $morador))
            ->assertOk();
    }

    public function test_update_modifies_morador(): void
    {
        $morador = Morador::factory()->create(['nome_social' => 'Antigo']);

        $this->actingAs($this->user)
            ->put(route('moradores.update', $morador), [
                'nome_social' => 'Atualizado',
            ]);

        $this->assertDatabaseHas('moradores', ['id' => $morador->id, 'nome_social' => 'Atualizado']);
    }

    public function test_destroy_soft_deletes_morador(): void
    {
        $morador = Morador::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('moradores.destroy', $morador))
            ->assertRedirect();

        $this->assertSoftDeleted('moradores', ['id' => $morador->id]);
    }
}
