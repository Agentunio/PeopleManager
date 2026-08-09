<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlannerShiftStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['worked', 'absent'])],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status zmiany jest wymagany.',
            'status.in' => 'Nieprawidłowy status zmiany.',
        ];
    }
}
