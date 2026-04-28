<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardDataRequest;
use App\Models\Worker;
use App\Services\PackageStatsService;
use App\Services\WorkerStatsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
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
        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $data = $this->getMainData($startDate, $endDate);
        $response = $this->buildPayload($data, withWorkers: true);

        if ($request->filled('compare_start_date')) {
            $compStart = Carbon::parse($request->compare_start_date)->startOfDay();
            $compEnd = Carbon::parse($request->compare_end_date)->endOfDay();

            $compData = $this->getMainData($compStart, $compEnd);

            $response['comparison'] = $this->buildPayload($compData, withWorkers: false);
            $response['changes'] = $this->buildAllChanges($data, $compData);
        }

        return response()->json($response);
    }

    private function getDashboardData(Carbon $startDate, Carbon $endDate): array
    {
        $daysDiff = $startDate->diffInDays($endDate) + 1;
        $prevEndDate = $startDate->copy()->subDay();
        $prevStartDate = $prevEndDate->copy()->subDays($daysDiff - 1);

        $mainData = $this->getMainData($startDate, $endDate);
        $prevData = $this->getMainData($prevStartDate, $prevEndDate);

        $dashboardData = $this->buildPayload($mainData, withWorkers: true);
        $dashboardData['changes'] = $this->buildAllChanges($mainData, $prevData);

        return [...$dashboardData, 'dashboardData' => $dashboardData];
    }

    private function buildPayload(array $data, bool $withWorkers): array
    {
        $payload = [
            'totalRevenue' => $data['totalRevenue'],
            'totalCost'    => $data['totalCost'],
            'totalProfit'  => $data['totalProfit'],
            'byShift'      => $data['byShift'],
            'packageStats' => $data['packageStats'],
        ];

        if ($withWorkers) {
            $payload['workers'] = $this->mapWorkers($data['workers']);
        }

        return $payload;
    }

    private function getMainData(Carbon $startDate, Carbon $endDate): array
    {
        $workers = Worker::select('id', 'first_name', 'last_name')
            ->whereHas('shifts', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('day', [$startDate, $endDate]);
            })->get();

        $workersWithStats = $this->workerStatsService
            ->getStatsForWorkers($workers, $startDate, $endDate);

        $totalCost = round((float) $workersWithStats->sum(fn($worker) => $worker->stats['salary']), 2);

        $packageStats = $this->packageStatsService
            ->getStatsForPackages($startDate, $endDate);

        $totalRevenue = $packageStats['total']['revenue'];
        $totalProfit = round($totalRevenue - $totalCost, 2);

        $byShift = [];
        foreach (self::SHIFTS as $shift) {
            $shiftCost = round((float) $workersWithStats->sum(fn($worker) => $worker->stats['byShift'][$shift]['salary']), 2);
            $shiftRevenue = $packageStats[$shift]['revenue'];
            $byShift[$shift] = [
                'revenue' => $shiftRevenue,
                'cost' => $shiftCost,
                'profit' => round($shiftRevenue - $shiftCost, 2),
            ];
        }

        return [
            'workers' => $workersWithStats,
            'totalCost' => $totalCost,
            'totalRevenue' => $totalRevenue,
            'totalProfit' => $totalProfit,
            'packageStats' => $packageStats,
            'byShift' => $byShift,
        ];
    }

    private function mapWorkers(Collection $workers): array
    {
        return $workers->map(function ($worker) {
            $stats = $worker->stats;

            return [
                'name' => $worker->first_name . ' ' . $worker->last_name,
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
            'cost' => $this->calculateChange($current['totalCost'], $previous['totalCost']),
            'revenue' => $this->calculateChange($current['totalRevenue'], $previous['totalRevenue']),
            'profit' => $this->calculateChange($current['totalProfit'], $previous['totalProfit']),
            'byShift' => [],
        ];

        foreach (self::SHIFTS as $shift) {
            $changes['byShift'][$shift] = [
                'cost' => $this->calculateChange($current['byShift'][$shift]['cost'], $previous['byShift'][$shift]['cost']),
                'revenue' => $this->calculateChange($current['byShift'][$shift]['revenue'], $previous['byShift'][$shift]['revenue']),
                'profit' => $this->calculateChange($current['byShift'][$shift]['profit'], $previous['byShift'][$shift]['profit']),
            ];
        }

        return $changes;
    }

    private function calculateChange(float $current, float $previous): ?array
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
