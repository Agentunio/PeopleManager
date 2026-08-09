<?php

namespace App\Http\Requests\Guest;

use App\Support\PasswordValidation;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => PasswordValidation::rules(),
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Brakuje tokenu resetującego hasło.',
            'email.required' => 'Adres e-mail jest wymagany.',
            'email.email' => 'Podaj prawidłowy adres e-mail.',
            ...PasswordValidation::messages(),
        ];
    }
}
