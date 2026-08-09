<?php

namespace App\Rules;

use App\Models\Worker;
use App\Models\WorkerAvailability;
use App\Models\WorkerShift;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Validator;

class WorkerAvailableForShift implements ValidationRule
{
    public function __construct(
        protected string $date,
        protected string $shiftTypeField,
    ) {}

    /**
     * Validate worker IDs and availability for all submitted assignments with
     * a bounded set of queries instead of one query per worker and shift.
     *
     * @param  array<array-key, mixed>  $entries
     */
    public static function validateBatch(array $entries, string $date, Validator $validator): void
    {
        $validEntries = [];

        foreach ($entries as $key => $entry) {
            if (! is_array($entry)
                || $validator->errors()->has("workers.{$key}.worker_id")
                || $validator->errors()->has("workers.{$key}.shift_type")) {
                continue;
            }

            $validEntries[$key] = [
                'worker_id' => (int) $entry['worker_id'],
                'shift_type' => (string) $entry['shift_type'],
            ];
        }

        if ($validEntries === []) {
            return;
        }

        $workerIds = array_values(array_unique(array_column($validEntries, 'worker_id')));
        $knownWorkerIds = Worker::query()->whereKey($workerIds)->pluck('id')->all();
        $knownWorkerLookup = array_fill_keys($knownWorkerIds, true);

        foreach ($validEntries as $key => $entry) {
            if (! isset($knownWorkerLookup[$entry['worker_id']])) {
                $validator->errors()->add("workers.{$key}.worker_id", 'Wybrany pracownik jest nieprawidłowy.');
            }
        }

        if ($knownWorkerIds === []) {
            return;
        }

        $assignedLookup = WorkerShift::query()
            ->where('day', $date)
            ->whereIn('worker_id', $knownWorkerIds)
            ->get(['worker_id', 'shift_type'])
            ->mapWithKeys(fn (WorkerShift $shift) => ["{$shift->worker_id}_{$shift->shift_type}" => true])
            ->all();
        $availabilityByWorker = WorkerAvailability::query()
            ->where('day', $date)
            ->whereIn('worker_id', $knownWorkerIds)
            ->get(['worker_id', 'morning_shift', 'afternoon_shift'])
            ->keyBy('worker_id');

        foreach ($validEntries as $key => $entry) {
            if (! isset($knownWorkerLookup[$entry['worker_id']])
                || isset($assignedLookup["{$entry['worker_id']}_{$entry['shift_type']}"])) {
                continue;
            }

            $availability = $availabilityByWorker->get($entry['worker_id']);
            $column = $entry['shift_type'].'_shift';

            if (! $availability instanceof WorkerAvailability || ! (bool) $availability->getAttribute($column)) {
                $validator->errors()->add("workers.{$key}.worker_id", 'Pracownik nie jest dostępny na tę zmianę.');
            }
        }
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $index = explode('.', $attribute)[1];
        $shiftType = request()->input("workers.{$index}.shift_type");

        if (! in_array($shiftType, ['morning', 'afternoon'], true)) {
            $fail('Nieprawidłowy typ zmiany.');

            return;
        }

        $alreadyAssigned = WorkerShift::where('worker_id', $value)
            ->where('day', $this->date)
            ->where('shift_type', $shiftType)
            ->exists();

        if ($alreadyAssigned) {
            return;
        }

        $available = WorkerAvailability::where('worker_id', $value)
            ->where('day', $this->date)
            ->where($shiftType.'_shift', true)
            ->exists();

        if (! $available) {
            $fail('Pracownik nie jest dostępny na tę zmianę.');
        }
    }
}
