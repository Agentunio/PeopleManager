<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\WorkerShift;
use Illuminate\View\View;
class EndDayController extends Controller
{
    public function index(): View
    {
        $packages = Package::select('id','name')->get();
        $day = request()->route('date');
        $workers_morning = WorkerShift::where('day', $day)->where('shift_type', 'morning')->get();
        $workers_afternoon = WorkerShift::where('day', $day)->where('shift_type', 'afternoon')->get();
        return view('admin.planner.day.end-day.index', ['date' => $day, 'packages' => $packages, 'workers_morning' => $workers_morning, 'workers_afternoon' => $workers_afternoon]);
    }

}
