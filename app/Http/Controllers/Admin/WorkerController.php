<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkerIndexRequest;
use App\Http\Requests\Admin\WorkerSettlementRequest;
use App\Http\Requests\Admin\WorkerStoreRequest;
use App\Models\Worker;
use App\Services\WorkerStatsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class WorkerController extends Controller
{
    public function __construct(
        private WorkerStatsService $statsService
    ) {}

    public function index(WorkerIndexRequest $request): View|JsonResponse
    {
        $search = $request->validated('searchWorker');
        $employment = $request->validated('filterEmployment');
        $student = $request->validated('filterStudent');
        $activeTab = $request->validated('tab', 'list');

        $workers = Worker::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'phone',
                'address',
                'date_of_birth',
                'is_student',
                'is_employed',
                'contract_from',
                'contract_to',
            ])
            ->with('user:id,worker_id,email,is_active,activation_token')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($employment !== null, fn ($query) => $query->where('is_employed', $employment))
            ->when($student !== null, fn ($query) => $query->where('is_student', $student))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(10);

        $workers->withQueryString();
        $filteredTotal = $workers->total();
        $hasActiveFilters = $search !== null || $employment !== null || $student !== null;
        $totalWorkers = $hasActiveFilters ? Worker::count() : $filteredTotal;

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'html' => view('admin.workers.partials.list', compact('workers'))->render(),
                'pagination' => $workers->links()->toHtml(),
                'filteredTotal' => $filteredTotal,
                'totalWorkers' => $totalWorkers,
            ]);
        }

        return view('admin.workers.index', compact(
            'workers',
            'activeTab',
            'filteredTotal',
            'totalWorkers'
        ));
    }

    public function settlements(WorkerSettlementRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'status' => 'success',
            ...$this->statsService->getSettlementData(
                isset($validated['workerId']) ? (int) $validated['workerId'] : null,
                Carbon::createFromFormat('Y-m-d', $validated['dateFrom']),
                Carbon::createFromFormat('Y-m-d', $validated['dateTo']),
                (int) ($validated['page'] ?? 1),
                $validated['searchWorker'] ?? null
            ),
        ]);
    }

    public function store(WorkerStoreRequest $request): JsonResponse
    {
        Worker::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Pracownik dodany pomyślnie',
        ]);
    }

    public function update(WorkerStoreRequest $request, Worker $worker): JsonResponse
    {
        $worker->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Pracownik został zaktualizowany',
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
}
