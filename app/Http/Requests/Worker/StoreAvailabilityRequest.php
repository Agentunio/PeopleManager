<?php

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'morning_shift' => ['nullable', 'boolean'],
            'afternoon_shift' => ['nullable', 'boolean'],
        ];
    }
}
