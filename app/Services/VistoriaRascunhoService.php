<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\VistoriaRascunho;

class VistoriaRascunhoService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function salvar(
        User $user,
        array $payload,
        int $etapaAtual,
        ?int $pontoId,
        ?float $lat,
        ?float $lng
    ): VistoriaRascunho {
        $contextKey = $this->buildContextKey($pontoId, $lat, $lng);
        [$latNorm, $lngNorm] = $this->normalizeCoords($lat, $lng);

        return VistoriaRascunho::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'context_key' => $contextKey,
            ],
            [
                'ponto_id' => $pontoId,
                'lat' => $latNorm,
                'lng' => $lngNorm,
                'payload' => $payload,
                'etapa_atual' => max(0, min(6, $etapaAtual)),
            ]
        );
    }

    public function recuperar(User $user, ?int $pontoId, ?float $lat, ?float $lng): ?VistoriaRascunho
    {
        $contextKey = $this->buildContextKey($pontoId, $lat, $lng);

        return VistoriaRascunho::query()
            ->where('user_id', $user->id)
            ->where('context_key', $contextKey)
            ->first();
    }

    public function descartar(User $user, ?int $pontoId, ?float $lat, ?float $lng): void
    {
        $contextKey = $this->buildContextKey($pontoId, $lat, $lng);

        VistoriaRascunho::query()
            ->where('user_id', $user->id)
            ->where('context_key', $contextKey)
            ->delete();
    }

    public function descartarAposStore(User $user, ?int $pontoId, ?float $lat, ?float $lng): void
    {
        $this->descartar($user, $pontoId, $lat, $lng);

        if ($pontoId !== null && $lat !== null && $lng !== null) {
            $this->descartar($user, null, $lat, $lng);
        }
    }

    public function buildContextKey(?int $pontoId, ?float $lat, ?float $lng): string
    {
        if ($pontoId !== null) {
            return 'ponto:'.$pontoId;
        }

        if ($lat !== null && $lng !== null) {
            [$latNorm, $lngNorm] = $this->normalizeCoords($lat, $lng);

            return sprintf('coords:%.6f,%.6f', $latNorm, $lngNorm);
        }

        return 'global';
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    public function normalizeCoords(?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null) {
            return [null, null];
        }

        return [round($lat, 6), round($lng, 6)];
    }

    public function limparExpirados(int $dias): int
    {
        if ($dias < 1) {
            return 0;
        }

        return VistoriaRascunho::query()
            ->where('updated_at', '<', now()->subDays($dias))
            ->delete();
    }
}
