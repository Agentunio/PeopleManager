<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EndDayUpdateRequest;
use App\Models\Package;
use App\Models\PackageShift;
use App\Models\WorkerShift;
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

        $shift_packages_morning = PackageShift::where('day', $day)->where('shift_type', 'morning')->first();
        $shift_packages_afternoon = PackageShift::where('day', $day)->where('shift_type', 'afternoon')->first();

        return view('admin.planner.day.end-day.index', ['date' => $day, 'packages' => $packages, 'workers_morning' => $workers_morning, 'workers_afternoon' => $workers_afternoon,  'shift_packages_morning' => $shift_packages_morning, 'shift_packages_afternoon' => $shift_packages_afternoon,]);
    }

    public function update(EndDayUpdateRequest $request, string $date)
    {
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

        if ($request->filled('morning_packages') || $request->filled('morning_package_rate')) {
            PackageShift::updateOrCreate(
                ['day' => $date, 'shift_type' => 'morning'],
                [
                    'packages_count' => $request->morning_packages ?? 0,
                    'package_id' => $request->morning_package_rate,
                ]
            );
        }

        if ($request->filled('afternoon_packages') || $request->filled('afternoon_package_rate')) {
            PackageShift::updateOrCreate(
                ['day' => $date, 'shift_type' => 'afternoon'],
                [
                    'packages_count' => $request->afternoon_packages ?? 0,
                    'package_id' => $request->afternoon_package_rate,
                ]
            );
        }

        return redirect()->back()->with('success', 'Rozliczenie zapisane');
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
