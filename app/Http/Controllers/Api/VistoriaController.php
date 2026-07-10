<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreVistoriaApiRequest;
use App\Models\Vistoria;
use App\Services\VistoriaService;
use Illuminate\Database\QueryException;
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

        try {
            $result = $this->vistoriaService->criarComRelacionamentos($request, $validated);
            $this->vistoriaService->invalidarCacheListagem();

            return $this->respostaVistoria($result['vistoria']->id, $validated['client_uuid']);
        } catch (QueryException $e) {
            if (! $this->ehViolacaoClientUuid($e)) {
                throw $e; // ex.: user_team_unique e outras violações → 500 honesto/retryável
            }

            // Corrida: outra requisição criou primeiro. Se for do mesmo usuário, devolve idempotente.
            $existente = Vistoria::query()
                ->where('user_id', $userId)
                ->where('client_uuid', $validated['client_uuid'])
                ->first();

            if ($existente) {
                return $this->respostaVistoria($existente->id, $validated['client_uuid']);
            }

            // client_uuid pertence a outro usuário — conflito real, não vaza dado alheio.
            return response()->json(['message' => 'client_uuid já utilizado por outro registro.'], 409);
        }
    }

    private function respostaVistoria(int $id, string $clientUuid): JsonResponse
    {
        return response()->json([
            'id' => $id,
            'redirect_url' => route('vistorias.show', $id),
            'client_uuid' => $clientUuid,
        ]);
    }

    private function ehViolacaoClientUuid(QueryException $e): bool
    {
        return $e->getCode() === '23505'
            && str_contains($e->getMessage(), 'vistorias_client_uuid_unique');
    }
}
