<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAccountActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_of_birth' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.required' => 'Data urodzenia jest wymagana.',
            'date_of_birth.date' => 'Podaj prawidłową datę.',
        ];
    }
}
