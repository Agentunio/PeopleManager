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
            'type' => ['required', 'in:signup,always,disabled'],
            'signup_deadline' => ['required_if:type,signup', 'nullable', 'date', 'after:now', 'before:start_date'],
            'start_date' => ['required_if:type,signup', 'nullable', 'date', 'after:signup_deadline'],
            'end_date' => ['required_if:type,signup', 'nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Wybierz typ grafiku',
            'type.in' => 'Nieprawidłowy typ grafiku',
            'signup_deadline.required_if' => 'Podaj termin zamknięcia zapisów',
            'signup_deadline.date' => 'Nieprawidłowy format terminu zapisów',
            'signup_deadline.after' => 'Termin zapisów musi być w przyszłości',
            'signup_deadline.before' => 'Termin zapisów musi być przed początkiem zakresu dni grafiku',
            'start_date.required_if' => 'Podaj początek zakresu dni grafiku',
            'start_date.date' => 'Nieprawidłowy format początku zakresu',
            'start_date.after' => 'Początek zakresu musi być po terminie zapisów',
            'end_date.required_if' => 'Podaj koniec zakresu dni grafiku',
            'end_date.date' => 'Nieprawidłowy format końca zakresu',
            'end_date.after_or_equal' => 'Koniec zakresu nie może być wcześniejszy niż początek',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('type') !== 'signup') {
            $this->merge([
                'signup_deadline' => null,
                'start_date' => null,
                'end_date' => null,
            ]);
        }
    }
}
