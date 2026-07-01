<?php

namespace Tests\Feature;

use App\Models\Ponto;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkflowZeladoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tipo_abordagem')->insert([
            ['id' => 1, 'tipo' => 'Orientativa'],
            ['id' => 5, 'tipo' => 'Comunicação de Zeladoria'],
        ]);
        DB::table('resultados_acoes')->insert([
            ['id' => 1, 'resultado' => 'Fenômeno persiste'],
            ['id' => 3, 'resultado' => 'Fenômeno deixou de ocorrer'],
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $adminRole = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'excluir vistorias']);
        Permission::create(['name' => 'reativar vistorias']);
        Permission::create(['name' => 'cancelar vistorias']);
        Permission::create(['name' => 'participar de equipes vistoria']);
        $adminRole->givePermissionTo(['excluir vistorias', 'reativar vistorias', 'cancelar vistorias']);

        $this->user = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_ponto_nasce_via_primeira_vistoria(): void
    {
        $pontosAntes = Ponto::count();

        $this->actingAs($this->user)->post(route('vistorias.store'), [
            'lat' => -19.9200,
            'lng' => -43.9400,
            'data_abordagem' => now()->format('Y-m-d\TH:i'),
            'tipo_abordagem_id' => 1,
            'resultado_acao_id' => 1,
        ]);

        $this->assertEquals($pontosAntes + 1, Ponto::count());
    }

    public function test_ponto_herda_resultado_da_ultima_vistoria(): void
    {
        $ponto = Ponto::factory()->create();

        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'resultado_acao_id' => 1,
            'data_abordagem' => now()->subDays(10),
        ]);

        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'resultado_acao_id' => 3,
            'data_abordagem' => now(),
        ]);

        $result = DB::selectOne('
            SELECT ra.resultado as resultado_acao
            FROM pontos p
            LEFT JOIN (SELECT ponto_id, MAX(id) as uid FROM vistorias WHERE deleted_at IS NULL GROUP BY ponto_id) uv ON uv.ponto_id = p.id
            LEFT JOIN vistorias v ON v.id = uv.uid
            LEFT JOIN resultados_acoes ra ON ra.id = v.resultado_acao_id
            WHERE p.id = ?
        ', [$ponto->id]);

        $this->assertEquals('Fenômeno deixou de ocorrer', $result->resultado_acao);
    }

    public function test_owner_pode_editar_apenas_vistoria_aberta_propria(): void
    {
        $ponto = Ponto::factory()->create();

        $aberta = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
        ]);

        $finalizada = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'finalizada' => true,
            'finalizada_em' => now(),
            'finalizada_por' => $this->user->id,
        ]);

        $this->actingAs($this->user)->get(route('vistorias.edit', $aberta))->assertOk();
        $this->actingAs($this->user)->get(route('vistorias.edit', $finalizada))->assertForbidden();
    }

    public function test_admin_pode_editar_vistoria_de_outro_usuario(): void
    {
        $ponto = Ponto::factory()->create();

        $vistoria = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'finalizada' => true,
            'finalizada_em' => now(),
            'finalizada_por' => $this->user->id,
        ]);

        $this->actingAs($this->admin)->get(route('vistorias.edit', $vistoria))->assertOk();
    }

    public function test_non_owner_non_admin_nao_pode_editar(): void
    {
        $ponto = Ponto::factory()->create();
        $outro = User::factory()->create();

        $vistoria = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($outro)->get(route('vistorias.edit', $vistoria))->assertForbidden();
    }

    public function test_mapa_carrega_corretamente(): void
    {
        $this->actingAs($this->user)
            ->get(route('mapa.index'))
            ->assertOk()
            ->assertSee('id="map"', false);
    }

    public function test_mapa_js_centraliza_em_bh_sem_max_bounds(): void
    {
        $jsContent = file_get_contents(base_path('resources/js/mapa.js'));
        $this->assertStringContainsString('BH_CENTER', $jsContent);
        $this->assertStringContainsString('DEFAULT_ZOOM', $jsContent);
        $this->assertStringNotContainsString('maxBoundsViscosity: 1.0', $jsContent);
        $this->assertStringNotContainsString('BH_BOUNDS', $jsContent);
    }

    public function test_minhas_vistorias_reutiliza_index(): void
    {
        $this->actingAs($this->user)
            ->get(route('vistorias.minhas'))
            ->assertOk()
            ->assertSee('Minhas Zeladorias');
    }

    public function test_listagem_vistorias_renderiza_tabela_crud(): void
    {
        $ponto = Ponto::factory()->create();

        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'houve_comunicado' => false,
            'data_abordagem' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('vistorias.index'))
            ->assertOk()
            ->assertSee('vistorias-table')
            ->assertSee('Aberta');
    }

    public function test_listagem_pontos_mostra_info_precaria(): void
    {
        $ponto = Ponto::factory()->create();

        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'data_abordagem' => now()->subDays(90),
        ]);

        $this->actingAs($this->user)
            ->get(route('pontos.index'))
            ->assertOk()
            ->assertSee('Informação Precária');
    }

    public function test_data_prevista_zeladoria_persiste_para_comunicacao_ou_comunicado(): void
    {
        $this->actingAs($this->user)->post(route('vistorias.store'), [
            'lat' => -19.9200,
            'lng' => -43.9400,
            'data_abordagem' => now()->format('Y-m-d\TH:i'),
            'tipo_abordagem_id' => 1,
            'resultado_acao_id' => 1,
            'data_prevista_zeladoria' => '2026-07-01',
            'periodo_zeladoria' => 'manha',
        ])->assertRedirect();

        $vistoriaOrientativa = Vistoria::query()->orderByDesc('id')->first();
        $this->assertNull($vistoriaOrientativa->data_prevista_zeladoria);
        $this->assertNull($vistoriaOrientativa->periodo_zeladoria);

        $this->actingAs($this->user)->post(route('vistorias.store'), [
            'lat' => -19.9205,
            'lng' => -43.9405,
            'data_abordagem' => now()->format('Y-m-d\TH:i'),
            'tipo_abordagem_id' => 1,
            'resultado_acao_id' => 1,
            'houve_comunicado' => '1',
            'data_comunicado' => '2026-06-01T10:00',
            'data_prevista_zeladoria' => '2026-07-05',
            'periodo_zeladoria' => 'tarde',
        ])->assertRedirect();

        $vistoriaComComunicado = Vistoria::query()->orderByDesc('id')->first();
        $this->assertNotNull($vistoriaComComunicado->data_prevista_zeladoria);
        $this->assertSame('tarde', $vistoriaComComunicado->periodo_zeladoria);

        $this->actingAs($this->user)->post(route('vistorias.store'), [
            'lat' => -19.9210,
            'lng' => -43.9410,
            'data_abordagem' => now()->format('Y-m-d\TH:i'),
            'tipo_abordagem_id' => 5,
            'resultado_acao_id' => 1,
            'data_prevista_zeladoria' => '2026-07-15',
            'periodo_zeladoria' => 'tarde',
        ])->assertRedirect();

        $vistoriaComunicacao = Vistoria::query()->orderByDesc('id')->first();
        $this->assertNotNull($vistoriaComunicacao->data_prevista_zeladoria);
        $this->assertSame('tarde', $vistoriaComunicacao->periodo_zeladoria);
    }

    public function test_exportar_roteiro_csv(): void
    {
        $ponto = Ponto::factory()->create();

        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'tipo_abordagem_id' => 5,
            'resultado_acao_id' => 1,
            'data_prevista_zeladoria' => '2026-07-10',
            'periodo_zeladoria' => 'manha',
            'data_abordagem' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('vistorias.roteiro', [
            'data_prevista_inicio' => '2026-07-01',
            'data_prevista_fim' => '2026-07-31',
            'format' => 'csv',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Data Prevista', $response->streamedContent());
        $this->assertStringContainsString('10/07/2026', $response->streamedContent());
    }
}
