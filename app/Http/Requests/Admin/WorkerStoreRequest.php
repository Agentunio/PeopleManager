<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WorkerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dobRules = ['nullable', 'date', 'before:today'];

        $worker = $this->route('worker');
        if ($worker && $worker->hasAccount()) {
            $dobRules[0] = 'required';
        }

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => $dobRules,
            'is_student' => ['required', 'boolean'],
            'is_employed' => ['required', 'boolean'],
            'contract_from' => ['nullable', 'date'],
            'contract_to' => ['nullable', 'date', 'after:contract_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Imię jest wymagane.',
            'first_name.max' => 'Imię może mieć maksymalnie 255 znaków.',
            'last_name.required' => 'Nazwisko jest wymagane.',
            'last_name.max' => 'Nazwisko może mieć maksymalnie 255 znaków.',
            'phone.max' => 'Telefon może mieć maksymalnie 255 znaków.',
            'address.max' => 'Adres może mieć maksymalnie 255 znaków.',
            'date_of_birth.required' => 'Data urodzenia jest wymagana.',
            'date_of_birth.date' => 'Podaj prawidłową datę urodzenia.',
            'date_of_birth.before' => 'Data urodzenia nie może być późniejsza niż dzisiaj.',
            'is_student.required' => 'Status ucznia jest wymagany.',
            'is_student.boolean' => 'Status ucznia ma nieprawidłową wartość.',
            'is_employed.required' => 'Status zatrudnienia jest wymagany.',
            'is_employed.boolean' => 'Status zatrudnienia ma nieprawidłową wartość.',
            'contract_from.date' => 'Podaj prawidłową datę początku umowy.',
            'contract_to.date' => 'Podaj prawidłową datę końca umowy.',
            'contract_to.after' => 'Data końca umowy musi być późniejsza niż data początku umowy.',
        ];
    }
}
