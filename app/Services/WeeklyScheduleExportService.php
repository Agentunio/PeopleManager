<?php

namespace App\Services;

use App\Models\WorkerShift;
use Carbon\Carbon;

class WeeklyScheduleExportService
{
    private const DAYS = [
        1 => 'Poniedziałek',
        2 => 'Wtorek',
        3 => 'Środa',
        4 => 'Czwartek',
        5 => 'Piątek',
        6 => 'Sobota',
        7 => 'Niedziela',
    ];

    public function getWeekData(Carbon $weekStart): array
    {
        $weekEnd = $weekStart->copy()->endOfWeek();

        $shifts = WorkerShift::with('worker:id,first_name,last_name')
            ->whereBetween('day', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->groupBy('day');

        $weekData = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $dateString = $date->toDateString();

            $dayShifts = $shifts->get($dateString, collect());

            $weekData[] = [
                'date' => $date->format('d.m'),
                'dayName' => self::DAYS[$date->dayOfWeekIso],
                'morning' => $dayShifts->where('shift_type', 'morning')
                    ->map(fn($s) => $s->worker->first_name . ' ' . $s->worker->last_name)
                    ->values()
                    ->toArray(),
                'afternoon' => $dayShifts->where('shift_type', 'afternoon')
                    ->map(fn($s) => $s->worker->first_name . ' ' . $s->worker->last_name)
                    ->values()
                    ->toArray(),
            ];
        }

        return $weekData;
    }

    public function needsSeparatePages(array $weekData): bool
    {
        foreach ($weekData as $day) {
            if (count($day['morning']) > 10 || count($day['afternoon']) > 10) {
                return true;
            }
        }
        return false;
    }

    public function generateHtmlTable(array $weekData, string $weekLabel, bool $forPdf = false): string
    {
        $separatePages = $forPdf && $this->needsSeparatePages($weekData);

        if ($separatePages) {
            return $this->generateTwoPageHtml($weekData, $weekLabel);
        }

        return $this->generateSinglePageHtml($weekData, $weekLabel);
    }

    private function generateSinglePageHtml(array $weekData, string $weekLabel): string
    {
        $html = $this->getHtmlHead($weekLabel);
        $html .= $this->generateShiftTable($weekData, 'morning', 'Rano');
        $html .= '<div class="spacer"></div>';
        $html .= $this->generateShiftTable($weekData, 'afternoon', 'Popołudnie');
        $html .= '</body></html>';

        return $html;
    }

    private function generateTwoPageHtml(array $weekData, string $weekLabel): string
    {
        $html = $this->getHtmlHead($weekLabel);
        $html .= $this->generateShiftTable($weekData, 'morning', 'Rano');
        $html .= '<div class="page-break"></div>';
        $html .= '<div class="title">Grafik: ' . $weekLabel . '</div>';
        $html .= $this->generateShiftTable($weekData, 'afternoon', 'Popołudnie');
        $html .= '</body></html>';

        return $html;
    }

    private function generateShiftTable(array $weekData, string $shiftType, string $label): string
    {
        $maxWorkers = $this->getMaxWorkers($weekData, $shiftType);

        $html = '<table>';
        $html .= $this->generateTableHeader($weekData);
        $html .= '<tbody>';
        $html .= $this->generateShiftRow($weekData, $shiftType, $label, $maxWorkers);
        $html .= '</tbody></table>';

        return $html;
    }

    private function getMaxWorkers(array $weekData, string $shiftType): int
    {
        return max(1, max(array_map(fn($d) => count($d[$shiftType]), $weekData)));
    }

    private function generateTableHeader(array $weekData): string
    {
        $html = '<thead><tr><th class="shift-label">Zmiana</th>';

        foreach ($weekData as $day) {
            $html .= '<th>' . $day['dayName'] . '<br>' . $day['date'] . '</th>';
        }

        $html .= '</tr></thead>';

        return $html;
    }

    private function generateShiftRow(array $weekData, string $shiftType, string $label, int $maxWorkers): string
    {
        $html = '<tr><td class="shift-label">' . $label . '</td>';

        foreach ($weekData as $day) {
            $html .= '<td class="day-column"><div class="workers-container">';

            for ($i = 0; $i < $maxWorkers; $i++) {
                $worker = $day[$shiftType][$i] ?? '';
                $isLast = ($i === $maxWorkers - 1);

                if ($worker) {
                    $class = $isLast ? 'worker-cell last' : 'worker-cell';
                    $html .= '<div class="' . $class . '">' . htmlspecialchars($worker) . '</div>';
                } else {
                    $html .= '<div class="worker-cell empty">&nbsp;</div>';
                }
            }

            $html .= '</div></td>';
        }

        $html .= '</tr>';

        return $html;
    }

    private function getHtmlHead(string $weekLabel): string
    {
        return '<!DOCTYPE html>
    <html lang="pl_PL">
    <head>
        <meta charset="UTF-8">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: DejaVu Sans, Arial, sans-serif;
                background: #fff;
                padding: 15px 25px;
            }
            .title {
                text-align: center;
                font-size: 20px;
                font-weight: bold;
                margin-bottom: 15px;
                color: #000;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                background: #fff;
                table-layout: fixed;
            }
            th, td {
                border: 1px solid #eee;
                padding: 6px 4px;
                text-align: center;
                vertical-align: middle;
                font-size: 13px;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            th {
                background: #fff;
                font-weight: bold;
                font-size: 12px;
            }
            .shift-label {
                font-weight: bold;
                background: #fff;
                width: 110px;
                font-size: 13px;
            }
            .day-column {
                padding: 0;
                vertical-align: top;
            }
            .worker-cell {
                border-bottom: 1px solid #eee;
                padding: 5px 3px;
                min-height: 22px;
                font-size: 13px;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            .worker-cell.last {
                border-bottom: none;
            }
            .worker-cell.empty {
                border-bottom: none;
            }
            .workers-container {
                display: flex;
                flex-direction: column;
            }
            .spacer {
                height: 15px;
            }
            .page-break {
                page-break-after: always;
                height: 0;
                margin: 0;
                padding: 0;
            }
        </style>
    </head>
    <body>
        <div class="title">Grafik: ' . $weekLabel . '</div>';
    }
}
