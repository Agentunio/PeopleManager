<?php

namespace App\Rules;

use App\Models\WorkerAvailability;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WorkerAvailableForShift implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function __construct(
        protected string $date,
        protected string $shiftTypeField
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $index = explode('.', $attribute)[1];
        $shiftType = request()->input("workers.{$index}.shift_type");

        $available = WorkerAvailability::where('worker_id', $value)
            ->where('day', $this->date)
            ->where($shiftType . '_shift', true)
            ->exists();

        if (!$available) {
            $fail('Pracownik nie jest dostępny na tę zmianę.');
        }
    }
}
