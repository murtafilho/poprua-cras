<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveVistoriaRascunhoRequest;
use App\Services\VistoriaRascunhoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VistoriaRascunhoController extends Controller
{
    public function __construct(
        private VistoriaRascunhoService $rascunhoService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ponto_id' => ['nullable', 'integer', 'exists:pontos,id'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $user = $request->user();
        assert($user !== null);

        $rascunho = $this->rascunhoService->recuperar(
            $user,
            isset($validated['ponto_id']) ? (int) $validated['ponto_id'] : null,
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lng']) ? (float) $validated['lng'] : null,
        );

        if ($rascunho === null) {
            return response()->json(['rascunho' => null]);
        }

        $this->authorize('view', $rascunho);

        return response()->json([
            'rascunho' => [
                'id' => $rascunho->id,
                'payload' => $rascunho->payload,
                'etapa_atual' => $rascunho->etapa_atual,
                'ponto_id' => $rascunho->ponto_id,
                'lat' => $rascunho->lat,
                'lng' => $rascunho->lng,
                'updated_at' => $rascunho->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(SaveVistoriaRascunhoRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        assert($user !== null);

        $rascunho = $this->rascunhoService->salvar(
            $user,
            $validated['payload'],
            (int) $validated['etapa_atual'],
            isset($validated['ponto_id']) ? (int) $validated['ponto_id'] : null,
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lng']) ? (float) $validated['lng'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Rascunho salvo.',
            'rascunho' => [
                'id' => $rascunho->id,
                'updated_at' => $rascunho->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ponto_id' => ['nullable', 'integer', 'exists:pontos,id'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $user = $request->user();
        assert($user !== null);

        $rascunho = $this->rascunhoService->recuperar(
            $user,
            isset($validated['ponto_id']) ? (int) $validated['ponto_id'] : null,
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lng']) ? (float) $validated['lng'] : null,
        );

        if ($rascunho !== null) {
            $this->authorize('delete', $rascunho);
        }

        $this->rascunhoService->descartar(
            $user,
            isset($validated['ponto_id']) ? (int) $validated['ponto_id'] : null,
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lng']) ? (float) $validated['lng'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Rascunho descartado.',
        ]);
    }
}
