<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EndDayUpdateRequest;
use App\Models\Package;
use App\Models\PackageShift;
use App\Models\WorkerShift;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class EndDayController extends Controller
{
    public function index(): View
    {
        $packages = Package::select('id','name')->get();
        $day = request()->route('date');

        $workers_morning = WorkerShift::with('worker:id,first_name,last_name')
            ->where('day', $day)
            ->where('shift_type', 'morning')->get();

        $workers_afternoon = WorkerShift::with('worker:id,first_name,last_name')
            ->where('day', $day)
            ->where('shift_type', 'afternoon')
            ->get();

        $shift_packages_morning = PackageShift::where('day', $day)->where('shift_type', 'morning')->get();
        $shift_packages_afternoon = PackageShift::where('day', $day)->where('shift_type', 'afternoon')->get();

        return view('admin.planner.day.end-day.index', ['date' => $day, 'packages' => $packages, 'workers_morning' => $workers_morning, 'workers_afternoon' => $workers_afternoon,  'shift_packages_morning' => $shift_packages_morning, 'shift_packages_afternoon' => $shift_packages_afternoon,]);
    }

    public function update(EndDayUpdateRequest $request, string $date)
    {
        DB::transaction(function () use ($request, $date) {
            foreach ($request->workers as $workerData) {
                $updateData = [];

                if (!empty($workerData['package'])) {
                    $updateData['package_id'] = $workerData['package'];
                }

                $minutes = $this->calculateMinutes($workerData);
                if ($minutes !== null) {
                    $updateData['minutes'] = $minutes;
                }

                if (!empty($updateData)) {
                    WorkerShift::where('worker_id', $workerData['id'])
                        ->where('day', $date)
                        ->where('shift_type', $workerData['shift_type'])
                        ->update($updateData);
                }
            }

            $this->savePackageEntries($request->morning_package_entries, $date, 'morning');
            $this->savePackageEntries($request->afternoon_package_entries, $date, 'afternoon');
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
