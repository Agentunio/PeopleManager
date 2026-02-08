<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackageShift;
use App\Models\WorkerShift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlannerController extends Controller
{
    private const MONTHS = [
        1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
        5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
        9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
    ];

    public function index(Request $request): View
    {
        $date = $this->resolveDate($request->query('month'));
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        $shifts = WorkerShift::with('worker:id,first_name,last_name')
            ->whereBetween('day', [$start, $end])
            ->get()
            ->groupBy('day')
            ->map(fn($dayShifts) => $dayShifts->groupBy('shift_type')
                ->map(fn($typeShifts) => $typeShifts->pluck('worker')->map(fn($w) => "$w->first_name $w->last_name")->toArray())
            )->toArray();

        $settled = PackageShift::whereBetween('day', [$start, $end])
            ->selectRaw('day')
            ->groupBy('day')
            ->havingRaw('COUNT(DISTINCT shift_type) = 2')
            ->pluck('day')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        return view('admin.planner.index', [
            'shifts' => $shifts,
            'settled' => $settled,
            'calendar' => [
                'month' => self::MONTHS[$date->month],
                'year' => $date->year,
                'days' => $date->daysInMonth,
                'startDay' => $start->dayOfWeekIso,
                'prev' => $date->copy()->subMonth()->format('Y-m'),
                'next' => $date->copy()->addMonth()->format('Y-m'),
                'today' => Carbon::today()->toDateString(),
            ],
            'start' => $start,
        ]);
    }

    private function resolveDate(?string $month): Carbon
    {
        try {
            return $month ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : Carbon::now();
        } catch (\Exception) {
            return Carbon::now();
        }
    }
}
