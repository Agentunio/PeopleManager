<?php

namespace App\Services;

use App\Models\Worker;
use App\Models\WorkerShift;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlannerShiftActionService
{
    public function __construct(
        private readonly PlannerDayStatusService $dayStatusService,
    ) {}

    public function updateStatus(WorkerShift $workerShift, string $status): WorkerShift
    {
        return DB::transaction(function () use ($workerShift, $status) {
            $shift = $this->lockedShift($workerShift);
            $this->ensureOriginalShift($shift);
            $this->ensureEditable($shift);

            $clearedWorkDetails = [
                'package_id' => null,
                'worker_from_time' => null,
                'worker_to_time' => null,
                'approved_from_time' => null,
                'approved_to_time' => null,
                'hours_source' => null,
            ];

            if ($status === 'absent') {
                $shift->update([
                    ...$clearedWorkDetails,
                    'status' => 'absent',
                    'minutes' => 0,
                ]);
            } else {
                WorkerShift::query()
                    ->where('substituted_for_shift_id', $shift->id)
                    ->delete();
                $shift->update([
                    ...$clearedWorkDetails,
                    'status' => 'worked',
                    'minutes' => null,
                ]);
            }

            return $shift->refresh();
        });
    }

    public function substituteCandidates(WorkerShift $workerShift): Collection
    {
        $this->ensureOriginalShift($workerShift);
        $this->ensureEditable($workerShift);

        if ($workerShift->status !== 'absent') {
            $this->fail('shift', 'Zastępstwo można dodać wyłącznie za nieobecnego pracownika.');
        }

        if (WorkerShift::query()->where('substituted_for_shift_id', $workerShift->id)->exists()) {
            $this->fail('shift', 'Ta nieobecność ma już przypisane zastępstwo.');
        }

        $availabilityColumn = $workerShift->shift_type === 'morning'
            ? 'morning_shift'
            : 'afternoon_shift';
        $assignedWorkerIds = WorkerShift::query()
            ->where('day', $workerShift->day)
            ->where('shift_type', $workerShift->shift_type)
            ->pluck('worker_id');

        return Worker::query()
            ->select(['id', 'first_name', 'last_name'])
            ->withExists(['availabilities as is_available' => function ($query) use ($workerShift, $availabilityColumn) {
                $query->where('day', $workerShift->day)
                    ->where($availabilityColumn, true);
            }])
            ->where('is_employed', true)
            ->whereNotIn('id', $assignedWorkerIds)
            ->orderByDesc('is_available')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Worker $worker) => [
                'id' => $worker->id,
                'name' => trim($worker->first_name.' '.$worker->last_name),
                'is_available' => (bool) $worker->is_available,
            ]);
    }

    public function addSubstitute(WorkerShift $workerShift, int $workerId): WorkerShift
    {
        return DB::transaction(function () use ($workerShift, $workerId) {
            $shift = $this->lockedShift($workerShift);
            $this->ensureOriginalShift($shift);
            $this->ensureEditable($shift);

            if ($shift->status !== 'absent') {
                $this->fail('worker_id', 'Zastępstwo można dodać wyłącznie za nieobecnego pracownika.');
            }

            if ($shift->worker_id === $workerId) {
                $this->fail('worker_id', 'Pracownik nie może zastąpić samego siebie.');
            }

            if (WorkerShift::query()->where('substituted_for_shift_id', $shift->id)->exists()) {
                $this->fail('worker_id', 'Dla tej nieobecności przypisano już zastępstwo.');
            }

            $candidate = Worker::query()
                ->whereKey($workerId)
                ->where('is_employed', true)
                ->lockForUpdate()
                ->first();

            if ($candidate === null) {
                $this->fail('worker_id', 'Wybrany pracownik nie jest obecnie zatrudniony.');
            }

            $alreadyAssigned = WorkerShift::query()
                ->where('worker_id', $candidate->id)
                ->where('day', $shift->day)
                ->where('shift_type', $shift->shift_type)
                ->exists();

            if ($alreadyAssigned) {
                $this->fail('worker_id', 'Ten pracownik jest już przypisany do tej zmiany.');
            }

            return WorkerShift::create([
                'worker_id' => $candidate->id,
                'day' => $shift->day,
                'shift_type' => $shift->shift_type,
                'status' => 'worked',
                'substituted_for_shift_id' => $shift->id,
                'is_draft' => $shift->is_draft,
            ]);
        });
    }

    public function remove(WorkerShift $workerShift): void
    {
        DB::transaction(function () use ($workerShift) {
            $shift = $this->lockedShift($workerShift);
            $this->ensureEditable($shift);

            if ($shift->substituted_for_shift_id === null) {
                WorkerShift::query()
                    ->where('substituted_for_shift_id', $shift->id)
                    ->delete();
            }

            $shift->delete();
        });
    }

    private function lockedShift(WorkerShift $workerShift): WorkerShift
    {
        return WorkerShift::query()
            ->lockForUpdate()
            ->findOrFail($workerShift->id);
    }

    private function ensureOriginalShift(WorkerShift $workerShift): void
    {
        if ($workerShift->substituted_for_shift_id !== null) {
            $this->fail('shift', 'Ta operacja jest dostępna tylko dla pierwotnego przydziału.');
        }
    }

    private function ensureEditable(WorkerShift $workerShift): void
    {
        if ($this->dayStatusService->isSettled((string) $workerShift->day)) {
            $this->fail('shift', 'Rozliczony dzień jest zablokowany.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
