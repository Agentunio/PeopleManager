<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlannerAvailableStoreRequest;
use App\Models\Schedule;
use Carbon\Carbon;
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
        $data = $request->validated();

        Schedule::updateOrCreate(['id' => 1], $data);

        $message = match ($data['type']) {
            'signup' => sprintf(
                'Grafik aktywny do %s, zakres dni: %s – %s',
                Carbon::parse($data['signup_deadline'])->format('d.m.Y H:i'),
                Carbon::parse($data['start_date'])->format('d.m.Y'),
                Carbon::parse($data['end_date'])->format('d.m.Y'),
            ),
            'always' => 'Grafik będzie aktywny do jego wyłączenia',
            'disabled' => 'Grafik nie jest już aktywny',
        };

        return back()->with('success', $message);
    }
}
