<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WeeklyExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'week_start' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'week_start.required' => 'Początek tygodnia jest wymagany.',
            'week_start.date_format' => 'Początek tygodnia musi mieć format RRRR-MM-DD.',
        ];
    }
}
