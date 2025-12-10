<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'is_student' => ['required', 'boolean'],
            'is_employed' => ['required', 'boolean'],
            'contract_from' => ['nullable', 'date'],
            'contract_to' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Imię jest wymagane.',
            'last_name.required' => 'Nazwisko jest wymagane.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_student' => $this->boolean('is_student'),
            'is_employed' => $this->boolean('is_employed'),
        ]);
    }
}
