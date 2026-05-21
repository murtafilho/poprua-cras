<?php

namespace Tests\Feature;

use App\Models\Ponto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PontoPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tipo_abordagem')->insert(['tipo' => 'Rotina']);
        DB::table('resultados_acoes')->insert(['resultado' => 'Orientação']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_any_authenticated_user_can_view_pontos(): void
    {
        $user = User::factory()->create();
        $ponto = Ponto::factory()->create();

        $this->actingAs($user)
            ->get(route('pontos.show', $ponto))
            ->assertOk();
    }

    public function test_regular_user_cannot_update_ponto(): void
    {
        $user = User::factory()->create();
        $ponto = Ponto::factory()->create();

        $response = $this->actingAs($user)
            ->put(route('pontos.update', $ponto), [
                'numero' => '100',
                'lat' => -19.91,
                'lng' => -43.94,
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_any_ponto(): void
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'editar qualquer vistoria']);
        $role->givePermissionTo('editar qualquer vistoria');
        $admin->assignRole('admin');

        $ponto = Ponto::factory()->create();

        $this->actingAs($admin)
            ->put(route('pontos.update', $ponto), [
                'numero' => '200',
                'complemento' => 'Teste admin',
                'lat' => -19.91,
                'lng' => -43.94,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pontos', ['id' => $ponto->id, 'complemento' => 'Teste admin']);
    }
}
