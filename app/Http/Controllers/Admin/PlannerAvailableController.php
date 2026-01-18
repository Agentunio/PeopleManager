<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlannerAvailableStoreRequest;
use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
class PlannerAvailableController extends Controller
{
    public function index(): View
    {
        $schedule = Schedule::getCurrent();
        return view('admin.planner.schedule.index', ['schedule' => $schedule]);
    }

    public function store(PlannerAvailableStoreRequest $request): RedirectResponse
    {
        $type = $request->type;
        if ($type === 'range') {
            $start_date = $request->start_date;
            $end_date = $request->end_date;
        }

        if ($type === 'week') {
            $days = $request->days;
        }

        Schedule::updateOrCreate(['id' => 1], $request->validated());

        if ($type === 'range') {
            return back()->with('success', "Poprawnie zaplanowano grafik pomiędzy {$start_date} a {$end_date}");
        } else if ($type === 'week') {
            return back()->with('success', "Poprawnie zaplanowano grafik pomiędzy na następne {$days} dni");
        } else if ($type === 'disabled'){
            return back()->with('success', "Grafik nie jest już aktywny");
        } else {
            return back()->with('success', "Grafik będzie aktywny do jego wyłączenia");
        }
    }

}
