<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Ponto;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VistoriaControllerFotoPublicaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $tipoAbordagemId;

    private int $resultadoAcaoId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        Queue::fake();

        $this->user = User::factory()->create();

        // Seed lookup tables e guarda os IDs
        $this->tipoAbordagemId = \DB::table('tipo_abordagem')->insertGetId(['tipo' => 'Rotina']);
        $this->resultadoAcaoId = \DB::table('resultados_acoes')->insertGetId(['resultado' => 'Orientacao']);
    }

    public function test_store_salva_fotos_com_legenda_e_publica(): void
    {
        $file = UploadedFile::fake()->image('foto1.jpg', 640, 480);

        $ponto = Ponto::factory()->create(['lat' => -19.8, 'lng' => -43.9]);

        $response = $this->actingAs($this->user)->postJson(route('vistorias.store'), [
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
            'data_abordagem' => now()->format('Y-m-d\TH:i'),
            'lat' => $ponto->lat,
            'lng' => $ponto->lng,
            'casal' => false,
            'fotos' => [$file],
            'legendas_fotos' => ['Legenda da foto'],
            'publicas_fotos' => ['1'],
        ]);

        $response->assertRedirect();

        $vistoria = Vistoria::latest()->first();
        $this->assertNotNull($vistoria);

        $fotos = $vistoria->getMedia('fotos');
        $this->assertCount(1, $fotos);

        $foto = $fotos->first();
        $this->assertEquals('Legenda da foto', $foto->getCustomProperty('legenda'));
        $this->assertTrue($foto->getCustomProperty('publica', false));
    }

    public function test_store_salva_foto_sem_publica_default_false(): void
    {
        $file = UploadedFile::fake()->image('foto2.jpg', 640, 480);

        $ponto = Ponto::factory()->create(['lat' => -19.8, 'lng' => -43.9]);

        $response = $this->actingAs($this->user)->postJson(route('vistorias.store'), [
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
            'data_abordagem' => now()->format('Y-m-d\TH:i'),
            'lat' => $ponto->lat,
            'lng' => $ponto->lng,
            'casal' => false,
            'fotos' => [$file],
            'legendas_fotos' => [''],
            'publicas_fotos' => ['0'],
        ]);

        $response->assertRedirect();

        $vistoria = Vistoria::latest()->first();
        $foto = $vistoria->getMedia('fotos')->first();
        $this->assertFalse($foto->getCustomProperty('publica', false));
    }

    public function test_update_salva_fotos_novas_com_legenda_e_publica(): void
    {
        $vistoria = Vistoria::factory()->create([
            'user_id' => $this->user->id,
            'ponto_id' => Ponto::factory()->create(['lat' => -19.8, 'lng' => -43.9]),
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $file = UploadedFile::fake()->image('foto-nova.jpg', 640, 480);

        $response = $this->actingAs($this->user)->putJson(route('vistorias.update', $vistoria), [
            'tipo_abordagem_id' => $vistoria->tipo_abordagem_id,
            'resultado_acao_id' => $vistoria->resultado_acao_id,
            'data_abordagem' => $vistoria->data_abordagem->format('Y-m-d\TH:i'),
            'casal' => false,
            'fotos' => [$file],
            'legendas_fotos' => ['Nova legenda'],
            'publicas_fotos' => ['1'],
        ]);

        $response->assertRedirect();

        $fotos = $vistoria->getMedia('fotos');
        $this->assertCount(1, $fotos);

        $foto = $fotos->first();
        $this->assertEquals('Nova legenda', $foto->getCustomProperty('legenda'));
        $this->assertTrue($foto->getCustomProperty('publica', false));
    }

    public function test_store_multiplos_arquivos_com_diferentes_publicas(): void
    {
        $file1 = UploadedFile::fake()->image('foto-a.jpg', 640, 480);
        $file2 = UploadedFile::fake()->image('foto-b.jpg', 640, 480);

        $ponto = Ponto::factory()->create(['lat' => -19.8, 'lng' => -43.9]);

        $response = $this->actingAs($this->user)->postJson(route('vistorias.store'), [
            'ponto_id' => $ponto->id,
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
            'data_abordagem' => now()->format('Y-m-d\TH:i'),
            'lat' => $ponto->lat,
            'lng' => $ponto->lng,
            'casal' => false,
            'fotos' => [$file1, $file2],
            'legendas_fotos' => ['Foto A', 'Foto B'],
            'publicas_fotos' => ['1', '0'],
        ]);

        $response->assertRedirect();

        $vistoria = Vistoria::latest()->first();
        $fotos = $vistoria->getMedia('fotos');
        $this->assertCount(2, $fotos);

        // Primeira foto publica
        $this->assertTrue($fotos->first()->getCustomProperty('publica', false));
        // Segunda foto nao publica
        $this->assertFalse($fotos->last()->getCustomProperty('publica', false));
    }
}
