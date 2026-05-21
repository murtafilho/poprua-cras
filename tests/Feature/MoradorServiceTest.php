<?php

namespace Tests\Feature;

use App\Models\Morador;
use App\Models\MoradorHistorico;
use App\Models\Ponto;
use App\Models\Vistoria;
use App\Services\MoradorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MoradorServiceTest extends TestCase
{
    use RefreshDatabase;

    private MoradorService $service;

    private int $tipoAbordagemId;

    private int $resultadoAcaoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MoradorService::class);
        $this->tipoAbordagemId = DB::table('tipo_abordagem')->insertGetId(['tipo' => 'Rotina']);
        $this->resultadoAcaoId = DB::table('resultados_acoes')->insertGetId(['resultado' => 'Orientação']);
    }

    public function test_registrar_entrada_cria_historico_e_atualiza_ponto_atual(): void
    {
        $morador = Morador::factory()->create();
        $ponto = Ponto::factory()->create();

        $historico = $this->service->registrarEntrada($morador, $ponto);

        $this->assertInstanceOf(MoradorHistorico::class, $historico);
        $this->assertEquals($ponto->id, $historico->ponto_id);
        $this->assertEquals($morador->id, $historico->morador_id);
        $this->assertNotNull($historico->data_entrada);
        $this->assertNull($historico->data_saida);
        $this->assertEquals($ponto->id, $morador->fresh()->ponto_atual_id);
    }

    public function test_registrar_entrada_fecha_historico_anterior(): void
    {
        $morador = Morador::factory()->create();
        $pontoAntigo = Ponto::factory()->create();
        $pontoNovo = Ponto::factory()->create();

        $historicoAntigo = $this->service->registrarEntrada($morador, $pontoAntigo);

        $this->service->registrarEntrada($morador, $pontoNovo);

        $historicoAntigo->refresh();
        $this->assertNotNull($historicoAntigo->data_saida);
        $this->assertEquals($pontoNovo->id, $morador->fresh()->ponto_atual_id);
    }

    public function test_registrar_entrada_com_vistoria(): void
    {
        $ponto = Ponto::factory()->create();
        $morador = Morador::factory()->create();
        $vistoria = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $historico = $this->service->registrarEntrada($morador, $ponto, $vistoria);

        $this->assertEquals($vistoria->id, $historico->vistoria_entrada_id);
    }

    public function test_registrar_entrada_com_data_especifica(): void
    {
        $morador = Morador::factory()->create();
        $ponto = Ponto::factory()->create();
        $data = now()->subDays(5);

        $historico = $this->service->registrarEntrada($morador, $ponto, null, $data);

        $this->assertEquals($data->toDateString(), $historico->data_entrada->toDateString());
    }

    public function test_registrar_saida_fecha_historico_e_limpa_ponto(): void
    {
        $morador = Morador::factory()->create();
        $ponto = Ponto::factory()->create();

        $historicoEntrada = $this->service->registrarEntrada($morador, $ponto);
        $historicoSaida = $this->service->registrarSaida($morador);

        $this->assertNotNull($historicoSaida);
        $this->assertEquals($historicoEntrada->id, $historicoSaida->id);
        $this->assertNotNull($historicoSaida->data_saida);
        $this->assertNull($morador->fresh()->ponto_atual_id);
    }

    public function test_registrar_saida_sem_historico_aberto(): void
    {
        $morador = Morador::factory()->create();

        $result = $this->service->registrarSaida($morador);

        $this->assertNull($result);
        $this->assertNull($morador->fresh()->ponto_atual_id);
    }

    public function test_registrar_saida_com_vistoria(): void
    {
        $ponto = Ponto::factory()->create();
        $morador = Morador::factory()->create();
        $vistoria = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $this->service->registrarEntrada($morador, $ponto);
        $historico = $this->service->registrarSaida($morador, $vistoria);

        $this->assertEquals($vistoria->id, $historico->vistoria_saida_id);
    }

    public function test_transferir_fecha_ponto_anterior_e_abre_novo(): void
    {
        $morador = Morador::factory()->create();
        $pontoOrigem = Ponto::factory()->create();
        $pontoDestino = Ponto::factory()->create();

        $historicoOrigem = $this->service->registrarEntrada($morador, $pontoOrigem);
        $historicoDestino = $this->service->transferir($morador, $pontoDestino);

        $historicoOrigem->refresh();
        $this->assertNotNull($historicoOrigem->data_saida);
        $this->assertEquals($pontoDestino->id, $historicoDestino->ponto_id);
        $this->assertNotNull($historicoDestino->data_entrada);
        $this->assertNull($historicoDestino->data_saida);
        $this->assertEquals($pontoDestino->id, $morador->fresh()->ponto_atual_id);
    }

    public function test_transferir_com_vistorias_distintas(): void
    {
        $pontoOrigem = Ponto::factory()->create();
        $pontoDestino = Ponto::factory()->create();
        $morador = Morador::factory()->create();

        $vistoriaSaida = Vistoria::factory()->create([
            'ponto_id' => $pontoOrigem->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);
        $vistoriaEntrada = Vistoria::factory()->create([
            'ponto_id' => $pontoDestino->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $this->service->registrarEntrada($morador, $pontoOrigem);
        $historico = $this->service->transferir($morador, $pontoDestino, $vistoriaSaida, $vistoriaEntrada);

        $historicoSaida = MoradorHistorico::where('morador_id', $morador->id)
            ->where('ponto_id', $pontoOrigem->id)
            ->first();

        $this->assertEquals($vistoriaSaida->id, $historicoSaida->vistoria_saida_id);
        $this->assertEquals($vistoriaEntrada->id, $historico->vistoria_entrada_id);
    }

    public function test_criar_com_entrada(): void
    {
        $ponto = Ponto::factory()->create();
        $dados = [
            'nome_social' => 'João Silva',
            'apelido' => 'Jota',
            'genero' => 'Homem cisgênero',
        ];

        $morador = $this->service->criarComEntrada($dados, $ponto);

        $this->assertInstanceOf(Morador::class, $morador);
        $this->assertEquals('João Silva', $morador->nome_social);
        $this->assertEquals($ponto->id, $morador->ponto_atual_id);

        $historico = MoradorHistorico::where('morador_id', $morador->id)->first();
        $this->assertNotNull($historico);
        $this->assertEquals($ponto->id, $historico->ponto_id);
    }

    public function test_criar_com_entrada_e_vistoria(): void
    {
        $ponto = Ponto::factory()->create();
        $vistoria = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $morador = $this->service->criarComEntrada(
            ['nome_social' => 'Maria', 'genero' => 'Mulher cisgênero'],
            $ponto,
            $vistoria
        );

        $historico = MoradorHistorico::where('morador_id', $morador->id)->first();
        $this->assertEquals($vistoria->id, $historico->vistoria_entrada_id);
    }

    public function test_atualizar_presenca_registra_saidas(): void
    {
        $ponto = Ponto::factory()->create();
        $vistoria = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $morador1 = Morador::factory()->create();
        $morador2 = Morador::factory()->create();
        $this->service->registrarEntrada($morador1, $ponto);
        $this->service->registrarEntrada($morador2, $ponto);

        $this->service->atualizarPresencaVistoria($vistoria, [$morador1->id]);

        $this->assertEquals($ponto->id, $morador1->fresh()->ponto_atual_id);
        $this->assertNull($morador2->fresh()->ponto_atual_id);
    }

    public function test_atualizar_presenca_registra_entradas(): void
    {
        $ponto = Ponto::factory()->create();
        $vistoria = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $moradorExistente = Morador::factory()->create();
        $moradorNovo = Morador::factory()->create();

        $this->service->registrarEntrada($moradorExistente, $ponto);

        $this->service->atualizarPresencaVistoria(
            $vistoria,
            [$moradorExistente->id, $moradorNovo->id]
        );

        $this->assertEquals($ponto->id, $moradorExistente->fresh()->ponto_atual_id);
        $this->assertEquals($ponto->id, $moradorNovo->fresh()->ponto_atual_id);
    }

    public function test_atualizar_presenca_cria_novos_moradores(): void
    {
        $ponto = Ponto::factory()->create();
        $vistoria = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $this->service->atualizarPresencaVistoria($vistoria, [], [
            ['nome_social' => 'Novo Morador', 'genero' => 'Homem cisgênero'],
            ['nome_social' => 'Outra Pessoa', 'genero' => 'Mulher cisgênero'],
        ]);

        $this->assertDatabaseHas('moradores', ['nome_social' => 'Novo Morador']);
        $this->assertDatabaseHas('moradores', ['nome_social' => 'Outra Pessoa']);

        $novo = Morador::where('nome_social', 'Novo Morador')->first();
        $this->assertEquals($ponto->id, $novo->ponto_atual_id);
    }

    public function test_atualizar_presenca_cenario_completo(): void
    {
        $ponto = Ponto::factory()->create();
        $vistoria = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $moradorFica = Morador::factory()->create();
        $moradorSai = Morador::factory()->create();
        $moradorEntra = Morador::factory()->create();

        $this->service->registrarEntrada($moradorFica, $ponto);
        $this->service->registrarEntrada($moradorSai, $ponto);

        $this->service->atualizarPresencaVistoria(
            $vistoria,
            [$moradorFica->id, $moradorEntra->id],
            [['nome_social' => 'Criado Na Vistoria', 'genero' => 'Não-binário']]
        );

        $this->assertEquals($ponto->id, $moradorFica->fresh()->ponto_atual_id);
        $this->assertNull($moradorSai->fresh()->ponto_atual_id);
        $this->assertEquals($ponto->id, $moradorEntra->fresh()->ponto_atual_id);
        $this->assertDatabaseHas('moradores', ['nome_social' => 'Criado Na Vistoria']);
    }

    public function test_buscar_por_nome_retorna_resultados(): void
    {
        $morador = Morador::factory()->create(['nome_social' => 'Carlos Eduardo']);
        Morador::factory()->create(['nome_social' => 'Ana Maria']);

        $resultados = $this->service->buscarPorNome('Carlos');

        $this->assertCount(1, $resultados);
        $this->assertEquals($morador->id, $resultados->first()->id);
    }

    public function test_buscar_por_nome_exclui_ponto(): void
    {
        $ponto = Ponto::factory()->create();
        $moradorNoPonto = Morador::factory()->create([
            'nome_social' => 'Carlos',
            'ponto_atual_id' => $ponto->id,
        ]);
        $moradorFora = Morador::factory()->create([
            'nome_social' => 'Carlos Outro',
            'ponto_atual_id' => null,
        ]);

        $resultados = $this->service->buscarPorNome('Carlos', $ponto->id);

        $ids = $resultados->pluck('id')->toArray();
        $this->assertNotContains($moradorNoPonto->id, $ids);
        $this->assertContains($moradorFora->id, $ids);
    }

    public function test_get_historico_retorna_com_relacoes(): void
    {
        $ponto = Ponto::factory()->create();
        $morador = Morador::factory()->create();

        $this->service->registrarEntrada($morador, $ponto);
        $this->service->registrarSaida($morador);

        $historico = $this->service->getHistorico($morador);

        $this->assertCount(1, $historico);
        $this->assertTrue($historico->first()->relationLoaded('ponto'));
    }

    public function test_get_moradores_do_ponto(): void
    {
        $ponto = Ponto::factory()->create();
        $morador1 = Morador::factory()->create();
        $morador2 = Morador::factory()->create();

        $this->service->registrarEntrada($morador1, $ponto);
        $this->service->registrarEntrada($morador2, $ponto);

        $moradores = $this->service->getMoradoresDoPonto($ponto);

        $this->assertCount(2, $moradores);
    }

    public function test_multiplas_transferencias_mantem_historico_completo(): void
    {
        $morador = Morador::factory()->create();
        $pontoA = Ponto::factory()->create();
        $pontoB = Ponto::factory()->create();
        $pontoC = Ponto::factory()->create();

        $this->service->registrarEntrada($morador, $pontoA);
        $this->service->transferir($morador, $pontoB);
        $this->service->transferir($morador, $pontoC);

        $historicos = MoradorHistorico::where('morador_id', $morador->id)
            ->orderBy('data_entrada')
            ->get();

        $this->assertCount(3, $historicos);

        $this->assertEquals($pontoA->id, $historicos[0]->ponto_id);
        $this->assertNotNull($historicos[0]->data_saida);

        $this->assertEquals($pontoB->id, $historicos[1]->ponto_id);
        $this->assertNotNull($historicos[1]->data_saida);

        $this->assertEquals($pontoC->id, $historicos[2]->ponto_id);
        $this->assertNull($historicos[2]->data_saida);

        $this->assertEquals($pontoC->id, $morador->fresh()->ponto_atual_id);
    }
}
