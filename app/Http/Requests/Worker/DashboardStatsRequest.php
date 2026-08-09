<?php

namespace App\Http\Requests\Worker;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class DashboardStatsRequest extends FormRequest
{
    /**
     * Longest span the dashboard calendar ever needs (one month plus the
     * padding days Flatpickr renders). Without a cap a single request could
     * pull the worker's entire shift history.
     */
    private const MAX_SPAN_DAYS = 62;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.required' => 'Data początkowa jest wymagana.',
            'from.date' => 'Data początkowa musi być prawidłową datą.',
            'to.required' => 'Data końcowa jest wymagana.',
            'to.date' => 'Data końcowa musi być prawidłową datą.',
            'to.after_or_equal' => 'Data końcowa nie może być wcześniejsza niż data początkowa.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $span = Carbon::parse($this->input('from'))
                ->startOfDay()
                ->diffInDays(Carbon::parse($this->input('to'))->startOfDay());

            if ($span > self::MAX_SPAN_DAYS) {
                $validator->errors()->add(
                    'to',
                    'Zakres nie może przekraczać ' . self::MAX_SPAN_DAYS . ' dni.'
                );
            }
        });
    }
}
