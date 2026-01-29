<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkerShiftStoreRequest;
use App\Http\Requests\Admin\WorkerStoreAvailabilityRequest;
use App\Models\Worker;
use App\Models\WorkerAvailability;
use App\Models\WorkerShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DayController extends Controller
{
    public function index(): View
    {
        $day = request()->route('date');
        $workers = Worker::with(['availabilities' => function($query) use ($day) {
            $query->where('day', $day);
        }])->get();
        $workers_on_shift = WorkerShift::with('worker')->where('day', $day)->get();

        $workersJson = $workers->map(fn($w) => [
            'id' => $w->id,
            'name' => $w->first_name . ' ' . $w->last_name,
            'morning' => $w->availabilities->first()?->morning_shift ?? false,
            'afternoon' => $w->availabilities->first()?->afternoon_shift ?? false,
        ]);

        return view('admin.planner.day.index', [
            'date' => $day,
            'workers' => $workers,
            'workers_on_shift' => $workers_on_shift,
            'workersJson' => $workersJson
        ]);
    }

    public function storeAvailability(WorkerStoreAvailabilityRequest $request, $date): JsonResponse
    {
        foreach ($request->validated()['workers'] as $data) {
            $morning = isset($data['morning_shift']);
            $afternoon = isset($data['afternoon_shift']);

            if ($morning || $afternoon) {
                WorkerAvailability::updateOrCreate(
                    ['worker_id' => $data['worker_id'], 'day' => $date],
                    [
                        'morning_shift' => $morning,
                        'afternoon_shift' => $afternoon,
                    ]
                );
            } else {
                WorkerAvailability::where('worker_id', $data['worker_id'])
                    ->where('day', $date)
                    ->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Zaktualizowano poprawnie dostępności pracowników',
            'html' => view('admin.planner.partials.workeravailability', [
                'workers' => Worker::with(['availabilities' => function($query) use ($date) {
                    $query->where('day', $date);
                }])->get(),
                'workers_on_shift' => WorkerShift::where('day', $date)->get()
            ])->render(),
        ]);
    }

    public function storeShift(WorkerShiftStoreRequest $request, $date): RedirectResponse
    {
        foreach ($request->validated()['workers'] as $data) {
            WorkerShift::updateOrCreate(
                ['worker_id' => $data['worker_id'], 'day' => $date, 'shift_type' => $data['shift_type']]
            );
        }

        return back()->with('success', 'Grafik został zapisany');
    }
}
