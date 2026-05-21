<?php

namespace Tests\Unit\Models;

use App\Models\Encaminhamento;
use App\Models\Ponto;
use App\Models\ResultadoAcao;
use App\Models\TipoAbordagem;
use App\Models\TipoAbrigoDesmontado;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VistoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_criar_vistoria_com_factory(): void
    {
        $vistoria = Vistoria::factory()->create();

        $this->assertInstanceOf(Vistoria::class, $vistoria);
        $this->assertDatabaseHas('vistorias', ['id' => $vistoria->id]);
    }

    public function test_vistoria_pertence_a_ponto(): void
    {
        $ponto = Ponto::factory()->create();
        $vistoria = Vistoria::factory()->create(['ponto_id' => $ponto->id]);

        $this->assertInstanceOf(Ponto::class, $vistoria->ponto);
        $this->assertEquals($ponto->id, $vistoria->ponto->id);
    }

    public function test_vistoria_pertence_a_usuario(): void
    {
        $user = User::factory()->create();
        $vistoria = Vistoria::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $vistoria->user);
        $this->assertEquals($user->id, $vistoria->user->id);
    }

    public function test_vistoria_pode_ter_tipo_abordagem(): void
    {
        // Primeiro criamos o tipo na tabela
        $tipoAbordagem = TipoAbordagem::create(['tipo' => 'Abordagem de Rotina']);

        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $tipoAbordagem->id,
        ]);

        $vistoria->load('tipoAbordagem');

        $this->assertInstanceOf(TipoAbordagem::class, $vistoria->tipoAbordagem);
        $this->assertEquals('Abordagem de Rotina', $vistoria->tipoAbordagem->nome);
    }

    public function test_vistoria_pode_ter_resultado_acao(): void
    {
        $resultadoAcao = ResultadoAcao::create(['resultado' => 'Encaminhado']);

        $vistoria = Vistoria::factory()->create([
            'resultado_acao_id' => $resultadoAcao->id,
        ]);

        $vistoria->load('resultadoAcao');

        $this->assertInstanceOf(ResultadoAcao::class, $vistoria->resultadoAcao);
        $this->assertEquals('Encaminhado', $vistoria->resultadoAcao->nome);
    }

    public function test_vistoria_pode_ter_tipo_abrigo_desmontado(): void
    {
        $tipoAbrigo = TipoAbrigoDesmontado::create(['tipo_abrigo' => 'Barraca']);

        $vistoria = Vistoria::factory()->create([
            'tipo_abrigo_desmontado_id' => $tipoAbrigo->id,
        ]);

        $vistoria->load('tipoAbrigoDesmontado');

        $this->assertInstanceOf(TipoAbrigoDesmontado::class, $vistoria->tipoAbrigoDesmontado);
        $this->assertEquals('Barraca', $vistoria->tipoAbrigoDesmontado->nome);
    }

    public function test_vistoria_com_complexidade_alta(): void
    {
        $vistoria = Vistoria::factory()->comComplexidadeAlta()->create();

        $this->assertTrue((bool) $vistoria->resistencia);
        $this->assertTrue((bool) $vistoria->fixacao_antiga);
        $this->assertTrue((bool) $vistoria->excesso_objetos);
        $this->assertTrue((bool) $vistoria->trafico_ilicitos);
        $this->assertTrue((bool) $vistoria->saude_mental);
    }

    public function test_vistoria_com_complexidade_baixa(): void
    {
        $vistoria = Vistoria::factory()->comComplexidadeBaixa()->create();

        $this->assertFalse((bool) $vistoria->resistencia);
        $this->assertFalse((bool) $vistoria->fixacao_antiga);
        $this->assertFalse((bool) $vistoria->trafico_ilicitos);
        $this->assertFalse((bool) $vistoria->animais);
    }

    public function test_vistoria_recente(): void
    {
        $vistoria = Vistoria::factory()->recente()->create();

        $this->assertTrue($vistoria->data_abordagem->isAfter(now()->subDays(7)));
    }

    public function test_vistoria_antiga(): void
    {
        $vistoria = Vistoria::factory()->antiga()->create();

        $this->assertTrue($vistoria->data_abordagem->isBefore(now()->subMonths(6)));
    }

    public function test_cast_data_abordagem(): void
    {
        $vistoria = Vistoria::factory()->create([
            'data_abordagem' => '2025-06-15 14:30:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $vistoria->data_abordagem);
    }

    public function test_cast_campos_boolean(): void
    {
        $vistoria = Vistoria::factory()->create([
            'resistencia' => 1,
            'casal' => 0,
            'conducao_forcas_seguranca' => true,
        ]);

        $this->assertTrue($vistoria->resistencia);
        $this->assertFalse($vistoria->casal);
        $this->assertTrue($vistoria->conducao_forcas_seguranca);
    }

    public function test_cast_abrigos_tipos_array(): void
    {
        $tipos = [1, 2, 3];
        $vistoria = Vistoria::factory()->create([
            'abrigos_tipos' => $tipos,
        ]);

        $this->assertIsArray($vistoria->abrigos_tipos);
        $this->assertEquals($tipos, $vistoria->abrigos_tipos);
    }

    public function test_vistoria_fillable_campos_corretos(): void
    {
        $dados = [
            'ponto_id' => Ponto::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'data_abordagem' => now(),
            'quantidade_pessoas' => 5,
            'nomes_pessoas' => 'João, Maria',
            'tipo_abordagem_id' => 1,
            'resultado_acao_id' => 1,
            'observacao' => 'Teste de observação',
        ];

        $vistoria = Vistoria::create($dados);

        $this->assertEquals(5, $vistoria->quantidade_pessoas);
        $this->assertEquals('João, Maria', $vistoria->nomes_pessoas);
        $this->assertEquals('Teste de observação', $vistoria->observacao);
    }

    public function test_vistoria_pode_ter_multiplos_encaminhamentos(): void
    {
        $e1 = Encaminhamento::create(['encaminhamento' => 'CRAS']);
        $e2 = Encaminhamento::create(['encaminhamento' => 'CAPS']);

        $vistoria = Vistoria::factory()->create([
            'e1_id' => $e1->id,
            'e2_id' => $e2->id,
            'e3_id' => null,
            'e4_id' => null,
        ]);

        $vistoria->load(['encaminhamento1', 'encaminhamento2']);

        $this->assertEquals('CRAS', $vistoria->encaminhamento1->nome);
        $this->assertEquals('CAPS', $vistoria->encaminhamento2->nome);
        $this->assertNull($vistoria->encaminhamento3);
    }

    public function test_quantidade_casais_depende_de_casal(): void
    {
        $vistoriaComCasal = Vistoria::factory()->create([
            'casal' => true,
            'qtd_casais' => 2,
        ]);

        $vistoriaSemCasal = Vistoria::factory()->create([
            'casal' => false,
            'qtd_casais' => 0,
        ]);

        $this->assertEquals(2, $vistoriaComCasal->qtd_casais);
        $this->assertEquals(0, $vistoriaSemCasal->qtd_casais);
    }

    public function test_quantidade_animais_depende_de_animais(): void
    {
        $vistoriaComAnimais = Vistoria::factory()->create([
            'animais' => true,
            'qtd_animais' => 3,
        ]);

        $this->assertEquals(3, $vistoriaComAnimais->qtd_animais);
    }
}
