<?php

namespace App\Services;

use App\Models\WorkerShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlannerCalendarService
{
    private const WEEKDAY_SHORT = [
        1 => 'Pn',
        2 => 'Wt',
        3 => 'Śr',
        4 => 'Cz',
        5 => 'Pt',
        6 => 'Sb',
        7 => 'Nd',
    ];

    public function __construct(
        private readonly ShiftStartService $shiftStartService,
        private readonly PlannerDayStatusService $dayStatusService,
    ) {}

    public function forMonth(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $dates = $this->dateStringsBetween($start, $end);
        $shifts = $this->queryShifts($start->toDateString(), $end->toDateString());
        $shiftsByDay = $shifts->groupBy('day');
        $starts = $this->shiftStartService->scheduleDataForDates($dates);
        $settledDates = array_flip($this->dayStatusService->settledDates(
            $start->toDateString(),
            $end->toDateString(),
        ));

        $days = [];
        foreach ($dates as $date) {
            $days[] = $this->buildDay(
                $date,
                $shiftsByDay->get($date, collect()),
                $starts[$date] ?? [],
                isset($settledDates[$date]),
            );
        }

        return $days;
    }

    public function forDate(string $date): array
    {
        $shifts = $this->queryShifts($date, $date);
        $starts = $this->shiftStartService->scheduleDataForDates([$date]);

        return $this->buildDay(
            $date,
            $shifts,
            $starts[$date] ?? [],
            $this->dayStatusService->isSettled($date),
        );
    }

    private function queryShifts(string $startDate, string $endDate): Collection
    {
        return WorkerShift::query()
            ->select([
                'id',
                'worker_id',
                'day',
                'shift_type',
                'minutes',
                'status',
                'substituted_for_shift_id',
                'is_draft',
            ])
            ->with('worker:id,first_name,last_name')
            ->whereBetween('day', [$startDate, $endDate])
            ->get();
    }

    private function buildDay(
        string $date,
        Collection $shifts,
        array $startData,
        bool $isSettled,
    ): array {
        $carbon = Carbon::parse($date);
        $isDraft = $shifts->contains(fn (WorkerShift $shift) => $shift->is_draft);
        $status = $isSettled ? 'settled' : ($isDraft ? 'draft' : 'active');
        $shiftsByType = $shifts->groupBy('shift_type');

        return [
            'date' => $date,
            'day_number' => $carbon->day,
            'weekday_short' => self::WEEKDAY_SHORT[$carbon->dayOfWeekIso],
            'weekday_long' => $carbon->locale('pl')->translatedFormat('l'),
            'month_name' => $carbon->locale('pl')->translatedFormat('F'),
            'status' => $status,
            'locked' => $isSettled,
            'is_today' => $carbon->isToday(),
            'shifts' => [
                'morning' => $this->buildShift(
                    $shiftsByType->get('morning', collect()),
                    $startData['morning'] ?? [],
                ),
                'afternoon' => $this->buildShift(
                    $shiftsByType->get('afternoon', collect()),
                    $startData['afternoon'] ?? [],
                ),
            ],
        ];
    }

    private function buildShift(Collection $shifts, array $startData): array
    {
        $originals = $shifts
            ->whereNull('substituted_for_shift_id')
            ->sortBy(fn (WorkerShift $shift) => $this->workerSortKey($shift));
        $substitutes = $shifts
            ->whereNotNull('substituted_for_shift_id')
            ->groupBy('substituted_for_shift_id');
        $people = [];
        $usedSubstituteIds = [];

        foreach ($originals as $original) {
            $linkedSubstitutes = $substitutes->get($original->id, collect())
                ->sortBy(fn (WorkerShift $shift) => $this->workerSortKey($shift));
            $people[] = $this->personData($original, $linkedSubstitutes->isNotEmpty());

            foreach ($linkedSubstitutes as $substitute) {
                $people[] = $this->personData($substitute, false, $original);
                $usedSubstituteIds[$substitute->id] = true;
            }
        }

        foreach ($shifts->whereNotNull('substituted_for_shift_id') as $orphanedSubstitute) {
            if (! isset($usedSubstituteIds[$orphanedSubstitute->id])) {
                $people[] = $this->personData($orphanedSubstitute, false);
            }
        }

        return [
            'start_time' => $this->shiftStartService->format($startData['unlock_minutes'] ?? null),
            'people' => $people,
        ];
    }

    private function personData(
        WorkerShift $shift,
        bool $hasSubstitute,
        ?WorkerShift $replacedShift = null,
    ): array {
        $isSubstitute = $shift->substituted_for_shift_id !== null;

        return [
            'shift_id' => $shift->id,
            'name' => trim($shift->worker->first_name.' '.$shift->worker->last_name),
            'state' => $isSubstitute
                ? 'substitute'
                : ($shift->status === 'absent' ? 'unavailable' : 'ok'),
            'has_substitute' => $hasSubstitute,
            'replaces' => $replacedShift !== null
                ? trim($replacedShift->worker->first_name.' '.$replacedShift->worker->last_name)
                : null,
        ];
    }

    private function workerSortKey(WorkerShift $shift): string
    {
        return mb_strtolower($shift->worker->last_name.' '.$shift->worker->first_name);
    }

    private function dateStringsBetween(Carbon $start, Carbon $end): array
    {
        $dates = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }
}
