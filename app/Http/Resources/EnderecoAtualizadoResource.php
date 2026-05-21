<?php

namespace App\Http\Resources;

use App\Models\EnderecoAtualizado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EnderecoAtualizado */
class EnderecoAtualizadoResource extends JsonResource
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
            'tipo' => $this->tipo,
            'logradouro' => $this->logradouro,
            'numero' => $this->numero,
            'bairro' => $this->bairro,
            'regional' => $this->regional,
            'cep' => $this->cep,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'endereco_completo' => $this->endereco_completo,
        ];
    }
}
