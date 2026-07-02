<?php

namespace App\Http\Requests;

use App\Services\ParametroService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMoradorRequest extends FormRequest
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
        $fotoRegras = app(ParametroService::class)->regrasValidacaoFoto();
        $fotoItemRegras = array_values(array_filter($fotoRegras, fn (string $regra): bool => $regra !== 'nullable'));

        return [
            'nome_social' => ['sometimes', 'required', 'string', 'max:255'],
            'nome_registro' => ['nullable', 'string', 'max:255'],
            'apelido' => ['nullable', 'string', 'max:255'],
            'genero' => ['nullable', 'string', 'max:100'],
            'observacoes' => ['nullable', 'string'],
            'documento' => ['nullable', 'string', 'max:50'],
            'contato' => ['nullable', 'string', 'max:50'],
            'fotografia' => $fotoRegras,
            'fotografias' => ['nullable', 'array'],
            'fotografias.*' => $fotoItemRegras,
        ];
    }
}
