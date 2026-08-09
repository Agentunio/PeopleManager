<?php

namespace App\Services;

use App\Models\Worker;
use App\Models\WorkerShift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WorkerStatsService
{
    private const DASHBOARD_WORKERS_PER_PAGE = 10;

    private const SETTLEMENT_WORKERS_PER_PAGE = 10;

    private const WORKER_COST_SQL = "CASE WHEN worker_shifts.status = 'absent' THEN 0 "
        .'ELSE COALESCE(worker_shifts.minutes, 0) * COALESCE(packages.price, 0) / 60.0 END';

    public function calculateStats(Collection $shifts): array
    {
        return $this->calculateStatsData($shifts, true);
    }

    private function calculateStatsData(Collection $shifts, bool $includeAbsentDetails): array
    {
        $byShiftType = $shifts->groupBy('shift_type');

        $stats = $this->buildStats($shifts, $includeAbsentDetails);
        $stats['byShift'] = [
            'morning' => $this->buildStats($byShiftType->get('morning', collect()), $includeAbsentDetails),
            'afternoon' => $this->buildStats($byShiftType->get('afternoon', collect()), $includeAbsentDetails),
        ];

        return $stats;
    }

    private function buildStats(Collection $shifts, bool $includeAbsentDetails): array
    {
        $absentShifts = $shifts->where('status', 'absent');
        $workedShifts = $shifts->where('status', '!=', 'absent');

        $totalMinutes = (int) $workedShifts->sum('minutes');

        $totalSalary = $workedShifts->sum(function ($shift) {
            $hours = $shift->minutes / 60;
            $hourlyRate = $shift->package?->price ?? 0;

            return $hours * $hourlyRate;
        });

        $absentDays = $includeAbsentDetails
            ? $absentShifts->map(function ($shift) {
                $substituteWorker = $shift->substitute?->worker;

                return [
                    'day' => $shift->day,
                    'substitute' => $substituteWorker
                        ? ($substituteWorker->first_name.' '.$substituteWorker->last_name)
                        : null,
                ];
            })->unique('day')->sortBy('day')->values()->toArray()
            : [];

        return [
            'hours' => $this->formatHours($totalMinutes),
            'salary' => round($totalSalary, 2),
            'totalMinutes' => $totalMinutes,
            'absences' => $absentShifts->pluck('day')->unique()->count(),
            'absentDays' => $absentDays,
        ];
    }

    public function formatHours(int $totalMinutes): string
    {
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        $formatted = '';
        if ($hours > 0) {
            $formatted .= $hours.'h';
        }
        if ($minutes > 0) {
            $formatted .= ($hours > 0 ? ' ' : '').$minutes.'min';
        }

        return $formatted ?: '0h';
    }

    public function getStatsForWorker(Worker $worker, Carbon $dateFrom, Carbon $dateTo): array
    {
        $shifts = $worker->shifts()
            ->published()
            ->with(['package', 'substitute.worker'])
            ->whereBetween('day', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get();

        return $this->calculateStats($shifts);
    }

    /**
     * Salary alone, aggregated in SQL — the MoM trend needs no other stats,
     * so hydrating shifts with substitutes would be wasted work.
     */
    public function getSalaryForWorker(Worker $worker, Carbon $dateFrom, Carbon $dateTo): float
    {
        $salary = $worker->shifts()
            ->published()
            ->leftJoin('packages', 'packages.id', '=', 'worker_shifts.package_id')
            ->whereBetween('worker_shifts.day', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('SUM('.self::WORKER_COST_SQL.') AS salary')
            ->value('salary');

        return round((float) $salary, 2);
    }

    public function getStatsForWorkers(
        Collection $workers,
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $shiftType = null
    ): Collection {
        $workerIds = $workers->pluck('id');

        $shifts = WorkerShift::query()
            ->select([
                'id',
                'worker_id',
                'day',
                'shift_type',
                'package_id',
                'minutes',
                'status',
                'substituted_for_shift_id',
            ])
            ->with(['package:id,price', 'substitute.worker:id,first_name,last_name'])
            ->published()
            ->whereIn('worker_id', $workerIds)
            ->whereBetween('day', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($shiftType !== null, fn (Builder $query) => $query->where('shift_type', $shiftType))
            ->get()
            ->groupBy('worker_id');

        return $workers->each(function ($worker) use ($shifts) {
            $workerShifts = $shifts->get($worker->id, collect());
            $worker->stats = $this->calculateStats($workerShifts);
        });
    }

    /**
     * Return the exact worker rows needed by the cost PDF without hydrating
     * every shift, package and substitution model in the selected period.
     */
    public function getCostExportRows(Carbon $dateFrom, Carbon $dateTo): Collection
    {
        return Worker::query()
            ->select(['workers.id', 'workers.first_name', 'workers.last_name'])
            ->join('worker_shifts', 'worker_shifts.worker_id', '=', 'workers.id')
            ->leftJoin('packages', 'packages.id', '=', 'worker_shifts.package_id')
            ->where('worker_shifts.is_draft', false)
            ->whereBetween('worker_shifts.day', [
                $dateFrom->toDateString(),
                $dateTo->toDateString(),
            ])
            ->groupBy('workers.id', 'workers.first_name', 'workers.last_name')
            ->selectRaw(
                "SUM(CASE WHEN worker_shifts.status = 'absent' THEN 0 "
                .'ELSE COALESCE(worker_shifts.minutes, 0) END) AS export_total_minutes'
            )
            ->selectRaw('SUM('.self::WORKER_COST_SQL.') AS export_salary')
            ->orderBy('workers.id')
            ->get()
            ->each(function (Worker $worker): void {
                $totalMinutes = (int) $worker->getAttribute('export_total_minutes');

                $worker->setAttribute('stats', [
                    'hours' => $this->formatHours($totalMinutes),
                    'totalMinutes' => $totalMinutes,
                    'salary' => round((float) $worker->getAttribute('export_salary'), 2),
                ]);
            });
    }

    public function getCostByShift(Carbon $dateFrom, Carbon $dateTo): array
    {
        $costs = WorkerShift::query()
            ->leftJoin('packages', 'packages.id', '=', 'worker_shifts.package_id')
            ->published()
            ->whereBetween('worker_shifts.day', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('worker_shifts.shift_type, SUM('.self::WORKER_COST_SQL.') AS cost')
            ->groupBy('worker_shifts.shift_type')
            ->pluck('cost', 'shift_type');

        return [
            'morning' => round((float) $costs->get('morning', 0), 2),
            'afternoon' => round((float) $costs->get('afternoon', 0), 2),
        ];
    }

    public function getDashboardWorkerPage(
        Carbon $dateFrom,
        Carbon $dateTo,
        int $page,
        string $shift
    ): LengthAwarePaginator {
        $paginator = $this->dashboardWorkerQuery($dateFrom, $dateTo, $shift)->paginate(
            self::DASHBOARD_WORKERS_PER_PAGE,
            ['*'],
            'page',
            $page
        );

        if ($paginator->isEmpty() && $paginator->total() > 0 && $page > $paginator->lastPage()) {
            $paginator = $this->dashboardWorkerQuery($dateFrom, $dateTo, $shift)->paginate(
                self::DASHBOARD_WORKERS_PER_PAGE,
                ['*'],
                'page',
                $paginator->lastPage()
            );
        }

        return $paginator->setCollection(
            $this->getStatsForWorkers(
                $paginator->getCollection(),
                $dateFrom,
                $dateTo,
                $shift === 'total' ? null : $shift
            )
        );
    }

    private function dashboardWorkerQuery(Carbon $dateFrom, Carbon $dateTo, string $shift): Builder
    {
        return Worker::query()
            ->select(['workers.id', 'workers.first_name', 'workers.last_name'])
            ->join('worker_shifts', 'worker_shifts.worker_id', '=', 'workers.id')
            ->leftJoin('packages', 'packages.id', '=', 'worker_shifts.package_id')
            ->where('worker_shifts.is_draft', false)
            ->whereBetween('worker_shifts.day', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when(
                $shift !== 'total',
                fn (Builder $query) => $query->where('worker_shifts.shift_type', $shift)
            )
            ->groupBy('workers.id', 'workers.first_name', 'workers.last_name')
            ->selectRaw('SUM('.self::WORKER_COST_SQL.') AS dashboard_salary')
            ->orderByDesc('dashboard_salary')
            ->orderBy('workers.last_name')
            ->orderBy('workers.first_name');
    }

    public function getSettlementData(
        ?int $workerId,
        Carbon $dateFrom,
        Carbon $dateTo,
        int $page = 1,
        ?string $search = null
    ): array {
        $workerPaginator = $this->paginateSettlementWorkers($page, $search);
        $workers = $workerPaginator->getCollection();

        if ($workers->isEmpty()) {
            return [
                'range' => [
                    'from' => $dateFrom->toDateString(),
                    'to' => $dateTo->toDateString(),
                ],
                'workers' => [],
                'selected' => null,
                'pagination' => $this->settlementPaginationMeta($workerPaginator),
            ];
        }

        $selectedWorker = $workers->firstWhere('id', $workerId);

        if ($selectedWorker === null && $workerId !== null && $search === null) {
            $selectedWorker = Worker::query()
                ->select(['id', 'first_name', 'last_name'])
                ->find($workerId);
        }

        $selectedWorker ??= $workers->first();

        // Page summaries need only worked-minutes and salary totals — aggregate
        // them in SQL instead of hydrating every shift of every listed worker.
        $summaryTotals = WorkerShift::query()
            ->leftJoin('packages', 'packages.id', '=', 'worker_shifts.package_id')
            ->published()
            ->whereIn('worker_shifts.worker_id', $workers->pluck('id'))
            ->whereBetween('worker_shifts.day', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->groupBy('worker_shifts.worker_id')
            ->selectRaw('worker_shifts.worker_id AS worker_id')
            ->selectRaw(
                "SUM(CASE WHEN worker_shifts.status = 'absent' THEN 0 "
                .'ELSE COALESCE(worker_shifts.minutes, 0) END) AS total_minutes'
            )
            ->selectRaw('SUM('.self::WORKER_COST_SQL.') AS salary')
            ->get()
            ->keyBy('worker_id');

        $summaries = $workers->map(function (Worker $worker) use ($summaryTotals): array {
            $totals = $summaryTotals->get($worker->id);
            $totalMinutes = (int) ($totals->total_minutes ?? 0);

            return $this->settlementSummary($worker, [
                'hours' => $this->formatHours($totalMinutes),
                'totalMinutes' => $totalMinutes,
                'salary' => round((float) ($totals->salary ?? 0), 2),
            ]);
        })->values();

        $selectedShifts = WorkerShift::query()
            ->select([
                'id',
                'worker_id',
                'day',
                'shift_type',
                'package_id',
                'minutes',
                'status',
            ])
            ->with('package:id,price')
            ->published()
            ->where('worker_id', $selectedWorker->id)
            ->whereBetween('day', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get();

        $selectedStats = $this->calculateStatsData($selectedShifts, false);

        return [
            'range' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
            'workers' => $summaries,
            'selected' => [
                ...$this->settlementSummary($selectedWorker, $selectedStats),
                'byShift' => $selectedStats['byShift'],
                'absences' => $selectedStats['absences'],
                'days' => $this->settlementDays($selectedShifts, $dateFrom, $dateTo),
            ],
            'pagination' => $this->settlementPaginationMeta($workerPaginator),
        ];
    }

    private function paginateSettlementWorkers(int $page, ?string $search): LengthAwarePaginator
    {
        $paginator = $this->settlementWorkerQuery($search)->paginate(
            self::SETTLEMENT_WORKERS_PER_PAGE,
            ['*'],
            'page',
            $page
        );

        if ($paginator->isEmpty() && $paginator->total() > 0 && $page > $paginator->lastPage()) {
            return $this->settlementWorkerQuery($search)->paginate(
                self::SETTLEMENT_WORKERS_PER_PAGE,
                ['*'],
                'page',
                $paginator->lastPage()
            );
        }

        return $paginator;
    }

    private function settlementWorkerQuery(?string $search): Builder
    {
        $query = Worker::query()->select(['id', 'first_name', 'last_name']);

        if ($search !== null) {
            $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($terms as $term) {
                $query->where(function (Builder $workerQuery) use ($term): void {
                    $workerQuery
                        ->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%");
                });
            }
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    private function settlementPaginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    private function settlementSummary(Worker $worker, array $stats): array
    {
        return [
            'id' => $worker->id,
            'name' => $worker->first_name.' '.$worker->last_name,
            'initials' => mb_strtoupper(
                mb_substr($worker->first_name, 0, 1).mb_substr($worker->last_name, 0, 1)
            ),
            'hours' => $stats['hours'],
            'totalMinutes' => $stats['totalMinutes'],
            'salary' => $stats['salary'],
        ];
    }

    private function settlementDays(Collection $shifts, Carbon $dateFrom, Carbon $dateTo): array
    {
        $shiftsByDay = $shifts->groupBy(
            fn (WorkerShift $shift): string => Carbon::parse($shift->day)->toDateString()
        );

        return collect(CarbonPeriod::create($dateFrom, $dateTo))
            ->map(function (Carbon $date) use ($shiftsByDay): array {
                $dayShifts = $shiftsByDay->get($date->toDateString(), collect());
                $workedShifts = $dayShifts->where('status', '!=', 'absent');
                $morningMinutes = (int) $workedShifts
                    ->where('shift_type', 'morning')
                    ->sum('minutes');
                $afternoonMinutes = (int) $workedShifts
                    ->where('shift_type', 'afternoon')
                    ->sum('minutes');
                $salary = $workedShifts->sum(function (WorkerShift $shift): float {
                    return ((int) $shift->minutes / 60) * (float) ($shift->package?->price ?? 0);
                });

                return [
                    'date' => $date->toDateString(),
                    'morningMinutes' => $morningMinutes,
                    'afternoonMinutes' => $afternoonMinutes,
                    'salary' => round($salary, 2),
                    'absent' => $dayShifts->contains('status', 'absent'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Per-day minutes, salary and absence flag, keyed by date (Y-m-d).
     *
     * Salary is intentionally NOT rounded per day — buildStats() rounds only the
     * final sum, so rounding here would drift by cents against getStatsForWorker().
     * Absent shifts are skipped in the loop (never added to minutes/salary) but
     * still create an entry with absent=true, because the dashboard calendar
     * marks days the worker did not show up. Matching buildStats() exactly, a
     * NULL status counts as worked.
     *
     * @return array<string, array{minutes: int, salary: float, absent: bool}>
     */
    public function getDailyBreakdown(Worker $worker, Carbon $dateFrom, Carbon $dateTo): array
    {
        $shifts = $worker->shifts()
            ->published()
            ->with('package')
            ->whereBetween('day', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get();

        return $this->buildDailyBreakdown($shifts);
    }

    /**
     * Reuses one monthly query for the dashboard totals and calendar.
     *
     * @return array{stats: array, days: array<string, array{minutes: int, salary: float, absent: bool}>}
     */
    public function getDashboardMonthData(
        Worker $worker,
        Carbon $dateFrom,
        Carbon $statsDateTo,
        Carbon $calendarDateTo
    ): array {
        $shifts = $worker->shifts()
            ->published()
            ->with('package')
            ->whereBetween('day', [$dateFrom->toDateString(), $calendarDateTo->toDateString()])
            ->get();

        $statsCutoff = $statsDateTo->toDateString();

        return [
            'stats' => $this->calculateStatsData(
                $shifts->filter(fn (WorkerShift $shift): bool => $shift->day <= $statsCutoff),
                false
            ),
            'days' => $this->buildDailyBreakdown($shifts),
        ];
    }

    /**
     * @return array<string, array{minutes: int, salary: float, absent: bool}>
     */
    private function buildDailyBreakdown(Collection $shifts): array
    {

        $breakdown = [];

        foreach ($shifts as $shift) {
            $day = Carbon::parse($shift->day)->toDateString();

            if (! isset($breakdown[$day])) {
                $breakdown[$day] = ['minutes' => 0, 'salary' => 0.0, 'absent' => false];
            }

            if ($shift->status === 'absent') {
                $breakdown[$day]['absent'] = true;

                continue;
            }

            $breakdown[$day]['minutes'] += (int) $shift->minutes;
            $breakdown[$day]['salary'] += ($shift->minutes / 60) * ($shift->package?->price ?? 0);
        }

        return $breakdown;
    }

    /**
     * Percentage change against a previous period.
     * Returns null when there is no comparison base (previous = 0).
     *
     * @return array{percent: float, isPositive: bool}|null
     */
    public function calculateChange(float $current, float $previous): ?array
    {
        if ($previous == 0) {
            return null;
        }

        $percent = (($current - $previous) / $previous) * 100;

        return [
            'percent' => round(abs($percent), 1),
            'isPositive' => $percent >= 0,
        ];
    }
}
