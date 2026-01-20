<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkerStoreAvailability;
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

    public function storeAvailability(WorkerStoreAvailability $request, $date): JsonResponse
    {
        foreach ($request->validated()['workers'] as $data) {
            WorkerAvailability::updateOrCreate(
                ['worker_id' => $data['worker_id'], 'day' => $date],
                [
                    'morning_shift' => isset($data['morning_shift']),
                    'afternoon_shift' => isset($data['afternoon_shift']),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Zaktualizowano poprawnie dostępności pracowników'
        ]);
    }
}
