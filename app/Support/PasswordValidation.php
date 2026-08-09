<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class PasswordValidation
{
    public static function rules(): array
    {
        return ['required', 'string', 'max:255', 'confirmed', Password::defaults()];
    }

    public static function messages(): array
    {
        return [
            'password.required' => 'Hasło jest wymagane.',
            'password.confirmed' => 'Hasła nie są zgodne.',
            'password.min' => 'Hasło musi mieć minimum :min znaków.',
            'password.max' => 'Hasło może mieć maksymalnie 255 znaków.',
            'password.letters' => 'Hasło musi zawierać litery.',
            'password.mixed' => 'Hasło musi zawierać małą i wielką literę.',
            'password.numbers' => 'Hasło musi zawierać cyfrę.',
            'password.symbols' => 'Hasło musi zawierać znak specjalny.',
        ];
    }
}
