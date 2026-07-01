<?php

namespace Tests\Feature;

use App\Models\Ponto;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $resultadoAcaoId;

    private int $tipoAbordagemId;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->user = User::factory()->create();

        // Lookup tables required by Vistoria factory
        $this->tipoAbordagemId = DB::table('tipo_abordagem')->insertGetId(['tipo' => 'Rotina']);
        $this->resultadoAcaoId = DB::table('resultados_acoes')->insertGetId(['resultado' => 'Persiste']);
    }

    // ========================================
    // Authentication
    // ========================================

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    // ========================================
    // Rendering
    // ========================================

    public function test_dashboard_renders_with_correct_view(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard');
    }

    // ========================================
    // Total pontos count
    // ========================================

    public function test_dashboard_shows_correct_total_pontos_count(): void
    {
        Ponto::factory()->count(5)->create();

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalPontos', 5);
    }

    // ========================================
    // Vistorias count
    // ========================================

    public function test_dashboard_shows_correct_vistorias_count(): void
    {
        $ponto = Ponto::factory()->create();

        Vistoria::factory()->count(3)->create([
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $totais = $response->viewData('totais');
        $this->assertEquals(3, $totais->vistorias);
    }

    // ========================================
    // Pontos vistoriados count
    // ========================================

    public function test_dashboard_shows_correct_pontos_vistoriados_count(): void
    {
        $pontoA = Ponto::factory()->create();
        $pontoB = Ponto::factory()->create();
        $pontoC = Ponto::factory()->create();

        // Two vistorias on pontoA, one on pontoB, none on pontoC
        Vistoria::factory()->count(2)->create([
            'ponto_id' => $pontoA->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);
        Vistoria::factory()->create([
            'ponto_id' => $pontoB->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $totais = $response->viewData('totais');
        $this->assertEquals(2, $totais->pontos_vistoriados);
    }

    // ========================================
    // Empty state
    // ========================================

    public function test_dashboard_with_no_data_shows_zeros(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalPontos', 0);

        $totais = $response->viewData('totais');
        $this->assertEquals(0, $totais->vistorias);
        $this->assertEquals(0, $totais->pontos_vistoriados);

        $dadosMensais = $response->viewData('dadosMensais');
        $this->assertCount(0, $dadosMensais);
    }

    // ========================================
    // Monthly data structure
    // ========================================

    public function test_dashboard_monthly_data_has_correct_structure(): void
    {
        $ponto = Ponto::factory()->create();

        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
            'data_abordagem' => '2025-03-15',
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $dadosMensais = $response->viewData('dadosMensais');
        $this->assertNotEmpty($dadosMensais);

        $expectedKeys = [
            'mes',
            'total_existentes',
            'total_pontos',
            'persiste',
            'impactado_parcial',
            'deixou_ocorrer',
            'ausente',
            'nao_constatado',
            'conformidade',
            'sem_vistoria',
            'extintos',
            'ativos',
            'total_efetivo',
        ];

        $firstRow = $dadosMensais->first();
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $firstRow, "Missing key: {$key}");
        }

        // mes should be YYYY-MM format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $firstRow['mes']);

        // Numeric fields should be integers
        foreach ($expectedKeys as $key) {
            if ($key === 'mes') {
                continue;
            }
            $this->assertIsInt($firstRow[$key], "Key {$key} should be an integer");
        }
    }

    // ========================================
    // Caching
    // ========================================

    public function test_dashboard_caches_results(): void
    {
        Cache::flush();

        $this->assertFalse(Cache::has('dashboard:total_pontos'));
        $this->assertFalse(Cache::has('dashboard:totais'));
        $this->assertFalse(Cache::has('dashboard:dados_mensais'));

        $this->actingAs($this->user)->get(route('dashboard'));

        $this->assertTrue(Cache::has('dashboard:total_pontos'));
        $this->assertTrue(Cache::has('dashboard:totais'));
        $this->assertTrue(Cache::has('dashboard:dados_mensais'));
    }

    // ========================================
    // Soft-deleted vistorias excluded
    // ========================================

    public function test_dashboard_excludes_soft_deleted_vistorias(): void
    {
        $ponto = Ponto::factory()->create();

        Vistoria::factory()->count(2)->create([
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        // Soft-delete one vistoria
        Vistoria::first()->delete();

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $totais = $response->viewData('totais');
        $this->assertEquals(1, $totais->vistorias);
    }

    // ========================================
    // View receives resultados
    // ========================================

    public function test_dashboard_passes_resultados_to_view(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('resultados');
    }
}
