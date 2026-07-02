<?php

namespace App\Http\Requests\Api;

use App\Services\ParametroService;
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
        $fotoBase = array_values(array_filter(
            app(ParametroService::class)->regrasValidacaoFoto(),
            fn (string $regra): bool => $regra !== 'nullable'
        ));

        return [
            'foto' => array_merge(['required_without:fotos'], $fotoBase),
            'fotos' => ['required_without:foto', 'array'],
            'fotos.*' => $fotoBase,
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
