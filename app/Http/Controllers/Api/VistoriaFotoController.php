<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vistoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VistoriaFotoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'vistoria_id' => 'required|exists:vistorias,id',
            'foto' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'descricao' => 'nullable|string|max:255',
        ]);

        $vistoria = Vistoria::findOrFail($request->vistoria_id);
        $this->authorize('update', $vistoria);

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($request->file('foto')->getClientOriginalName(), PATHINFO_FILENAME));
        $media = $vistoria->addMedia($request->file('foto'))
            ->usingName($safeName)
            ->toMediaCollection('fotos');

        $thumb = $media->hasGeneratedConversion('thumb')
            ? $media->getUrl('thumb')
            : $media->getUrl();

        return response()->json([
            'id' => $media->id,
            'url' => $media->getUrl(),
            'thumb' => $thumb,
        ], 201);
    }

    public function status(Vistoria $vistoria): JsonResponse
    {
        $this->authorize('view', $vistoria);

        $vistoria->loadMissing('media');

        $fotos = $vistoria->getMedia('fotos')->map(fn ($media) => [
            'id' => $media->id,
            'url' => $media->getUrl(),
            'thumb' => $media->getUrl('thumb'),
            'name' => $media->name,
            'publica' => (bool) $media->getCustomProperty('publica', false),
        ]);

        return response()->json(['fotos' => $fotos]);
    }

    public function togglePublica(Request $request, Vistoria $vistoria, int $mediaId): JsonResponse
    {
        $this->authorize('update', $vistoria);

        $media = $vistoria->media()
            ->where('id', $mediaId)
            ->where('collection_name', 'fotos')
            ->firstOrFail();

        $atual = (bool) $media->getCustomProperty('publica', false);
        $novo = ! $atual;

        $media->setCustomProperty('publica', $novo);
        $media->save();

        return response()->json([
            'id' => $media->id,
            'publica' => $novo,
        ]);
    }
}
