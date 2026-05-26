<?php

namespace Tests\Feature;

use App\Models\Parametro;
use App\Models\Ponto;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Teste E2E da jornada completa de criação de vistoria — cobre as 7 etapas
 * do form (Dados, Caract., Relatorio, Encam., Pessoas, Fotos, Revisar) e o
 * upload de foto separado via API.
 *
 * Não testa o JS (Alpine/stepper) — cobre apenas o que o backend processa
 * quando o form é submetido. Para testar o JS, usar Dusk (não instalado).
 */
class VistoriaJourneyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $tipoAbordagemId;

    private int $resultadoAcaoId;

    private int $tipoAbrigoId;

    /** @var array<int, int> */
    private array $encaminhamentoIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::create(['name' => 'participar de equipes vistoria']);
        Role::create(['name' => 'agente']);

        $this->user = User::factory()->create(['ativo' => true]);

        // Lookups exigidos pelas regras de validação
        $this->tipoAbordagemId = DB::table('tipo_abordagem')->insertGetId(['tipo' => 'Rotina']);
        $this->resultadoAcaoId = DB::table('resultados_acoes')->insertGetId(['resultado' => 'Orientação']);
        $this->tipoAbrigoId = DB::table('tipo_abrigo_desmontado')->insertGetId(['tipo_abrigo' => 'Barraca']);

        // 6 encaminhamentos (campos e1..e6 do form)
        foreach (['CRAS', 'CREAS', 'SAUDE', 'TRABALHO', 'JURIDICO', 'EDUCACAO'] as $nome) {
            $this->encaminhamentoIds[] = DB::table('encaminhamentos')->insertGetId(['encaminhamento' => $nome]);
        }
    }

    /**
     * Payload simulando agente preenchendo todas as etapas — usado como base
     * para o happy-path. Cada teste pode sobrescrever campos via $overrides.
     *
     * @return array<string, mixed>
     */
    private function fullJourneyPayload(array $overrides = []): array
    {
        $ponto = Ponto::factory()->create();

        return array_merge([
            // ---------- Aba 0: Dados ----------
            'ponto_id' => $ponto->id,
            'lat' => -19.9167,
            'lng' => -43.9345,
            'data_abordagem' => '2026-05-25T10:30',
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'quantidade_pessoas' => 4,
            'nomes_pessoas' => 'João, Maria, Pedro, Ana',
            'data_prevista_zeladoria' => '2026-06-01',
            'periodo_zeladoria' => 'manha',

            // ---------- Aba 1: Caract. (complexidade + abrigos) ----------
            'resistencia' => '1',
            'num_reduzido' => '0',
            'casal' => '1',
            'qtd_casais' => 1,
            'catador_reciclados' => '0',
            'fixacao_antiga' => '1',
            'excesso_objetos' => '1',
            'trafico_ilicitos' => '0',
            'crianca_adolescente' => '0',
            'idosos' => '1',
            'gestante' => '0',
            'lgbtqiapn' => '0',
            'cena_uso_caracterizada' => '0',
            'deficiente' => '0',
            'agrupamento_quimico' => '0',
            'saude_mental' => '1',
            'animais' => '1',
            'qtd_animais' => 2,
            'qtd_abrigos_provisorios' => 2,
            'abrigos_tipos' => [$this->tipoAbrigoId],

            // ---------- Aba 2: Relatorio (acoes + comunicado) ----------
            'resultado_acao_id' => $this->resultadoAcaoId,
            'tipo_abrigo_desmontado_id' => $this->tipoAbrigoId,
            'qtd_kg' => 30,
            'observacao' => 'Relatório descritivo da abordagem realizada.',
            'apreensao_fiscal' => '0',
            'auto_fiscalizacao_aplicado' => '0',
            'houve_lavratura' => '1',
            'tipo_protocolo' => 'normal',
            'houve_comunicado' => '1',
            'data_comunicado' => '2026-05-20',
            'conducao_forcas_seguranca' => '0',

            // ---------- Aba 3: Encam. (até 6 encaminhamentos) ----------
            'e1_id' => $this->encaminhamentoIds[0],
            'e2_id' => $this->encaminhamentoIds[1],
            'e3_id' => $this->encaminhamentoIds[2],
            'e4_id' => null,
            'e5_id' => null,
            'e6_id' => null,

            // ---------- Aba 4: Pessoas (não vinculadas, criação inline) ----------
            'moradores_presentes' => [],
            'novos_moradores' => [],

            // ---------- Aba 5: Fotos — upload é separado via /api/vistorias/fotos ----------
            // ---------- Aba 6: Revisar — confirma submit ----------
        ], $overrides);
    }

    public function test_guest_nao_acessa_create_e_e_redirecionado_para_login(): void
    {
        $this->get(route('vistorias.create', ['lat' => -19.9, 'lng' => -43.9]))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_autenticado_abre_form_de_create_com_parametros_do_mapa(): void
    {
        $this->actingAs($this->user)
            ->get(route('vistorias.create', [
                'lat' => -19.93650814,
                'lng' => -43.95808786,
                'endereco_tipo' => 'RUA',
                'endereco_logradouro' => 'ALMIRANTE TAMANDARE',
                'endereco_numero' => '733',
                'endereco_bairro' => 'Gutierrez',
                'endereco_regional' => 'OESTE',
            ]))
            ->assertOk();
    }

    public function test_jornada_completa_cria_vistoria_com_todos_os_campos(): void
    {
        $payload = $this->fullJourneyPayload();

        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), $payload);

        $response->assertSessionHasNoErrors();

        $vistoria = Vistoria::latest('id')->first();
        $this->assertNotNull($vistoria, 'Vistoria deveria ter sido criada');

        $response->assertRedirect(route('vistorias.show', $vistoria));

        // Aba 0: Dados
        $this->assertEquals($this->user->id, $vistoria->user_id);
        $this->assertEquals($this->tipoAbordagemId, $vistoria->tipo_abordagem_id);
        $this->assertEquals(4, $vistoria->quantidade_pessoas);
        $this->assertNotNull($vistoria->data_prevista_zeladoria);
        $this->assertEquals('manha', $vistoria->periodo_zeladoria);

        // Aba 1: Caract.
        $this->assertTrue((bool) $vistoria->resistencia);
        $this->assertTrue((bool) $vistoria->casal);
        $this->assertEquals(1, $vistoria->qtd_casais);
        $this->assertTrue((bool) $vistoria->animais);
        $this->assertEquals(2, $vistoria->qtd_animais);
        $this->assertEquals(2, $vistoria->qtd_abrigos_provisorios);

        // Aba 2: Relatorio
        $this->assertEquals($this->resultadoAcaoId, $vistoria->resultado_acao_id);
        $this->assertEquals(30, $vistoria->qtd_kg);
        $this->assertEquals('Relatório descritivo da abordagem realizada.', $vistoria->observacao);
        $this->assertTrue((bool) $vistoria->houve_lavratura);
        $this->assertEquals('normal', $vistoria->tipo_protocolo);
        $this->assertTrue((bool) $vistoria->houve_comunicado);
        $this->assertNotNull($vistoria->data_comunicado);

        // Aba 3: Encam.
        $this->assertEquals($this->encaminhamentoIds[0], $vistoria->e1_id);
        $this->assertEquals($this->encaminhamentoIds[1], $vistoria->e2_id);
        $this->assertEquals($this->encaminhamentoIds[2], $vistoria->e3_id);
        $this->assertNull($vistoria->e4_id);

        // Workflow zeladoria — defaults de novo registro
        $this->assertFalse((bool) $vistoria->finalizada);
        $this->assertFalse((bool) $vistoria->cancelada);
    }

    public function test_jornada_falha_sem_campos_obrigatorios_de_dados(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), [
                // Sem lat/lng/data_abordagem/tipo_abordagem_id/resultado_acao_id
            ]);

        $response->assertSessionHasErrors(['lat', 'lng', 'data_abordagem', 'tipo_abordagem_id', 'resultado_acao_id']);
        $this->assertEquals(0, Vistoria::count());
    }

    public function test_jornada_com_comunicado_negativo_descarta_data_comunicado(): void
    {
        $payload = $this->fullJourneyPayload([
            'houve_comunicado' => '0',
            'data_comunicado' => '2026-05-20',
        ]);

        $this->actingAs($this->user)->post(route('vistorias.store'), $payload);

        $vistoria = Vistoria::latest('id')->first();
        $this->assertFalse((bool) $vistoria->houve_comunicado);
        $this->assertNull($vistoria->data_comunicado);
    }

    public function test_jornada_sem_lavratura_descarta_tipo_protocolo(): void
    {
        $payload = $this->fullJourneyPayload([
            'houve_lavratura' => '0',
            'tipo_protocolo' => 'chuva',
        ]);

        $this->actingAs($this->user)->post(route('vistorias.store'), $payload);

        $vistoria = Vistoria::latest('id')->first();
        $this->assertFalse((bool) $vistoria->houve_lavratura);
        $this->assertNull($vistoria->tipo_protocolo);
    }

    public function test_exigir_comunicado_desligado_permite_agendar_sem_comunicado(): void
    {
        // Default: parametro desligado — comportamento legado.
        Parametro::set('exigir_comunicado', '0');

        $payload = $this->fullJourneyPayload([
            'houve_comunicado' => '0',
            'data_comunicado' => null,
            'data_prevista_zeladoria' => '2026-06-15',
            'periodo_zeladoria' => 'tarde',
        ]);

        $response = $this->actingAs($this->user)->post(route('vistorias.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, Vistoria::count());
    }

    public function test_exigir_comunicado_ligado_bloqueia_agendar_zeladoria_sem_comunicado(): void
    {
        Parametro::set('exigir_comunicado', '1');

        $payload = $this->fullJourneyPayload([
            'houve_comunicado' => '0',
            'data_comunicado' => null,
            'data_prevista_zeladoria' => '2026-06-15',
            'periodo_zeladoria' => 'tarde',
        ]);

        $response = $this->actingAs($this->user)->post(route('vistorias.store'), $payload);

        $response->assertSessionHasErrors(['houve_comunicado']);
        $this->assertEquals(0, Vistoria::count());
    }

    public function test_exigir_comunicado_ligado_permite_quando_comunicado_marcado(): void
    {
        Parametro::set('exigir_comunicado', '1');

        $payload = $this->fullJourneyPayload([
            'houve_comunicado' => '1',
            'data_comunicado' => '2026-05-20',
            'data_prevista_zeladoria' => '2026-06-15',
            'periodo_zeladoria' => 'tarde',
        ]);

        $response = $this->actingAs($this->user)->post(route('vistorias.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, Vistoria::count());
    }

    public function test_exigir_comunicado_ligado_permite_quando_nao_agenda_zeladoria(): void
    {
        Parametro::set('exigir_comunicado', '1');

        $payload = $this->fullJourneyPayload([
            'houve_comunicado' => '0',
            'data_comunicado' => null,
            'data_prevista_zeladoria' => null,
            'periodo_zeladoria' => null,
        ]);

        $response = $this->actingAs($this->user)->post(route('vistorias.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, Vistoria::count());
    }

    public function test_aba_fotos_upload_anexa_imagem_via_api(): void
    {
        Storage::fake('public');

        // 1. Cria a vistoria
        $this->actingAs($this->user)
            ->post(route('vistorias.store'), $this->fullJourneyPayload());
        $vistoria = Vistoria::latest('id')->first();

        // 2. Upload da foto (etapa 5, separada via API)
        $foto = UploadedFile::fake()->image('vistoria.jpg', 1200, 800);

        $response = $this->actingAs($this->user)
            ->postJson('/api/vistorias/fotos', [
                'vistoria_id' => $vistoria->id,
                'foto' => $foto,
            ]);

        $response->assertStatus(201);

        // 3. Verifica que a media foi associada à vistoria
        $vistoria->refresh();
        $this->assertGreaterThan(0, $vistoria->getMedia('fotos')->count(),
            'Foto deveria ter sido associada à vistoria via MediaLibrary');
    }

    public function test_foto_upload_com_legenda_inicial_persiste_a_legenda(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user)->post(route('vistorias.store'), $this->fullJourneyPayload());
        $vistoria = Vistoria::latest('id')->first();

        $resp = $this->actingAs($this->user)->postJson('/api/vistorias/fotos', [
            'vistoria_id' => $vistoria->id,
            'foto' => UploadedFile::fake()->image('foto.jpg'),
            'legenda' => 'Barraca debaixo do viaduto',
        ]);

        $resp->assertStatus(201);
        $resp->assertJsonFragment(['legenda' => 'Barraca debaixo do viaduto']);

        $media = $vistoria->getMedia('fotos')->first();
        $this->assertEquals('Barraca debaixo do viaduto', $media->getCustomProperty('legenda'));
    }

    public function test_patch_legenda_atualiza_custom_property(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user)->post(route('vistorias.store'), $this->fullJourneyPayload());
        $vistoria = Vistoria::latest('id')->first();

        $this->actingAs($this->user)->postJson('/api/vistorias/fotos', [
            'vistoria_id' => $vistoria->id,
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ]);
        $media = $vistoria->getMedia('fotos')->first();

        // Define legenda via PATCH
        $resp = $this->actingAs($this->user)->patchJson(
            "/api/vistorias/{$vistoria->id}/fotos/{$media->id}/legenda",
            ['legenda' => 'Pessoa em situação de rua — homem adulto']
        );

        $resp->assertOk()->assertJsonFragment(['legenda' => 'Pessoa em situação de rua — homem adulto']);
        $this->assertEquals(
            'Pessoa em situação de rua — homem adulto',
            $vistoria->fresh()->getMedia('fotos')->first()->getCustomProperty('legenda')
        );

        // Sobrescreve com texto vazio (limpar)
        $resp = $this->actingAs($this->user)->patchJson(
            "/api/vistorias/{$vistoria->id}/fotos/{$media->id}/legenda",
            ['legenda' => '']
        );
        $resp->assertOk();
        $this->assertEquals('', $vistoria->fresh()->getMedia('fotos')->first()->getCustomProperty('legenda'));
    }

    public function test_legenda_acima_de_255_caracteres_e_rejeitada(): void
    {
        Storage::fake('public');
        $this->actingAs($this->user)->post(route('vistorias.store'), $this->fullJourneyPayload());
        $vistoria = Vistoria::latest('id')->first();
        $this->actingAs($this->user)->postJson('/api/vistorias/fotos', [
            'vistoria_id' => $vistoria->id,
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ]);
        $media = $vistoria->getMedia('fotos')->first();

        $resp = $this->actingAs($this->user)->patchJson(
            "/api/vistorias/{$vistoria->id}/fotos/{$media->id}/legenda",
            ['legenda' => str_repeat('x', 256)]
        );

        $resp->assertStatus(422)->assertJsonValidationErrors('legenda');
    }
}
