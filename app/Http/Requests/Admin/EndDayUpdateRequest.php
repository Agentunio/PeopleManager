<?php

namespace App\Http\Requests\Admin;

use App\Models\WorkerShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'workers.*.is_substitute' => 'nullable|boolean',
            'workers.*.substituted_for_shift_id' => [
                'nullable', 'integer',
                Rule::exists('worker_shifts', 'id')->where(function ($query) {
                    $query->where('day', $this->route('date'));
                }),
            ],
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
            $usedSubstitutedShiftIds = [];
            $allWorkers = $this->input('workers', []);

            foreach ($allWorkers as $index => $worker) {
                if (!empty($worker['is_substitute']) && !empty($worker['substituted_for_shift_id'])) {
                    $absentShift = WorkerShift::find($worker['substituted_for_shift_id']);

                    if ($absentShift) {
                        if ($absentShift->worker_id == $worker['id']) {
                            $validator->errors()->add(
                                "workers.{$index}.id",
                                'Pracownik nie może zastąpić samego siebie'
                            );
                        }

                        $submittedKey = "{$absentShift->worker_id}_{$absentShift->shift_type}";
                        $submittedStatus = $allWorkers[$submittedKey]['status'] ?? null;
                        $dbIsAbsent = $absentShift->status === 'absent';

                        if ($submittedStatus !== 'absent' && !$dbIsAbsent) {
                            $validator->errors()->add(
                                "workers.{$index}.substituted_for_shift_id",
                                'Zastępstwo można przypisać tylko za nieobecnego pracownika'
                            );
                        }
                    }

                    $alreadyOnShift = WorkerShift::where('worker_id', $worker['id'])
                        ->where('day', $this->route('date'))
                        ->where('shift_type', $worker['shift_type'] ?? '')
                        ->whereNull('substituted_for_shift_id')
                        ->exists();

                    if ($alreadyOnShift) {
                        $validator->errors()->add(
                            "workers.{$index}.id",
                            'Ten pracownik jest już przypisany do tej zmiany'
                        );
                    }

                    if (in_array($worker['substituted_for_shift_id'], $usedSubstitutedShiftIds)) {
                        $validator->errors()->add(
                            "workers.{$index}.substituted_for_shift_id",
                            'Dla jednej nieobecności można przypisać tylko jedno zastępstwo'
                        );
                    }
                    $usedSubstitutedShiftIds[] = $worker['substituted_for_shift_id'];
                }

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
