<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WorkerStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
        ];
    }

    public function messages(): array
    {
        return [
            'dateFrom.required' => 'Data początkowa jest wymagana.',
            'dateFrom.date' => 'Data początkowa musi być prawidłową datą.',
            'dateTo.required' => 'Data końcowa jest wymagana.',
            'dateTo.date' => 'Data końcowa musi być prawidłową datą.',
            'dateTo.after_or_equal' => 'Data końcowa nie może być wcześniejsza niż data początkowa.',
        ];
    }
}
