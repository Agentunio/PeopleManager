<?php

namespace App\Services;

use App\Models\Worker;
use App\Models\WorkerShift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class WorkerStatsService
{
    private const DASHBOARD_WORKERS_PER_PAGE = 10;

    private const SETTLEMENT_WORKERS_PER_PAGE = 10;

    private const WORKER_COST_SQL = "CASE WHEN worker_shifts.status = 'absent' THEN 0 "
        .'ELSE COALESCE(worker_shifts.minutes, 0) * COALESCE(packages.price, 0) / 60.0 END';

    private const WORKER_MINUTES_SQL = "CASE WHEN worker_shifts.status = 'absent' THEN 0 "
        .'ELSE COALESCE(worker_shifts.minutes, 0) END';

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

    /**
     * Minutes and salary are aggregated in SQL and absences are fetched only for
     * the shifts that actually are absences - hydrating every shift of every
     * listed worker just to sum ten table rows was the dashboard's hot spot.
     */
    public function getStatsForWorkers(
        Collection $workers,
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $shiftType = null
    ): Collection {
        $workerIds = $workers->pluck('id')->all();

        if ($workerIds === []) {
            return $workers;
        }

        $totals = $this->workerShiftTotals($workerIds, $dateFrom, $dateTo, $shiftType);
        $absences = $this->workerAbsences($workerIds, $dateFrom, $dateTo, $shiftType);

        return $workers->each(function (Worker $worker) use ($totals, $absences): void {
            $workerTotals = $totals->get($worker->id, collect());
            $workerAbsences = $absences->get($worker->id, collect());
            $totalsByShift = $workerTotals->keyBy('shift_type');
            $absencesByShift = $workerAbsences->groupBy('shift_type');

            $worker->stats = [
                ...$this->buildAggregatedStats($workerTotals, $workerAbsences),
                'byShift' => [
                    'morning' => $this->buildAggregatedStats(
                        collect(array_filter([$totalsByShift->get('morning')])),
                        $absencesByShift->get('morning', collect()),
                    ),
                    'afternoon' => $this->buildAggregatedStats(
                        collect(array_filter([$totalsByShift->get('afternoon')])),
                        $absencesByShift->get('afternoon', collect()),
                    ),
                ],
            ];
        });
    }

    /**
     * Worked minutes and salary per worker and shift type, keyed by worker id.
     *
     * @param  array<int, int>  $workerIds
     * @return Collection<array-key, Collection<int, \stdClass>>
     */
    private function workerShiftTotals(
        array $workerIds,
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $shiftType
    ): Collection {
        return WorkerShift::query()
            ->leftJoin('packages', 'packages.id', '=', 'worker_shifts.package_id')
            ->published()
            ->whereIn('worker_shifts.worker_id', $workerIds)
            ->whereBetween('worker_shifts.day', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when(
                $shiftType !== null,
                fn (Builder $query) => $query->where('worker_shifts.shift_type', $shiftType)
            )
            ->groupBy('worker_shifts.worker_id', 'worker_shifts.shift_type')
            ->selectRaw('worker_shifts.worker_id AS worker_id')
            ->selectRaw('worker_shifts.shift_type AS shift_type')
            ->selectRaw('SUM('.self::WORKER_MINUTES_SQL.') AS total_minutes')
            ->selectRaw('SUM('.self::WORKER_COST_SQL.') AS salary')
            ->toBase()
            ->get()
            ->groupBy('worker_id');
    }

    /**
     * Absent shifts with the substitute that covered them, keyed by worker id
     * and ordered by day, so the first row of a day wins - exactly what the
     * hydrated substitute() relation used to return.
     *
     * @param  array<int, int>  $workerIds
     * @return Collection<array-key, Collection<int, \stdClass>>
     */
    private function workerAbsences(
        array $workerIds,
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $shiftType
    ): Collection {
        return WorkerShift::query()
            ->leftJoin(
                'worker_shifts as substitute_shifts',
                'substitute_shifts.substituted_for_shift_id',
                '=',
                'worker_shifts.id'
            )
            ->leftJoin('workers as substitute_workers', 'substitute_workers.id', '=', 'substitute_shifts.worker_id')
            ->published()
            ->where('worker_shifts.status', 'absent')
            ->whereIn('worker_shifts.worker_id', $workerIds)
            ->whereBetween('worker_shifts.day', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when(
                $shiftType !== null,
                fn (Builder $query) => $query->where('worker_shifts.shift_type', $shiftType)
            )
            ->orderBy('worker_shifts.day')
            ->orderBy('worker_shifts.id')
            ->orderBy('substitute_shifts.id')
            ->selectRaw('worker_shifts.id AS shift_id')
            ->selectRaw('worker_shifts.worker_id AS worker_id')
            ->selectRaw('worker_shifts.day AS day')
            ->selectRaw('worker_shifts.shift_type AS shift_type')
            ->selectRaw('substitute_workers.first_name AS substitute_first_name')
            ->selectRaw('substitute_workers.last_name AS substitute_last_name')
            ->toBase()
            ->get()
            ->unique('shift_id')
            ->groupBy('worker_id');
    }

    /**
     * @param  Collection<int, \stdClass>  $totals  aggregated rows of one scope
     * @param  Collection<int, \stdClass>  $absences  absent shifts of the same scope
     */
    private function buildAggregatedStats(Collection $totals, Collection $absences): array
    {
        $totalMinutes = (int) $totals->sum(fn (\stdClass $row): int => (int) $row->total_minutes);
        $salary = (float) $totals->sum(fn (\stdClass $row): float => (float) $row->salary);

        $absentDays = $absences
            ->unique('day')
            ->map(fn (\stdClass $row): array => [
                'day' => $row->day,
                'substitute' => $row->substitute_first_name !== null
                    ? $row->substitute_first_name.' '.$row->substitute_last_name
                    : null,
            ])
            ->values()
            ->all();

        return [
            'hours' => $this->formatHours($totalMinutes),
            'salary' => round($salary, 2),
            'totalMinutes' => $totalMinutes,
            'absences' => count($absentDays),
            'absentDays' => $absentDays,
        ];
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
            ->selectRaw('SUM('.self::WORKER_MINUTES_SQL.') AS export_total_minutes')
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

    /**
     * @return array{morning: float, afternoon: float}
     */
    public function getCostByShift(Carbon $dateFrom, Carbon $dateTo): array
    {
        return $this->getCostByShiftForRanges([[$dateFrom, $dateTo]])[0];
    }

    /**
     * Cost per shift type for several periods in a single pass - the dashboard
     * always needs the compared period too, and scanning the shift index twice
     * for it was the most expensive statement of the whole request.
     *
     * @param  array<int, array{0: Carbon, 1: Carbon}>  $ranges
     * @return array<int, array{morning: float, afternoon: float}>
     */
    public function getCostByShiftForRanges(array $ranges): array
    {
        $query = WorkerShift::query()
            ->leftJoin('packages', 'packages.id', '=', 'worker_shifts.package_id')
            ->published()
            ->where(function (Builder $dayFilter) use ($ranges): void {
                foreach ($ranges as [$dateFrom, $dateTo]) {
                    $dayFilter->orWhereBetween('worker_shifts.day', [
                        $dateFrom->toDateString(),
                        $dateTo->toDateString(),
                    ]);
                }
            })
            ->groupBy('worker_shifts.shift_type')
            ->selectRaw('worker_shifts.shift_type AS shift_type');

        foreach ($ranges as $index => [$dateFrom, $dateTo]) {
            $query->selectRaw(
                'SUM(CASE WHEN worker_shifts.day BETWEEN ? AND ? THEN '
                .self::WORKER_COST_SQL.' ELSE 0 END) AS cost_'.$index,
                [$dateFrom->toDateString(), $dateTo->toDateString()]
            );
        }

        $costs = $query->toBase()->get()->keyBy('shift_type');

        return array_map(
            fn (int $index): array => [
                'morning' => round((float) ($costs->get('morning')->{'cost_'.$index} ?? 0), 2),
                'afternoon' => round((float) ($costs->get('afternoon')->{'cost_'.$index} ?? 0), 2),
            ],
            array_keys($ranges)
        );
    }

    public function getDashboardWorkerPage(
        Carbon $dateFrom,
        Carbon $dateTo,
        int $page,
        string $shift
    ): LengthAwarePaginator {
        // Counting distinct workers costs a fraction of what re-running the
        // grouped salary aggregate through the paginator's count query does.
        $total = $this->countDashboardWorkers($dateFrom, $dateTo, $shift);
        $perPage = self::DASHBOARD_WORKERS_PER_PAGE;
        $lastPage = max((int) ceil($total / $perPage), 1);
        $currentPage = $total > 0 ? min($page, $lastPage) : $page;

        $workers = $total > 0
            ? $this->dashboardWorkerQuery($dateFrom, $dateTo, $shift)
                ->forPage($currentPage, $perPage)
                ->get()
            : new Collection;

        $paginator = new LengthAwarePaginator($workers, $total, $perPage, $currentPage, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);

        return $paginator->setCollection(
            $this->getStatsForWorkers(
                $paginator->getCollection(),
                $dateFrom,
                $dateTo,
                $shift === 'total' ? null : $shift
            )
        );
    }

    private function countDashboardWorkers(Carbon $dateFrom, Carbon $dateTo, string $shift): int
    {
        return WorkerShift::query()
            ->published()
            ->whereBetween('day', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when(
                $shift !== 'total',
                fn (Builder $query) => $query->where('shift_type', $shift)
            )
            ->distinct()
            ->count('worker_id');
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
            ->selectRaw('SUM('.self::WORKER_MINUTES_SQL.') AS total_minutes')
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
