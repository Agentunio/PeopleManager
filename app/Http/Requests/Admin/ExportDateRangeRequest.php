<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ExportDateRangeRequest extends FormRequest
{
    private const MAX_RANGE_DAYS = 366;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }

                $startDate = Carbon::createFromFormat('Y-m-d', (string) $this->input('start_date'));
                $endDate = Carbon::createFromFormat('Y-m-d', (string) $this->input('end_date'));

                if ($startDate->diffInDays($endDate) >= self::MAX_RANGE_DAYS) {
                    $validator->errors()->add(
                        'end_date',
                        'Zakres eksportu nie może przekraczać 366 dni.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'Data początkowa jest wymagana.',
            'start_date.date_format' => 'Data początkowa musi mieć format RRRR-MM-DD.',
            'end_date.required' => 'Data końcowa jest wymagana.',
            'end_date.date_format' => 'Data końcowa musi mieć format RRRR-MM-DD.',
            'end_date.after_or_equal' => 'Data końcowa nie może być wcześniejsza niż początkowa.',
        ];
    }
}
