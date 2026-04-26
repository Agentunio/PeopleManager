<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PackageSetDefaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'package_id.integer' => 'Nieprawidlowy identyfikator stawki.',
            'package_id.exists' => 'Wybrana stawka nie istnieje.',
        ];
    }
}
