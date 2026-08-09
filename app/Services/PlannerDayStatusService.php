<?php

namespace App\Services;

use App\Models\PackageShift;
use App\Models\WorkerShift;
use Carbon\Carbon;

class PlannerDayStatusService
{
    public function isSettled(string $date): bool
    {
        $nextDate = Carbon::parse($date)->addDay()->toDateString();
        $packageShiftCount = PackageShift::query()
            ->where('day', '>=', $date)
            ->where('day', '<', $nextDate)
            ->distinct()
            ->count('shift_type');

        if ($packageShiftCount !== 2) {
            return false;
        }

        // A day without any published shift is never settled - otherwise a
        // draft-only day would inherit the "settled" (locked) state from
        // package entries alone and could no longer be edited.
        $publishedShifts = WorkerShift::query()
            ->published()
            ->where('day', '>=', $date)
            ->where('day', '<', $nextDate)
            ->toBase()
            ->selectRaw('COUNT(*) as total, COUNT(minutes) as with_hours')
            ->first();

        $total = (int) ($publishedShifts->total ?? 0);

        return $total > 0 && $total === (int) ($publishedShifts->with_hours ?? 0);
    }

    public function settledDates(string $startDate, string $endDate): array
    {
        $daysWithBothPackageShifts = PackageShift::query()
            ->whereBetween('day', [$startDate, $endDate])
            ->selectRaw('day')
            ->groupBy('day')
            ->havingRaw('COUNT(DISTINCT shift_type) = 2')
            ->pluck('day')
            ->map(fn ($day) => Carbon::parse($day)->toDateString())
            ->all();

        // Only days that have published shifts and hours logged on every one of
        // them qualify - draft-only days keep their draft state.
        $daysWithCompleteHours = WorkerShift::query()
            ->published()
            ->whereBetween('day', [$startDate, $endDate])
            ->selectRaw('day')
            ->groupBy('day')
            ->havingRaw('COUNT(*) = COUNT(minutes)')
            ->pluck('day')
            ->map(fn ($day) => Carbon::parse($day)->toDateString())
            ->all();

        return array_values(array_intersect($daysWithBothPackageShifts, $daysWithCompleteHours));
    }
}
