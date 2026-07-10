<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreVistoriaApiRequest;
use App\Models\Vistoria;
use App\Services\VistoriaService;
use Illuminate\Http\JsonResponse;

class VistoriaController extends Controller
{
    public function __construct(private VistoriaService $vistoriaService) {}

    /**
     * Criação de vistoria via JSON (usada pela fila offline).
     * Idempotente por client_uuid: reenvio do mesmo uuid retorna a existente.
     */
    public function store(StoreVistoriaApiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->id;

        $existente = Vistoria::query()
            ->where('user_id', $userId)
            ->where('client_uuid', $validated['client_uuid'])
            ->first();

        if ($existente) {
            return $this->respostaVistoria($existente->id, $validated['client_uuid']);
        }

        $result = $this->vistoriaService->criarComRelacionamentos($request, $validated);
        $this->vistoriaService->invalidarCacheListagem();

        return $this->respostaVistoria($result['vistoria']->id, $validated['client_uuid']);
    }

    private function respostaVistoria(int $id, string $clientUuid): JsonResponse
    {
        return response()->json([
            'id' => $id,
            'redirect_url' => route('vistorias.show', $id),
            'client_uuid' => $clientUuid,
        ]);
    }
}
