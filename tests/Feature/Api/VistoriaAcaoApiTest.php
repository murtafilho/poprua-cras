<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VistoriaAcaoApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cria um usuário com role "admin" + a permission indicada, seguindo o
     * mesmo padrão de VistoriaPolicyTest/WorkflowZeladoriaTest.
     */
    private function criarAdminComPermissao(string $permissao): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $role = Role::firstOrCreate(['name' => 'admin']);
        Permission::firstOrCreate(['name' => $permissao]);
        $role->givePermissionTo($permissao);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_finalizar_via_api(): void
    {
        $user = User::factory()->create();
        $vistoria = Vistoria::factory()->create(['user_id' => $user->id, 'finalizada' => false]);

        $this->actingAs($user)
            ->postJson("/api/vistorias/{$vistoria->id}/finalizar")
            ->assertOk()
            ->assertJson(['id' => $vistoria->id, 'finalizada' => true]);

        $this->assertTrue($vistoria->fresh()->finalizada);
    }

    public function test_finalizar_e_idempotente(): void
    {
        // finalizar() autoriza via ability 'update'. Para não-admin, VistoriaPolicy::update
        // nega incondicionalmente quando a vistoria já está finalizada/cancelada — inclusive
        // para o dono (ver comentário na própria policy e o teste de regressão
        // VistoriaPolicyTest::test_owner_cannot_finalize_already_finalized_vistoria).
        // Só o admin passa por 'update' independente do estado, então usamos admin aqui
        // para exercitar a idempotência do endpoint sem esbarrar nessa regra de autorização.
        $admin = $this->criarAdminComPermissao('reativar vistorias');
        $vistoria = Vistoria::factory()->create(['user_id' => $admin->id, 'finalizada' => false]);

        $this->actingAs($admin)->postJson("/api/vistorias/{$vistoria->id}/finalizar")->assertOk();
        $this->actingAs($admin)->postJson("/api/vistorias/{$vistoria->id}/finalizar")->assertOk();

        $this->assertTrue($vistoria->fresh()->finalizada);
    }

    public function test_cancelar_e_reativar_via_api(): void
    {
        // reativar() exige a permission "reativar vistorias" e nunca é liberado para o dono
        // comum (VistoriaPolicy::reativar) — por isso um admin reabre a vistoria e, em
        // seguida, o próprio dono (agora com a vistoria reaberta) a cancela.
        $dono = User::factory()->create();
        $admin = $this->criarAdminComPermissao('reativar vistorias');
        $vistoria = Vistoria::factory()->create(['user_id' => $dono->id, 'finalizada' => true]);

        $this->actingAs($admin)->postJson("/api/vistorias/{$vistoria->id}/reativar")
            ->assertOk()->assertJson(['finalizada' => false]);
        $this->assertFalse($vistoria->fresh()->finalizada);

        $this->actingAs($dono)->postJson("/api/vistorias/{$vistoria->id}/cancelar")
            ->assertOk()->assertJson(['cancelada' => true]);
        $this->assertTrue($vistoria->fresh()->cancelada);
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $dono = User::factory()->create();
        $outro = User::factory()->create();
        $vistoria = Vistoria::factory()->create(['user_id' => $dono->id, 'finalizada' => false]);

        $this->actingAs($outro)
            ->postJson("/api/vistorias/{$vistoria->id}/finalizar")
            ->assertStatus(403);
    }
}
