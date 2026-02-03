<?php

namespace App\Services;

use App\Models\Worker;
use App\Models\WorkerShift;
use Illuminate\Support\Collection;

class WorkerStatsService
{
    public function calculateStats(Collection $shifts): array
    {
        $totalMinutes = (int) $shifts->sum('minutes');

        $totalSalary = $shifts->sum(function ($shift) {
            $hours = $shift->minutes / 60;
            $hourlyRate = $shift->package?->price ?? 0;
            return $hours * $hourlyRate;
        });

        return [
            'hours' => $this->formatHours($totalMinutes),
            'salary' => round($totalSalary, 2),
            'totalMinutes' => $totalMinutes,
        ];
    }

    public function formatHours(int $totalMinutes): string
    {
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        $formatted = '';
        if ($hours > 0) {
            $formatted .= $hours . 'h';
        }
        if ($minutes > 0) {
            $formatted .= ($hours > 0 ? ' ' : '') . $minutes . 'min';
        }

        return $formatted ?: '0';
    }

    public function getStatsForWorker(Worker $worker, string $dateFrom, string $dateTo): array
    {
        $shifts = $worker->shifts()
            ->with('package')
            ->whereBetween('day', [$dateFrom, $dateTo])
            ->get();

        return $this->calculateStats($shifts);
    }

    public function getStatsForWorkers(Collection $workers, string $dateFrom, string $dateTo): Collection
    {
        $workerIds = $workers->pluck('id');

        $shifts = WorkerShift::with('package')
            ->whereIn('worker_id', $workerIds)
            ->whereBetween('day', [$dateFrom, $dateTo])
            ->get()
            ->groupBy('worker_id');

        return $workers->each(function ($worker) use ($shifts) {
            $workerShifts = $shifts->get($worker->id, collect());
            $worker->stats = $this->calculateStats($workerShifts);
        });
    }
}
