<?php

namespace App\Http\Requests;

use App\Models\Vistoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateMinhaEquipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Vistoria::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'membros' => ['nullable', 'array'],
            'membros.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'membros.array' => 'O campo membros deve ser um array.',
            'membros.*.integer' => 'Cada membro deve ser um ID numérico.',
            'membros.*.exists' => 'Um dos membros informados não existe.',
        ];
    }
}
