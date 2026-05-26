<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FotoService
{
    /**
     * Adiciona uma foto à coleção de mídia do modelo.
     *
     * @param  HasMedia&Model  $modelo
     * @param  UploadedFile  $arquivo
     * @param  array<string, mixed>  $propriedades  Propriedades customizadas opcionais
     * @return array<string, mixed> Dados serializados da mídia criada
     */
    public function adicionarFoto(HasMedia $modelo, $arquivo, string $colecao = 'fotos', array $propriedades = []): array
    {
        $safeName = preg_replace(
            '/[^a-zA-Z0-9._-]/',
            '_',
            pathinfo($arquivo->getClientOriginalName(), PATHINFO_FILENAME)
        );

        $adder = $modelo->addMedia($arquivo)->usingName($safeName);

        if (! empty($propriedades)) {
            $adder->withCustomProperties($propriedades);
        }

        $media = $adder->toMediaCollection($colecao);

        return $this->serializarMedia($media);
    }

    /**
     * Alterna a propriedade 'publica' de uma mídia de vistoria.
     *
     * @return array{id: int, publica: bool}
     */
    public function togglePublica(HasMedia $modelo, int $mediaId, string $colecao = 'fotos'): array
    {
        /** @var Media $media */
        $media = $modelo->media()
            ->where('id', $mediaId)
            ->where('collection_name', $colecao)
            ->firstOrFail();

        $atual = (bool) $media->getCustomProperty('publica', false);
        $novo = ! $atual;

        $media->setCustomProperty('publica', $novo);
        $media->save();

        return [
            'id' => $media->id,
            'publica' => $novo,
        ];
    }

    /**
     * Define a legenda (custom property) de uma mídia. Texto curto que
     * aparece sob a foto no relatório quando a foto está marcada publica.
     *
     * @return array{id: int, legenda: string}
     */
    public function setLegenda(HasMedia $modelo, int $mediaId, string $legenda, string $colecao = 'fotos'): array
    {
        /** @var Media $media */
        $media = $modelo->media()
            ->where('id', $mediaId)
            ->where('collection_name', $colecao)
            ->firstOrFail();

        $media->setCustomProperty('legenda', $legenda);
        $media->save();

        return [
            'id' => $media->id,
            'legenda' => $legenda,
        ];
    }

    /**
     * Lista fotos de uma coleção com dados serializados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarFotos(HasMedia $modelo, string $colecao = 'fotos'): array
    {
        $modelo->loadMissing('media');

        return $modelo->getMedia($colecao)->map(fn (Media $media) => [
            'id' => $media->id,
            'url' => $media->getUrl(),
            'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
            'name' => $media->name,
            'publica' => (bool) $media->getCustomProperty('publica', false),
            'legenda' => (string) $media->getCustomProperty('legenda', ''),
        ])->all();
    }

    /**
     * Serializa uma mídia para resposta JSON.
     *
     * @return array<string, mixed>
     */
    public function serializarMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'url' => $media->getUrl(),
            'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
            'preview' => $media->hasGeneratedConversion('preview') ? $media->getUrl('preview') : $media->getUrl(),
            'created_at' => $media->created_at?->toIso8601String(),
            'publica' => (bool) $media->getCustomProperty('publica', false),
            'legenda' => (string) $media->getCustomProperty('legenda', ''),
        ];
    }
}
