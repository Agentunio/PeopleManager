<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkerStoreRequest;
use App\Http\Requests\WorkerUpdateRequest;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkerController extends Controller
{
    public function index(Request $request): View
    {
        $workers = Worker::orderBy('last_name')->get();

        return view('workers.index', [
            'workers' => $workers,
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

    public function destroy(Worker $worker): JsonResponse
    {
        $worker->delete();

        return response()->json([
           'status' => 'success',
           'message' => 'Pracownik usunięty pomyślnie',
        ]);
    }

}
