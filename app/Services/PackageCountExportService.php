<?php

namespace App\Services;

use App\Models\PackageShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PackageCountExportService
{
    private const DAY_NAMES = [
        1 => 'Pn',
        2 => 'Wt',
        3 => 'Sr',
        4 => 'Cz',
        5 => 'Pt',
        6 => 'So',
        7 => 'Nd',
    ];

    public function getExportData(Carbon $startDate, Carbon $endDate): array
    {
        $shifts = PackageShift::query()
            ->select('day', 'shift_type', 'packages_count', 'package_id')
            ->with('packageRate:id,name')
            ->whereBetween('day', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('packages_count', '>', 0)
            ->get();

        $weeks = $this->buildWeeks($shifts, $startDate, $endDate);
        $periodSummary = $this->buildPeriodSummary($shifts);

        return [
            'weeks' => $weeks,
            'periodSummary' => $periodSummary,
        ];
    }

    public function generateHtml(array $data, string $periodLabel): string
    {
        $html = $this->getHtmlHead($periodLabel);

        if (empty($data['weeks'])) {
            $html .= '<div class="empty">Brak paczek w wybranym okresie</div>';
        } else {
            foreach ($data['weeks'] as $i => $week) {
                $html .= $this->renderWeekTable($week, $i + 1);
            }

            $html .= $this->renderPeriodSummary($data['periodSummary']);
        }

        $html .= '</body></html>';

        return $html;
    }

    private function buildWeeks(Collection $shifts, Carbon $startDate, Carbon $endDate): array
    {
        $shiftsByDate = $shifts->groupBy(fn($shift) => $shift->day->toDateString());

        $weeks = [];
        $cursor = $startDate->copy()->startOfDay();
        $rangeEnd = $endDate->copy()->startOfDay();

        while ($cursor->lte($rangeEnd)) {
            $weekStart = $cursor->copy()->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();

            $effectiveStart = $weekStart->lt($startDate) ? $startDate->copy()->startOfDay() : $weekStart;
            $effectiveEnd = $weekEnd->gt($rangeEnd) ? $rangeEnd->copy() : $weekEnd;

            $days = $this->buildDays($effectiveStart, $effectiveEnd);
            $weekShifts = $this->collectWeekShifts($shiftsByDate, $days);

            $weekData = $this->assembleWeek($weekShifts, $days, $effectiveStart, $effectiveEnd);

            if ($weekData !== null) {
                $weeks[] = $weekData;
            }

            $cursor = $weekEnd->copy()->addDay();
        }

        return $weeks;
    }

    private function buildDays(Carbon $start, Carbon $end): array
    {
        $days = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $days[] = [
                'date' => $cursor->toDateString(),
                'dateLabel' => $cursor->format('d.m'),
                'dayName' => self::DAY_NAMES[$cursor->dayOfWeekIso],
            ];
            $cursor->addDay();
        }

        return $days;
    }

    private function collectWeekShifts(Collection $shiftsByDate, array $days): Collection
    {
        $collected = collect();

        foreach ($days as $day) {
            $dayShifts = $shiftsByDate->get($day['date']);
            if ($dayShifts) {
                $collected = $collected->concat($dayShifts);
            }
        }

        return $collected;
    }

    private function assembleWeek(Collection $weekShifts, array $days, Carbon $start, Carbon $end): ?array
    {
        $weekTotal = (int) $weekShifts->sum('packages_count');

        if ($weekTotal === 0) {
            return null;
        }

        $rates = $this->buildRates($weekShifts, $days);
        $dayTotals = $this->buildDayTotals($weekShifts, $days);

        return [
            'label' => $start->format('d.m') . ' - ' . $end->format('d.m.Y'),
            'days' => $days,
            'rates' => $rates,
            'dayTotals' => $dayTotals,
            'weekTotal' => $weekTotal,
        ];
    }

    private function buildRates(Collection $weekShifts, array $days): array
    {
        $rates = [];

        foreach ($weekShifts->groupBy('package_id') as $rateShifts) {
            $total = (int) $rateShifts->sum('packages_count');

            if ($total === 0) {
                continue;
            }

            $rates[] = [
                'name' => $rateShifts->first()->packageRate?->name ?? 'Nieznana stawka',
                'cells' => $this->buildRateCells($rateShifts, $days),
                'weekTotal' => $total,
            ];
        }

        usort($rates, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $rates;
    }

    private function buildRateCells(Collection $rateShifts, array $days): array
    {
        $byDate = $rateShifts->groupBy(fn($shift) => $shift->day->toDateString());
        $cells = [];

        foreach ($days as $day) {
            $dayShifts = $byDate->get($day['date'], collect());
            $cells[] = [
                'morning' => (int) $dayShifts->where('shift_type', 'morning')->sum('packages_count'),
                'afternoon' => (int) $dayShifts->where('shift_type', 'afternoon')->sum('packages_count'),
            ];
        }

        return $cells;
    }

    private function buildDayTotals(Collection $weekShifts, array $days): array
    {
        $byDate = $weekShifts->groupBy(fn($shift) => $shift->day->toDateString());
        $totals = [];

        foreach ($days as $day) {
            $dayShifts = $byDate->get($day['date'], collect());
            $totals[] = [
                'morning' => (int) $dayShifts->where('shift_type', 'morning')->sum('packages_count'),
                'afternoon' => (int) $dayShifts->where('shift_type', 'afternoon')->sum('packages_count'),
            ];
        }

        return $totals;
    }

    private function buildPeriodSummary(Collection $shifts): array
    {
        $rows = [];

        foreach ($shifts->groupBy('package_id') as $rateShifts) {
            $total = (int) $rateShifts->sum('packages_count');

            if ($total === 0) {
                continue;
            }

            $rows[] = [
                'name' => $rateShifts->first()->packageRate?->name ?? 'Nieznana stawka',
                'total' => $total,
            ];
        }

        usort($rows, fn($a, $b) => $b['total'] <=> $a['total']);

        return [
            'rows' => $rows,
            'grandTotal' => (int) $shifts->sum('packages_count'),
        ];
    }

    private function renderWeekTable(array $week, int $weekIndex): string
    {
        $html = '<div class="week-block">';
        $html .= '<div class="week-title">Tydzien ' . $weekIndex . ': ' . htmlspecialchars($week['label'], ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '<table>';
        $html .= $this->renderWeekHeader($week['days']);
        $html .= '<tbody>';

        foreach ($week['rates'] as $rate) {
            $html .= $this->renderRateRow($rate);
        }

        $html .= $this->renderDayTotalsRow($week['dayTotals'], $week['weekTotal']);
        $html .= '</tbody></table>';
        $html .= '</div>';

        return $html;
    }

    private function renderWeekHeader(array $days): string
    {
        $html = '<thead><tr><th class="rate-col" rowspan="2">Stawka</th>';

        foreach ($days as $day) {
            $label = htmlspecialchars($day['dayName'] . ' ' . $day['dateLabel'], ENT_QUOTES, 'UTF-8');
            $html .= '<th colspan="2">' . $label . '</th>';
        }

        $html .= '<th class="total-col" rowspan="2">Razem</th></tr>';
        $html .= '<tr>';

        foreach ($days as $ignored) {
            $html .= '<th class="shift-sub" title="Rano">R</th><th class="shift-sub" title="Popoludnie">P</th>';
        }

        $html .= '</tr></thead>';

        return $html;
    }

    private function renderRateRow(array $rate): string
    {
        $html = '<tr><td class="rate-name">' . htmlspecialchars($rate['name'], ENT_QUOTES, 'UTF-8') . '</td>';

        foreach ($rate['cells'] as $cell) {
            $html .= '<td>' . $this->formatCount($cell['morning']) . '</td>';
            $html .= '<td>' . $this->formatCount($cell['afternoon']) . '</td>';
        }

        $html .= '<td class="total-col">' . $rate['weekTotal'] . '</td></tr>';

        return $html;
    }

    private function renderDayTotalsRow(array $dayTotals, int $weekTotal): string
    {
        $html = '<tr class="total-row"><td class="rate-name">Razem</td>';

        foreach ($dayTotals as $total) {
            $html .= '<td>' . $this->formatCount($total['morning']) . '</td>';
            $html .= '<td>' . $this->formatCount($total['afternoon']) . '</td>';
        }

        $html .= '<td class="total-col">' . $weekTotal . '</td></tr>';

        return $html;
    }

    private function renderPeriodSummary(array $summary): string
    {
        if (empty($summary['rows'])) {
            return '';
        }

        $html = '<div class="summary-block">';
        $html .= '<div class="week-title">Podsumowanie okresu</div>';
        $html .= '<table class="summary-table">';
        $html .= '<thead><tr><th class="rate-col">Stawka</th><th class="total-col">Razem</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($summary['rows'] as $row) {
            $html .= '<tr><td class="rate-name">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td class="total-col">' . $row['total'] . '</td></tr>';
        }

        $html .= '<tr class="total-row"><td class="rate-name">Razem</td>';
        $html .= '<td class="total-col">' . $summary['grandTotal'] . '</td></tr>';
        $html .= '</tbody></table></div>';

        return $html;
    }

    private function formatCount(int $value): string
    {
        return $value > 0 ? (string) $value : '';
    }

    private function getHtmlHead(string $periodLabel): string
    {
        return '<!DOCTYPE html>
<html lang="pl_PL">
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; background:#fff; color:#000; padding:15px 20px; }
    .title { text-align:center; font-size:18px; font-weight:bold; margin-bottom:18px; }
    .week-block { margin-bottom:18px; page-break-inside:avoid; }
    .week-title { font-weight:bold; font-size:13px; margin-bottom:6px; }
    table { width:100%; border-collapse:collapse; table-layout:fixed; }
    th, td { border:1px solid #bbb; padding:4px 2px; text-align:center; font-size:10px; vertical-align:middle; word-wrap:break-word; }
    th { background:#f2f2f2; font-weight:bold; }
    .rate-col { width:18%; text-align:left; }
    .total-col { width:8%; background:#f7f7f7; font-weight:bold; }
    .shift-sub { background:#fafafa; font-size:9px; font-weight:normal; }
    .rate-name { text-align:left; padding-left:6px; font-weight:500; }
    .total-row td { background:#eaeaea; font-weight:bold; }
    .summary-block { margin-top:24px; max-width:420px; }
    .summary-table td, .summary-table th { padding:6px 10px; font-size:12px; }
    .empty { text-align:center; font-size:14px; color:#666; padding:40px; }
</style>
</head>
<body>
<div class="title">Paczki: ' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}
