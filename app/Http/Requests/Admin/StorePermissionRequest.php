<?php

namespace App\Http\Requests\Admin;

use App\Support\RoleDisplay;
use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9 ]+$/', 'unique:permissions,name'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => RoleDisplay::normalizePermission((string) $this->input('name', '')),
        ]);
    }
}
