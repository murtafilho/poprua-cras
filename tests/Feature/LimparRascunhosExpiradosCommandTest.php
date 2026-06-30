<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Parametro;
use App\Models\User;
use App\Models\VistoriaRascunho;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimparRascunhosExpiradosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_remove_rascunhos_antigos(): void
    {
        Parametro::set('rascunho_dias_expiracao', '30');

        $user = User::factory()->create();

        $expirado = VistoriaRascunho::query()->create([
            'user_id' => $user->id,
            'context_key' => 'global',
            'payload' => [],
            'etapa_atual' => 0,
        ]);
        VistoriaRascunho::query()->whereKey($expirado->id)->update(['updated_at' => now()->subDays(31)]);

        $recente = VistoriaRascunho::query()->create([
            'user_id' => $user->id,
            'context_key' => 'ponto:1',
            'payload' => [],
            'etapa_atual' => 1,
        ]);
        VistoriaRascunho::query()->whereKey($recente->id)->update(['updated_at' => now()->subDays(5)]);

        $this->artisan('rascunhos:limpar')
            ->assertSuccessful()
            ->expectsOutputToContain('Removidos 1 rascunho');

        $this->assertEquals(1, VistoriaRascunho::query()->count());
    }

    public function test_dry_run_nao_exclui(): void
    {
        Parametro::set('rascunho_dias_expiracao', '7');

        $user = User::factory()->create();

        $expirado = VistoriaRascunho::query()->create([
            'user_id' => $user->id,
            'context_key' => 'global',
            'payload' => [],
            'etapa_atual' => 0,
        ]);
        VistoriaRascunho::query()->whereKey($expirado->id)->update(['updated_at' => now()->subDays(10)]);

        $this->artisan('rascunhos:limpar', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('1 rascunho(s) expirado');

        $this->assertEquals(1, VistoriaRascunho::query()->count());
    }
}
