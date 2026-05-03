<?php

namespace App\Services;

use App\Models\ShiftStart;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShiftStartService
{
    private const FALLBACK_STARTS = [
        'morning' => 540,
        'afternoon' => 1260,
    ];

    private const SHIFT_TYPES = ['morning', 'afternoon'];

    public function parse(?string $time): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour * 60) + $minute;
    }

    public function format(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function label(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function resolveStartMinutes(string $date, string $shiftType): int
    {
        $startTime = ShiftStart::query()
            ->where('day', $date)
            ->where('shift_type', $shiftType)
            ->value('start_time');

        return $startTime ?? $this->fallbackStartMinutes($shiftType);
    }

    public function inputValuesForDate(string $date): array
    {
        return $this->recordsForDates([$date])
            ->mapWithKeys(fn (ShiftStart $start) => [
                $start->shift_type => $this->format($start->start_time),
            ])
            ->all();
    }

    public function scheduleDataForDates(array $dates): array
    {
        $starts = $this->recordsForDates($dates)
            ->keyBy(fn (ShiftStart $start) => Carbon::parse($start->day)->toDateString() . '_' . $start->shift_type);

        $data = [];

        foreach ($dates as $date) {
            foreach (self::SHIFT_TYPES as $shiftType) {
                $configured = $starts->get($date . '_' . $shiftType)?->start_time;
                $minutes = $configured ?? $this->fallbackStartMinutes($shiftType);

                $data[$date][$shiftType] = [
                    'configured_label' => $this->label($configured),
                    'unlock_minutes' => $minutes,
                    'unlock_label' => $this->label($minutes),
                ];
            }
        }

        return $data;
    }

    public function saveForDate(string $date, array $input): void
    {
        foreach (self::SHIFT_TYPES as $shiftType) {
            $field = $shiftType . '_start_time';

            if (!array_key_exists($field, $input)) {
                continue;
            }

            $minutes = $this->parse($input[$field]);
            $shiftStart = ShiftStart::firstOrNew([
                'day' => $date,
                'shift_type' => $shiftType,
            ]);

            if ($minutes === null && !$shiftStart->exists) {
                continue;
            }

            $shiftStart->start_time = $minutes;
            $shiftStart->save();
        }
    }

    private function fallbackStartMinutes(string $shiftType): int
    {
        return self::FALLBACK_STARTS[$shiftType];
    }

    private function recordsForDates(array $dates): Collection
    {
        $dates = array_values(array_unique($dates));

        if (empty($dates)) {
            return collect();
        }

        return ShiftStart::query()
            ->whereIn('day', $dates)
            ->get();
    }
}
