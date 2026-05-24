<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserRoleControllerTest extends TestCase
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

    public function test_admin_pode_listar_usuarios(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertViewIs('admin.users.index')
            ->assertViewHas('users')
            ->assertViewHas('roles');
    }

    public function test_admin_pode_acessar_formulario_criacao_usuario(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertViewIs('admin.users.create')
            ->assertViewHas('roles');
    }

    public function test_admin_pode_criar_usuario_com_role(): void
    {
        Role::create(['name' => 'agente', 'guard_name' => 'web']);

        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'password' => 'Senh@Forte123',
            'password_confirmation' => 'Senh@Forte123',
            'role' => 'agente',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'name' => 'Maria Silva',
        ]);

        $user = User::where('email', 'maria@example.com')->first();
        $this->assertTrue($user->hasRole('agente'));
    }

    public function test_admin_pode_atualizar_role_de_usuario(): void
    {
        Role::create(['name' => 'coordenador', 'guard_name' => 'web']);
        $targetUser = User::factory()->create();

        $response = $this->actingAs($this->admin)->put(
            route('admin.users.roles.update', $targetUser),
            ['role' => 'coordenador']
        );

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('coordenador'));
    }

    public function test_usuario_sem_admin_nao_pode_acessar_usuarios(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }
}
