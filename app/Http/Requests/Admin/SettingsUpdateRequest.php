<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'worker_self_hours_enabled' => ['nullable', 'boolean'],
            'force_disable_with_pending' => ['nullable', 'boolean'],
        ];
    }
}
