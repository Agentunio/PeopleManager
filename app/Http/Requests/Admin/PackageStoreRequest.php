<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PackageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'unique:settings,name'],
            'price' => ['required', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Wszystkie pola są wymagane.',
            'name.unique' => 'Taki pakiet już istnieje.',
            'price.required' => 'Wszystkie pola są wymagane.',
            'price.numeric' => 'Musisz podać poprawną liczbę.',
        ];
    }
}
