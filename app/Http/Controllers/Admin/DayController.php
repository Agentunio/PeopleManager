<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkerShiftRequest;
use App\Http\Requests\Admin\WorkerStoreAvailabilityRequest;
use App\Models\Worker;
use App\Models\WorkerAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DayController extends Controller
{
    public function index(): View{
        $day = request()->route('date');
        $workers = Worker::with(['availabilities' => function($query) use ($day){
            $query->where('day', $day);
        }])->get();
        return view('admin.planner.day.index', ['date' => request()->route('date'), 'workers' => $workers]);
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
                }])->get()
            ])->render()
        ]);
    }

    public function storeShift(WorkerShiftRequest $request, $date): JsonResponse
    {
        return false;
    }
}
