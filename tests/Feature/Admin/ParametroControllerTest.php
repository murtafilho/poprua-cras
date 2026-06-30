<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ParametroControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();
    }

    public function test_nao_admin_nao_acessa_parametros(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.parametros.index'))
            ->assertForbidden();
    }

    public function test_nao_autenticado_redireciona_login(): void
    {
        $this->get(route('admin.parametros.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_visualiza_parametros(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.parametros.index'))
            ->assertOk()
            ->assertViewIs('admin.parametros.index')
            ->assertViewHas('parametros');
    }

    public function test_admin_atualiza_parametros(): void
    {
        Cache::put('param:info_precaria_dias', 'cached', 3600);

        $response = $this->actingAs($this->admin)->put(route('admin.parametros.update'), [
            'parametros' => [
                'info_precaria_dias' => '45',
            ],
        ]);

        $response->assertRedirect(route('admin.parametros.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('parametros', [
            'chave' => 'info_precaria_dias',
            'valor' => '45',
        ]);

        $this->assertFalse(Cache::has('param:info_precaria_dias'));
        $this->assertSame(45, Parametro::get('info_precaria_dias'));
    }

    public function test_admin_cria_parametro(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.parametros.create'), [
            'chave' => 'teste_config',
            'valor' => '42',
            'tipo' => 'integer',
            'grupo' => 'geral',
            'descricao' => 'Parâmetro de teste',
        ]);

        $response->assertRedirect(route('admin.parametros.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('parametros', [
            'chave' => 'teste_config',
            'valor' => '42',
        ]);
    }

    public function test_admin_remove_parametro(): void
    {
        Parametro::query()->create([
            'chave' => 'remover_me',
            'valor' => '1',
            'tipo' => 'boolean',
            'grupo' => 'geral',
        ]);

        Cache::put('param:remover_me', '1', 3600);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.parametros.destroy', 'remover_me'));

        $response->assertRedirect(route('admin.parametros.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('parametros', ['chave' => 'remover_me']);
        $this->assertFalse(Cache::has('param:remover_me'));
    }
}
