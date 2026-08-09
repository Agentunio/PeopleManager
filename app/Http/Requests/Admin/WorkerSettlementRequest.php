<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorkerSettlementRequest extends FormRequest
{
    private const MAX_RANGE_DAYS = 366;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workerId' => ['nullable', 'integer', 'exists:workers,id'],
            'dateFrom' => ['required', 'date_format:Y-m-d'],
            'dateTo' => ['required', 'date_format:Y-m-d', 'after_or_equal:dateFrom'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'searchWorker' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('searchWorker');

        if (! is_string($search)) {
            return;
        }

        $this->merge([
            'searchWorker' => trim($search) ?: null,
        ]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['dateFrom', 'dateTo'])) {
                    return;
                }

                $dateFrom = Carbon::createFromFormat('Y-m-d', (string) $this->input('dateFrom'));
                $dateTo = Carbon::createFromFormat('Y-m-d', (string) $this->input('dateTo'));

                if ($dateFrom->diffInDays($dateTo) >= self::MAX_RANGE_DAYS) {
                    $validator->errors()->add(
                        'dateTo',
                        'Zakres rozliczenia nie może przekraczać 366 dni.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'workerId.exists' => 'Wybrany pracownik nie istnieje.',
            'dateFrom.required' => 'Data początkowa jest wymagana.',
            'dateFrom.date_format' => 'Data początkowa musi mieć format RRRR-MM-DD.',
            'dateTo.required' => 'Data końcowa jest wymagana.',
            'dateTo.date_format' => 'Data końcowa musi mieć format RRRR-MM-DD.',
            'dateTo.after_or_equal' => 'Data końcowa nie może być wcześniejsza niż początkowa.',
            'page.integer' => 'Numer strony musi być liczbą całkowitą.',
            'page.min' => 'Numer strony musi być większy od zera.',
            'page.max' => 'Numer strony jest zbyt duży.',
            'searchWorker.string' => 'Wyszukiwana fraza musi być tekstem.',
            'searchWorker.max' => 'Wyszukiwana fraza może mieć maksymalnie 100 znaków.',
        ];
    }
}
