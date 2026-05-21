<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Morador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class MoradorFotoController extends Controller
{
    public function index(Morador $morador): JsonResponse
    {
        $morador->loadMissing('media');

        $fotos = $morador->getMedia('fotos')
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (Media $m) => $this->serializeMedia($m));

        return response()->json(['fotos' => $fotos]);
    }

    public function store(Request $request, Morador $morador): JsonResponse
    {
        $request->validate([
            'foto' => ['required_without:fotos', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'fotos' => ['required_without:foto', 'array'],
            'fotos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $arquivos = $request->hasFile('fotos')
            ? $request->file('fotos')
            : [$request->file('foto')];

        $userId = $request->user()?->id;
        $criadas = [];

        foreach ($arquivos as $arquivo) {
            $safeName = preg_replace(
                '/[^a-zA-Z0-9._-]/',
                '_',
                pathinfo($arquivo->getClientOriginalName(), PATHINFO_FILENAME)
            );

            $media = $morador->addMedia($arquivo)
                ->usingName($safeName)
                ->withCustomProperties(['uploaded_by_user_id' => $userId])
                ->toMediaCollection('fotos');

            $criadas[] = $this->serializeMedia($media);
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
                return response()->json(['error' => 'Foto nao pertence a este morador.'], Response::HTTP_FORBIDDEN);
            }
            $media->delete();
        } else {
            $morador->clearMediaCollection('fotos');
        }

        return response()->json(['success' => true]);
    }

    /** @return array<string, mixed> */
    private function serializeMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'url' => $media->getUrl(),
            'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
            'preview' => $media->hasGeneratedConversion('preview') ? $media->getUrl('preview') : $media->getUrl(),
            'created_at' => $media->created_at->toIso8601String(),
        ];
    }
}
