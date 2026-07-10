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

    /**
     * Garantia de idempotência para o retry offline: o outbox reenvia a ação quando a
     * RESPOSTA do primeiro POST se perde (ex.: queda de rede após o servidor já ter
     * aplicado a mudança). O dono é o ator real desse fluxo — antes da correção, o
     * retry batia em VistoriaPolicy::update (que nega para o dono quando já
     * finalizada) e devolvia 403 para uma ação que na verdade teve sucesso.
     */
    public function test_finalizar_retry_pelo_dono_e_idempotente_200(): void
    {
        $dono = User::factory()->create();
        $vistoria = Vistoria::factory()->create(['user_id' => $dono->id, 'finalizada' => false]);

        $this->actingAs($dono)
            ->postJson("/api/vistorias/{$vistoria->id}/finalizar")
            ->assertOk()
            ->assertJson(['finalizada' => true]);

        // Retry do outbox pelo mesmo dono: deve ser 200 idempotente, não 403.
        $this->actingAs($dono)
            ->postJson("/api/vistorias/{$vistoria->id}/finalizar")
            ->assertOk()
            ->assertJson(['finalizada' => true]);

        $this->assertTrue($vistoria->fresh()->finalizada);
    }

    /**
     * reativar() exige a permission "reativar vistorias" na PRIMEIRA chamada (regra
     * de negócio inalterada), então o setup usa um admin autorizado. O retry, porém,
     * deve funcionar para esse mesmo ator via authorize('view') — sem reaplicar a
     * reativação nem esbarrar de novo na ability 'reativar'.
     */
    public function test_reativar_retry_apos_sucesso_e_idempotente_200(): void
    {
        $admin = $this->criarAdminComPermissao('reativar vistorias');
        $vistoria = Vistoria::factory()->create(['user_id' => $admin->id, 'finalizada' => true]);

        $this->actingAs($admin)
            ->postJson("/api/vistorias/{$vistoria->id}/reativar")
            ->assertOk()
            ->assertJson(['finalizada' => false]);

        // Retry do outbox pelo mesmo ator: idempotente, 200 (não bate mais na ability
        // 'reativar', que exigiria finalizada == true).
        $this->actingAs($admin)
            ->postJson("/api/vistorias/{$vistoria->id}/reativar")
            ->assertOk()
            ->assertJson(['finalizada' => false]);

        $this->assertFalse($vistoria->fresh()->finalizada);
    }

    /**
     * cancelar() pelo dono é autorizado diretamente pela policy (vistoria não
     * finalizada, dono da vistoria). O retry, com a vistoria já cancelada, deve
     * devolver 200 idempotente em vez de 403 (a policy nega cancelar() quando
     * cancelada == true, para qualquer ator).
     */
    public function test_cancelar_retry_apos_sucesso_e_idempotente_200(): void
    {
        $dono = User::factory()->create();
        $vistoria = Vistoria::factory()->create(['user_id' => $dono->id, 'finalizada' => false, 'cancelada' => false]);

        $this->actingAs($dono)
            ->postJson("/api/vistorias/{$vistoria->id}/cancelar")
            ->assertOk()
            ->assertJson(['cancelada' => true]);

        // Retry do outbox pelo mesmo dono: idempotente, 200.
        $this->actingAs($dono)
            ->postJson("/api/vistorias/{$vistoria->id}/cancelar")
            ->assertOk()
            ->assertJson(['cancelada' => true]);

        $this->assertTrue($vistoria->fresh()->cancelada);
    }
}
