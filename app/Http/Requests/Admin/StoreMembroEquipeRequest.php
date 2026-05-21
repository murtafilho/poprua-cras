<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\MembroEquipeController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembroEquipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'matricula' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'equipe' => ['required', Rule::in(array_keys(MembroEquipeController::EQUIPES))],
            'ativo' => 'sometimes|boolean',
        ];
    }

    /** @return array<string, mixed> */
    protected function prepareForValidation(): void
    {
        $this->merge(['ativo' => $this->boolean('ativo')]);
    }
}
