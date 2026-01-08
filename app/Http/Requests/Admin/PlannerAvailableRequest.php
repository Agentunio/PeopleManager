<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PlannerAvailableRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:range,week,always,disabled'],
            'days' => ['required_if:option,week', 'nullable', 'in:7,14,30'],
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
}
