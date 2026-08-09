<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PlannerSubstituteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'worker_id.required' => 'Wybierz pracownika na zastępstwo.',
            'worker_id.integer' => 'Nieprawidłowy pracownik.',
            'worker_id.exists' => 'Wybrany pracownik nie istnieje.',
        ];
    }
}
