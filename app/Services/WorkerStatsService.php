<?php

namespace App\Services;

use App\Models\Worker;
use App\Models\WorkerShift;
use Illuminate\Support\Collection;

class WorkerStatsService
{
    public function calculateStats(Collection $shifts): array
    {
        $absentShifts = $shifts->where('status', 'absent');
        $workedShifts = $shifts->where('status', '!=', 'absent');

        $totalMinutes = (int) $workedShifts->sum('minutes');

        $totalSalary = $workedShifts->sum(function ($shift) {
            $hours = $shift->minutes / 60;
            $hourlyRate = $shift->package?->price ?? 0;
            return $hours * $hourlyRate;
        });

        $absentDays = $absentShifts->map(function ($shift) {
            $substitute = $shift->substitute;
            return [
                'day' => $shift->day,
                'substitute' => $substitute
                    ? ($substitute->worker->first_name . ' ' . $substitute->worker->last_name)
                    : null,
            ];
        })->unique('day')->sortBy('day')->values()->toArray();

        return [
            'hours' => $this->formatHours($totalMinutes),
            'salary' => round($totalSalary, 2),
            'totalMinutes' => $totalMinutes,
            'absences' => count($absentDays),
            'absentDays' => $absentDays,
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

        return $formatted ?: '0h';
    }

    public function getStatsForWorker(Worker $worker, string $dateFrom, string $dateTo): array
    {
        $shifts = $worker->shifts()
            ->with(['package', 'substitute.worker'])
            ->whereBetween('day', [$dateFrom, $dateTo])
            ->get();

        return $this->calculateStats($shifts);
    }

    public function getStatsForWorkers(Collection $workers, string $dateFrom, string $dateTo): Collection
    {
        $workerIds = $workers->pluck('id');

        $shifts = WorkerShift::with(['package', 'substitute.worker'])
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
