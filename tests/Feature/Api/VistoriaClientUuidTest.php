<?php

namespace Tests\Feature\Api;

use App\Models\Vistoria;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VistoriaClientUuidTest extends TestCase
{
    use RefreshDatabase;

    public function test_vistoria_persiste_client_uuid(): void
    {
        $uuid = '11111111-1111-4111-8111-111111111111';
        $vistoria = Vistoria::factory()->create(['client_uuid' => $uuid]);

        $this->assertDatabaseHas('vistorias', [
            'id' => $vistoria->id,
            'client_uuid' => $uuid,
        ]);
    }

    public function test_client_uuid_tem_indice_unico(): void
    {
        $uuid = '22222222-2222-4222-8222-222222222222';
        Vistoria::factory()->create(['client_uuid' => $uuid]);

        $this->expectException(QueryException::class);
        Vistoria::factory()->create(['client_uuid' => $uuid]);
    }
}
