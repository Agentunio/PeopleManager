<?php

namespace App\Services;

use App\Models\PackageShift;

class PackageStatsService
{
    public function getStatsForPackages(string $startDate, string $endDate): array
    {
        $packages = PackageShift::with('packageRate:id,price')
            ->whereBetween('day', [$startDate, $endDate])
            ->get();

        return [
            'morning' => $this->calculateStats($packages->where('shift_type', 'morning')),
            'afternoon' => $this->calculateStats($packages->where('shift_type', 'afternoon')),
            'total' => $this->calculateStats($packages),
        ];
    }

    private function calculateStats($shifts): array
    {
        $totalPackages = $shifts->sum('packages_count');

        $totalRevenue = $shifts->sum(function ($shift) {
            $price = $shift->packageRate?->price ?? 0;
            return $shift->packages_count * $price;
        });

        return [
            'packages' => $totalPackages,
            'revenue' => round($totalRevenue, 2),
        ];
    }
}
