<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WorkerShiftRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'workers' => ['required', 'array'],
            'workers.*.worker_id' => 'required|exists:workers,id',
            'workers.*.shift_type' => 'in:morning,afternoon',
            'workers.*.package_id' => 'exists:settings,id',
            'workers.*.hours' => ['nullable', 'numeric'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
