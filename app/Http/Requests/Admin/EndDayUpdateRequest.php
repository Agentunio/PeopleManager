<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EndDayUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'workers' => 'array',
            'workers.*.id' => 'required|exists:workers,id',
            'workers.*.shift_type' => 'required|in:morning,afternoon',
            'workers.*.package' => 'nullable|exists:packages,id',
            'workers.*.from_hour' => 'nullable|integer|min:0|max:23',
            'workers.*.from_minute' => 'nullable|integer|min:0|max:59',
            'workers.*.to_hour' => 'nullable|integer|min:0|max:23',
            'workers.*.to_minute' => 'nullable|integer|min:0|max:59',
            'morning_packages' => 'nullable|integer|min:0',
            'morning_package_rate' => 'nullable|exists:packages,id',
            'afternoon_packages' => 'nullable|integer|min:0',
            'afternoon_package_rate' => 'nullable|exists:packages,id',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach ($this->workers ?? [] as $index => $worker) {
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
