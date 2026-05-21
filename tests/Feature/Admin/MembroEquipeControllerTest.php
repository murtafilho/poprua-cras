<?php

namespace Tests\Feature\Admin;

use App\Models\MembroEquipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MembroEquipeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();
    }

    public function test_non_admin_cannot_access_index(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.membros-equipe.index'))
            ->assertForbidden();
    }

    public function test_admin_lista_membros_agrupados_por_equipe(): void
    {
        MembroEquipe::query()->create(['nome' => 'Joao', 'equipe' => 'gcm', 'ativo' => true]);
        MembroEquipe::query()->create(['nome' => 'Maria', 'equipe' => 'slu', 'ativo' => true]);

        $this->actingAs($this->admin)
            ->get(route('admin.membros-equipe.index'))
            ->assertOk()
            ->assertSee('Joao')
            ->assertSee('Maria')
            ->assertSee('GCM')
            ->assertSee('SLU');
    }

    public function test_store_cria_membro(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.membros-equipe.store'), [
                'nome' => 'Carlos Silva',
                'matricula' => 'GCM-1234',
                'email' => 'carlos@gcm.local',
                'equipe' => 'gcm',
                'ativo' => '1',
            ])
            ->assertRedirect(route('admin.membros-equipe.index'));

        $this->assertDatabaseHas('membros_equipe', [
            'nome' => 'Carlos Silva',
            'matricula' => 'GCM-1234',
            'equipe' => 'gcm',
            'ativo' => true,
        ]);
    }

    public function test_store_rejeita_equipe_invalida(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.membros-equipe.store'), [
                'nome' => 'X',
                'equipe' => 'tipo-inexistente',
            ])
            ->assertSessionHasErrors('equipe');
    }

    public function test_update_altera_membro(): void
    {
        $m = MembroEquipe::query()->create(['nome' => 'Antigo', 'equipe' => 'gcm', 'ativo' => true]);

        $this->actingAs($this->admin)
            ->put(route('admin.membros-equipe.update', $m), [
                'nome' => 'Atualizado',
                'equipe' => 'slu',
                'ativo' => '0',
            ])
            ->assertRedirect(route('admin.membros-equipe.index'));

        $this->assertDatabaseHas('membros_equipe', [
            'id' => $m->id,
            'nome' => 'Atualizado',
            'equipe' => 'slu',
            'ativo' => false,
        ]);
    }

    public function test_destroy_remove_membro(): void
    {
        $m = MembroEquipe::query()->create(['nome' => 'Removivel', 'equipe' => 'gcm']);

        $this->actingAs($this->admin)
            ->delete(route('admin.membros-equipe.destroy', $m))
            ->assertRedirect(route('admin.membros-equipe.index'));

        $this->assertDatabaseMissing('membros_equipe', ['id' => $m->id]);
    }
}
