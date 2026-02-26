<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackageShift;
use App\Models\Worker;
use App\Services\PackageStatsService;
use App\Services\WorkerStatsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{

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

    public function data(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $data = $this->getDashboardData($startDate, $endDate);

        return response()->json([
            'totalRevenue' => $data['totalRevenue'],
            'totalCost' => $data['totalCost'],
            'totalProfit' => $data['totalProfit'],
            'packageStats' => $data['packageStats'],
            'changes' => $data['changes'],
            'workers' => $data['workers']->map(fn($worker) => [
                'name' => $worker->first_name . ' ' . $worker->last_name,
                'hours' => $worker->stats['hours'],
                'salary' => $worker->stats['salary'],
            ])->values(),
        ]);
    }

    private function getDashboardData(Carbon $startDate, Carbon $endDate): array
    {
        $daysDiff = $startDate->diffInDays($endDate) + 1;
        $prevEndDate = $startDate->copy()->subDay();
        $prevStartDate = $prevEndDate->copy()->subDays($daysDiff - 1);

        $workers = Worker::select('id', 'first_name', 'last_name')
            ->whereHas('shifts', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('day', [$startDate, $endDate]);
            })->get();

        $workersWithStats = $this->workerStatsService
            ->getStatsForWorkers($workers, $startDate, $endDate);

        $totalCost = $workersWithStats->sum(fn($worker) => $worker->stats['salary']);

        $prevWorkers = Worker::select('id')
            ->whereHas('shifts', function ($query) use ($prevStartDate, $prevEndDate) {
                $query->whereBetween('day', [$prevStartDate, $prevEndDate]);
            })->get();

        $prevWorkersWithStats = $this->workerStatsService
            ->getStatsForWorkers($prevWorkers, $prevStartDate, $prevEndDate);

        $prevTotalCost = $prevWorkersWithStats->sum(fn($worker) => $worker->stats['salary']);

        $packageStats = $this->packageStatsService
            ->getStatsForPackages($startDate, $endDate);

        $prevPackageStats = $this->packageStatsService
            ->getStatsForPackages($prevStartDate, $prevEndDate);

        $totalRevenue = $packageStats['total']['revenue'];
        $prevTotalRevenue = $prevPackageStats['total']['revenue'];
        $totalProfit = $totalRevenue - $totalCost;
        $prevTotalProfit = $prevTotalRevenue - $prevTotalCost;

        $changes = [
            'cost' => $this->calculateChange($totalCost, $prevTotalCost),
            'revenue' => $this->calculateChange($totalRevenue, $prevTotalRevenue),
            'profit' => $this->calculateChange($totalProfit, $prevTotalProfit),
        ];

        return [
            'workers' => $workersWithStats,
            'totalCost' => $totalCost,
            'totalRevenue' => $totalRevenue,
            'totalProfit' => $totalProfit,
            'packageStats' => $packageStats,
            'changes' => $changes,
        ];
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
