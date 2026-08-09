<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pending' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
