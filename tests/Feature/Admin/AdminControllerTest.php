<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create();
        Role::create(['name' => 'admin']);
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();
    }

    // ---------------------------------------------------------------
    // Authorization / Authentication
    // ---------------------------------------------------------------

    public function test_non_admin_cannot_access_admin_users(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_non_authenticated_redirects_to_login(): void
    {
        $this->get(route('admin.users.index'))
            ->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------
    // UserRoleController
    // ---------------------------------------------------------------

    public function test_admin_can_view_users_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertViewIs('admin.users.index')
            ->assertViewHas('users')
            ->assertViewHas('roles');
    }

    public function test_admin_can_access_create_user_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertViewIs('admin.users.create')
            ->assertViewHas('roles');
    }

    public function test_admin_can_create_user_with_role_assignment(): void
    {
        Role::create(['name' => 'agente']);

        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Novo Usuario',
            'email' => 'novo@example.com',
            'password' => 'Senh@Forte123',
            'password_confirmation' => 'Senh@Forte123',
            'role' => 'agente',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'novo@example.com',
            'name' => 'Novo Usuario',
        ]);

        $createdUser = User::where('email', 'novo@example.com')->first();
        $this->assertTrue($createdUser->hasRole('agente'));
    }

    public function test_admin_can_update_user_roles(): void
    {
        Role::create(['name' => 'supervisor']);
        $targetUser = User::factory()->create();

        $response = $this->actingAs($this->admin)->put(
            route('admin.users.roles.update', $targetUser),
            ['role' => 'supervisor']
        );

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('supervisor'));
    }

    // ---------------------------------------------------------------
    // RoleController
    // ---------------------------------------------------------------

    public function test_admin_can_view_roles_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertViewIs('admin.roles.index')
            ->assertViewHas('roles');
    }

    public function test_admin_can_create_role(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name' => 'coordenador',
            'description' => 'Coordenador de equipe',
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('roles', [
            'name' => 'coordenador',
            'guard_name' => 'web',
        ]);
    }

    public function test_admin_can_edit_role_and_assign_permissions(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $perm1 = Permission::create(['name' => 'editar pontos', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'excluir pontos', 'guard_name' => 'web']);

        // Verify the edit page loads
        $this->actingAs($this->admin)
            ->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->assertViewIs('admin.roles.edit')
            ->assertViewHas('role')
            ->assertViewHas('permissions');

        // Update the role with permissions
        $response = $this->actingAs($this->admin)->put(
            route('admin.roles.update', $role),
            [
                'name' => 'editor',
                'description' => 'Editor atualizado',
                'permissions' => [$perm1->id, $perm2->id],
            ]
        );

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('success');

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('editar pontos'));
        $this->assertTrue($role->hasPermissionTo('excluir pontos'));
    }

    public function test_admin_can_delete_role(): void
    {
        $role = Role::create(['name' => 'temporario', 'guard_name' => 'web']);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.roles.destroy', $role));

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['name' => 'temporario']);
    }

    public function test_admin_cannot_delete_role_with_users(): void
    {
        $role = Role::create(['name' => 'ocupada', 'guard_name' => 'web']);
        $this->regularUser->assignRole('ocupada');

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.roles.destroy', $role));

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['name' => 'ocupada']);
    }

    // ---------------------------------------------------------------
    // PermissionController
    // ---------------------------------------------------------------

    public function test_admin_can_view_permissions_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.permissions.index'))
            ->assertOk()
            ->assertViewIs('admin.permissions.index')
            ->assertViewHas('permissions');
    }

    public function test_admin_can_create_permission(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.permissions.store'), [
            'name' => 'gerenciar relatorios',
        ]);

        $response->assertRedirect(route('admin.permissions.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('permissions', [
            'name' => 'gerenciar relatorios',
            'guard_name' => 'web',
        ]);
    }

    public function test_admin_can_delete_permission(): void
    {
        $permission = Permission::create(['name' => 'permissao temporaria', 'guard_name' => 'web']);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.permissions.destroy', $permission));

        $response->assertRedirect(route('admin.permissions.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('permissions', ['name' => 'permissao temporaria']);
    }

    public function test_admin_cannot_delete_permission_attached_to_role(): void
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
}
