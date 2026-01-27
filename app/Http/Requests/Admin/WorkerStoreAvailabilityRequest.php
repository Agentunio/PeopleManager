<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WorkerStoreAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workers' => ['required', 'array'],
            'workers.*.worker_id' => 'required|exists:workers,id',
            'workers.*.morning_shift' => ['nullable'],
            'workers.*.afternoon_shift' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'worker_id.required' => 'ID pracownika jest wymagane.',
        ];
    }
}
