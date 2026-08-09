<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlannerAvailableStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type');
        $requiresRange = in_array($type, ['signup', 'admin'], true);

        return [
            'type' => ['required', Rule::in(['signup', 'always', 'admin', 'disabled'])],
            'signup_deadline' => [
                Rule::requiredIf($type === 'signup'),
                'nullable',
                'date',
                'after:now',
                'before:start_date',
            ],
            'start_date' => [
                Rule::requiredIf($requiresRange),
                'nullable',
                'date',
                'required_with:end_date',
                Rule::when($type === 'signup', ['after:signup_deadline']),
                Rule::when(in_array($type, ['always', 'admin'], true), ['after_or_equal:today']),
            ],
            'end_date' => [
                Rule::requiredIf($requiresRange),
                'nullable',
                'date',
                'required_with:start_date',
                'after_or_equal:start_date',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Wybierz typ grafiku',
            'type.in' => 'Nieprawidłowy typ grafiku',
            'signup_deadline.required' => 'Podaj termin zamknięcia zapisów',
            'signup_deadline.date' => 'Nieprawidłowy format terminu zapisów',
            'signup_deadline.after' => 'Termin zapisów musi być w przyszłości',
            'signup_deadline.before' => 'Termin zapisów musi być przed początkiem zakresu dni grafiku',
            'start_date.required' => 'Podaj początek zakresu dni grafiku',
            'start_date.required_with' => 'Podaj początek zakresu dni grafiku',
            'start_date.date' => 'Nieprawidłowy format początku zakresu',
            'start_date.after' => 'Początek zakresu musi być po terminie zapisów',
            'start_date.after_or_equal' => 'Początek zakresu nie może być w przeszłości',
            'end_date.required' => 'Podaj koniec zakresu dni grafiku',
            'end_date.required_with' => 'Podaj koniec zakresu dni grafiku',
            'end_date.date' => 'Nieprawidłowy format końca zakresu',
            'end_date.after_or_equal' => 'Koniec zakresu nie może być wcześniejszy niż początek',
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type');

        if ($type === 'disabled') {
            $this->merge([
                'signup_deadline' => null,
                'start_date' => null,
                'end_date' => null,
            ]);

            return;
        }

        if ($type !== 'signup') {
            $this->merge(['signup_deadline' => null]);
        }
    }
}
