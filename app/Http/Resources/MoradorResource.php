<?php

namespace App\Http\Resources;

use App\Models\Morador;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Morador */
class MoradorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome_social' => $this->nome_social,
            'nome_registro' => $this->nome_registro,
            'apelido' => $this->apelido,
            'genero' => $this->genero,
            'documento' => $this->documento,
            'contato' => $this->contato,
            'observacoes' => $this->observacoes,
            'fotos' => $this->getMedia('fotos')->sortByDesc('created_at')->values()->map(fn ($m) => [
                'id' => $m->id,
                'url' => $m->getUrl(),
                'thumb' => $m->hasGeneratedConversion('thumb') ? $m->getUrl('thumb') : $m->getUrl(),
                'preview' => $m->hasGeneratedConversion('preview') ? $m->getUrl('preview') : $m->getUrl(),
                'created_at' => $m->created_at?->toIso8601String(),
            ]),

            // Ponto atual
            'ponto_atual_id' => $this->ponto_atual_id,
            'ponto_atual' => new PontoResource($this->whenLoaded('pontoAtual')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
