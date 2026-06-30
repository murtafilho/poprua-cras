<?php

namespace Tests\Feature;

use App\Models\Ponto;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ParticipantesEquipeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function show_exibe_participantes_agrupados_por_tipo_equipe(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'agente', 'guard_name' => 'web']);

        DB::table('tipo_abordagem')->insert(['id' => 1, 'tipo' => 'Rotina']);
        DB::table('resultados_acoes')->insert(['id' => 1, 'resultado' => 'Orientação']);

        $autor = User::factory()->create();
        $supervisor = User::factory()->create(['name' => 'Ana Supervisor']);
        $supervisor->assignRole('supervisor');
        $agente = User::factory()->create(['name' => 'Bruno Agente']);
        $agente->assignRole('agente');

        $ponto = Ponto::factory()->create();
        $vistoria = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $autor->id,
            'tipo_abordagem_id' => 1,
            'resultado_acao_id' => 1,
            'data_abordagem' => now(),
        ]);
        $vistoria->participantes()->sync([$supervisor->id, $agente->id]);

        $response = $this->actingAs($autor)->get(route('vistorias.show', $vistoria));

        $response->assertOk();
        $response->assertSee('Supervisores');
        $response->assertSee('Agentes de Campo');
        $response->assertSee('Ana Supervisor');
        $response->assertSee('Bruno Agente');
    }
}
