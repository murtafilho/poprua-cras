<?php

namespace Tests\Feature\Api;

use App\Models\Morador;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VistoriaStoreApiTest extends TestCase
{
    use RefreshDatabase;

    private int $tipoAbordagemId;

    private int $resultadoAcaoId;

    protected function setUp(): void
    {
        parent::setUp();

        // Registros de lookup necessários para validar tipo_abordagem_id e
        // resultado_acao_id (tabelas sem seeder padrão em RefreshDatabase).
        $this->tipoAbordagemId = DB::table('tipo_abordagem')->insertGetId(['tipo' => 'Rotina']);
        $this->resultadoAcaoId = DB::table('resultados_acoes')->insertGetId(['resultado' => 'Orientação']);
    }

    /** @return array<string, mixed> */
    private function payloadValido(string $uuid): array
    {
        return [
            'client_uuid' => $uuid,
            'lat' => -19.9227,
            'lng' => -43.9451,
            'data_abordagem' => now()->format('Y-m-d\TH:i'),
            'tipo_abordagem_id' => $this->tipoAbordagemId,
            'resultado_acao_id' => $this->resultadoAcaoId,
        ];
    }

    public function test_cria_vistoria_via_json_e_retorna_id_e_redirect(): void
    {
        $user = User::factory()->create();
        $uuid = '33333333-3333-4333-8333-333333333333';

        $resp = $this->actingAs($user)
            ->postJson('/api/vistorias', $this->payloadValido($uuid));

        $resp->assertOk()
            ->assertJsonStructure(['id', 'redirect_url', 'client_uuid']);
        $this->assertDatabaseHas('vistorias', [
            'id' => $resp->json('id'),
            'client_uuid' => $uuid,
            'user_id' => $user->id,
        ]);
    }

    public function test_reenvio_com_mesmo_client_uuid_nao_duplica(): void
    {
        $user = User::factory()->create();
        $uuid = '44444444-4444-4444-8444-444444444444';
        $payload = $this->payloadValido($uuid);

        $r1 = $this->actingAs($user)->postJson('/api/vistorias', $payload);
        $r2 = $this->actingAs($user)->postJson('/api/vistorias', $payload);

        $r1->assertOk();
        $r2->assertOk();
        $this->assertSame($r1->json('id'), $r2->json('id'));
        $this->assertSame(1, Vistoria::where('client_uuid', $uuid)->count());
    }

    public function test_client_uuid_de_outro_usuario_retorna_conflito_em_vez_de_500(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $uuid = '66666666-6666-4666-8666-666666666666';

        $rA = $this->actingAs($userA)->postJson('/api/vistorias', $this->payloadValido($uuid));
        $rA->assertOk();

        $rB = $this->actingAs($userB)->postJson('/api/vistorias', $this->payloadValido($uuid));

        $rB->assertStatus(409);
        $this->assertArrayNotHasKey('id', $rB->json());
        $this->assertSame(1, Vistoria::where('client_uuid', $uuid)->count());
    }

    public function test_valida_campos_obrigatorios(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/vistorias', ['client_uuid' => '55555555-5555-4555-8555-555555555555'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng', 'data_abordagem', 'tipo_abordagem_id', 'resultado_acao_id']);
    }

    public function test_exige_autenticacao(): void
    {
        $this->postJson('/api/vistorias', [])->assertStatus(401);
    }

    /**
     * Reproduz o SHAPE exato que buildApiPayloadFromForm() (vistoria-form.js)
     * monta a partir do FormData do formulário nativo: novos_moradores como
     * array de objetos (novos_moradores[0][nome_social] etc.), participantes
     * e moradores_presentes como arrays simples (participantes[], value=id),
     * e campos de complexidade booleanos chegando como STRING de form
     * ('1'/'0') — só um deles como bool nativo, pra cobrir os dois formatos
     * que o request aceita. Isso trava o contrato cliente↔servidor da fila
     * offline: se o formato do payload divergir do esperado pela API, este
     * teste quebra antes de virar perda silenciosa de dado em produção.
     */
    public function test_cria_vistoria_com_payload_aninhado_do_formulario_offline(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'participar de equipes vistoria', 'guard_name' => 'web']);

        $user = User::factory()->create(['ativo' => true]);
        $participante = User::factory()->create(['ativo' => true]);
        $participante->givePermissionTo('participar de equipes vistoria');

        $moradorExistente = Morador::factory()->create(['nome_social' => 'Pessoa Já Cadastrada']);

        $uuid = '77777777-7777-4777-8777-777777777777';

        $payload = array_merge($this->payloadValido($uuid), [
            'novos_moradores' => [
                [
                    'nome_social' => 'Fulano da Silva',
                    'apelido' => 'Fulaninho',
                    'genero' => 'Homem cisgênero',
                    'documento' => '',
                    'contato' => '',
                    'observacoes' => '',
                ],
            ],
            'participantes' => [$participante->id],
            'moradores_presentes' => [$moradorExistente->id],
            // Campos boolean como o form realmente envia: strings '1'/'0'
            // (FormData.entries() nunca produz bool nativo) — 'idosos' como
            // bool nativo também, pra garantir que o request tolera ambos.
            'resistencia' => '1',
            'crianca_adolescente' => '0',
            'idosos' => true,
        ]);

        $resp = $this->actingAs($user)->postJson('/api/vistorias', $payload);

        $resp->assertOk()->assertJsonStructure(['id', 'redirect_url', 'client_uuid']);

        $vistoriaId = $resp->json('id');

        $this->assertDatabaseHas('vistorias', [
            'id' => $vistoriaId,
            'client_uuid' => $uuid,
            'resistencia' => true,
            'crianca_adolescente' => false,
            'idosos' => true,
        ]);

        $this->assertDatabaseHas('moradores', [
            'nome_social' => 'Fulano da Silva',
            'apelido' => 'Fulaninho',
        ]);

        $this->assertDatabaseHas('vistoria_participantes', [
            'vistoria_id' => $vistoriaId,
            'user_id' => $participante->id,
        ]);

        // Morador pré-existente marcado como "presente" teve entrada
        // registrada no ponto recém-criado da vistoria (atualizarPresencaVistoria).
        $vistoria = Vistoria::findOrFail($vistoriaId);
        $moradorExistente->refresh();
        $this->assertSame($vistoria->ponto_id, $moradorExistente->ponto_atual_id);
    }
}
