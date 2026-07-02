<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreVistoriaFotoRequest;
use App\Http\Requests\Api\UpdateVistoriaFotoLegendaRequest;
use App\Models\Vistoria;
use App\Services\FotoService;
use Illuminate\Http\JsonResponse;

class VistoriaFotoController extends Controller
{
    public function __construct(
        private readonly FotoService $fotoService,
    ) {}

    public function store(StoreVistoriaFotoRequest $request): JsonResponse
    {
        $vistoria = Vistoria::findOrFail($request->validated('vistoria_id'));
        $this->authorize('update', $vistoria);

        $propriedades = [];
        $legenda = trim((string) $request->validated('legenda', ''));
        if ($legenda !== '') {
            $propriedades['legenda'] = $legenda;
        }
        $propriedades['publica'] = $request->validated('publica') === '1' || $request->validated('publica') === true;

        $resultado = $this->fotoService->adicionarFoto(
            $vistoria,
            $request->file('foto'),
            'fotos',
            $propriedades,
        );

        return response()->json($resultado, 201);
    }

    public function status(Vistoria $vistoria): JsonResponse
    {
        $this->authorize('view', $vistoria);

        $fotos = $this->fotoService->listarFotos($vistoria);

        return response()->json(['fotos' => $fotos]);
    }

    public function togglePublica(Vistoria $vistoria, int $mediaId): JsonResponse
    {
        $this->authorize('update', $vistoria);

        $resultado = $this->fotoService->togglePublica($vistoria, $mediaId);

        return response()->json($resultado);
    }

    public function setLegenda(UpdateVistoriaFotoLegendaRequest $request, Vistoria $vistoria, int $mediaId): JsonResponse
    {
        $this->authorize('update', $vistoria);

        $resultado = $this->fotoService->setLegenda(
            $vistoria,
            $mediaId,
            (string) $request->validated('legenda', ''),
        );

        return response()->json($resultado);
    }
}
