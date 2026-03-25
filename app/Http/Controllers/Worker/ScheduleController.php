<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Worker\StoreAvailabilityRequest;
use App\Http\Requests\Worker\StoreHoursRequest;
use App\Models\Schedule;
use App\Models\WorkerAvailability;
use App\Models\WorkerShift;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(?string $week = null): View
    {
        $worker = auth()->user()->worker;

        abort_unless($worker, 403, 'Brak powiązanego profilu pracownika');

        $schedule = Schedule::getCurrent();

        if ($week) {
            $parts = explode('-', $week);
            if (count($parts) !== 3 || !checkdate((int) $parts[1], (int) $parts[0], (int) $parts[2])) {
                abort(404);
            }
            $weekStart = Carbon::createFromFormat('d-m-Y', $week)->startOfWeek();
        } else {
            $weekStart = now()->startOfWeek();
        }

        $weekEnd = $weekStart->copy()->endOfWeek();

        $availabilities = WorkerAvailability::where('worker_id', $worker->id)
            ->whereBetween('day', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->keyBy('day');

        $isCurrentWeek = $weekStart->eq(now()->startOfWeek());
        $showAllWorkers = $weekStart->gte(now()->startOfWeek());

        $shiftsQuery = WorkerShift::whereBetween('day', [$weekStart->toDateString(), $weekEnd->toDateString()]);

        if (!$showAllWorkers) {
            $shiftsQuery->where('worker_id', $worker->id);
        }

        $allShifts = $shiftsQuery->with('worker')->get()->groupBy('day');

        $myShifts = collect();
        if ($isCurrentWeek) {
            $myShifts = $allShifts->flatten(1)
                ->where('worker_id', $worker->id)
                ->whereNull('substituted_for_shift_id')
                ->keyBy(fn ($s) => $s->day . '_' . $s->shift_type);
        }

        $days = $this->buildDaysArray($weekStart, $weekEnd, $worker, $schedule, $availabilities, $allShifts, $myShifts, $isCurrentWeek);

        return view('worker.schedule.index', [
            'schedule' => $schedule,
            'days' => $days,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'prevWeek' => $weekStart->copy()->subWeek()->format('d-m-Y'),
            'nextWeek' => $weekStart->copy()->addWeek()->format('d-m-Y'),
            'worker' => $worker,
        ]);
    }

    public function storeAvailability(StoreAvailabilityRequest $request, string $date): JsonResponse
    {
        $worker = auth()->user()->worker;

        abort_unless($worker, 403, 'Brak powiązanego profilu pracownika');

        $schedule = Schedule::getCurrent();

        if (!$schedule || !$schedule->isActive()) {
            return response()->json(['message' => 'Grafik jest nieaktywny'], 422);
        }

        $parsedDate = Carbon::parse($date);

        if ($parsedDate->lte(now()->startOfDay())) {
            return response()->json(['message' => 'Nie można edytować dostępności dla dzisiejszego lub przeszłego dnia'], 422);
        }

        if (!$schedule->isDateInSchedule($parsedDate)) {
            return response()->json(['message' => 'Ten dzień wykracza poza zakres aktywnego grafiku'], 422);
        }

        $morning = (bool) $request->input('morning_shift', false);
        $afternoon = (bool) $request->input('afternoon_shift', false);

        $assignedShifts = WorkerShift::where('worker_id', $worker->id)
            ->where('day', $date)
            ->pluck('shift_type')
            ->toArray();

        if (in_array('morning', $assignedShifts)) {
            $morning = true;
        }
        if (in_array('afternoon', $assignedShifts)) {
            $afternoon = true;
        }

        if ($morning || $afternoon) {
            WorkerAvailability::updateOrCreate(
                ['worker_id' => $worker->id, 'day' => $date],
                [
                    'morning_shift' => $morning,
                    'afternoon_shift' => $afternoon,
                ]
            );
        } else {
            WorkerAvailability::where('worker_id', $worker->id)
                ->where('day', $date)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Dostępność została zapisana',
        ]);
    }

    public function storeHours(StoreHoursRequest $request, string $date): JsonResponse
    {
        $worker = auth()->user()->worker;

        abort_unless($worker, 403, 'Brak powiązanego profilu pracownika');

        $parsedDate = Carbon::parse($date);
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        if (!$parsedDate->between($weekStart, $weekEnd)) {
            return response()->json(['message' => 'Można wpisywać godziny tylko dla bieżącego tygodnia'], 422);
        }

        if ($parsedDate->gt(now()->startOfDay())) {
            return response()->json(['message' => 'Nie można wpisać godzin dla przyszłego dnia'], 422);
        }

        $shiftType = $request->input('shift_type');

        if ($parsedDate->isToday()) {
            $currentMinutes = now()->hour * 60 + now()->minute;
            $allowedFrom = WorkerShift::hoursAvailableFrom($shiftType);

            if ($currentMinutes < $allowedFrom) {
                $label = WorkerShift::hoursAvailableLabel($shiftType);
                return response()->json([
                    'message' => "Godziny dla tej zmiany można wpisać dopiero po $label",
                ], 422);
            }
        }

        $shift = WorkerShift::where('worker_id', $worker->id)
            ->where('day', $date)
            ->where('shift_type', $shiftType)
            ->whereNull('substituted_for_shift_id')
            ->first();

        if (!$shift) {
            return response()->json(['message' => 'Brak przypisanej zmiany'], 403);
        }

        if ($shift->status === 'absent') {
            return response()->json(['message' => 'Nie można wpisać godzin — jesteś oznaczony jako nieobecny'], 422);
        }

        if ($shift->hours_source === 'admin') {
            return response()->json(['message' => 'Godziny zostały już zatwierdzone przez administratora'], 422);
        }

        $fromMinutes = WorkerShift::parseTimeToMinutes($request->input('from_time'));
        $toMinutes = WorkerShift::parseTimeToMinutes($request->input('to_time'));

        $shift->update([
            'worker_from_time' => $fromMinutes,
            'worker_to_time' => $toMinutes,
            'hours_source' => 'worker',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Godziny zostały zapisane',
        ]);
    }

    private function buildDaysArray(
        Carbon $weekStart,
        Carbon $weekEnd,
        $worker,
        ?Schedule $schedule,
        $availabilities,
        $allShifts,
        $myShifts,
        bool $isCurrentWeek
    ): array {
        $days = [];

        for ($date = $weekStart->copy(); $date->lte($weekEnd); $date->addDay()) {
            $dateStr = $date->toDateString();
            $availability = $availabilities->get($dateStr);
            $dayShifts = $allShifts->get($dateStr, collect());

            $myAssigned = [
                'morning' => $dayShifts->contains(fn ($s) => $s->worker_id === $worker->id && $s->shift_type === 'morning'),
                'afternoon' => $dayShifts->contains(fn ($s) => $s->worker_id === $worker->id && $s->shift_type === 'afternoon'),
            ];

            $assignedWorkers = $dayShifts->map(fn ($s) => [
                'name' => $s->worker->first_name . ' ' . $s->worker->last_name,
                'shift_type' => $s->shift_type,
                'is_me' => $s->worker_id === $worker->id,
            ])->values()->all();

            $inSchedule = $schedule && (!$schedule->end_date || $date->lte($schedule->end_date));

            $morningShift = $myShifts->get($dateStr . '_morning');
            $afternoonShift = $myShifts->get($dateStr . '_afternoon');

            $days[] = [
                'date' => $dateStr,
                'weekday' => Str::ucfirst($date->translatedFormat('l')),
                'short_date' => $date->translatedFormat('j') . ' ' . Str::ucfirst($date->translatedFormat('M')),
                'is_today' => $date->isToday(),
                'is_past' => $date->lte(now()->startOfDay()),
                'is_current_week' => $isCurrentWeek,
                'in_schedule' => $inSchedule,
                'morning' => $availability?->morning_shift ?? false,
                'afternoon' => $availability?->afternoon_shift ?? false,
                'assigned_morning' => $myAssigned['morning'],
                'assigned_afternoon' => $myAssigned['afternoon'],
                'assigned_workers' => $assignedWorkers,
                'morning_shift' => $morningShift,
                'afternoon_shift' => $afternoonShift,
                'morning_status' => $morningShift?->status,
                'afternoon_status' => $afternoonShift?->status,
            ];
        }

        return $days;
    }
}
