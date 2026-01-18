<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PlannerAvailableStoreRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:range,week,always,disabled'],
            'start_date' => ['required_if:option,range', 'nullable', 'date'],
            'end_date' => ['required_if:option,range', 'nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Pole wymagane',
            'days.required' => 'Pole daty jest wymagane',
            'start_date.required' => 'Pole daty jest wymagane',
            'end_date.required' => 'Pole daty jest wymagane',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->input('type'),
        ]);

        if ($this->input('type') === 'week') {
            $this->merge([
                'start_date' => now(),
                'end_date' => now()->addDays((int) $this->input('days')),
            ]);
        }

        else if ($this->input('type') === 'range') {
            $this->merge([
                'start_date' => $this->input('start_date'),
                'end_date' => $this->input('end_date'),
            ]);
        }
    }
}
