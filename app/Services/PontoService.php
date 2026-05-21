<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ponto;

class PontoService
{
    public function __construct(private EnderecoService $enderecoService) {}

    /** @return array{id: int, created: bool} */
    public function findOrCreateFromCoordinates(float $lat, float $lng, ?string $complemento = null): array
    {
        $pontoProximo = Ponto::query()
            ->whereRaw('geom && ST_Expand(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geometry, 0.001)', [$lng, $lat])
            ->whereRaw('ST_Distance(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) < 50', [$lng, $lat])
            ->orderByRaw('geom <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)', [$lng, $lat])
            ->first();

        if ($pontoProximo) {
            return ['id' => $pontoProximo->id, 'created' => false];
        }

        $ponto = Ponto::create([
            'lat' => $lat,
            'lng' => $lng,
            'numero' => 'S/N',
        ]);

        $this->enderecoService->vincularEnderecoAoPonto($ponto->id, $lat, $lng, $complemento);

        return ['id' => $ponto->id, 'created' => true];
    }
}
