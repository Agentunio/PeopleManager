<?php

namespace App\Services;

use App\Models\PackageShift;

class PackageStatsService
{
    public function getStatsForPackages(string $startDate, string $endDate): array
    {
        $packages = PackageShift::with('packageRate:id,name,price')
            ->whereBetween('day', [$startDate, $endDate])
            ->get();

        $morning = $packages->where('shift_type', 'morning');
        $afternoon = $packages->where('shift_type', 'afternoon');

        return [
            'morning' => $this->calculateStats($morning),
            'afternoon' => $this->calculateStats($afternoon),
            'total' => [
                'packages' => $packages->sum('packages_count'),
                'revenue' => round($packages->sum(function ($shift) {
                    return $shift->packages_count * ($shift->packageRate?->price ?? 0);
                }), 2),
            ],
        ];
    }

    private function calculateStats($shifts): array
    {
        $breakdown = $shifts->groupBy('package_id')->map(function ($group) {
            return [
                'name' => $group->first()->packageRate?->name ?? 'Nieznana stawka',
                'packages' => $group->sum('packages_count'),
            ];
        })->sortByDesc('packages')->values()->all();

        return [
            'packages' => $shifts->sum('packages_count'),
            'breakdown' => $breakdown,
        ];
    }
}
