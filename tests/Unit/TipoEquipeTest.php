<?php

namespace Tests\Unit;

use App\Enums\TipoEquipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TipoEquipeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    #[Test]
    public function mapeia_role_supervisor(): void
    {
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('supervisor');

        $this->assertSame(TipoEquipe::Supervisores, TipoEquipe::fromUser($user->load('roles')));
    }

    #[Test]
    public function prioriza_coordenador_sobre_agente(): void
    {
        Role::firstOrCreate(['name' => 'coordenador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'agente', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole(['agente', 'coordenador']);

        $this->assertSame(TipoEquipe::Coordenadores, TipoEquipe::fromUser($user->load('roles')));
    }

    #[Test]
    public function usuarios_sem_role_mapeados_para_outros(): void
    {
        $user = User::factory()->create();

        $this->assertSame(TipoEquipe::Outros, TipoEquipe::fromUser($user));
    }
}
