<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Worker\StoreAvailabilityRequest;
use App\Http\Requests\Worker\StoreHoursRequest;
use App\Models\AppSetting;
use App\Models\Package;
use App\Models\Schedule;
use App\Models\WorkerAvailability;
use App\Models\WorkerShift;
use App\Services\ShiftStartService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ShiftStartService $shiftStartService
    ) {}

    public function index(?string $week = null): View
    {
        $worker = auth()->user()->worker;

        abort_unless($worker, 403, 'Brak powiązanego profilu pracownika');

        $schedule = Schedule::getCurrent();
        $scheduleStatus = $schedule?->toStatusArray() ?? ['is_active' => false, 'text' => ''];

        if ($week) {
            $parts = explode('-', $week);
            if (count($parts) !== 3 || ! checkdate((int) $parts[1], (int) $parts[0], (int) $parts[2])) {
                abort(404);
            }
            $weekStart = Carbon::createFromFormat('d-m-Y', $week)->startOfWeek();
        } else {
            $weekStart = now()->startOfWeek();
        }

        $weekEnd = $weekStart->copy()->endOfWeek();
        $weekDates = $this->dateStringsBetween($weekStart, $weekEnd);
        $shiftStartData = $this->shiftStartService->scheduleDataForDates($weekDates);

        $availabilities = WorkerAvailability::where('worker_id', $worker->id)
            ->whereBetween('day', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->keyBy('day');

        $isCurrentWeek = $weekStart->eq(now()->startOfWeek());
        $showAllWorkers = $weekStart->gte(now()->startOfWeek());

        $shiftsQuery = WorkerShift::published()
            ->whereBetween('day', [$weekStart->toDateString(), $weekEnd->toDateString()]);

        if (! $showAllWorkers) {
            $shiftsQuery->where('worker_id', $worker->id);
        }

        $allShifts = $shiftsQuery->with('worker')->get()->groupBy('day');

        $myShifts = collect();
        if ($isCurrentWeek) {
            $myShifts = $allShifts->flatten(1)
                ->where('worker_id', $worker->id)
                ->keyBy(fn ($s) => $s->day.'_'.$s->shift_type);
        }

        $workerSelfHoursEnabled = AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS);
        $days = $this->buildDaysArray($weekStart, $weekEnd, $worker, $schedule, $availabilities, $allShifts, $myShifts, $isCurrentWeek, $shiftStartData, $workerSelfHoursEnabled);

        return view('worker.schedule.index', [
            'scheduleStatus' => $scheduleStatus,
            'days' => $days,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'prevWeek' => $weekStart->copy()->subWeek()->format('d-m-Y'),
            'nextWeek' => $weekStart->copy()->addWeek()->format('d-m-Y'),
            'workerSelfHoursEnabled' => $workerSelfHoursEnabled,
        ]);
    }

    public function storeAvailability(StoreAvailabilityRequest $request, string $date): JsonResponse
    {
        $worker = auth()->user()->worker;

        abort_unless($worker, 403, 'Brak powiązanego profilu pracownika');

        $schedule = Schedule::getCurrent();

        if (! $schedule || ! $schedule->isActive()) {
            return response()->json(['message' => 'Grafik jest nieaktywny'], 422);
        }

        $parsedDate = Carbon::parse($date);

        if ($parsedDate->lte(now()->startOfDay())) {
            return response()->json(['message' => 'Nie można edytować dostępności dla dzisiejszego lub przeszłego dnia'], 422);
        }

        if (! $schedule->isDateInSchedule($parsedDate)) {
            return response()->json(['message' => 'Ten dzień wykracza poza zakres aktywnego grafiku'], 422);
        }

        $morning = (bool) $request->input('morning_shift', false);
        $afternoon = (bool) $request->input('afternoon_shift', false);

        // Locked so a concurrent admin assignment cannot slip between reading
        // the shifts and persisting availability (assigned => available).
        DB::transaction(function () use ($worker, $date, $morning, $afternoon) {
            $assignedShifts = WorkerShift::published()
                ->where('worker_id', $worker->id)
                ->where('day', $date)
                ->lockForUpdate()
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
        });

        return response()->json([
            'success' => true,
            'message' => 'Dostępność została zapisana',
        ]);
    }

    public function storeHours(StoreHoursRequest $request, string $date): JsonResponse
    {
        $worker = auth()->user()->worker;

        abort_unless($worker, 403, 'Brak powiązanego profilu pracownika');

        if (! AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS)) {
            return response()->json(['message' => 'Wpisywanie godzin zostalo wylaczone przez administratora'], 403);
        }

        $parsedDate = Carbon::parse($date);
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        if (! $parsedDate->between($weekStart, $weekEnd)) {
            return response()->json(['message' => 'Można wpisywać godziny tylko dla bieżącego tygodnia'], 422);
        }

        if ($parsedDate->gt(now()->startOfDay())) {
            return response()->json(['message' => 'Nie można wpisać godzin dla przyszłego dnia'], 422);
        }

        $shiftType = $request->input('shift_type');

        return DB::transaction(function () use ($date, $parsedDate, $request, $shiftType, $worker): JsonResponse {
            $shift = WorkerShift::published()
                ->where('worker_id', $worker->id)
                ->where('day', $date)
                ->where('shift_type', $shiftType)
                ->lockForUpdate()
                ->first();

            if (! $shift) {
                return response()->json(['message' => 'Brak przypisanej zmiany'], 403);
            }

            if ($shift->status === 'absent') {
                return response()->json(['message' => 'Nie można wpisać godzin — jesteś oznaczony jako nieobecny'], 422);
            }

            if ($shift->hours_source === 'admin') {
                return response()->json(['message' => 'Godziny zostały już zatwierdzone przez administratora'], 422);
            }

            if ($parsedDate->isToday()) {
                $currentMinutes = now()->hour * 60 + now()->minute;
                $allowedFrom = $this->shiftStartService->resolveStartMinutes($date, $shiftType);

                if ($currentMinutes < $allowedFrom) {
                    $label = $this->shiftStartService->label($allowedFrom);

                    return response()->json([
                        'message' => "Godziny dla tej zmiany można wpisać dopiero po $label",
                    ], 422);
                }
            }

            $fromMinutes = WorkerShift::parseTimeToMinutes($request->input('from_time'));
            $toMinutes = WorkerShift::parseTimeToMinutes($request->input('to_time'));

            $updates = [
                'worker_from_time' => $fromMinutes,
                'worker_to_time' => $toMinutes,
                'hours_source' => 'worker',
            ];

            if ($shift->package_id === null) {
                $defaultPackageId = Package::where('is_default', true)->value('id');
                if ($defaultPackageId) {
                    $updates['package_id'] = $defaultPackageId;
                }
            }

            $shift->update($updates);

            $shiftData = [
                'status' => $shift->status,
                'hours_source' => 'worker',
                'minutes' => $shift->minutes,
                'from' => sprintf('%02d:%02d', $shift->worker_from_hour, $shift->worker_from_minute),
                'to' => sprintf('%02d:%02d', $shift->worker_to_hour, $shift->worker_to_minute),
                'blocked' => false,
                'block_label' => '',
            ];

            return response()->json([
                'success' => true,
                'message' => 'Godziny zostały zapisane',
                'html' => view('worker.dashboard.partials.shift-hours', [
                    'shift' => $shiftData,
                    'type' => $shiftType,
                    'workerSelfHoursEnabled' => true,
                ])->render(),
            ]);
        });
    }

    private function dateStringsBetween(Carbon $start, Carbon $end): array
    {
        $dates = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    private function buildDaysArray(
        Carbon $weekStart,
        Carbon $weekEnd,
        $worker,
        ?Schedule $schedule,
        $availabilities,
        $allShifts,
        $myShifts,
        bool $isCurrentWeek,
        array $shiftStartData,
        bool $workerSelfHoursEnabled
    ): array {
        $days = [];
        $scheduleActive = $schedule && $schedule->isActive();

        for ($date = $weekStart->copy(); $date->lte($weekEnd); $date->addDay()) {
            $dateStr = $date->toDateString();
            $availability = $availabilities->get($dateStr);
            $dayShifts = $allShifts->get($dateStr, collect());
            $dayStartData = $shiftStartData[$dateStr] ?? [];
            $morningStart = $dayStartData['morning'] ?? [];
            $afternoonStart = $dayStartData['afternoon'] ?? [];

            $myAssigned = [
                'morning' => $dayShifts->contains(fn ($s) => $s->worker_id === $worker->id && $s->shift_type === 'morning'),
                'afternoon' => $dayShifts->contains(fn ($s) => $s->worker_id === $worker->id && $s->shift_type === 'afternoon'),
            ];

            $assignedWorkers = $dayShifts->map(fn ($s) => [
                'name' => $s->worker->first_name.' '.$s->worker->last_name,
                'shift_type' => $s->shift_type,
                'is_me' => $s->worker_id === $worker->id,
            ])->values()->all();

            $inSchedule = $schedule ? $schedule->isDateInSchedule($date) : false;
            $isToday = $date->isToday();
            $isPast = $date->lte(now()->startOfDay());

            $canViewHours = $isCurrentWeek && $isPast && ($myAssigned['morning'] || $myAssigned['afternoon']);
            $canInputHours = $canViewHours && $workerSelfHoursEnabled;
            $isClickable = ($scheduleActive && ! $isPast && $inSchedule)
                || ($isToday && $scheduleActive && $inSchedule)
                || $canViewHours;

            $morningShift = $myShifts->get($dateStr.'_morning');
            $afternoonShift = $myShifts->get($dateStr.'_afternoon');

            $days[] = [
                'date' => $dateStr,
                'weekday' => Str::ucfirst($date->translatedFormat('l')),
                'short_date' => $date->translatedFormat('j').' '.Str::ucfirst($date->translatedFormat('M')),
                'is_today' => $isToday,
                'is_past' => $isPast,
                'is_current_week' => $isCurrentWeek,
                'in_schedule' => $inSchedule,
                'is_clickable' => $isClickable,
                'can_view_hours' => $canViewHours,
                'can_input_hours' => $canInputHours,
                'morning' => $availability?->morning_shift ?? false,
                'afternoon' => $availability?->afternoon_shift ?? false,
                'morning_start_label' => $morningStart['configured_label'] ?? null,
                'afternoon_start_label' => $afternoonStart['configured_label'] ?? null,
                'morning_unlock_minutes' => $morningStart['unlock_minutes'],
                'morning_unlock_label' => $morningStart['unlock_label'],
                'afternoon_unlock_minutes' => $afternoonStart['unlock_minutes'],
                'afternoon_unlock_label' => $afternoonStart['unlock_label'],
                'assigned_morning' => $myAssigned['morning'],
                'assigned_afternoon' => $myAssigned['afternoon'],
                'assigned_workers' => $assignedWorkers,
                'morning_from' => $morningShift?->worker_from_time !== null
                    ? sprintf('%02d:%02d', $morningShift->worker_from_hour, $morningShift->worker_from_minute) : '',
                'morning_to' => $morningShift?->worker_to_time !== null
                    ? sprintf('%02d:%02d', $morningShift->worker_to_hour, $morningShift->worker_to_minute) : '',
                'morning_source' => $morningShift?->hours_source ?? '',
                'morning_minutes' => $morningShift?->minutes ?? '',
                'afternoon_from' => $afternoonShift?->worker_from_time !== null
                    ? sprintf('%02d:%02d', $afternoonShift->worker_from_hour, $afternoonShift->worker_from_minute) : '',
                'afternoon_to' => $afternoonShift?->worker_to_time !== null
                    ? sprintf('%02d:%02d', $afternoonShift->worker_to_hour, $afternoonShift->worker_to_minute) : '',
                'afternoon_source' => $afternoonShift?->hours_source ?? '',
                'afternoon_minutes' => $afternoonShift?->minutes ?? '',
                'morning_status' => $morningShift?->status ?? '',
                'afternoon_status' => $afternoonShift?->status ?? '',
            ];
        }

        return $days;
    }
}
