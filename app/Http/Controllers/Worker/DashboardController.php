<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\WorkerShift;
use App\Services\WorkerStatsService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private WorkerStatsService $workerStatsService
    ) {}

    public function index(): View
    {
        $worker = auth()->user()->worker;

        abort_unless($worker, 403, 'Brak powiązanego profilu pracownika');

        $schedule = Schedule::getCurrent();
        $scheduleStatus = $schedule?->toStatusArray() ?? ['is_active' => false, 'text' => ''];
        $stats = $this->workerStatsService->getStatsForWorker(
            $worker,
            now()->startOfMonth(),
            now()
        );

        $minDate = now()->hour >= 21 ? now()->addDay()->toDateString() : now()->toDateString();

        $nextDay = WorkerShift::published()
            ->where('worker_id', $worker->id)
            ->where('day', '>=', $minDate)
            ->orderBy('day')
            ->value('day');

        $nextShiftDay = $nextDay
            ? WorkerShift::published()->where('worker_id', $worker->id)->where('day', $nextDay)->get()
            : null;

        $nextShift = null;
        if ($nextShiftDay && $nextShiftDay->isNotEmpty()) {
            $firstShift = $nextShiftDay->first();
            $date = Carbon::parse($firstShift->day);
            $nextShift = [
                'date' => $firstShift->day,
                'weekday' => Str::ucfirst($date->translatedFormat('l')),
                'short_date' => $date->translatedFormat('j') . ' ' . Str::ucfirst($date->translatedFormat('F')) . ' ' . $date->format('Y'),
                'shifts' => $nextShiftDay->pluck('shift_type')->toArray(),
            ];
        }

        $lastShift = $this->getLastShiftData($worker->id);

        return view('worker.dashboard.index', compact('worker', 'schedule', 'scheduleStatus', 'stats', 'nextShift', 'lastShift'));
    }

    private function getLastShiftData(int $workerId): ?array
    {
        $weekStart = now()->startOfWeek()->toDateString();
        $today = now()->toDateString();

        $shifts = WorkerShift::published()
            ->where('worker_id', $workerId)
            ->whereBetween('day', [$weekStart, $today])
            ->whereNull('substituted_for_shift_id')
            ->orderByDesc('day')
            ->get();

        if ($shifts->isEmpty()) {
            return null;
        }

        $latestDay = $shifts->first()->day;
        $dayShifts = $shifts->where('day', $latestDay);

        $date = Carbon::parse($latestDay);
        $isToday = $date->isToday();

        $shiftsData = [];
        foreach ($dayShifts as $shift) {
            $shiftsData[$shift->shift_type] = [
                'status' => $shift->status,
                'hours_source' => $shift->hours_source,
                'minutes' => $shift->minutes,
                'from' => $shift->worker_from_time !== null
                    ? sprintf('%02d:%02d', $shift->worker_from_hour, $shift->worker_from_minute)
                    : null,
                'to' => $shift->worker_to_time !== null
                    ? sprintf('%02d:%02d', $shift->worker_to_hour, $shift->worker_to_minute)
                    : null,
            ];
        }

        $shiftsData = collect($shiftsData)->sortBy(fn($v, $k) => $k === 'morning' ? 0 : 1)->all();

        $currentMinutes = now()->hour * 60 + now()->minute;
        $allBlocked = true;

        foreach ($shiftsData as $type => &$shift) {
            $shift['blocked'] = false;
            $shift['block_label'] = '';

            if ($shift['status'] !== 'absent' && $shift['hours_source'] !== 'admin') {
                if ($isToday) {
                    $allowedFrom = WorkerShift::hoursAvailableFrom($type);
                    if ($currentMinutes < $allowedFrom) {
                        $shift['blocked'] = true;
                        $shift['block_label'] = WorkerShift::hoursAvailableLabel($type);
                    } else {
                        $allBlocked = false;
                    }
                } else {
                    $allBlocked = false;
                }
            }
        }
        unset($shift);

        return [
            'date' => $latestDay,
            'weekday' => Str::ucfirst($date->translatedFormat('l')),
            'short_date' => $date->translatedFormat('j') . ' ' . Str::ucfirst($date->translatedFormat('F')) . ' ' . $date->format('Y'),
            'is_today' => $isToday,
            'shifts' => $shiftsData,
            'all_blocked' => $allBlocked,
        ];
    }
}
