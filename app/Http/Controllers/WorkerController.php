<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkerStoreRequest;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkerController extends Controller
{
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
            ->get();

        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'html' => view('workers.partials.list', compact('workers'))->render(),
                'count' => $workers->count(),
            ]);
        }

        return view('workers.index', [
            'workers' => $workers,
        ]);
    }

    public function store(WorkerStoreRequest $request): JsonResponse
    {
        $worker = Worker::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Pracownik dodany pomyślnie',
            'html' => view('workers.partials.card', compact('worker'))->render(),
        ]);
    }

    public function update(WorkerStoreRequest $request, Worker $worker): JsonResponse
    {
        $worker->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Pracownik został zaktualizowany',
            'html' => view('workers.partials.card', compact('worker'))->render(),
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
