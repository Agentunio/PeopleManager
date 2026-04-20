<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EndDayUpdateRequest;
use App\Models\Package;
use App\Models\PackageShift;
use App\Models\Worker;
use App\Models\WorkerShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class EndDayController extends Controller
{
    public function index(): View
    {
        $packages = Package::select('id','name')->get();
        $day = request()->route('date');

        $workers_morning = WorkerShift::with('worker:id,first_name,last_name')
            ->published()
            ->where('day', $day)
            ->where('shift_type', 'morning')
            ->whereNull('substituted_for_shift_id')
            ->get();

        $workers_afternoon = WorkerShift::with('worker:id,first_name,last_name')
            ->published()
            ->where('day', $day)
            ->where('shift_type', 'afternoon')
            ->whereNull('substituted_for_shift_id')
            ->get();

        $substitutes_morning = WorkerShift::with(['worker:id,first_name,last_name', 'substitutedForShift.worker:id,first_name,last_name'])
            ->published()
            ->where('day', $day)
            ->where('shift_type', 'morning')
            ->whereNotNull('substituted_for_shift_id')
            ->get();

        $substitutes_afternoon = WorkerShift::with(['worker:id,first_name,last_name', 'substitutedForShift.worker:id,first_name,last_name'])
            ->published()
            ->where('day', $day)
            ->where('shift_type', 'afternoon')
            ->whereNotNull('substituted_for_shift_id')
            ->get();

        $shift_packages_morning = PackageShift::where('day', $day)->where('shift_type', 'morning')->get();
        $shift_packages_afternoon = PackageShift::where('day', $day)->where('shift_type', 'afternoon')->get();

        return view('admin.planner.day.end-day.index', [
            'date' => $day,
            'packages' => $packages,
            'workers_morning' => $workers_morning,
            'workers_afternoon' => $workers_afternoon,
            'substitutes_morning' => $substitutes_morning,
            'substitutes_afternoon' => $substitutes_afternoon,
            'shift_packages_morning' => $shift_packages_morning,
            'shift_packages_afternoon' => $shift_packages_afternoon,
        ]);
    }

    public function availableForSubstitution(string $date, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shift_type' => 'required|in:morning,afternoon',
        ]);

        $assignedWorkerIds = WorkerShift::where('day', $date)
            ->where('shift_type', $validated['shift_type'])
            ->pluck('worker_id');

        $available = Worker::select('id', 'first_name', 'last_name')
            ->whereNotIn('id', $assignedWorkerIds)
            ->orderBy('last_name')
            ->get();

        return response()->json($available);
    }

    public function update(EndDayUpdateRequest $request, string $date)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $date) {
            foreach ($validated['workers'] ?? [] as $workerData) {
                $minutes = $this->calculateMinutes($workerData);

                if (!empty($workerData['is_substitute'])) {
                    $attributes = [
                        'status' => 'worked',
                        'package_id' => !empty($workerData['package']) ? $workerData['package'] : null,
                        'minutes' => $minutes ?? 0,
                        'substituted_for_shift_id' => $workerData['substituted_for_shift_id'],
                    ];

                    if ($minutes !== null) {
                        $attributes['hours_source'] = 'admin';
                    }

                    WorkerShift::updateOrCreate(
                        [
                            'worker_id' => $workerData['id'],
                            'day' => $date,
                            'shift_type' => $workerData['shift_type'],
                        ],
                        $attributes
                    );
                    continue;
                }

                if (($workerData['status'] ?? '') === 'absent') {
                    WorkerShift::where('worker_id', $workerData['id'])
                        ->where('day', $date)
                        ->where('shift_type', $workerData['shift_type'])
                        ->update([
                            'status' => 'absent',
                            'minutes' => 0,
                            'package_id' => null,
                            'hours_source' => null,
                        ]);
                    continue;
                }

                $updateData = ['status' => 'worked'];

                if (!empty($workerData['package'])) {
                    $updateData['package_id'] = $workerData['package'];
                }

                if ($minutes !== null) {
                    $updateData['minutes'] = $minutes;
                    $updateData['hours_source'] = 'admin';
                }

                WorkerShift::where('worker_id', $workerData['id'])
                    ->where('day', $date)
                    ->where('shift_type', $workerData['shift_type'])
                    ->update($updateData);
            }

            $submittedShiftTypes = collect($validated['workers'] ?? [])
                ->pluck('shift_type')
                ->unique();

            foreach ($submittedShiftTypes as $shiftType) {
                $substituteIdsForShift = collect($validated['workers'] ?? [])
                    ->filter(fn($w) => !empty($w['is_substitute']) && ($w['shift_type'] ?? '') === $shiftType)
                    ->pluck('id')
                    ->filter()
                    ->all();

                $query = WorkerShift::where('day', $date)
                    ->where('shift_type', $shiftType)
                    ->whereNotNull('substituted_for_shift_id');

                if (!empty($substituteIdsForShift)) {
                    $query->whereNotIn('worker_id', $substituteIdsForShift);
                }

                $query->delete();
            }

            $this->savePackageEntries($validated['morning_package_entries'] ?? null, $date, 'morning');
            $this->savePackageEntries($validated['afternoon_package_entries'] ?? null, $date, 'afternoon');
        });

        return redirect()->back()->with('success', 'Rozliczenie zapisane');
    }

    private function savePackageEntries(?array $entries, string $date, string $shiftType): void
    {
        PackageShift::where('day', $date)->where('shift_type', $shiftType)->delete();

        foreach ($entries ?? [] as $entry) {
            if (!empty($entry['packages_count']) && !empty($entry['package_id'])) {
                PackageShift::create([
                    'day' => $date,
                    'shift_type' => $shiftType,
                    'packages_count' => $entry['packages_count'],
                    'package_id' => $entry['package_id'],
                ]);
            }
        }
    }


    private function calculateMinutes(array $data): ?int
    {
        if (empty($data['from_hour']) && empty($data['to_hour'])) {
            return null;
        }

        $from = (($data['from_hour'] ?? 0) * 60) + ($data['from_minute'] ?? 0);
        $to = (($data['to_hour'] ?? 0) * 60) + ($data['to_minute'] ?? 0);

        return $to - $from;
    }

}
