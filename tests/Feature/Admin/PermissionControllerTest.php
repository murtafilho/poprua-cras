<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();
    }

    public function test_admin_pode_listar_permissoes(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.permissions.index'))
            ->assertOk()
            ->assertViewIs('admin.permissions.index')
            ->assertViewHas('permissions');
    }

    public function test_admin_pode_acessar_formulario_criacao_permissao(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.permissions.create'))
            ->assertOk()
            ->assertViewIs('admin.permissions.create');
    }

    public function test_admin_pode_criar_permissao(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.permissions.store'), [
            'name' => 'gerenciar moradores',
        ]);

        $response->assertRedirect(route('admin.permissions.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('permissions', [
            'name' => 'gerenciar moradores',
            'guard_name' => 'web',
        ]);
    }

    public function test_admin_pode_excluir_permissao_sem_vinculo(): void
    {
        $permission = Permission::create(['name' => 'permissao avulsa', 'guard_name' => 'web']);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.permissions.destroy', $permission));

        $response->assertRedirect(route('admin.permissions.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('permissions', ['name' => 'permissao avulsa']);
    }

    public function test_admin_nao_pode_excluir_permissao_vinculada_a_role(): void
    {
        $permission = Permission::create(['name' => 'permissao vinculada', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'vinculada', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.permissions.destroy', $permission));

        $response->assertRedirect(route('admin.permissions.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('permissions', ['name' => 'permissao vinculada']);
    }

    public function test_usuario_sem_admin_nao_pode_acessar_permissoes(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.permissions.index'))
            ->assertForbidden();
    }
}
