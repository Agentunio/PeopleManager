<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkerIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['list', 'settlements'])],
            'searchWorker' => ['nullable', 'string', 'max:100'],
            'filterEmployment' => ['nullable', Rule::in(['0', '1'])],
            'filterStudent' => ['nullable', Rule::in(['0', '1'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('searchWorker');

        if (is_string($search)) {
            $this->merge(['searchWorker' => trim($search) ?: null]);
        }
    }
}
