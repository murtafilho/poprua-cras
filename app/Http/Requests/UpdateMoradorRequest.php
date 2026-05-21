<?php

namespace App\Http\Requests;

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
        return [
            'nome_social' => ['sometimes', 'required', 'string', 'max:255'],
            'nome_registro' => ['nullable', 'string', 'max:255'],
            'apelido' => ['nullable', 'string', 'max:255'],
            'genero' => ['nullable', 'string', 'max:100'],
            'observacoes' => ['nullable', 'string'],
            'documento' => ['nullable', 'string', 'max:50'],
            'contato' => ['nullable', 'string', 'max:50'],
            'fotografia' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'fotografias' => ['nullable', 'array'],
            'fotografias.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ];
    }
}
