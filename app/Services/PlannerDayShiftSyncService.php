<?php

namespace App\Services;

use App\Models\WorkerAvailability;
use App\Models\WorkerShift;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlannerDayShiftSyncService
{
    public function __construct(
        private readonly ShiftStartService $shiftStartService,
        private readonly PlannerDayStatusService $dayStatusService,
    ) {}

    /**
     * Sync the day's shift assignments with the submitted set, preserving
     * work details (status, hours, package) when a worker is moved between
     * shift types within the same day.
     */
    public function sync(string $date, array $validated, bool $isDraft): void
    {
        $submitted = collect($validated['workers'] ?? [])->values()->map(fn (array $entry) => [
            'worker_id' => (int) $entry['worker_id'],
            'shift_type' => (string) $entry['shift_type'],
        ]);

        DB::transaction(function () use ($date, $validated, $submitted, $isDraft) {
            $existing = WorkerShift::query()
                ->where('day', $date)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->ensureEditable($date);
            $this->shiftStartService->saveForDate($date, $validated);

            $submittedByKey = $submitted->keyBy(
                fn (array $entry) => $entry['worker_id'].'_'.$entry['shift_type'],
            );
            $existingByKey = $existing->keyBy(
                fn (WorkerShift $shift) => $shift->worker_id.'_'.$shift->shift_type,
            );
            $toDelete = $existing->filter(
                fn (WorkerShift $shift) => ! $submittedByKey->has($shift->worker_id.'_'.$shift->shift_type),
            );
            $deletedOriginalIds = $toDelete
                ->whereNull('substituted_for_shift_id')
                ->modelKeys();

            if ($deletedOriginalIds !== []) {
                $deletedOriginalLookup = array_fill_keys($deletedOriginalIds, true);
                $substituteKeysToRemove = $existing
                    ->filter(fn (WorkerShift $shift) => $shift->substituted_for_shift_id !== null
                        && isset($deletedOriginalLookup[$shift->substituted_for_shift_id]))
                    ->mapWithKeys(fn (WorkerShift $shift) => [
                        $shift->worker_id.'_'.$shift->shift_type => true,
                    ]);

                $submitted = $submitted
                    ->reject(fn (array $entry) => $substituteKeysToRemove->has(
                        $entry['worker_id'].'_'.$entry['shift_type'],
                    ))
                    ->values();
                $submittedByKey = $submitted->keyBy(
                    fn (array $entry) => $entry['worker_id'].'_'.$entry['shift_type'],
                );
                $toDelete = $existing->filter(
                    fn (WorkerShift $shift) => ! $submittedByKey->has($shift->worker_id.'_'.$shift->shift_type),
                );
            }

            $this->ensureNewAssignmentsAvailable($date, $submitted, $existingByKey);

            $carried = $this->carriedAttributes($existing, $toDelete, $submitted);

            if ($toDelete->isNotEmpty()) {
                WorkerShift::query()->whereKey($toDelete->modelKeys())->delete();
            }

            if ($submitted->isEmpty()) {
                return;
            }

            $timestamp = now();
            $rows = $submitted->map(function (array $entry) use ($carried, $date, $existingByKey, $isDraft, $timestamp): array {
                $key = $entry['worker_id'].'_'.$entry['shift_type'];
                /** @var WorkerShift|null $existingShift */
                $existingShift = $existingByKey->has($key)
                    ? $existingByKey->get($key)
                    : null;
                $attributes = $existingShift?->only([
                    'status',
                    'package_id',
                    'minutes',
                    'worker_from_time',
                    'worker_to_time',
                    'approved_from_time',
                    'approved_to_time',
                    'hours_source',
                ]) ?? ($carried[$key] ?? []);

                return [
                    'worker_id' => $entry['worker_id'],
                    'day' => $date,
                    'shift_type' => $entry['shift_type'],
                    'package_id' => $attributes['package_id'] ?? null,
                    'minutes' => $attributes['minutes'] ?? null,
                    'status' => $attributes['status'] ?? 'worked',
                    'substituted_for_shift_id' => $existingShift?->substituted_for_shift_id,
                    'worker_from_time' => $attributes['worker_from_time'] ?? null,
                    'worker_to_time' => $attributes['worker_to_time'] ?? null,
                    'approved_from_time' => $attributes['approved_from_time'] ?? null,
                    'approved_to_time' => $attributes['approved_to_time'] ?? null,
                    'hours_source' => $attributes['hours_source'] ?? null,
                    'is_draft' => $isDraft,
                    'created_at' => $existingShift->created_at ?? $timestamp,
                    'updated_at' => $timestamp,
                ];
            })->all();

            WorkerShift::query()->upsert(
                $rows,
                ['worker_id', 'day', 'shift_type'],
                [
                    'package_id',
                    'minutes',
                    'status',
                    'substituted_for_shift_id',
                    'worker_from_time',
                    'worker_to_time',
                    'approved_from_time',
                    'approved_to_time',
                    'hours_source',
                    'is_draft',
                    'updated_at',
                ],
            );
        }, 3);
    }

    /**
     * Recheck new assignments under the same transaction that writes them.
     *
     * @param  BaseCollection<int, array{worker_id: int, shift_type: string}>  $submitted
     * @param  BaseCollection<string, WorkerShift>  $existingByKey
     */
    private function ensureNewAssignmentsAvailable(
        string $date,
        BaseCollection $submitted,
        BaseCollection $existingByKey,
    ): void {
        $newAssignments = $submitted
            ->filter(fn (array $entry): bool => ! $existingByKey->has(
                $entry['worker_id'].'_'.$entry['shift_type'],
            ))
            ->values();

        if ($newAssignments->isEmpty()) {
            return;
        }

        $workerIds = $newAssignments
            ->pluck('worker_id')
            ->unique()
            ->sort()
            ->values()
            ->all();
        $availabilityLookup = WorkerAvailability::query()
            ->where('day', $date)
            ->whereIn('worker_id', $workerIds)
            ->orderBy('worker_id')
            ->lockForUpdate()
            ->get(['worker_id', 'morning_shift', 'afternoon_shift'])
            ->mapWithKeys(fn (WorkerAvailability $availability): array => [
                (int) $availability->getAttribute('worker_id') => [
                    'morning' => (bool) $availability->getAttribute('morning_shift'),
                    'afternoon' => (bool) $availability->getAttribute('afternoon_shift'),
                ],
            ])
            ->all();

        foreach ($newAssignments as $entry) {
            if ($availabilityLookup[$entry['worker_id']][$entry['shift_type']] ?? false) {
                continue;
            }

            throw ValidationException::withMessages([
                'workers' => "Dost\u{0119}pno\u{015B}\u{0107} pracownik\u{00F3}w zmieni\u{0142}a si\u{0119}. Od\u{015B}wie\u{017C} grafik i spr\u{00F3}buj ponownie.",
            ]);
        }
    }

    private function ensureEditable(string $date): void
    {
        if ($this->dayStatusService->isSettled($date)) {
            throw ValidationException::withMessages([
                'shift' => 'Rozliczony dzien jest zablokowany.',
            ]);
        }
    }

    /**
     * Work details to copy onto recreated rows when a worker is moved to the
     * other shift type. Moves that would break a substitution pair are rejected,
     * mirroring the invariants enforced by PlannerShiftActionService.
     *
     * @param  Collection<int, WorkerShift>  $existing
     * @param  Collection<int, WorkerShift>  $toDelete
     * @param  BaseCollection<int, array{worker_id: int, shift_type: string}>  $submitted
     * @return array<string, array<string, mixed>>
     */
    private function carriedAttributes(Collection $existing, Collection $toDelete, BaseCollection $submitted): array
    {
        $carried = [];
        $existingByAssignment = $existing->keyBy(
            fn (WorkerShift $shift) => $shift->worker_id.'_'.$shift->shift_type,
        );
        $toDeleteByWorker = $toDelete->keyBy('worker_id');
        $originalIdsWithSubstitute = $existing
            ->whereNotNull('substituted_for_shift_id')
            ->pluck('substituted_for_shift_id')
            ->flip();

        foreach ($submitted as $entry) {
            $assignmentKey = $entry['worker_id'].'_'.$entry['shift_type'];
            if ($existingByAssignment->has($assignmentKey)) {
                continue;
            }

            $source = $toDeleteByWorker->get($entry['worker_id']);

            if ($source === null) {
                continue;
            }

            if ($source->substituted_for_shift_id !== null) {
                throw ValidationException::withMessages([
                    'workers' => 'Zastępstwa nie można przenieść na inną zmianę — usuń je i dodaj ponownie.',
                ]);
            }

            if ($originalIdsWithSubstitute->has($source->id)) {
                throw ValidationException::withMessages([
                    'workers' => 'Nie można przenieść nieobecnego z przypisanym zastępstwem — najpierw usuń zastępstwo.',
                ]);
            }

            $carried[$entry['worker_id'].'_'.$entry['shift_type']] = $source->only([
                'status',
                'package_id',
                'minutes',
                'worker_from_time',
                'worker_to_time',
                'approved_from_time',
                'approved_to_time',
                'hours_source',
            ]);
        }

        return $carried;
    }
}
