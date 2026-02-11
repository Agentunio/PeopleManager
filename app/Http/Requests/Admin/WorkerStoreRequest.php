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
        return [
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'phone' => 'string',
            'address' => 'string',
            'date_of_birth' => ['date', 'before:today'],
            'is_student' => 'boolean',
            'is_employed' => 'boolean',
            'contract_from' => 'date',
            'contract_to' => ['date', 'after:contract_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Imię jest wymagane.',
            'last_name.required' => 'Nazwisko jest wymagane.',
            'phone.required' => 'Telefon jest wymagany.',
            'address.required' => 'Adres jest wymagany.',
            'date_of_birth.required' => 'Data urodzenia jest wymagana.',
            'date_of_birth.before' => 'Data urodzenia nie może być późniejsza niż dzisiaj.',
            'is_student.required' => 'Status studenta jest wymagany.',
            'is_employed.required' => 'Status zatrudnienia jest wymagany.',
            'contract_from.required' => 'Data startu umowy jest wymagana.',
            'contract_to.required' => 'Data końca umowy jest wymagana.',
            'contract_to.after' => 'Data końca umowy nie może być przed datą początku umowy',
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
