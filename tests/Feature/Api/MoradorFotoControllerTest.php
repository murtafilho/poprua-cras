<?php

namespace Tests\Feature\Api;

use App\Models\Morador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MoradorFotoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_upload_foto_unica_morador(): void
    {
        Storage::fake(config('media-library.disk_name', 'public'));
        $morador = Morador::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/fotos", [
                'foto' => UploadedFile::fake()->image('retrato.jpg', 640, 480),
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'url', 'thumb', 'preview', 'created_at']);

        $this->assertCount(1, $morador->fresh()->getMedia('fotos'));
    }

    public function test_upload_multiplas_fotos(): void
    {
        Storage::fake(config('media-library.disk_name', 'public'));
        $morador = Morador::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/fotos", [
                'fotos' => [
                    UploadedFile::fake()->image('foto1.jpg'),
                    UploadedFile::fake()->image('foto2.jpg'),
                    UploadedFile::fake()->image('foto3.jpg'),
                ],
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['fotos' => [['id', 'url', 'thumb']]]);

        $this->assertCount(3, $morador->fresh()->getMedia('fotos'));
    }

    public function test_upload_acumula_em_vez_de_substituir(): void
    {
        Storage::fake(config('media-library.disk_name', 'public'));
        $morador = Morador::factory()->create();

        $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/fotos", [
                'foto' => UploadedFile::fake()->image('foto1.jpg'),
            ]);

        $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/fotos", [
                'foto' => UploadedFile::fake()->image('foto2.jpg'),
            ]);

        $this->assertCount(2, $morador->fresh()->getMedia('fotos'));
    }

    public function test_upload_rejeita_nao_imagem(): void
    {
        $morador = Morador::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/moradores/{$morador->id}/fotos", [
                'foto' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('foto');
    }

    public function test_upload_exige_autenticacao(): void
    {
        $morador = Morador::factory()->create();

        $response = $this->postJson("/api/moradores/{$morador->id}/fotos", [
            'foto' => UploadedFile::fake()->image('retrato.jpg'),
        ]);

        $response->assertUnauthorized();
    }

    public function test_index_lista_fotos_ordenadas_por_data_desc(): void
    {
        Storage::fake(config('media-library.disk_name', 'public'));
        $morador = Morador::factory()->create();

        $morador->addMedia(UploadedFile::fake()->image('antiga.jpg'))->toMediaCollection('fotos');
        $morador->addMedia(UploadedFile::fake()->image('nova.jpg'))->toMediaCollection('fotos');

        $response = $this->actingAs($this->user)
            ->getJson("/api/moradores/{$morador->id}/fotos");

        $response->assertOk()
            ->assertJsonStructure(['fotos' => [['id', 'url', 'thumb', 'created_at']]]);

        $this->assertCount(2, $response->json('fotos'));
    }

    public function test_destroy_remove_uma_foto_especifica(): void
    {
        Storage::fake(config('media-library.disk_name', 'public'));
        $morador = Morador::factory()->create();
        $m1 = $morador->addMedia(UploadedFile::fake()->image('1.jpg'))->toMediaCollection('fotos');
        $m2 = $morador->addMedia(UploadedFile::fake()->image('2.jpg'))->toMediaCollection('fotos');

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/moradores/{$morador->id}/fotos/{$m1->id}");

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertCount(1, $morador->fresh()->getMedia('fotos'));
        $this->assertNotNull($morador->fresh()->getMedia('fotos')->firstWhere('id', $m2->id));
    }

    public function test_destroy_rejeita_foto_de_outro_morador(): void
    {
        Storage::fake(config('media-library.disk_name', 'public'));
        $m1 = Morador::factory()->create();
        $m2 = Morador::factory()->create();
        $media = $m1->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('fotos');

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/moradores/{$m2->id}/fotos/{$media->id}");

        $response->assertForbidden();
        $this->assertCount(1, $m1->fresh()->getMedia('fotos'));
    }

    public function test_delete_compat_limpa_toda_a_colecao(): void
    {
        Storage::fake(config('media-library.disk_name', 'public'));
        $morador = Morador::factory()->create();
        $morador->addMedia(UploadedFile::fake()->image('1.jpg'))->toMediaCollection('fotos');
        $morador->addMedia(UploadedFile::fake()->image('2.jpg'))->toMediaCollection('fotos');

        $this->assertCount(2, $morador->getMedia('fotos'));

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/moradores/{$morador->id}/foto");

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertCount(0, $morador->fresh()->getMedia('fotos'));
    }

    public function test_store_morador_com_foto_via_web_singular(): void
    {
        Storage::fake(config('media-library.disk_name', 'public'));

        $response = $this->actingAs($this->user)
            ->post(route('moradores.store'), [
                'nome_social' => 'Maria',
                'fotografia' => UploadedFile::fake()->image('foto.jpg', 640, 480),
            ]);

        $response->assertRedirect();
        $morador = Morador::where('nome_social', 'Maria')->first();
        $this->assertNotNull($morador);
        $this->assertCount(1, $morador->getMedia('fotos'));
    }

    public function test_store_morador_com_multiplas_fotos_via_web(): void
    {
        Storage::fake(config('media-library.disk_name', 'public'));

        $response = $this->actingAs($this->user)
            ->post(route('moradores.store'), [
                'nome_social' => 'Joao',
                'fotografias' => [
                    UploadedFile::fake()->image('a.jpg'),
                    UploadedFile::fake()->image('b.jpg'),
                ],
            ]);

        $response->assertRedirect();
        $morador = Morador::where('nome_social', 'Joao')->first();
        $this->assertCount(2, $morador->getMedia('fotos'));
    }

    public function test_update_morador_acumula_fotos_via_web(): void
    {
        Storage::fake(config('media-library.disk_name', 'public'));
        $morador = Morador::factory()->create();
        $morador->addMedia(UploadedFile::fake()->image('antiga.jpg'))->toMediaCollection('fotos');

        $response = $this->actingAs($this->user)
            ->put(route('moradores.update', $morador), [
                'nome_social' => $morador->nome_social,
                'fotografias' => [UploadedFile::fake()->image('nova.jpg')],
            ]);

        $response->assertRedirect();
        $this->assertCount(2, $morador->fresh()->getMedia('fotos'));
    }
}
