<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientLogRequest extends FormRequest
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
            'logs' => ['required', 'array', 'max:50'],
            'logs.*.level' => ['required', 'in:debug,info,warn,error'],
            'logs.*.message' => ['required', 'string', 'max:1000'],
            'logs.*.context' => ['nullable', 'array'],
            'logs.*.timestamp' => ['nullable', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logs.required' => 'O array de logs é obrigatório.',
            'logs.max' => 'O máximo permitido é 50 registros por requisição.',
            'logs.*.level.required' => 'O nível de cada log é obrigatório.',
            'logs.*.level.in' => 'O nível deve ser: debug, info, warn ou error.',
            'logs.*.message.required' => 'A mensagem de cada log é obrigatória.',
            'logs.*.message.max' => 'A mensagem não pode ter mais de 1000 caracteres.',
        ];
    }
}
