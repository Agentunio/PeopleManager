<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkerStoreRequest;
use App\Http\Requests\Admin\WorkerStatsRequest;
use App\Models\Worker;
use App\Services\WorkerStatsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkerController extends Controller
{
    public function __construct(
        private WorkerStatsService $statsService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $workers = Worker::query()
            ->when($request->searchWorker, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($request->filterStatus !== null && $request->filterStatus !== '', function ($query) use ($request) {
                $query->where('is_employed', $request->filterStatus);
            })
            ->orderBy('last_name')
            ->paginate(10);

        $this->statsService->getStatsForWorkers(
            $workers->getCollection(),
            Carbon::now()->startOfMonth()->toDateString(),
            Carbon::now()->toDateString()
        );

        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'html' => view('admin.workers.partials.list', compact('workers'))->render(),
                'pagination' => $workers->links()->toHtml(),
            ]);
        }

        return view('admin.workers.index', compact('workers'));
    }

    public function store(WorkerStoreRequest $request): JsonResponse
    {
        $worker = Worker::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Pracownik dodany pomyślnie',
            'html' => view('admin.workers.partials.card', compact('worker'))->render(),
        ]);
    }

    public function update(WorkerStoreRequest $request, Worker $worker): JsonResponse
    {
        $worker->update($request->validated());

        $this->statsService->getStatsForWorkers(
            collect([$worker]),
            Carbon::now()->startOfMonth()->toDateString(),
            Carbon::now()->toDateString()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pracownik został zaktualizowany',
            'html' => view('admin.workers.partials.card', compact('worker'))->render(),
        ]);
    }

    public function destroy(Worker $worker): JsonResponse
    {
        $worker->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Pracownik usunięty pomyślnie',
        ]);
    }

    public function stats(WorkerStatsRequest $request, Worker $worker): JsonResponse
    {
        $stats = $this->statsService->getStatsForWorker(
            $worker,
            $request->validated('dateFrom'),
            $request->validated('dateTo')
        );

        return response()->json([
            'status' => 'success',
            'hours' => $stats['hours'],
            'salary' => number_format($stats['salary'], 2, '.', ''),
        ]);
    }
}
