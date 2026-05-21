<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePontoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero' => ['nullable', 'string', 'max:10'],
            'complemento' => ['nullable', 'string', 'max:120'],
            'observacao' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'endereco_atualizado_id' => ['nullable', 'integer', 'exists:endereco_atualizados,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'lat.between' => 'Latitude deve estar entre -90 e 90.',
            'lng.between' => 'Longitude deve estar entre -180 e 180.',
            'endereco_atualizado_id.exists' => 'Endereco selecionado nao encontrado.',
        ];
    }
}
