<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Worker\DashboardStatsRequest;
use App\Models\Worker;
use App\Services\WorkerDashboardService;
use App\Services\WorkerStatsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private WorkerStatsService $workerStatsService,
        private WorkerDashboardService $dashboardService
    ) {}

    public function index(): View
    {
        $worker = auth()->user()->worker;

        abort_unless($worker, 403, 'Brak powiązanego profilu pracownika');

        return view('worker.dashboard.index', $this->dashboardService->indexData($worker));
    }

    /**
     * Per-day minutes and salary for the dashboard range filter.
     */
    public function stats(DashboardStatsRequest $request): JsonResponse
    {
        /** @var Worker|null $worker */
        $worker = auth()->user()->worker;

        // CheckUserRole only verifies the role, not that a worker profile exists.
        abort_unless($worker !== null, 403, 'Brak powiązanego profilu pracownika');

        $from = Carbon::parse($request->validated('from'));
        $to = Carbon::parse($request->validated('to'));

        return response()->json([
            'days' => $this->workerStatsService->getDailyBreakdown($worker, $from, $to),
            'salaryTrend' => $this->dashboardService->buildRangeSalaryTrend($worker, $from, $to),
        ]);
    }
}
