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
            'compare_start_date' => 'nullable|date',
            'compare_end_date' => 'nullable|date|after_or_equal:compare_start_date|required_with:compare_start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $data = $this->getMainData($startDate, $endDate);

        $response = [
            'totalRevenue' => $data['totalRevenue'],
            'totalCost' => $data['totalCost'],
            'totalProfit' => $data['totalProfit'],
            'packageStats' => $data['packageStats'],
            'workers' => $data['workers']->map(fn($worker) => [
                'name' => $worker->first_name . ' ' . $worker->last_name,
                'hours' => $worker->stats['hours'],
                'salary' => $worker->stats['salary'],
            ])->values(),
        ];

        if ($request->filled('compare_start_date')) {
            $compStart = Carbon::parse($request->compare_start_date)->startOfDay();
            $compEnd = Carbon::parse($request->compare_end_date)->endOfDay();

            $compData = $this->getMainData($compStart, $compEnd);

            $response['comparison'] = [
                'totalRevenue' => $compData['totalRevenue'],
                'totalCost' => $compData['totalCost'],
                'totalProfit' => $compData['totalProfit'],
                'packageStats' => $compData['packageStats'],
            ];

            $response['changes'] = [
                'cost' => $this->calculateChange($data['totalCost'], $compData['totalCost']),
                'revenue' => $this->calculateChange($data['totalRevenue'], $compData['totalRevenue']),
                'profit' => $this->calculateChange($data['totalProfit'], $compData['totalProfit']),
            ];
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

        $mainData['changes'] = [
            'cost' => $this->calculateChange($mainData['totalCost'], $prevData['totalCost']),
            'revenue' => $this->calculateChange($mainData['totalRevenue'], $prevData['totalRevenue']),
            'profit' => $this->calculateChange($mainData['totalProfit'], $prevData['totalProfit']),
        ];

        return $mainData;
    }

    private function getMainData(Carbon $startDate, Carbon $endDate): array
    {
        $workers = Worker::select('id', 'first_name', 'last_name')
            ->whereHas('shifts', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('day', [$startDate, $endDate]);
            })->get();

        $workersWithStats = $this->workerStatsService
            ->getStatsForWorkers($workers, $startDate, $endDate);

        $totalCost = $workersWithStats->sum(fn($worker) => $worker->stats['salary']);

        $packageStats = $this->packageStatsService
            ->getStatsForPackages($startDate, $endDate);

        $totalRevenue = $packageStats['total']['revenue'];
        $totalProfit = $totalRevenue - $totalCost;

        return [
            'workers' => $workersWithStats,
            'totalCost' => $totalCost,
            'totalRevenue' => $totalRevenue,
            'totalProfit' => $totalProfit,
            'packageStats' => $packageStats,
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
