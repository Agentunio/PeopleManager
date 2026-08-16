<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardDataRequest;
use App\Services\PackageStatsService;
use App\Services\WorkerStatsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const ALL_SHIFTS = 'total';

    private const SHIFTS = ['morning', 'afternoon'];

    public function __construct(
        private WorkerStatsService $workerStatsService,
        private PackageStatsService $packageStatsService
    ) {}

    public function index(): View
    {
        $startDate = now()->startOfMonth();
        $endDate = now();

        $data = $this->getDashboardData($startDate, $endDate);

        return view('admin.dashboard.index', $data);
    }

    public function data(DashboardDataRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();
        $page = (int) ($validated['page'] ?? 1);
        $shift = $validated['shift'] ?? self::ALL_SHIFTS;

        $hasComparison = isset($validated['compare_start_date']);
        $compStart = $hasComparison ? Carbon::parse($validated['compare_start_date'])->startOfDay() : null;
        $compEnd = $hasComparison ? Carbon::parse($validated['compare_end_date'])->endOfDay() : null;

        $ranges = [[$startDate, $endDate]];

        if ($hasComparison) {
            $ranges[] = [$compStart, $compEnd];
        }

        $costs = $this->workerStatsService->getCostByShiftForRanges($ranges);

        $data = $this->getMainData($startDate, $endDate, $costs[0]);
        $workers = $this->workerStatsService->getDashboardWorkerPage(
            $startDate,
            $endDate,
            $page,
            $shift
        );
        $response = $this->buildPayload($data, $workers);

        if ($hasComparison) {
            $compData = $this->getMainData($compStart, $compEnd, $costs[1]);

            $response['comparison'] = $this->buildPayload($compData);
            $response['changes'] = $this->buildAllChanges($data, $compData);
        }

        return response()->json($response);
    }

    private function getDashboardData(Carbon $startDate, Carbon $endDate): array
    {
        $daysDiff = $startDate->diffInDays($endDate) + 1;
        $prevEndDate = $startDate->copy()->subDay();
        $prevStartDate = $prevEndDate->copy()->subDays($daysDiff - 1);

        $costs = $this->workerStatsService->getCostByShiftForRanges([
            [$startDate, $endDate],
            [$prevStartDate, $prevEndDate],
        ]);

        $mainData = $this->getMainData($startDate, $endDate, $costs[0]);
        $prevData = $this->getMainData($prevStartDate, $prevEndDate, $costs[1]);
        $workers = $this->workerStatsService->getDashboardWorkerPage(
            $startDate,
            $endDate,
            1,
            self::ALL_SHIFTS
        );

        $dashboardData = $this->buildPayload($mainData, $workers);
        $dashboardData['changes'] = $this->buildAllChanges($mainData, $prevData);

        return [...$dashboardData, 'dashboardData' => $dashboardData];
    }

    private function buildPayload(array $data, ?LengthAwarePaginator $workers = null): array
    {
        $payload = [
            'totalRevenue' => $data['totalRevenue'],
            'totalCost' => $data['totalCost'],
            'totalProfit' => $data['totalProfit'],
            'byShift' => $data['byShift'],
            'packageStats' => $data['packageStats'],
        ];

        if ($workers !== null) {
            $payload['workers'] = $this->mapWorkers($workers->getCollection());
            $payload['workerPagination'] = $this->workerPaginationMeta($workers);
        }

        return $payload;
    }

    /**
     * @param  array{morning: float, afternoon: float}  $costByShift
     */
    private function getMainData(Carbon $startDate, Carbon $endDate, array $costByShift): array
    {
        $totalCost = round(array_sum($costByShift), 2);
        $packageStats = $this->packageStatsService->getStatsForPackages($startDate, $endDate);
        $totalRevenue = $packageStats['total']['revenue'];
        $totalProfit = round($totalRevenue - $totalCost, 2);

        $byShift = [];
        foreach (self::SHIFTS as $shift) {
            $shiftCost = $costByShift[$shift];
            $shiftRevenue = $packageStats[$shift]['revenue'];
            $byShift[$shift] = [
                'revenue' => $shiftRevenue,
                'cost' => $shiftCost,
                'profit' => round($shiftRevenue - $shiftCost, 2),
            ];
        }

        return [
            'totalCost' => $totalCost,
            'totalRevenue' => $totalRevenue,
            'totalProfit' => $totalProfit,
            'packageStats' => $packageStats,
            'byShift' => $byShift,
        ];
    }

    private function workerPaginationMeta(LengthAwarePaginator $workers): array
    {
        return [
            'currentPage' => $workers->currentPage(),
            'lastPage' => $workers->lastPage(),
            'perPage' => $workers->perPage(),
            'total' => $workers->total(),
            'from' => $workers->firstItem(),
            'to' => $workers->lastItem(),
        ];
    }

    private function mapWorkers(Collection $workers): array
    {
        return $workers->map(function ($worker) {
            $stats = $worker->stats;

            return [
                'name' => $worker->first_name.' '.$worker->last_name,
                'hours' => $stats['hours'],
                'salary' => $stats['salary'],
                'totalMinutes' => $stats['totalMinutes'],
                'absences' => $stats['absences'],
                'absentDays' => $stats['absentDays'],
                'byShift' => $stats['byShift'],
            ];
        })->values()->all();
    }

    private function buildAllChanges(array $current, array $previous): array
    {
        $changes = [
            'cost' => $this->workerStatsService->calculateChange($current['totalCost'], $previous['totalCost']),
            'revenue' => $this->workerStatsService->calculateChange($current['totalRevenue'], $previous['totalRevenue']),
            'profit' => $this->workerStatsService->calculateChange($current['totalProfit'], $previous['totalProfit']),
            'byShift' => [],
        ];

        foreach (self::SHIFTS as $shift) {
            $changes['byShift'][$shift] = [
                'cost' => $this->workerStatsService->calculateChange($current['byShift'][$shift]['cost'], $previous['byShift'][$shift]['cost']),
                'revenue' => $this->workerStatsService->calculateChange($current['byShift'][$shift]['revenue'], $previous['byShift'][$shift]['revenue']),
                'profit' => $this->workerStatsService->calculateChange($current['byShift'][$shift]['profit'], $previous['byShift'][$shift]['profit']),
            ];
        }

        return $changes;
    }
}
