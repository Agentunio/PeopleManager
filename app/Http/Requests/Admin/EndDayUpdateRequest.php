<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EndDayUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $workers = $this->input('workers', []);

        foreach ($workers as $key => $worker) {
            foreach (['from_hour', 'to_hour'] as $field) {
                $value = $worker[$field] ?? null;

                if ($value !== null && is_string($value) && str_contains($value, ':')) {
                    $parts = explode(':', $value);
                    $workers[$key][$field] = (int) $parts[0];

                    $minuteField = str_replace('_hour', '_minute', $field);
                    if (empty($worker[$minuteField]) && isset($parts[1])) {
                        $workers[$key][$minuteField] = (int) $parts[1];
                    }
                } elseif ($value !== null && $value !== '' && is_numeric($value)) {
                    $workers[$key][$field] = (int) $value;
                }
            }

            foreach (['from_minute', 'to_minute'] as $field) {
                $value = $workers[$key][$field] ?? null;
                if ($value !== null && $value !== '' && is_numeric($value)) {
                    $workers[$key][$field] = (int) $value;
                }
            }
        }

        $this->merge(['workers' => $workers]);
    }

    public function rules(): array
    {
        return [
            'workers' => 'array',
            'workers.*.id' => 'required|exists:workers,id',
            'workers.*.shift_type' => 'required|in:morning,afternoon',
            'workers.*.status' => 'nullable|in:worked,absent',
            'workers.*.package' => 'nullable|exists:packages,id',
            'workers.*.from_hour' => 'nullable|integer|min:0|max:23',
            'workers.*.from_minute' => 'nullable|integer|min:0|max:59',
            'workers.*.to_hour' => 'nullable|integer|min:0|max:23',
            'workers.*.to_minute' => 'nullable|integer|min:0|max:59',
            'morning_package_entries' => 'nullable|array|max:50',
            'morning_package_entries.*.packages_count' => 'nullable|integer|min:0',
            'morning_package_entries.*.package_id' => 'nullable|exists:packages,id',
            'afternoon_package_entries' => 'nullable|array|max:50',
            'afternoon_package_entries.*.packages_count' => 'nullable|integer|min:0',
            'afternoon_package_entries.*.package_id' => 'nullable|exists:packages,id',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach ($this->workers ?? [] as $index => $worker) {
                if (($worker['status'] ?? '') === 'absent') {
                    continue;
                }

                $fromMinutes = (($worker['from_hour'] ?? 0) * 60) + ($worker['from_minute'] ?? 0);
                $toMinutes = (($worker['to_hour'] ?? 0) * 60) + ($worker['to_minute'] ?? 0);

                if ($toMinutes <= $fromMinutes && !empty($worker['to_hour'])) {
                    $validator->errors()->add(
                        "workers.{$index}.to_hour",
                        'Godzina zakończenia musi być późniejsza niż rozpoczęcia'
                    );
                }
            }
        });
    }

    public function authorize(): bool
    {
        return true;
    }
}
