<?php

namespace Tests\Feature;

use App\Models\Ponto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PontoControllerWebTest extends TestCase
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
        $this->get(route('pontos.index'))->assertRedirect(route('login'));
    }

    public function test_index_renders_with_pontos(): void
    {
        $this->actingAs($this->user)
            ->get(route('pontos.index'))
            ->assertOk()
            ->assertViewHas('pontos');
    }

    public function test_show_displays_ponto_details(): void
    {
        $ponto = Ponto::factory()->create();
        DB::statement('UPDATE pontos SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?', [$ponto->lng, $ponto->lat, $ponto->id]);

        $this->actingAs($this->user)
            ->get(route('pontos.show', $ponto))
            ->assertOk();
    }

    public function test_edit_renders_form(): void
    {
        $ponto = Ponto::factory()->create();

        $this->actingAs($this->user)
            ->get(route('pontos.edit', $ponto))
            ->assertOk();
    }

    public function test_update_modifies_ponto(): void
    {
        $ponto = Ponto::factory()->create(['complemento' => 'Antigo', 'lat' => -19.91, 'lng' => -43.94]);

        $role = Role::firstOrCreate(['name' => 'admin']);
        Permission::firstOrCreate(['name' => 'editar qualquer vistoria']);
        $role->givePermissionTo('editar qualquer vistoria');
        $this->user->assignRole('admin');

        $this->actingAs($this->user)
            ->put(route('pontos.update', $ponto), [
                'numero' => $ponto->numero ?? 'S/N',
                'complemento' => 'Novo complemento',
                'observacao' => 'Observacao teste',
                'lat' => $ponto->lat,
                'lng' => $ponto->lng,
            ]);

        $this->assertDatabaseHas('pontos', ['id' => $ponto->id, 'complemento' => 'Novo complemento']);
    }

    public function test_nao_georreferenciados_requires_authentication(): void
    {
        $this->get(route('pontos.nao-georreferenciados'))->assertRedirect(route('login'));
    }

    public function test_nao_georreferenciados_renders(): void
    {
        $this->actingAs($this->user)
            ->get(route('pontos.nao-georreferenciados'))
            ->assertOk();
    }

    public function test_index_filters_by_regional(): void
    {
        $this->actingAs($this->user)
            ->get(route('pontos.index', ['regional' => 'NOROESTE']))
            ->assertOk();
    }

    public function test_index_validates_per_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('pontos.index', ['per_page' => 'abc']))
            ->assertSessionHasErrors('per_page');
    }
}
