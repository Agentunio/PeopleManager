<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkerShiftStoreRequest;
use App\Http\Requests\Admin\WorkerStoreAvailabilityRequest;
use App\Models\Worker;
use App\Models\WorkerAvailability;
use App\Models\WorkerShift;
use App\Services\PlannerDayShiftSyncService;
use App\Services\ShiftStartService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DayController extends Controller
{
    public function __construct(
        private readonly ShiftStartService $shiftStartService,
        private readonly PlannerDayShiftSyncService $dayShiftSyncService,
    ) {}

    public function index(): View
    {
        $day = request()->route('date');
        $workers = Worker::with(['availabilities' => function ($query) use ($day) {
            $query->where('day', $day);
        }])->get();
        $workers_on_shift = WorkerShift::with('worker')->where('day', $day)->get();
        $isDraft = $workers_on_shift->isNotEmpty() && $workers_on_shift->every(fn ($s) => $s->is_draft);
        $shiftStartTimes = $this->shiftStartService->inputValuesForDate($day);

        $workersJson = $workers->map(fn (Worker $worker) => $this->workerAvailabilityData($worker));

        return view('admin.planner.day.index', [
            'date' => $day,
            'workers' => $workers,
            'workers_on_shift' => $workers_on_shift,
            'workersJson' => $workersJson,
            'isDraft' => $isDraft,
            'shiftStartTimes' => $shiftStartTimes,
            'assignedCounts' => $this->assignedCounts($workers_on_shift),
        ]);
    }

    public function storeAvailability(WorkerStoreAvailabilityRequest $request, $date): JsonResponse
    {
        $timestamp = now();
        $validated = $request->validated();
        $upserts = [];
        $workerIdsToDelete = [];

        foreach ($validated['workers'] as $data) {
            $workerId = (int) $data['worker_id'];
            $morning = (bool) $data['morning_shift'];
            $afternoon = (bool) $data['afternoon_shift'];

            if ($morning || $afternoon) {
                $upserts[$workerId] = [
                    'worker_id' => $workerId,
                    'day' => $date,
                    'morning_shift' => $morning,
                    'afternoon_shift' => $afternoon,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                unset($workerIdsToDelete[$workerId]);

                continue;
            }

            unset($upserts[$workerId]);
            $workerIdsToDelete[$workerId] = true;
        }

        DB::transaction(function () use ($date, $upserts, $workerIdsToDelete): void {
            if ($upserts !== []) {
                WorkerAvailability::query()->upsert(
                    array_values($upserts),
                    ['worker_id', 'day'],
                    ['morning_shift', 'afternoon_shift', 'updated_at'],
                );
            }

            if ($workerIdsToDelete !== []) {
                WorkerAvailability::query()
                    ->where('day', $date)
                    ->whereIn('worker_id', array_keys($workerIdsToDelete))
                    ->delete();
            }
        }, 3);

        $workers = Worker::with(['availabilities' => function ($query) use ($date) {
            $query->where('day', $date);
        }])->get();

        return response()->json([
            'success' => true,
            'message' => 'Zaktualizowano poprawnie dostępności pracowników',
            'html' => view('admin.planner.partials.workeravailability', [
                'workers' => $workers,
                'workers_on_shift' => WorkerShift::where('day', $date)->get(),
            ])->render(),
            'workers' => $workers->map(fn (Worker $worker) => $this->workerAvailabilityData($worker)),
        ]);
    }

    public function storeShift(WorkerShiftStoreRequest $request, $date): RedirectResponse
    {
        $validated = $request->validated();
        $isDraft = (bool) ($validated['is_draft'] ?? false);

        $this->dayShiftSyncService->sync($date, $validated, $isDraft);

        $message = $isDraft ? 'Grafik zapisany jako szkic' : 'Grafik został zapisany';

        return back()->with('success', $message);
    }

    /**
     * Liczba osób, które faktycznie wyjdą na zmianę — nieobecni się nie liczą,
     * zastępcy tak. Ta sama semantyka co licznik „Przypisani” w projekcie.
     *
     * @param  Collection<int, WorkerShift>  $shifts
     * @return array{morning: int, afternoon: int}
     */
    private function assignedCounts(Collection $shifts): array
    {
        $working = $shifts->reject(fn (WorkerShift $shift): bool => $shift->status === 'absent');

        return [
            'morning' => $working->where('shift_type', 'morning')->count(),
            'afternoon' => $working->where('shift_type', 'afternoon')->count(),
        ];
    }

    /**
     * @return array{id: int, name: string, morning: bool, afternoon: bool}
     */
    private function workerAvailabilityData(Worker $worker): array
    {
        /** @var WorkerAvailability|null $availability */
        $availability = $worker->availabilities->isNotEmpty()
            ? $worker->availabilities->first()
            : null;

        return [
            'id' => $worker->id,
            'name' => $worker->first_name.' '.$worker->last_name,
            'morning' => (bool) ($availability->morning_shift ?? false),
            'afternoon' => (bool) ($availability->afternoon_shift ?? false),
        ];
    }
}
