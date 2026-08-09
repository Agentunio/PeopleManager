<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Schedule;
use App\Models\Worker;
use App\Models\WorkerShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WorkerDashboardService
{
    private const UPCOMING_SHIFTS_LIMIT = 3;

    /**
     * Covers the next-shift day plus UPCOMING_SHIFTS_LIMIT following days even
     * when every day holds both shifts, so no follow-up query is ever needed.
     */
    private const FORWARD_SHIFTS_LIMIT = 12;

    /** Skróty spójne z grafikiem (worker/schedule/index.blade.php). */
    private const WEEKDAY_ABBR = [
        'Monday' => 'Pn',
        'Tuesday' => 'Wt',
        'Wednesday' => 'Śr',
        'Thursday' => 'Cz',
        'Friday' => 'Pt',
        'Saturday' => 'Sb',
        'Sunday' => 'Nd',
    ];

    public function __construct(
        private readonly WorkerStatsService $workerStatsService,
        private readonly ShiftStartService $shiftStartService,
    ) {}

    /**
     * Full payload for the worker dashboard view.
     */
    public function indexData(Worker $worker): array
    {
        $schedule = Schedule::getCurrent();
        $signup = $schedule?->toSignupArray() ?? ['is_active' => false];
        $signup['countdown_label'] = $this->signupCountdownLabel($signup['days_left'] ?? null);

        $monthData = $this->workerStatsService->getDashboardMonthData(
            $worker,
            now()->startOfMonth(),
            now(),
            now()->endOfMonth()
        );
        $stats = $monthData['stats'];

        $today = now()->toDateString();

        $forwardShifts = WorkerShift::published()
            ->where('worker_id', $worker->id)
            ->where('day', '>=', $today)
            ->orderBy('day')
            ->orderBy('shift_type')
            ->limit(self::FORWARD_SHIFTS_LIMIT)
            ->get();

        $lastShifts = WorkerShift::published()
            ->where('worker_id', $worker->id)
            ->whereBetween('day', [now()->startOfWeek()->toDateString(), $today])
            ->orderByDesc('day')
            ->get();

        // One shift_starts lookup for every date the dashboard touches.
        $startDates = $forwardShifts->pluck('day')->map(fn ($day) => $this->dayKey($day));
        if ($lastShifts->isNotEmpty()) {
            $startDates->push($this->dayKey($lastShifts->first()->day));
        }
        $startData = $this->shiftStartService->scheduleDataForDates($startDates->unique()->values()->all());

        [$nextShift, $nextDay] = $this->buildNextShift($forwardShifts, $startData, $today);
        $lastShift = $this->buildLastShift($lastShifts, $startData);
        $workerSelfHoursEnabled = AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS);

        return [
            'worker' => $worker,
            'signup' => $signup,
            'stats' => $stats,
            'salaryTrend' => $this->buildSalaryTrend($worker, (float) $stats['salary']),
            'monthDays' => $monthData['days'],
            'calendar' => [
                'statsUrl' => route('worker.dashboard.stats'),
                'monthStart' => now()->startOfMonth()->toDateString(),
                'monthEnd' => now()->endOfMonth()->toDateString(),
                'today' => $today,
            ],
            'nextShift' => $nextShift,
            'upcomingShifts' => $this->buildUpcomingShifts($forwardShifts, $startData, $nextDay),
            'lastShift' => $lastShift,
            'workerSelfHoursEnabled' => $workerSelfHoursEnabled,
            'showLastShiftCard' => $this->shouldShowLastShiftCard($lastShift, $workerSelfHoursEnabled),
        ];
    }

    /**
     * Month-over-month salary comparison against the same span of the previous
     * month (1st .. same day). subMonthNoOverflow() clamps the day to the length
     * of the previous month, so 31.03 compares against the end of February
     * instead of overflowing into April.
     */
    private function buildSalaryTrend(Worker $worker, float $currentSalary): ?array
    {
        $previousEnd = now()->subMonthNoOverflow();

        $previousSalary = $this->workerStatsService->getSalaryForWorker(
            $worker,
            $previousEnd->copy()->startOfMonth(),
            $previousEnd
        );

        $change = $this->workerStatsService->calculateChange($currentSalary, $previousSalary);

        if ($change === null) {
            return null;
        }

        $change['prev_month_label'] = Str::lower($previousEnd->translatedFormat('F'));

        return $change;
    }

    /**
     * Salary trend for an arbitrary range picked in the dashboard filter.
     *
     * Ranges that start on the 1st are compared against the matching span of
     * the previous month (so "1–15 lipca" is measured against "1–15 czerwca")
     * and keep the month name as the label. Any other range is compared with
     * the equally long span directly preceding it, where a month name would be
     * misleading, so a neutral label is used instead.
     */
    public function buildRangeSalaryTrend(Worker $worker, Carbon $from, Carbon $to): ?array
    {
        if ($from->isSameDay($from->copy()->startOfMonth())) {
            $previousEnd = $to->copy()->subMonthNoOverflow();
            $previousStart = $previousEnd->copy()->startOfMonth();
            $label = Str::lower($previousStart->translatedFormat('F'));
        } else {
            $length = $from->diffInDays($to);
            $previousEnd = $from->copy()->subDay();
            $previousStart = $previousEnd->copy()->subDays($length);
            $label = 'poprzedni okres';
        }

        $change = $this->workerStatsService->calculateChange(
            $this->workerStatsService->getSalaryForWorker($worker, $from, $to),
            $this->workerStatsService->getSalaryForWorker($worker, $previousStart, $previousEnd)
        );

        if ($change === null) {
            return null;
        }

        $change['prev_month_label'] = $label;

        return $change;
    }

    /**
     * @return array{0: array|null, 1: string|null} next shift payload and its day
     */
    private function buildNextShift(Collection $forwardShifts, array $startData, string $today): array
    {
        $currentMinutes = now()->hour * 60 + now()->minute;

        $eligibleShifts = $forwardShifts->take(4)->filter(function (WorkerShift $shift) use (
            $startData,
            $currentMinutes,
            $today
        ): bool {
            if ($shift->day > $today) {
                return true;
            }

            $startMinutes = $startData[$today][$shift->shift_type]['unlock_minutes'] ?? 0;

            return $currentMinutes < $startMinutes;
        });

        $nextDay = $eligibleShifts->pluck('day')->first();

        if ($nextDay === null) {
            return [null, null];
        }

        $nextShiftDay = $eligibleShifts->where('day', $nextDay)->values();
        $firstShift = $nextShiftDay->first();
        $date = Carbon::parse($firstShift->day);
        $nextStartData = $startData[$this->dayKey($firstShift->day)] ?? [];
        // Date-to-date, so a few hours cannot shift the count by one.
        $inDays = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        $nextShift = [
            'date' => $firstShift->day,
            'weekday' => Str::ucfirst($date->translatedFormat('l')),
            'short_date' => $date->translatedFormat('j').' '.Str::ucfirst($date->translatedFormat('F')).' '.$date->format('Y'),
            'shifts' => $nextShiftDay->pluck('shift_type')->toArray(),
            'in_days' => $inDays,
            'in_days_label' => $this->relativeDayLabel($inDays),
            'entries' => $this->buildShiftEntries($nextShiftDay->pluck('shift_type')->all(), $nextStartData),
            'start_labels' => [
                'morning' => $nextStartData['morning']['configured_label'] ?? null,
                'afternoon' => $nextStartData['afternoon']['configured_label'] ?? null,
            ],
        ];

        return [$nextShift, $nextDay];
    }

    /**
     * Following days with shifts, after the upcoming one. The limit counts DAYS
     * (distinct), because a day holding both shifts would otherwise consume two
     * slots of a row-level limit. Built from the prefetched forward window.
     *
     * @return array<int, array{weekday_abbr: string, short_date: string, start_label: string|null}>
     */
    private function buildUpcomingShifts(Collection $forwardShifts, array $startData, ?string $afterDay): array
    {
        if ($afterDay === null) {
            return [];
        }

        $afterKey = $this->dayKey($afterDay);
        $shiftsByDay = $forwardShifts->groupBy(fn (WorkerShift $shift) => $this->dayKey($shift->day));

        return $shiftsByDay->keys()
            ->filter(fn (string $day) => $day > $afterKey)
            ->sort()
            ->take(self::UPCOMING_SHIFTS_LIMIT)
            ->map(function (string $day) use ($shiftsByDay, $startData) {
                $types = $shiftsByDay->get($day, collect())->pluck('shift_type')->all();
                $type = in_array('morning', $types, true) ? 'morning' : 'afternoon';
                $date = Carbon::parse($day);

                return [
                    'weekday_abbr' => self::WEEKDAY_ABBR[$date->format('l')],
                    'short_date' => $date->format('d.m'),
                    'start_label' => $startData[$day][$type]['configured_label']
                        ?? $startData[$day][$type]['unlock_label']
                        ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Zmiany najbliższego dnia w formie „start + typ" (układ z designu).
     * Dzień może mieć obie zmiany — wtedy powstają dwa wiersze.
     *
     * @param  array<int, string>  $shiftTypes
     * @return array<int, array{label: string, start: string|null}>
     */
    private function buildShiftEntries(array $shiftTypes, array $startData): array
    {
        $labels = ['morning' => 'poranna', 'afternoon' => 'popołudniowa'];
        $entries = [];

        foreach ($labels as $type => $label) {
            if (! in_array($type, $shiftTypes, true)) {
                continue;
            }

            $entries[] = [
                'label' => $label,
                'start' => $startData[$type]['configured_label']
                    ?? $startData[$type]['unlock_label']
                    ?? null,
            ];
        }

        return $entries;
    }

    private function relativeDayLabel(int $days): string
    {
        return match (true) {
            $days <= 0 => 'DZIŚ',
            $days === 1 => 'JUTRO',
            default => 'ZA '.$days.' DNI',
        };
    }

    private function signupCountdownLabel(?int $daysLeft): ?string
    {
        if ($daysLeft === null || $daysLeft < 0) {
            return null;
        }

        return match (true) {
            $daysLeft === 0 => 'OSTATNI DZIEŃ',
            $daysLeft === 1 => 'POZOSTAŁ 1 DZIEŃ',
            default => 'POZOSTAŁO '.$daysLeft.' DNI',
        };
    }

    private function shouldShowLastShiftCard(?array $lastShift, bool $workerSelfHoursEnabled): bool
    {
        if (! $lastShift) {
            return false;
        }

        if ($workerSelfHoursEnabled) {
            return true;
        }

        return collect($lastShift['shifts'])->contains(
            fn ($s) => $s['status'] === 'absent' || in_array($s['hours_source'], ['admin', 'worker'], true)
        );
    }

    private function buildLastShift(Collection $shifts, array $startData): ?array
    {
        if ($shifts->isEmpty()) {
            return null;
        }

        $latestDay = $shifts->first()->day;
        $dayShifts = $shifts->where('day', $latestDay);
        $dayStartData = $startData[$this->dayKey($latestDay)] ?? [];

        $date = Carbon::parse($latestDay);
        $isToday = $date->isToday();

        $shiftsData = [];
        foreach ($dayShifts as $shift) {
            $shiftStart = $dayStartData[$shift->shift_type] ?? [];

            $shiftsData[$shift->shift_type] = [
                'status' => $shift->status,
                'hours_source' => $shift->hours_source,
                'minutes' => $shift->minutes,
                'from' => $shift->worker_from_time !== null
                    ? sprintf('%02d:%02d', $shift->worker_from_hour, $shift->worker_from_minute)
                    : null,
                'to' => $shift->worker_to_time !== null
                    ? sprintf('%02d:%02d', $shift->worker_to_hour, $shift->worker_to_minute)
                    : null,
                'start_label' => $shiftStart['configured_label'] ?? null,
                'unlock_minutes' => $shiftStart['unlock_minutes'],
                'unlock_label' => $shiftStart['unlock_label'],
            ];
        }

        $shiftsData = collect($shiftsData)->sortBy(fn ($v, $k) => $k === 'morning' ? 0 : 1)->all();

        $currentMinutes = now()->hour * 60 + now()->minute;
        $allBlocked = true;

        foreach ($shiftsData as $type => &$shift) {
            $shift['blocked'] = false;
            $shift['block_label'] = '';

            if ($shift['status'] !== 'absent' && $shift['hours_source'] !== 'admin') {
                if ($isToday) {
                    $allowedFrom = $shift['unlock_minutes'];
                    if ($currentMinutes < $allowedFrom) {
                        $shift['blocked'] = true;
                        $shift['block_label'] = $shift['unlock_label'];
                    } else {
                        $allBlocked = false;
                    }
                } else {
                    $allBlocked = false;
                }
            }
        }
        unset($shift);

        return [
            'date' => $latestDay,
            'weekday' => Str::ucfirst($date->translatedFormat('l')),
            'short_date' => $date->translatedFormat('j').' '.Str::ucfirst($date->translatedFormat('F')).' '.$date->format('Y'),
            'is_today' => $isToday,
            'shifts' => $shiftsData,
            'all_blocked' => $allBlocked,
        ];
    }

    private function dayKey(string $day): string
    {
        return Carbon::parse($day)->toDateString();
    }
}
