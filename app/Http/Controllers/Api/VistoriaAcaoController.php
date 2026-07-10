<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vistoria;
use App\Services\VistoriaService;
use Illuminate\Http\JsonResponse;

class VistoriaAcaoController extends Controller
{
    public function __construct(private VistoriaService $vistoriaService) {}

    public function finalizar(Vistoria $vistoria): JsonResponse
    {
        $this->authorize('update', $vistoria);
        $this->vistoriaService->finalizar($vistoria);

        return $this->estado($vistoria);
    }

    public function cancelar(Vistoria $vistoria): JsonResponse
    {
        $this->authorize('cancelar', $vistoria);
        $this->vistoriaService->cancelar($vistoria);

        return $this->estado($vistoria);
    }

    public function reativar(Vistoria $vistoria): JsonResponse
    {
        $this->authorize('reativar', $vistoria);
        $this->vistoriaService->reativar($vistoria);

        return $this->estado($vistoria);
    }

    private function estado(Vistoria $vistoria): JsonResponse
    {
        return response()->json([
            'id' => $vistoria->id,
            'finalizada' => (bool) $vistoria->finalizada,
            'cancelada' => (bool) $vistoria->cancelada,
        ]);
    }
}
