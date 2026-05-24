<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleControllerTest extends TestCase
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

    public function test_admin_pode_listar_roles(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertViewIs('admin.roles.index')
            ->assertViewHas('roles');
    }

    public function test_admin_pode_acessar_formulario_criacao_role(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.roles.create'))
            ->assertOk()
            ->assertViewIs('admin.roles.create');
    }

    public function test_admin_pode_criar_role(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name' => 'supervisor',
            'description' => 'Supervisiona equipes de campo',
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('roles', [
            'name' => 'supervisor',
            'guard_name' => 'web',
        ]);
    }

    public function test_admin_pode_atualizar_role_com_permissoes(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'editar pontos', 'guard_name' => 'web']);

        $response = $this->actingAs($this->admin)->put(route('admin.roles.update', $role), [
            'name' => 'editor',
            'description' => 'Editor atualizado',
            'permissions' => [$perm->id],
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('success');

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('editar pontos'));
    }

    public function test_admin_pode_excluir_role_sem_usuarios(): void
    {
        $role = Role::create(['name' => 'temporaria', 'guard_name' => 'web']);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.roles.destroy', $role));

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['name' => 'temporaria']);
    }

    public function test_usuario_sem_admin_nao_pode_acessar_roles(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }
}
