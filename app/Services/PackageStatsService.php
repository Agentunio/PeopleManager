<?php

namespace App\Services;

use App\Models\PackageShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PackageStatsService
{
    private const UNKNOWN_RATE_LABEL = 'Nieznana stawka';

    /**
     * Package counts and revenue are grouped in SQL - a year of package entries
     * used to be hydrated in full just to be summed per shift and rate.
     */
    public function getStatsForPackages(Carbon $startDate, Carbon $endDate): array
    {
        $rows = PackageShift::query()
            ->leftJoin('packages', 'packages.id', '=', 'package_shifts.package_id')
            ->whereBetween('package_shifts.day', [$startDate, $endDate])
            ->groupBy('package_shifts.shift_type', 'package_shifts.package_id', 'packages.name')
            ->selectRaw('package_shifts.shift_type AS shift_type')
            ->selectRaw('packages.name AS rate_name')
            ->selectRaw('SUM(package_shifts.packages_count) AS total_packages')
            ->selectRaw('SUM(package_shifts.packages_count * COALESCE(packages.price, 0)) AS total_revenue')
            ->orderByDesc('total_packages')
            ->orderBy('packages.name')
            ->toBase()
            ->get();

        $byShift = $rows->groupBy('shift_type');

        return [
            'morning' => $this->calculateStats($byShift->get('morning', collect())),
            'afternoon' => $this->calculateStats($byShift->get('afternoon', collect())),
            'total' => [
                'packages' => $this->sumPackages($rows),
                'revenue' => $this->sumRevenue($rows),
            ],
        ];
    }

    /**
     * @param  Collection<int, \stdClass>  $rows  aggregated rows of one shift type
     */
    private function calculateStats(Collection $rows): array
    {
        return [
            'packages' => $this->sumPackages($rows),
            'revenue' => $this->sumRevenue($rows),
            'breakdown' => $rows
                ->map(fn (\stdClass $row): array => [
                    'name' => $row->rate_name ?? self::UNKNOWN_RATE_LABEL,
                    'packages' => (int) $row->total_packages,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     */
    private function sumPackages(Collection $rows): int
    {
        return (int) $rows->sum(fn (\stdClass $row): int => (int) $row->total_packages);
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     */
    private function sumRevenue(Collection $rows): float
    {
        return round($rows->sum(fn (\stdClass $row): float => (float) $row->total_revenue), 2);
    }
}
