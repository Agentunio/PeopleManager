<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackageShift;
use App\Models\Worker;
use App\Services\PackageStatsService;
use App\Services\WorkerStatsService;
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

        $prevStartDate = now()->subMonth()->startOfMonth();
        $prevEndDate = now()->subMonth()->endOfMonth();

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

        return view('admin.dashboard.index', ['workers' => $workersWithStats, 'totalCost' => $totalCost, 'totalRevenue' => $totalRevenue, 'totalProfit' => $totalProfit, 'packageStats' => $packageStats, 'changes' => $changes,]);    }

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
