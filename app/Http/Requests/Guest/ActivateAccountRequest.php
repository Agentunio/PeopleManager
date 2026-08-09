<?php

namespace App\Http\Requests\Guest;

use App\Support\PasswordValidation;
use Illuminate\Foundation\Http\FormRequest;

class ActivateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => PasswordValidation::rules(),
        ];
    }

    public function messages(): array
    {
        return PasswordValidation::messages();
    }
}
