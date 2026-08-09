<?php

namespace App\Http\Requests\Admin;

use App\Rules\WorkerAvailableForShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorkerShiftStoreRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            '_route_date' => (string) $this->route('date'),
        ]);
    }

    public function rules(): array
    {
        return [
            'workers' => 'array',
            '_route_date' => ['required', Rule::date()->format('Y-m-d')],
            'is_draft' => ['sometimes', 'boolean'],
            'morning_start_time' => 'nullable|date_format:H:i',
            'afternoon_start_time' => 'nullable|date_format:H:i',
            'workers.*.worker_id' => ['bail', 'required', 'integer'],
            'workers.*.shift_type' => 'required|in:morning,afternoon',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $entries = is_array($this->input('workers')) ? $this->input('workers') : [];
            $seenAssignments = [];

            foreach ($entries as $key => $entry) {
                if (! is_array($entry)
                    || $validator->errors()->has("workers.{$key}.worker_id")
                    || $validator->errors()->has("workers.{$key}.shift_type")) {
                    continue;
                }

                $assignmentKey = ((int) $entry['worker_id']).'_'.((string) $entry['shift_type']);

                if (isset($seenAssignments[$assignmentKey])) {
                    $validator->errors()->add(
                        "workers.{$key}.shift_type",
                        'Pracownik moze byc przypisany do danej zmiany tylko raz.',
                    );

                    continue;
                }

                $seenAssignments[$assignmentKey] = true;
            }

            WorkerAvailableForShift::validateBatch(
                $entries,
                (string) $this->route('date'),
                $validator,
            );
        });
    }

    public function authorize(): bool
    {
        return true;
    }
}
