<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ponto;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VistoriaReportPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $autor;

    private Vistoria $vistoria;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tipo_abordagem')->insert(['id' => 1, 'tipo' => 'Rotina']);
        DB::table('resultados_acoes')->insert(['id' => 1, 'resultado' => 'Orientação']);

        $this->autor = User::factory()->create([
            'name' => 'Agente Teste',
            'email' => 'agente.secreto@pbh.gov.br',
        ]);

        $ponto = Ponto::factory()->create();
        $this->vistoria = Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->autor->id,
            'tipo_abordagem_id' => 1,
            'resultado_acao_id' => 1,
            'data_abordagem' => now(),
        ]);
    }

    #[Test]
    public function relatorio_impressao_exibe_apenas_fotos_publicas(): void
    {
        $this->vistoria
            ->addMedia(UploadedFile::fake()->image('foto-publica.jpg'))
            ->withCustomProperties(['publica' => true, 'legenda' => 'Legenda foto pública'])
            ->toMediaCollection('fotos');

        $this->vistoria
            ->addMedia(UploadedFile::fake()->image('foto-privada.jpg'))
            ->withCustomProperties(['publica' => false, 'legenda' => 'Legenda foto privada'])
            ->toMediaCollection('fotos');

        $print = $this->actingAs($this->autor)->get(route('vistorias.report.print', $this->vistoria));
        $print->assertOk();
        $print->assertSee('Registro fotográfico (1)', false);
        $print->assertDontSee('Legenda foto privada', false);

        $screen = $this->actingAs($this->autor)->get(route('vistorias.report', $this->vistoria));
        $screen->assertOk();
        $screen->assertSee('Registro fotográfico (2', false);
    }

    #[Test]
    public function relatorio_impressao_exibe_status_cancelada(): void
    {
        $this->vistoria->update([
            'cancelada' => true,
            'cancelada_em' => now(),
        ]);

        $response = $this->actingAs($this->autor)->get(route('vistorias.report.print', $this->vistoria));

        $response->assertOk();
        $response->assertSee('Zeladoria cancelada', false);
    }

    #[Test]
    public function relatorio_impressao_exibe_houve_lavratura(): void
    {
        $this->vistoria->update(['houve_lavratura' => true]);

        $response = $this->actingAs($this->autor)->get(route('vistorias.report.print', $this->vistoria));

        $response->assertOk();
        $response->assertSee('Ações fiscalizatórias e materiais', false);
        $response->assertSee('Houve lavratura', false);
        $response->assertSee('Sim', false);
    }

    #[Test]
    public function relatorio_impressao_nao_exibe_email_do_registrador(): void
    {
        $response = $this->actingAs($this->autor)->get(route('vistorias.report.print', $this->vistoria));

        $response->assertOk();
        $response->assertSee('Agente Teste', false);
        $response->assertDontSee('agente.secreto@pbh.gov.br', false);
    }
}
