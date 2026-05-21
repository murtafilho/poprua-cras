<?php

namespace Tests\Feature;

use App\Models\Ponto;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VistoriaFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $tipoAbordagemId;

    private int $resultadoAcaoId;

    private int $tipoAbrigoId;

    private int $encaminhamentoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Criar registros nas tabelas de lookup
        $this->tipoAbordagemId = DB::table('tipo_abordagem')->insertGetId(['tipo' => 'Rotina']);
        $this->resultadoAcaoId = DB::table('resultados_acoes')->insertGetId(['resultado' => 'Orientação']);
        $this->tipoAbrigoId = DB::table('tipo_abrigo_desmontado')->insertGetId(['tipo_abrigo' => 'Barraca']);
        $this->encaminhamentoId = DB::table('encaminhamentos')->insertGetId(['encaminhamento' => 'CRAS']);
    }

    private function validStoreData(array $overrides = []): array
    {
        $ponto = Ponto::factory()->create();

        return array_merge([
            'ponto_id' => $ponto->id,
            'lat' => $ponto->lat ?? -19.9167,
            'lng' => $ponto->lng ?? -43.9345,
            'data_abordagem' => '2026-03-01T10:00',
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
            'quantidade_pessoas' => 3,
            'nomes_pessoas' => 'João, Maria',
            'observacao' => 'Teste de observação',
        ], $overrides);
    }

    private function validUpdateData(array $overrides = []): array
    {
        return array_merge([
            'data_abordagem' => '2026-03-01T10:00',
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
            'quantidade_pessoas' => 5,
            'nomes_pessoas' => 'Pedro, Ana',
            'observacao' => 'Observação atualizada',
        ], $overrides);
    }

    // ========================================
    // Autenticação
    // ========================================

    public function test_guest_cannot_access_vistorias_index(): void
    {
        $response = $this->get(route('vistorias.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_vistorias_create(): void
    {
        $response = $this->get(route('vistorias.create'));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_store_vistoria(): void
    {
        $response = $this->post(route('vistorias.store'), $this->validStoreData());

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_update_vistoria(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $response = $this->put(route('vistorias.update', $vistoria), $this->validUpdateData());

        $response->assertRedirect(route('login'));
    }

    // ========================================
    // Listagem (index)
    // ========================================

    public function test_authenticated_user_can_access_vistorias_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('vistorias.index'));

        $response->assertOk();
    }

    // ========================================
    // Formulário de criação
    // ========================================

    public function test_authenticated_user_can_access_vistorias_create(): void
    {
        $response = $this->actingAs($this->user)->get(route('vistorias.create'));

        $response->assertOk();
    }

    public function test_create_page_accepts_lat_lng_query_params(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('vistorias.create', ['lat' => -19.9167, 'lng' => -43.9345]));

        $response->assertOk();
    }

    // ========================================
    // Store - Sucesso
    // ========================================

    public function test_store_vistoria_with_valid_data_redirects_with_success(): void
    {
        $data = $this->validStoreData();

        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $response->assertRedirectContains('/vistorias/');
        $response->assertSessionHas('success', 'Zeladoria registrada com sucesso!');
        $response->assertSessionHasNoErrors();
    }

    public function test_store_vistoria_creates_record_in_database(): void
    {
        $data = $this->validStoreData();

        $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $this->assertDatabaseHas('vistorias', [
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
            'quantidade_pessoas' => 3,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_vistoria_assigns_authenticated_user(): void
    {
        $data = $this->validStoreData();

        $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $vistoria = Vistoria::latest('id')->first();
        $this->assertEquals($this->user->id, $vistoria->user_id);
    }

    public function test_store_vistoria_with_all_complexity_fields(): void
    {
        $data = $this->validStoreData([
            'resistencia' => '1',
            'num_reduzido' => '1',
            'casal' => '1',
            'qtd_casais' => 2,
            'catador_reciclados' => '1',
            'fixacao_antiga' => '1',
            'excesso_objetos' => '1',
            'trafico_ilicitos' => '1',
            'crianca_adolescente' => '1',
            'idosos' => '1',
            'gestante' => '1',
            'lgbtqiapn' => '1',
            'cena_uso_caracterizada' => '1',
            'deficiente' => '1',
            'agrupamento_quimico' => '1',
            'saude_mental' => '1',
            'animais' => '1',
            'qtd_animais' => 3,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $response->assertRedirectContains('/vistorias/');
        $response->assertSessionHasNoErrors();

        $vistoria = Vistoria::latest('id')->first();
        $this->assertEquals(1, $vistoria->resistencia);
        $this->assertEquals(1, $vistoria->casal);
        $this->assertEquals(2, $vistoria->qtd_casais);
        $this->assertEquals(1, $vistoria->animais);
        $this->assertEquals(3, $vistoria->qtd_animais);
    }

    public function test_store_vistoria_with_encaminhamentos(): void
    {
        $enc2 = DB::table('encaminhamentos')->insertGetId(['encaminhamento' => 'CREAS']);

        $data = $this->validStoreData([
            'e1_id' => $this->encaminhamentoId,
            'e2_id' => $enc2,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasNoErrors();

        $vistoria = Vistoria::latest('id')->first();
        $this->assertEquals($this->encaminhamentoId, $vistoria->e1_id);
        $this->assertEquals($enc2, $vistoria->e2_id);
    }

    public function test_store_vistoria_with_abrigos_tipos(): void
    {
        $tipo2 = DB::table('tipo_abrigo_desmontado')->insertGetId(['tipo_abrigo' => 'Papelão']);

        $data = $this->validStoreData([
            'qtd_abrigos_provisorios' => 2,
            'abrigos_tipos' => [$this->tipoAbrigoId, $tipo2],
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasNoErrors();

        $vistoria = Vistoria::latest('id')->first();
        $this->assertEquals(2, $vistoria->qtd_abrigos_provisorios);
        $this->assertIsArray($vistoria->abrigos_tipos);
        $this->assertCount(2, $vistoria->abrigos_tipos);
    }

    public function test_store_vistoria_with_fiscalizacao_fields(): void
    {
        $data = $this->validStoreData([
            'conducao_forcas_seguranca' => '1',
            'conducao_forcas_observacao' => 'Observação teste',
            'apreensao_fiscal' => '1',
            'auto_fiscalizacao_aplicado' => '1',
            'auto_fiscalizacao_numero' => 'AF-12345',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasNoErrors();

        $vistoria = Vistoria::latest('id')->first();
        $this->assertTrue((bool) $vistoria->conducao_forcas_seguranca);
        $this->assertEquals('Observação teste', $vistoria->conducao_forcas_observacao);
        $this->assertTrue((bool) $vistoria->auto_fiscalizacao_aplicado);
        $this->assertEquals('AF-12345', $vistoria->auto_fiscalizacao_numero);
    }

    public function test_store_vistoria_clears_conducao_observacao_when_seguranca_is_zero(): void
    {
        $data = $this->validStoreData([
            'conducao_forcas_seguranca' => '0',
            'conducao_forcas_observacao' => 'Isto deve ser ignorado',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasNoErrors();

        $vistoria = Vistoria::latest('id')->first();
        $this->assertFalse((bool) $vistoria->conducao_forcas_seguranca);
        $this->assertNull($vistoria->conducao_forcas_observacao);
    }

    public function test_store_vistoria_clears_auto_fiscalizacao_numero_when_not_applied(): void
    {
        $data = $this->validStoreData([
            'auto_fiscalizacao_aplicado' => '0',
            'auto_fiscalizacao_numero' => 'AF-99999',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasNoErrors();

        $vistoria = Vistoria::latest('id')->first();
        $this->assertFalse((bool) $vistoria->auto_fiscalizacao_aplicado);
        $this->assertNull($vistoria->auto_fiscalizacao_numero);
    }

    public function test_store_vistoria_qtd_casais_zero_when_casal_false(): void
    {
        $data = $this->validStoreData([
            'casal' => '0',
            'qtd_casais' => 5,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasNoErrors();

        $vistoria = Vistoria::latest('id')->first();
        $this->assertEquals(0, $vistoria->qtd_casais);
    }

    public function test_store_vistoria_qtd_animais_zero_when_animais_false(): void
    {
        $data = $this->validStoreData([
            'animais' => '0',
            'qtd_animais' => 10,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasNoErrors();

        $vistoria = Vistoria::latest('id')->first();
        $this->assertEquals(0, $vistoria->qtd_animais);
    }

    public function test_store_vistoria_with_novos_moradores(): void
    {
        $data = $this->validStoreData([
            'novos_moradores' => [
                [
                    'nome_social' => 'Carlos Silva',
                    'apelido' => 'Carlão',
                    'genero' => 'Homem cisgênero',
                    'documento' => '123.456.789-00',
                    'contato' => '31999999999',
                    'observacoes' => 'Teste morador',
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('moradores', [
            'nome_social' => 'Carlos Silva',
            'apelido' => 'Carlão',
        ]);
    }

    public function test_store_vistoria_with_minimal_data(): void
    {
        $ponto = Ponto::factory()->create();

        $data = [
            'ponto_id' => $ponto->id,
            'lat' => $ponto->lat,
            'lng' => $ponto->lng,
            'data_abordagem' => '2026-03-01T08:00',
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('vistorias.store'), $data);

        $response->assertRedirectContains('/vistorias/');
        $response->assertSessionHas('success');
        $response->assertSessionHasNoErrors();
    }

    // ========================================
    // Store - Validação (erros)
    // ========================================

    public function test_store_vistoria_requires_lat(): void
    {
        $data = $this->validStoreData();
        unset($data['lat']);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('lat');
    }

    public function test_store_vistoria_requires_lng(): void
    {
        $data = $this->validStoreData();
        unset($data['lng']);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('lng');
    }

    public function test_store_vistoria_requires_data_abordagem(): void
    {
        $data = $this->validStoreData();
        unset($data['data_abordagem']);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('data_abordagem');
    }

    public function test_store_vistoria_requires_tipo_abordagem_id(): void
    {
        $data = $this->validStoreData();
        unset($data['tipo_abordagem_id']);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('tipo_abordagem_id');
    }

    public function test_store_vistoria_requires_resultado_acao_id(): void
    {
        $data = $this->validStoreData();
        unset($data['resultado_acao_id']);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('resultado_acao_id');
    }

    public function test_store_vistoria_validates_data_abordagem_format(): void
    {
        $data = $this->validStoreData([
            'data_abordagem' => '01/03/2026 10:00',
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('data_abordagem');
    }

    public function test_store_vistoria_validates_tipo_abordagem_exists(): void
    {
        $data = $this->validStoreData([
            'tipo_abordagem_id' => 99999,
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('tipo_abordagem_id');
    }

    public function test_store_vistoria_validates_resultado_acao_exists(): void
    {
        $data = $this->validStoreData([
            'resultado_acao_id' => 99999,
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('resultado_acao_id');
    }

    public function test_store_vistoria_validates_lat_is_numeric(): void
    {
        $data = $this->validStoreData([
            'lat' => 'abc',
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('lat');
    }

    public function test_store_vistoria_validates_lng_is_numeric(): void
    {
        $data = $this->validStoreData([
            'lng' => 'abc',
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('lng');
    }

    public function test_store_vistoria_validates_quantidade_pessoas_min_zero(): void
    {
        $data = $this->validStoreData([
            'quantidade_pessoas' => -1,
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('quantidade_pessoas');
    }

    public function test_store_vistoria_validates_quantidade_pessoas_is_integer(): void
    {
        $data = $this->validStoreData([
            'quantidade_pessoas' => 'abc',
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('quantidade_pessoas');
    }

    public function test_store_vistoria_validates_qtd_kg_min_zero(): void
    {
        $data = $this->validStoreData([
            'qtd_kg' => -5,
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('qtd_kg');
    }

    public function test_store_vistoria_validates_encaminhamento_exists(): void
    {
        $data = $this->validStoreData([
            'e1_id' => 99999,
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('e1_id');
    }

    public function test_store_vistoria_validates_ponto_id_exists(): void
    {
        $data = $this->validStoreData([
            'ponto_id' => 99999,
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('ponto_id');
    }

    public function test_store_vistoria_validates_abrigos_tipos_exist(): void
    {
        $data = $this->validStoreData([
            'abrigos_tipos' => [99999],
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('abrigos_tipos.0');
    }

    public function test_store_vistoria_validates_conducao_forcas_seguranca_in_values(): void
    {
        $data = $this->validStoreData([
            'conducao_forcas_seguranca' => '2',
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('conducao_forcas_seguranca');
    }

    public function test_store_vistoria_validates_auto_fiscalizacao_numero_max_length(): void
    {
        $data = $this->validStoreData([
            'auto_fiscalizacao_numero' => str_repeat('A', 101),
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('auto_fiscalizacao_numero');
    }

    public function test_store_vistoria_validates_novos_moradores_nome_social_required(): void
    {
        $data = $this->validStoreData([
            'novos_moradores' => [
                [
                    'nome_social' => '',
                    'apelido' => 'Teste',
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('novos_moradores.0.nome_social');
    }

    public function test_store_vistoria_validates_moradores_presentes_exist(): void
    {
        $data = $this->validStoreData([
            'moradores_presentes' => [99999],
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.create'))
            ->post(route('vistorias.store'), $data);

        $response->assertSessionHasErrors('moradores_presentes.0');
    }

    // ========================================
    // Show
    // ========================================

    public function test_authenticated_user_can_view_vistoria(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('vistorias.show', $vistoria));

        $response->assertOk();
    }

    // ========================================
    // Edit
    // ========================================

    public function test_authenticated_user_can_access_edit_form(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('vistorias.edit', $vistoria));

        $response->assertOk();
    }

    // ========================================
    // Update - Sucesso
    // ========================================

    public function test_update_vistoria_with_valid_data_redirects_with_success(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('vistorias.update', $vistoria), $this->validUpdateData());

        $response->assertRedirectContains('/vistorias/');
        $response->assertSessionHas('success', 'Zeladoria atualizada com sucesso!');
        $response->assertSessionHasNoErrors();
    }

    public function test_update_vistoria_persists_changes(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
            'quantidade_pessoas' => 1,
        ]);

        $this->actingAs($this->user)
            ->put(route('vistorias.update', $vistoria), $this->validUpdateData([
                'quantidade_pessoas' => 10,
                'nomes_pessoas' => 'Atualizado',
            ]));

        $vistoria->refresh();
        $this->assertEquals(10, $vistoria->quantidade_pessoas);
        $this->assertEquals('Atualizado', $vistoria->nomes_pessoas);
    }

    public function test_update_vistoria_with_complexity_fields(): void
    {
        $vistoria = Vistoria::factory()->comComplexidadeBaixa()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('vistorias.update', $vistoria), $this->validUpdateData([
                'resistencia' => '1',
                'trafico_ilicitos' => '1',
                'saude_mental' => '1',
            ]));

        $response->assertSessionHasNoErrors();

        $vistoria->refresh();
        $this->assertEquals(1, $vistoria->resistencia);
        $this->assertEquals(1, $vistoria->trafico_ilicitos);
        $this->assertEquals(1, $vistoria->saude_mental);
    }

    public function test_update_vistoria_with_encaminhamentos(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('vistorias.update', $vistoria), $this->validUpdateData([
                'e1_id' => $this->encaminhamentoId,
            ]));

        $response->assertSessionHasNoErrors();

        $vistoria->refresh();
        $this->assertEquals($this->encaminhamentoId, $vistoria->e1_id);
    }

    // ========================================
    // Update - Validação (erros)
    // ========================================

    public function test_update_vistoria_requires_data_abordagem(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $data = $this->validUpdateData();
        unset($data['data_abordagem']);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.edit', $vistoria))
            ->put(route('vistorias.update', $vistoria), $data);

        $response->assertSessionHasErrors('data_abordagem');
    }

    public function test_update_vistoria_requires_tipo_abordagem_id(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $data = $this->validUpdateData();
        unset($data['tipo_abordagem_id']);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.edit', $vistoria))
            ->put(route('vistorias.update', $vistoria), $data);

        $response->assertSessionHasErrors('tipo_abordagem_id');
    }

    public function test_update_vistoria_requires_resultado_acao_id(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $data = $this->validUpdateData();
        unset($data['resultado_acao_id']);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.edit', $vistoria))
            ->put(route('vistorias.update', $vistoria), $data);

        $response->assertSessionHasErrors('resultado_acao_id');
    }

    public function test_update_vistoria_validates_data_abordagem_format(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.edit', $vistoria))
            ->put(route('vistorias.update', $vistoria), $this->validUpdateData([
                'data_abordagem' => '2026-13-45',
            ]));

        $response->assertSessionHasErrors('data_abordagem');
    }

    public function test_update_vistoria_validates_tipo_abordagem_exists(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.edit', $vistoria))
            ->put(route('vistorias.update', $vistoria), $this->validUpdateData([
                'tipo_abordagem_id' => 99999,
            ]));

        $response->assertSessionHasErrors('tipo_abordagem_id');
    }

    public function test_update_vistoria_validates_encaminhamento_exists(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('vistorias.edit', $vistoria))
            ->put(route('vistorias.update', $vistoria), $this->validUpdateData([
                'e3_id' => 99999,
            ]));

        $response->assertSessionHasErrors('e3_id');
    }

    // ========================================
    // Report
    // ========================================

    public function test_authenticated_user_can_access_report(): void
    {
        $vistoria = Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('vistorias.report', $vistoria));

        $response->assertOk();
    }

    // ========================================
    // createForPonto
    // ========================================

    public function test_create_for_ponto_redirects_with_coordinates(): void
    {
        $ponto = Ponto::factory()->georreferenciado()->create();

        $response = $this->actingAs($this->user)
            ->get(route('pontos.vistorias.create', $ponto));

        $response->assertRedirect();
        $this->assertStringContains('lat=', $response->headers->get('Location'));
    }

    public function test_create_for_ponto_without_coordinates_redirects_to_create(): void
    {
        $ponto = Ponto::factory()->naoGeorreferenciado()->create();

        $response = $this->actingAs($this->user)
            ->get(route('pontos.vistorias.create', $ponto));

        $response->assertRedirect(route('vistorias.create'));
    }

    // ========================================
    // Filtros na listagem
    // ========================================

    public function test_index_filters_by_resultado_acao(): void
    {
        $resultado2 = DB::table('resultados_acoes')->insertGetId(['resultado' => 'Remoção']);

        Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ]);
        Vistoria::factory()->create([
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $resultado2,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('vistorias.index', ['resultado' => $this->resultadoAcaoId]));

        $response->assertOk();
    }

    public function test_index_filters_by_date_range(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('vistorias.index', [
                'data_inicio' => '2026-01-01',
                'data_fim' => '2026-03-31',
            ]));

        $response->assertOk();
    }

    // ========================================
    // Helper assertion
    // ========================================

    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
