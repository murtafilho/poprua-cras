<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreVistoriaFotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vistoria_id' => ['required', 'integer', 'exists:vistorias,id'],
            'foto' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'legenda' => ['nullable', 'string', 'max:255'],
            'publica' => ['nullable', 'in:0,1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vistoria_id.required' => 'O ID da vistoria é obrigatório.',
            'vistoria_id.exists' => 'A vistoria informada não existe.',
            'foto.required' => 'A foto é obrigatória.',
            'foto.image' => 'O arquivo deve ser uma imagem.',
            'foto.mimes' => 'A imagem deve ser do tipo: jpeg, jpg, png ou webp.',
            'foto.max' => 'A imagem não pode ter mais de 10MB.',
        ];
    }
}
