<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PackageUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'price' => ['required', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nazwa pakietu jest wymagana.',
            'price.required' => 'Cena jest wymagana.',
            'price.numeric' => 'Musisz podać poprawną liczbę.',
        ];
    }
}
