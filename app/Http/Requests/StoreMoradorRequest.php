<?php

namespace App\Http\Requests;

use App\Services\ParametroService;
use Illuminate\Foundation\Http\FormRequest;

class StoreMoradorRequest extends FormRequest
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
            'nome_social' => ['required', 'string', 'max:255'],
            'nome_registro' => ['nullable', 'string', 'max:255'],
            'apelido' => ['nullable', 'string', 'max:255'],
            'genero' => ['nullable', 'string', 'max:100'],
            'observacoes' => ['nullable', 'string'],
            'documento' => ['nullable', 'string', 'max:50'],
            'contato' => ['nullable', 'string', 'max:50'],
            'fotografia' => $fotoRegras,
            'fotografias' => ['nullable', 'array'],
            'fotografias.*' => $fotoItemRegras,
            'ponto_id' => ['nullable', 'integer', 'exists:pontos,id'],
            'vistoria_id' => ['nullable', 'integer', 'exists:vistorias,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome_social.required' => 'O nome social é obrigatório.',
            'nome_social.max' => 'O nome social não pode ter mais de 255 caracteres.',
            'fotografia.image' => 'O arquivo deve ser uma imagem.',
            'fotografia.max' => 'A imagem não pode ter mais de 10MB.',
            'fotografias.*.image' => 'Cada arquivo deve ser uma imagem.',
            'fotografias.*.max' => 'Cada imagem não pode ter mais de 10MB.',
            'ponto_id.exists' => 'O ponto informado não existe.',
            'vistoria_id.exists' => 'A vistoria informada não existe.',
        ];
    }
}
