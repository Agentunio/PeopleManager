<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EndDayUpdateRequest;
use App\Models\Package;
use App\Models\PackageShift;
use App\Models\Worker;
use App\Models\WorkerShift;
use App\Services\PlannerDayStatusService;
use App\Services\ShiftStartService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EndDayController extends Controller
{
    private const SHIFT_TYPES = ['morning', 'afternoon'];

    public function __construct(
        private readonly ShiftStartService $shiftStartService,
        private readonly PlannerDayStatusService $dayStatusService,
    ) {}

    public function index(): View
    {
        $day = (string) request()->route('date');

        $packages = Package::query()
            ->select('id', 'name', 'price', 'is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Package $package): array => [
                'id' => $package->id,
                'name' => $package->name,
                'price' => (float) $package->price,
                'isDefault' => $package->is_default,
            ])
            ->all();

        $workerShifts = WorkerShift::query()
            ->with([
                'worker:id,first_name,last_name',
                'substitutedForShift.worker:id,first_name,last_name',
            ])
            ->published()
            ->where('day', $day)
            ->orderBy('id')
            ->get();

        $packageShifts = PackageShift::query()
            ->where('day', $day)
            ->orderBy('id')
            ->get();

        $startData = $this->shiftStartService->scheduleDataForDates([$day])[$day] ?? [];
        $packagePrices = collect($packages)->mapWithKeys(
            fn (array $package): array => [$package['id'] => $package['price']]
        );

        $shifts = [];

        foreach (self::SHIFT_TYPES as $shiftType) {
            $startMinutes = (int) ($startData[$shiftType]['unlock_minutes'] ?? 0);

            $shifts[$shiftType] = [
                'startTime' => $this->formatTime($startMinutes),
                'label' => $shiftType === 'morning' ? 'Zmiana ranna' : "Zmiana popo\u{0142}udniowa",
                'defaultEndTime' => $this->formatTime(($startMinutes + 480) % 1440),
                'workers' => $workerShifts
                    ->where('shift_type', $shiftType)
                    ->map(fn (WorkerShift $shift): array => $this->workerViewData($shift))
                    ->values()
                    ->all(),
                'packageEntries' => $packageShifts
                    ->where('shift_type', $shiftType)
                    ->map(fn (PackageShift $entry): array => [
                        'id' => $entry->id,
                        'count' => (int) $entry->packages_count,
                        'packageId' => $entry->package_id,
                    ])
                    ->values()
                    ->all(),
            ];
        }

        $date = CarbonImmutable::parse($day)->locale('pl');

        return view('admin.planner.day.end-day.index', [
            'date' => $day,
            'dateData' => [
                'day' => $date->format('d'),
                'weekdayShort' => $date->isoFormat('dd'),
                'heading' => $date->isoFormat('dddd, D MMMM'),
                'formatted' => $date->format('d.m.Y'),
            ],
            'packages' => $packages,
            'shifts' => $shifts,
            'summary' => $this->buildSummary($shifts, $packagePrices),
            'isSettled' => $this->dayStatusService->isSettled($day),
            'settlementConfig' => [
                'date' => $day,
                'packages' => $packages,
                'substitutionUrl' => route('planner.day.substitution.available', $day),
            ],
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
            ->where('is_employed', true)
            ->whereNotIn('id', $assignedWorkerIds)
            ->orderBy('last_name')
            ->get();

        return response()->json($available);
    }

    public function update(EndDayUpdateRequest $request, string $date)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $date) {
            WorkerShift::query()
                ->where('day', $date)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            foreach ($validated['workers'] ?? [] as $workerKey => $workerData) {
                $approvedTime = $this->approvedTimeAttributes($workerData);

                if (! empty($workerData['is_substitute'])) {
                    $substitutedShift = WorkerShift::query()
                        ->published()
                        ->whereKey($workerData['substituted_for_shift_id'])
                        ->where('day', $date)
                        ->where('shift_type', $workerData['shift_type'])
                        ->whereNull('substituted_for_shift_id')
                        ->lockForUpdate()
                        ->first();

                    $willBeMarkedAbsent = $substitutedShift && collect($validated['workers'] ?? [])
                        ->contains(fn (array $submittedWorker): bool => empty($submittedWorker['is_substitute'])
                            && (int) ($submittedWorker['id'] ?? 0) === $substitutedShift->worker_id
                            && ($submittedWorker['shift_type'] ?? null) === $substitutedShift->shift_type
                            && ($submittedWorker['status'] ?? null) === 'absent'
                        );

                    if (! $substitutedShift || ($substitutedShift->status !== 'absent' && ! $willBeMarkedAbsent)) {
                        throw ValidationException::withMessages([
                            "workers.{$workerKey}.substituted_for_shift_id" => 'Zastępstwo wymaga opublikowanej nieobecności na tej samej zmianie.',
                        ]);
                    }

                    $existingSubstituteShift = WorkerShift::query()
                        ->where('worker_id', $workerData['id'])
                        ->where('day', $date)
                        ->where('shift_type', $workerData['shift_type'])
                        ->where('substituted_for_shift_id', $workerData['substituted_for_shift_id'])
                        ->first();

                    if (! $existingSubstituteShift) {
                        $isEmployed = Worker::query()
                            ->whereKey($workerData['id'])
                            ->where('is_employed', true)
                            ->lockForUpdate()
                            ->first(['id']) !== null;

                        if (! $isEmployed) {
                            throw ValidationException::withMessages([
                                "workers.{$workerKey}.id" => 'Wybrany zastępca nie jest obecnie zatrudniony.',
                            ]);
                        }
                    }

                    $attributes = [
                        'status' => 'worked',
                        'package_id' => ! empty($workerData['package']) ? $workerData['package'] : null,
                        'substituted_for_shift_id' => $workerData['substituted_for_shift_id'],
                        ...$approvedTime,
                    ];

                    $substituteShift = $existingSubstituteShift ?? WorkerShift::firstOrNew([
                        'worker_id' => $workerData['id'],
                        'day' => $date,
                        'shift_type' => $workerData['shift_type'],
                    ]);

                    $substituteShift->fill($attributes);

                    if (empty($approvedTime) && $substituteShift->minutes === 0 && $substituteShift->hours_source === null) {
                        $substituteShift->minutes = null;
                    }

                    $substituteShift->save();

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
                            'approved_from_time' => null,
                            'approved_to_time' => null,
                            'hours_source' => null,
                        ]);

                    continue;
                }

                $updateData = [
                    'status' => 'worked',
                    ...$approvedTime,
                ];

                if (! empty($workerData['package'])) {
                    $updateData['package_id'] = $workerData['package'];
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
                    ->filter(fn ($worker): bool => ! empty($worker['is_substitute'])
                        && ($worker['shift_type'] ?? '') === $shiftType
                    )
                    ->pluck('id')
                    ->filter()
                    ->all();

                $query = WorkerShift::where('day', $date)
                    ->where('shift_type', $shiftType)
                    ->whereNotNull('substituted_for_shift_id');

                if (! empty($substituteIdsForShift)) {
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
            if (! empty($entry['packages_count']) && ! empty($entry['package_id'])) {
                PackageShift::create([
                    'day' => $date,
                    'shift_type' => $shiftType,
                    'packages_count' => $entry['packages_count'],
                    'package_id' => $entry['package_id'],
                ]);
            }
        }
    }

    private function approvedTimeAttributes(array $data): array
    {
        if (empty($data['from_hour']) && empty($data['to_hour'])) {
            return [];
        }

        $from = (($data['from_hour'] ?? 0) * 60) + ($data['from_minute'] ?? 0);
        $to = (($data['to_hour'] ?? 0) * 60) + ($data['to_minute'] ?? 0);

        return [
            'approved_from_time' => $from,
            'approved_to_time' => $to,
            'minutes' => $to - $from,
            'hours_source' => 'admin',
        ];
    }

    private function workerViewData(WorkerShift $shift): array
    {
        $workerFrom = $shift->worker_from_time !== null ? (int) $shift->worker_from_time : null;
        $workerTo = $shift->worker_to_time !== null ? (int) $shift->worker_to_time : null;
        $approvedFrom = $shift->approved_from_time !== null ? (int) $shift->approved_from_time : null;
        $approvedTo = $shift->approved_to_time !== null ? (int) $shift->approved_to_time : null;
        $displayFrom = $approvedFrom ?? ($shift->hours_source === 'worker' ? $workerFrom : null);
        $displayTo = $approvedTo ?? ($shift->hours_source === 'worker' ? $workerTo : null);
        $hasWorkerEntry = $workerFrom !== null && $workerTo !== null;
        $hasApprovedRange = $approvedFrom !== null && $approvedTo !== null;
        $displayMinutes = $shift->minutes;

        if ($displayMinutes === null && $displayFrom !== null && $displayTo !== null) {
            $displayMinutes = $displayTo - $displayFrom;
        }

        $worker = $shift->worker;
        $substitutedWorker = $shift->substituted_for_shift_id !== null
            ? $shift->substitutedForShift->worker
            : null;

        return [
            'shiftId' => $shift->id,
            'workerId' => $shift->worker_id,
            'shiftType' => $shift->shift_type,
            'name' => trim($worker->first_name.' '.$worker->last_name),
            'initials' => mb_strtoupper(
                mb_substr($worker->first_name, 0, 1)
                .mb_substr($worker->last_name, 0, 1)
            ),
            'status' => $shift->status ?? 'worked',
            'packageId' => $shift->package_id,
            'displayFrom' => $this->formatTime($displayFrom),
            'displayTo' => $this->formatTime($displayTo),
            'displayMinutes' => $displayMinutes !== null ? (int) $displayMinutes : null,
            'displayHours' => $this->formatDuration($displayMinutes),
            'hasWorkerEntry' => $hasWorkerEntry,
            'isWorkerEntryOverridden' => $hasWorkerEntry
                && $hasApprovedRange
                && ($workerFrom !== $approvedFrom || $workerTo !== $approvedTo),
            'isLegacyApproved' => $shift->hours_source === 'admin'
                && $shift->minutes !== null
                && ! $hasApprovedRange,
            'isSubstitute' => $shift->substituted_for_shift_id !== null,
            'substitutedForShiftId' => $shift->substituted_for_shift_id,
            'substituteForName' => $substitutedWorker
                ? trim($substitutedWorker->first_name.' '.$substitutedWorker->last_name)
                : null,
        ];
    }

    private function buildSummary(array $shifts, Collection $packagePrices): array
    {
        $packageEntries = collect($shifts)->flatMap(
            fn (array $shift): array => $shift['packageEntries']
        );
        $workers = collect($shifts)->flatMap(
            fn (array $shift): array => $shift['workers']
        );
        $workedWorkers = $workers->where('status', '!=', 'absent');

        $packageCount = (int) $packageEntries->sum('count');
        $packageValue = $packageEntries->sum(
            fn (array $entry): float => $entry['count'] * (float) $packagePrices->get($entry['packageId'], 0)
        );

        return [
            'packageCount' => $packageCount,
            'packageValue' => round($packageValue, 2),
            'minutes' => (int) $workedWorkers->sum(
                fn (array $worker): int => $worker['displayMinutes'] ?? 0
            ),
            'workerCount' => $workedWorkers->count(),
            'missingRates' => $workedWorkers->whereNull('packageId')->count(),
        ];
    }

    private function formatDuration(?int $minutes): string
    {
        if ($minutes === null) {
            return "\u{2014}";
        }

        $hours = $minutes / 60;
        $formatted = number_format($hours, 2, ',', '');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return $formatted.' h';
    }

    private function formatTime(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
