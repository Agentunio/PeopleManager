<?php

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;

class StoreHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_type' => ['required', 'in:morning,afternoon'],
            'from_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'to_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->parseMinutes($this->input('from_time'));
            $to = $this->parseMinutes($this->input('to_time'));

            if ($from === null || $to === null) {
                $validator->errors()->add('from_time', 'Nieprawidłowy format godziny');
                return;
            }

            if ($to <= $from) {
                $validator->errors()->add('to_time', 'Godzina zakończenia musi być późniejsza niż rozpoczęcia');
            }
        });
    }

    private function parseMinutes(?string $time): ?int
    {
        if (!$time || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            return null;
        }

        [$h, $m] = array_map('intval', explode(':', $time));

        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return null;
        }

        return $h * 60 + $m;
    }
}
