<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DayController extends Controller
{
    public function index(): View{
        return view('admin.planner.day.index', ['date' => request()->route('date')]);
    }
}
