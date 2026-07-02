<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreParametroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'chave' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', 'unique:parametros,chave'],
            'valor' => ['nullable', 'string', 'max:500'],
            'tipo' => ['required', 'in:string,integer,float,boolean'],
            'grupo' => ['required', 'string', 'max:50'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ];
    }
}
