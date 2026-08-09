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
            'name' => ['required', 'string', 'max:255', 'unique:packages,name'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Wszystkie pola są wymagane.',
            'name.unique' => 'Taki pakiet już istnieje.',
            'name.max' => 'Nazwa stawki może mieć maksymalnie 255 znaków.',
            'price.required' => 'Wszystkie pola są wymagane.',
            'price.numeric' => 'Musisz podać poprawną liczbę.',
            'price.min' => 'Cena nie może być ujemna.',
            'price.max' => 'Cena jest zbyt wysoka.',
        ];
    }
}
