<?php

namespace Tests\Unit\Models;

use App\Models\EnderecoAtualizado;
use App\Models\Morador;
use App\Models\Ponto;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PontoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_criar_ponto_com_factory(): void
    {
        $ponto = Ponto::factory()->create();

        $this->assertInstanceOf(Ponto::class, $ponto);
        $this->assertDatabaseHas('pontos', ['id' => $ponto->id]);
    }

    public function test_pode_criar_ponto_com_endereco(): void
    {
        $ponto = Ponto::factory()->comEndereco()->create();

        $this->assertNotNull($ponto->endereco_atualizado_id);
        $this->assertInstanceOf(EnderecoAtualizado::class, $ponto->enderecoAtualizado);
    }

    public function test_relacionamento_com_vistorias(): void
    {
        $ponto = Ponto::factory()->create();
        $vistorias = Vistoria::factory()->count(3)->create(['ponto_id' => $ponto->id]);

        $this->assertCount(3, $ponto->vistorias);
        $this->assertInstanceOf(Vistoria::class, $ponto->vistorias->first());
    }

    public function test_relacionamento_ultima_vistoria(): void
    {
        $ponto = Ponto::factory()->create();

        $vistoriaAntiga = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'data_abordagem' => now()->subDays(30),
        ]);

        $vistoriaRecente = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'data_abordagem' => now(),
        ]);

        $ponto->refresh();

        $this->assertEquals($vistoriaRecente->id, $ponto->ultimaVistoria->id);
    }

    public function test_relacionamento_com_moradores(): void
    {
        $ponto = Ponto::factory()->create();
        $moradores = Morador::factory()->count(2)->create(['ponto_atual_id' => $ponto->id]);

        $this->assertCount(2, $ponto->moradores);
        $this->assertInstanceOf(Morador::class, $ponto->moradores->first());
    }

    public function test_scope_georreferenciado(): void
    {
        Ponto::factory()->georreferenciado()->count(3)->create();
        Ponto::factory()->naoGeorreferenciado()->count(2)->create();

        $georreferenciados = Ponto::georreferenciado()->get();

        $this->assertCount(3, $georreferenciados);
    }

    public function test_scope_nao_georreferenciado(): void
    {
        Ponto::factory()->georreferenciado()->count(3)->create();
        Ponto::factory()->naoGeorreferenciado()->count(2)->create();

        $naoGeorreferenciados = Ponto::naoGeorreferenciado()->get();

        $this->assertCount(2, $naoGeorreferenciados);
    }

    public function test_scope_com_endereco(): void
    {
        Ponto::factory()->comEndereco()->count(2)->create();
        Ponto::factory()->count(3)->create(['endereco_atualizado_id' => null]);

        $comEndereco = Ponto::comEndereco()->get();

        $this->assertCount(2, $comEndereco);
    }

    public function test_scope_in_bounds(): void
    {
        // Ponto dentro da área
        Ponto::factory()->create([
            'lat' => -19.90,
            'lng' => -43.95,
        ]);

        // Ponto fora da área
        Ponto::factory()->create([
            'lat' => -20.50,
            'lng' => -44.50,
        ]);

        $dentroLimites = Ponto::inBounds(
            north: -19.80,
            south: -20.00,
            east: -43.80,
            west: -44.00
        )->get();

        $this->assertCount(1, $dentroLimites);
    }

    public function test_scope_regional(): void
    {
        $enderecoCentroSul = EnderecoAtualizado::factory()->regional('CENTRO-SUL')->create();
        $enderecoPampulha = EnderecoAtualizado::factory()->regional('PAMPULHA')->create();

        Ponto::factory()->create(['endereco_atualizado_id' => $enderecoCentroSul->id]);
        Ponto::factory()->create(['endereco_atualizado_id' => $enderecoPampulha->id]);

        $pontosCentroSul = Ponto::regional('CENTRO-SUL')->get();

        $this->assertCount(1, $pontosCentroSul);
    }

    public function test_accessor_total_vistorias(): void
    {
        $ponto = Ponto::factory()->create();
        Vistoria::factory()->count(5)->create(['ponto_id' => $ponto->id]);

        $ponto->load('vistorias');

        $this->assertEquals(5, $ponto->total_vistorias);
    }

    public function test_accessor_complexidade_sem_vistoria(): void
    {
        $ponto = Ponto::factory()->create();

        $this->assertEquals(0, $ponto->complexidade);
    }

    public function test_accessor_complexidade_com_vistoria(): void
    {
        $ponto = Ponto::factory()->create();
        Vistoria::factory()->comComplexidadeAlta()->create(['ponto_id' => $ponto->id]);

        $ponto->load('ultimaVistoria');

        $this->assertGreaterThan(0, $ponto->complexidade);
        $this->assertEquals(5, $ponto->complexidade); // 5 indicadores marcados
    }

    public function test_accessor_numero_endereco_com_endereco_carregado(): void
    {
        $endereco = EnderecoAtualizado::factory()->create(['NUMERO_IMOVEL' => 123]);
        $ponto = Ponto::factory()->create([
            'endereco_atualizado_id' => $endereco->id,
            'numero' => '999',
        ]);

        $ponto->load('enderecoAtualizado');

        $this->assertEquals('123', $ponto->numero_endereco);
    }

    public function test_accessor_numero_endereco_sem_endereco(): void
    {
        $ponto = Ponto::factory()->create([
            'endereco_atualizado_id' => null,
            'numero' => '456',
        ]);

        $this->assertEquals('456', $ponto->numero_endereco);
    }

    public function test_accessor_endereco_completo(): void
    {
        $endereco = EnderecoAtualizado::factory()->create([
            'SIGLA_TIPO_LOGRADOURO' => 'R',
            'NOME_LOGRADOURO' => 'DA PAZ',
            'NUMERO_IMOVEL' => 100,
            'NOME_BAIRRO_OFICIAL' => 'CENTRO',
        ]);

        $ponto = Ponto::factory()->create([
            'endereco_atualizado_id' => $endereco->id,
            'complemento' => 'Próximo ao mercado',
        ]);

        $ponto->load('enderecoAtualizado');

        $this->assertStringContainsString('R', $ponto->endereco_completo);
        $this->assertStringContainsString('DA PAZ', $ponto->endereco_completo);
        $this->assertStringContainsString('Próximo ao mercado', $ponto->endereco_completo);
    }

    public function test_ponto_sem_coordenadas_validas(): void
    {
        $ponto = Ponto::factory()->create([
            'lat' => 0,
            'lng' => 0,
        ]);

        $naoGeorreferenciados = Ponto::naoGeorreferenciado()->get();

        $this->assertTrue($naoGeorreferenciados->contains($ponto));
    }
}
