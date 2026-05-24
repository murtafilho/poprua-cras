<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreMoradorFotoRequest extends FormRequest
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
            'foto' => ['required_without:fotos', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'fotos' => ['required_without:foto', 'array'],
            'fotos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.required_without' => 'Envie ao menos uma foto (campo "foto" ou "fotos").',
            'foto.image' => 'O arquivo deve ser uma imagem.',
            'foto.mimes' => 'A imagem deve ser do tipo: jpeg, jpg, png ou webp.',
            'foto.max' => 'A imagem não pode ter mais de 10MB.',
            'fotos.required_without' => 'Envie ao menos uma foto (campo "foto" ou "fotos").',
            'fotos.*.image' => 'Cada arquivo deve ser uma imagem.',
            'fotos.*.mimes' => 'Cada imagem deve ser do tipo: jpeg, jpg, png ou webp.',
            'fotos.*.max' => 'Cada imagem não pode ter mais de 10MB.',
        ];
    }
}
