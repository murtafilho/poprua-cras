<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vistoria;
use App\Services\VistoriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class VistoriaServiceEstadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalizar_cancelar_reativar(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $service = app(VistoriaService::class);
        $vistoria = Vistoria::factory()->create(['finalizada' => false, 'cancelada' => false]);

        $service->finalizar($vistoria);
        $this->assertTrue($vistoria->fresh()->finalizada);
        $this->assertSame($user->id, $vistoria->fresh()->finalizada_por);

        $service->reativar($vistoria);
        $this->assertFalse($vistoria->fresh()->finalizada);
        $this->assertNull($vistoria->fresh()->finalizada_por);

        $service->cancelar($vistoria);
        $this->assertTrue($vistoria->fresh()->cancelada);
    }
}
