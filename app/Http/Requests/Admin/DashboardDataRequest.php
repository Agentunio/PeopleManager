<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class DashboardDataRequest extends FormRequest
{
    /**
     * The range calendar allows picking up to ~13 months back; anything longer
     * would materialize multi-year aggregates (twice, with comparison) in PHP.
     */
    private const MAX_SPAN_DAYS = 400;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'compare_start_date' => 'nullable|date',
            'compare_end_date' => 'nullable|date|after_or_equal:compare_start_date|required_with:compare_start_date',
            'page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'shift' => ['nullable', 'in:total,morning,afternoon'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->ensureSpanWithinLimit($validator, 'start_date', 'end_date');

            if ($this->filled('compare_start_date')) {
                $this->ensureSpanWithinLimit($validator, 'compare_start_date', 'compare_end_date');
            }
        });
    }

    private function ensureSpanWithinLimit(Validator $validator, string $fromField, string $toField): void
    {
        $span = Carbon::parse($this->input($fromField))
            ->startOfDay()
            ->diffInDays(Carbon::parse($this->input($toField))->startOfDay());

        if ($span > self::MAX_SPAN_DAYS) {
            $validator->errors()->add(
                $toField,
                'Zakres nie może przekraczać '.self::MAX_SPAN_DAYS.' dni.'
            );
        }
    }
}
