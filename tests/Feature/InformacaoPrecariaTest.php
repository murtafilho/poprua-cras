<?php

namespace Tests\Feature;

use App\Models\Ponto;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InformacaoPrecariaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tipo_abordagem')->insert(['tipo' => 'Monitoramento']);
        DB::table('resultados_acoes')->insert(['resultado' => 'Fenômeno persiste']);

        $this->user = User::factory()->create();
    }

    public function test_ponto_sem_vistoria_e_informacao_precaria(): void
    {
        $ponto = Ponto::factory()->create();

        $result = DB::selectOne("
            SELECT CASE WHEN v.data_abordagem IS NULL THEN true
                        WHEN v.data_abordagem < NOW() - INTERVAL '60 days' THEN true
                        ELSE false END as info_precaria
            FROM pontos p
            LEFT JOIN (SELECT ponto_id, MAX(id) as uid FROM vistorias WHERE deleted_at IS NULL GROUP BY ponto_id) uv ON uv.ponto_id = p.id
            LEFT JOIN vistorias v ON v.id = uv.uid
            WHERE p.id = ?
        ", [$ponto->id]);

        $this->assertTrue((bool) $result->info_precaria);
    }

    public function test_ponto_com_vistoria_recente_nao_e_precaria(): void
    {
        $ponto = Ponto::factory()->create();
        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'data_abordagem' => now()->subDays(30),
        ]);

        $result = DB::selectOne("
            SELECT CASE WHEN v.data_abordagem IS NULL THEN true
                        WHEN v.data_abordagem < NOW() - INTERVAL '60 days' THEN true
                        ELSE false END as info_precaria
            FROM pontos p
            LEFT JOIN (SELECT ponto_id, MAX(id) as uid FROM vistorias WHERE deleted_at IS NULL GROUP BY ponto_id) uv ON uv.ponto_id = p.id
            LEFT JOIN vistorias v ON v.id = uv.uid
            WHERE p.id = ?
        ", [$ponto->id]);

        $this->assertFalse((bool) $result->info_precaria);
    }

    public function test_ponto_com_vistoria_antiga_e_precaria(): void
    {
        $ponto = Ponto::factory()->create();
        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'data_abordagem' => now()->subDays(61),
        ]);

        $result = DB::selectOne("
            SELECT CASE WHEN v.data_abordagem IS NULL THEN true
                        WHEN v.data_abordagem < NOW() - INTERVAL '60 days' THEN true
                        ELSE false END as info_precaria
            FROM pontos p
            LEFT JOIN (SELECT ponto_id, MAX(id) as uid FROM vistorias WHERE deleted_at IS NULL GROUP BY ponto_id) uv ON uv.ponto_id = p.id
            LEFT JOIN vistorias v ON v.id = uv.uid
            WHERE p.id = ?
        ", [$ponto->id]);

        $this->assertTrue((bool) $result->info_precaria);
    }

    public function test_ponto_no_limite_60_dias_nao_e_precaria(): void
    {
        $ponto = Ponto::factory()->create();
        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'data_abordagem' => now()->subDays(59),
        ]);

        $result = DB::selectOne("
            SELECT CASE WHEN v.data_abordagem IS NULL THEN true
                        WHEN v.data_abordagem < NOW() - INTERVAL '60 days' THEN true
                        ELSE false END as info_precaria
            FROM pontos p
            LEFT JOIN (SELECT ponto_id, MAX(id) as uid FROM vistorias WHERE deleted_at IS NULL GROUP BY ponto_id) uv ON uv.ponto_id = p.id
            LEFT JOIN vistorias v ON v.id = uv.uid
            WHERE p.id = ?
        ", [$ponto->id]);

        $this->assertFalse((bool) $result->info_precaria);
    }

    public function test_nova_vistoria_remove_status_precaria(): void
    {
        $ponto = Ponto::factory()->create();
        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'data_abordagem' => now()->subDays(90),
        ]);

        $antes = DB::selectOne("
            SELECT CASE WHEN v.data_abordagem IS NULL THEN true
                        WHEN v.data_abordagem < NOW() - INTERVAL '60 days' THEN true
                        ELSE false END as info_precaria
            FROM pontos p
            LEFT JOIN (SELECT ponto_id, MAX(id) as uid FROM vistorias WHERE deleted_at IS NULL GROUP BY ponto_id) uv ON uv.ponto_id = p.id
            LEFT JOIN vistorias v ON v.id = uv.uid
            WHERE p.id = ?
        ", [$ponto->id]);

        $this->assertTrue((bool) $antes->info_precaria);

        Vistoria::factory()->create([
            'ponto_id' => $ponto->id,
            'user_id' => $this->user->id,
            'data_abordagem' => now(),
        ]);

        $depois = DB::selectOne("
            SELECT CASE WHEN v.data_abordagem IS NULL THEN true
                        WHEN v.data_abordagem < NOW() - INTERVAL '60 days' THEN true
                        ELSE false END as info_precaria
            FROM pontos p
            LEFT JOIN (SELECT ponto_id, MAX(id) as uid FROM vistorias WHERE deleted_at IS NULL GROUP BY ponto_id) uv ON uv.ponto_id = p.id
            LEFT JOIN vistorias v ON v.id = uv.uid
            WHERE p.id = ?
        ", [$ponto->id]);

        $this->assertFalse((bool) $depois->info_precaria);
    }

    public function test_filtro_info_precaria_na_listagem_de_pontos(): void
    {
        $this->actingAs($this->user)
            ->get(route('pontos.index', ['resultado' => 'info_precaria']))
            ->assertOk();
    }
}
