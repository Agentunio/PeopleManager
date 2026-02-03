<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\PackageShift;
use Illuminate\View\View;
class PlannerController extends Controller
{
    public function index(): View
    {
        $settledDays = PackageShift::select('day')
            ->groupBy('day')
            ->havingRaw('COUNT(DISTINCT shift_type) = 2')
            ->pluck('day')
            ->toArray();

        return view('admin.planner.index', compact('settledDays'));
    }
}
