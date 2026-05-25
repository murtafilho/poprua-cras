<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VistoriaPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherUser;

    private User $admin;

    private Vistoria $vistoria;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tipo_abordagem')->insertGetId(['tipo' => 'Rotina']);
        DB::table('resultados_acoes')->insertGetId(['resultado' => 'Orientação']);

        $this->owner = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->vistoria = Vistoria::factory()->create([
            'user_id' => $this->owner->id,
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'editar qualquer vistoria']);
        Permission::create(['name' => 'excluir vistorias']);
        Permission::create(['name' => 'reativar vistorias']);
        Permission::create(['name' => 'cancelar vistorias']);
        Permission::create(['name' => 'participar de equipes vistoria']);
        $adminRole->givePermissionTo(['editar qualquer vistoria', 'excluir vistorias', 'reativar vistorias', 'cancelar vistorias']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_any_authenticated_user_can_view_vistorias(): void
    {
        $this->actingAs($this->otherUser)
            ->get(route('vistorias.show', $this->vistoria))
            ->assertOk();
    }

    public function test_any_authenticated_user_can_view_index(): void
    {
        $this->actingAs($this->otherUser)
            ->get(route('vistorias.index'))
            ->assertOk();
    }

    public function test_owner_can_edit_own_vistoria(): void
    {
        $this->actingAs($this->owner)
            ->get(route('vistorias.edit', $this->vistoria))
            ->assertOk();
    }

    public function test_non_owner_cannot_edit_vistoria(): void
    {
        $this->actingAs($this->otherUser)
            ->get(route('vistorias.edit', $this->vistoria))
            ->assertForbidden();
    }

    public function test_admin_can_edit_others_vistoria(): void
    {
        $this->actingAs($this->admin)
            ->get(route('vistorias.edit', $this->vistoria))
            ->assertOk();
    }

    public function test_owner_can_delete_own_vistoria(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('vistorias.destroy', $this->vistoria))
            ->assertRedirect();

        $this->assertSoftDeleted('vistorias', ['id' => $this->vistoria->id]);
    }

    public function test_non_owner_cannot_delete_vistoria(): void
    {
        $this->actingAs($this->otherUser)
            ->delete(route('vistorias.destroy', $this->vistoria))
            ->assertForbidden();
    }

    public function test_admin_with_permission_can_delete_any_vistoria(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('vistorias.destroy', $this->vistoria))
            ->assertRedirect();

        $this->assertSoftDeleted('vistorias', ['id' => $this->vistoria->id]);
    }

    public function test_any_authenticated_user_can_create(): void
    {
        $this->actingAs($this->otherUser)
            ->get(route('vistorias.create'))
            ->assertOk();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('vistorias.index'))
            ->assertRedirect(route('login'));
    }

    public function test_owner_can_edit_finalized_vistoria(): void
    {
        $this->vistoria->update(['finalizada' => true, 'finalizada_em' => now(), 'finalizada_por' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('vistorias.edit', $this->vistoria))
            ->assertOk();
    }

    public function test_admin_can_edit_finalized_vistoria(): void
    {
        $this->vistoria->update(['finalizada' => true, 'finalizada_em' => now(), 'finalizada_por' => $this->owner->id]);

        $this->actingAs($this->admin)
            ->get(route('vistorias.edit', $this->vistoria))
            ->assertOk();
    }

    public function test_non_owner_cannot_edit_finalized_vistoria(): void
    {
        $this->vistoria->update(['finalizada' => true, 'finalizada_em' => now(), 'finalizada_por' => $this->owner->id]);

        $this->actingAs($this->otherUser)
            ->get(route('vistorias.edit', $this->vistoria))
            ->assertForbidden();
    }

    public function test_owner_can_finalize_already_finalized_vistoria(): void
    {
        $this->vistoria->update(['finalizada' => true, 'finalizada_em' => now(), 'finalizada_por' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->post(route('vistorias.finalizar', $this->vistoria))
            ->assertRedirect();
    }

    public function test_admin_can_reativar_finalized_vistoria(): void
    {
        $this->vistoria->update(['finalizada' => true, 'finalizada_em' => now(), 'finalizada_por' => $this->owner->id]);

        $this->actingAs($this->admin)
            ->post(route('vistorias.reativar', $this->vistoria))
            ->assertRedirect(route('vistorias.show', $this->vistoria));

        $this->assertDatabaseHas('vistorias', [
            'id' => $this->vistoria->id,
            'finalizada' => false,
            'finalizada_em' => null,
            'finalizada_por' => null,
        ]);
    }

    public function test_owner_can_edit_after_reativacao(): void
    {
        $this->vistoria->update(['finalizada' => true, 'finalizada_em' => now(), 'finalizada_por' => $this->owner->id]);

        $this->actingAs($this->admin)
            ->post(route('vistorias.reativar', $this->vistoria));

        $this->actingAs($this->owner)
            ->get(route('vistorias.edit', $this->vistoria))
            ->assertOk();
    }

    public function test_owner_cannot_reativar_own_vistoria(): void
    {
        $this->vistoria->update(['finalizada' => true, 'finalizada_em' => now(), 'finalizada_por' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->post(route('vistorias.reativar', $this->vistoria))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_reativar_vistoria(): void
    {
        $this->vistoria->update(['finalizada' => true, 'finalizada_em' => now(), 'finalizada_por' => $this->owner->id]);

        $this->actingAs($this->otherUser)
            ->post(route('vistorias.reativar', $this->vistoria))
            ->assertForbidden();
    }

    public function test_admin_cannot_reativar_non_finalized_vistoria(): void
    {
        $this->actingAs($this->admin)
            ->post(route('vistorias.reativar', $this->vistoria))
            ->assertForbidden();
    }

    // --- Cancelamento ---

    public function test_owner_can_cancel_own_open_vistoria(): void
    {
        $this->actingAs($this->owner)
            ->post(route('vistorias.cancelar', $this->vistoria))
            ->assertRedirect(route('vistorias.show', $this->vistoria));

        $this->assertDatabaseHas('vistorias', ['id' => $this->vistoria->id, 'cancelada' => true]);
    }

    public function test_non_owner_cannot_cancel_open_vistoria(): void
    {
        $this->actingAs($this->otherUser)
            ->post(route('vistorias.cancelar', $this->vistoria))
            ->assertForbidden();
    }

    public function test_admin_cannot_cancel_open_vistoria_of_another_user(): void
    {
        $this->actingAs($this->admin)
            ->post(route('vistorias.cancelar', $this->vistoria))
            ->assertForbidden();
    }

    public function test_admin_can_cancel_finalized_vistoria(): void
    {
        $this->vistoria->update(['finalizada' => true, 'finalizada_em' => now(), 'finalizada_por' => $this->owner->id]);

        $this->actingAs($this->admin)
            ->post(route('vistorias.cancelar', $this->vistoria))
            ->assertRedirect(route('vistorias.show', $this->vistoria));

        $this->assertDatabaseHas('vistorias', ['id' => $this->vistoria->id, 'cancelada' => true]);
    }

    public function test_owner_cannot_cancel_finalized_vistoria(): void
    {
        $this->vistoria->update(['finalizada' => true, 'finalizada_em' => now(), 'finalizada_por' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->post(route('vistorias.cancelar', $this->vistoria))
            ->assertForbidden();
    }

    public function test_nobody_can_cancel_already_cancelled_vistoria(): void
    {
        $this->vistoria->update(['cancelada' => true, 'cancelada_em' => now(), 'cancelada_por' => $this->owner->id]);

        $this->actingAs($this->owner)->post(route('vistorias.cancelar', $this->vistoria))->assertForbidden();
        $this->actingAs($this->admin)->post(route('vistorias.cancelar', $this->vistoria))->assertForbidden();
    }

    public function test_owner_can_edit_cancelled_vistoria(): void
    {
        $this->vistoria->update(['cancelada' => true, 'cancelada_em' => now(), 'cancelada_por' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('vistorias.edit', $this->vistoria))
            ->assertOk();
    }

    public function test_non_owner_cannot_edit_cancelled_vistoria(): void
    {
        $this->vistoria->update(['cancelada' => true, 'cancelada_em' => now(), 'cancelada_por' => $this->owner->id]);

        $this->actingAs($this->otherUser)
            ->get(route('vistorias.edit', $this->vistoria))
            ->assertForbidden();
    }
}
