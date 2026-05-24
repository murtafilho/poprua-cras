<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClientLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_logs_requer_autenticacao(): void
    {
        $this->postJson('/api/client-logs', [
            'logs' => [
                ['level' => 'info', 'message' => 'teste'],
            ],
        ])->assertUnauthorized();
    }

    public function test_usuario_autenticado_envia_logs_com_sucesso(): void
    {
        Log::shouldReceive('channel')->with('client')->andReturnSelf();
        Log::shouldReceive('info')->once();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/client-logs', [
                'logs' => [
                    [
                        'level' => 'info',
                        'message' => 'Página carregada com sucesso',
                        'context' => ['page' => '/mapa'],
                        'timestamp' => 1716300000,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['received' => 1]);
    }

    public function test_validacao_rejeita_dados_invalidos(): void
    {
        $user = User::factory()->create();

        // Sem campo logs
        $this->actingAs($user)
            ->postJson('/api/client-logs', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logs']);

        // Level inválido
        $this->actingAs($user)
            ->postJson('/api/client-logs', [
                'logs' => [
                    ['level' => 'critical', 'message' => 'teste'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logs.0.level']);

        // Mensagem ausente
        $this->actingAs($user)
            ->postJson('/api/client-logs', [
                'logs' => [
                    ['level' => 'error'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logs.0.message']);
    }

    public function test_validacao_limita_maximo_50_logs(): void
    {
        $user = User::factory()->create();

        $logs = array_fill(0, 51, ['level' => 'info', 'message' => 'teste']);

        $this->actingAs($user)
            ->postJson('/api/client-logs', ['logs' => $logs])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logs']);
    }
}
