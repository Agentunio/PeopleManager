<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use Illuminate\View\View;

class DayController extends Controller
{
    public function index(): View{
        $workers = Worker::all();
        return view('admin.planner.day.index', ['date' => request()->route('date'), 'workers' => $workers]);
    }
}
