<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MinhaEquipeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'agente', 'guard_name' => 'web']);
        Permission::create(['name' => 'participar de equipes vistoria', 'guard_name' => 'web']);

        $this->user = User::factory()->create(['ativo' => true]);
        $this->user->assignRole('agente');
        $this->user->givePermissionTo('participar de equipes vistoria');

        $this->unauthorizedUser = User::factory()->create(['ativo' => true]);
    }

    public function test_usuario_autorizado_pode_listar_minha_equipe(): void
    {
        $this->actingAs($this->user)
            ->get(route('minha-equipe.index'))
            ->assertOk()
            ->assertViewIs('minha-equipe.index')
            ->assertViewHas('usuarios')
            ->assertViewHas('marcados');
    }

    public function test_usuario_autorizado_pode_atualizar_equipe(): void
    {
        $membro = User::factory()->create(['ativo' => true]);
        $membro->givePermissionTo('participar de equipes vistoria');

        $response = $this->actingAs($this->user)->put(route('minha-equipe.update'), [
            'membros' => [$membro->id],
        ]);

        $response->assertRedirect(route('minha-equipe.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_team', [
            'owner_user_id' => $this->user->id,
            'member_user_id' => $membro->id,
        ]);
    }

    public function test_usuario_pode_limpar_equipe(): void
    {
        $membro = User::factory()->create(['ativo' => true]);
        $membro->givePermissionTo('participar de equipes vistoria');
        $this->user->team()->sync([$membro->id]);

        $response = $this->actingAs($this->user)->put(route('minha-equipe.update'), [
            'membros' => [],
        ]);

        $response->assertRedirect(route('minha-equipe.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('user_team', [
            'owner_user_id' => $this->user->id,
            'member_user_id' => $membro->id,
        ]);
    }

    public function test_usuario_nao_pode_adicionar_a_si_mesmo_na_equipe(): void
    {
        $membro = User::factory()->create(['ativo' => true]);
        $membro->givePermissionTo('participar de equipes vistoria');

        $response = $this->actingAs($this->user)->put(route('minha-equipe.update'), [
            'membros' => [$this->user->id, $membro->id],
        ]);

        $response->assertRedirect(route('minha-equipe.index'));

        $this->assertDatabaseMissing('user_team', [
            'owner_user_id' => $this->user->id,
            'member_user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('user_team', [
            'owner_user_id' => $this->user->id,
            'member_user_id' => $membro->id,
        ]);
    }

    public function test_usuario_nao_autenticado_redireciona_para_login(): void
    {
        $this->get(route('minha-equipe.index'))
            ->assertRedirect(route('login'));
    }
}
