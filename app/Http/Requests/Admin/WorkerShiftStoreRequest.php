<?php

namespace App\Http\Requests\Admin;

use App\Rules\WorkerAvailableForShift;
use Illuminate\Foundation\Http\FormRequest;

class WorkerShiftStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'workers' => 'array',
            'morning_start_time' => 'nullable|date_format:H:i',
            'afternoon_start_time' => 'nullable|date_format:H:i',
            'workers.*.worker_id' => [
                'required',
                'exists:workers,id',
                new WorkerAvailableForShift(request()->route('date'), 'shift_type')
            ],
            'workers.*.shift_type' => 'required|in:morning,afternoon',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
