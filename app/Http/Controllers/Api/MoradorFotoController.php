<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMoradorFotoRequest;
use App\Models\Morador;
use App\Services\FotoService;
use Illuminate\Http\JsonResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class MoradorFotoController extends Controller
{
    public function __construct(
        private readonly FotoService $fotoService,
    ) {}

    public function index(Morador $morador): JsonResponse
    {
        $morador->loadMissing('media');

        $fotos = $morador->getMedia('fotos')
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (Media $m) => $this->fotoService->serializarMedia($m));

        return response()->json(['fotos' => $fotos]);
    }

    public function store(StoreMoradorFotoRequest $request, Morador $morador): JsonResponse
    {
        $arquivos = $request->hasFile('fotos')
            ? $request->file('fotos')
            : [$request->file('foto')];

        $userId = $request->user()?->id;
        $criadas = [];

        foreach ($arquivos as $arquivo) {
            $criadas[] = $this->fotoService->adicionarFoto(
                $morador,
                $arquivo,
                'fotos',
                ['uploaded_by_user_id' => $userId],
            );
        }

        return response()->json(
            count($criadas) === 1 ? $criadas[0] : ['fotos' => $criadas],
            Response::HTTP_CREATED
        );
    }

    public function destroy(Morador $morador, ?Media $media = null): JsonResponse
    {
        if ($media !== null) {
            if ($media->model_type !== $morador->getMorphClass() || $media->model_id !== $morador->id) {
                return response()->json(['error' => 'Foto não pertence a este morador.'], Response::HTTP_FORBIDDEN);
            }
            $media->delete();
        } else {
            $morador->clearMediaCollection('fotos');
        }

        return response()->json(['success' => true]);
    }
}
