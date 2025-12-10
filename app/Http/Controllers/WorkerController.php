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
    /**
     * Lista pracowników
     */
    public function index(Request $request): View
    {
        $workers = Worker::orderBy('last_name')->get();

        return view('workers.index', [
            'workers' => $workers,
        ]);
    }

    /**
     * Waliduje dane pracownika (AJAX) - jak w oryginalnym workerAdd.php
     */
    public function store(WorkerStoreRequest $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Poprawny ajax',
        ]);
    }

    /**
     * Aktualizuje pracownika
     */
    public function update(WorkerUpdateRequest $request, Worker $worker): RedirectResponse
    {
        $worker->update($request->validated());

        return back()->with('success', 'Dane pracownika zostały zaktualizowane.');
    }

    /**
     * Usuwa pracownika
     */
    public function destroy(Worker $worker): RedirectResponse
    {
        $name = $worker->first_name . ' ' . $worker->last_name;
        $worker->delete();

        return back()->with('success', "Pracownik {$name} został usunięty.");
    }
}
