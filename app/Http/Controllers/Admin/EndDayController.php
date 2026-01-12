<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\View\View;
class EndDayController extends Controller
{
    public function index(): View
    {
        return view('admin.planner.day.end-day.index');
    }

}
