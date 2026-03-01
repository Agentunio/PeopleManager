<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class WorkerCostExportService
{
    public function generateHtml(Collection $workers, float $totalCost, string $periodLabel): string
    {
        $rows = '';
        foreach ($workers as $worker) {
            $name = htmlspecialchars($worker->first_name . ' ' . $worker->last_name);
            $hours = $worker->stats['totalMinutes'] > 0
                ? htmlspecialchars($worker->stats['hours'])
                : 'Brak danych';
            $salary = $worker->stats['salary'] > 0
                ? number_format($worker->stats['salary'], 2, ',', ' ') . ' zł'
                : 'Brak danych';

            $rows .= "<tr>
                <td>{$name}</td>
                <td class=\"center\">{$hours}</td>
                <td class=\"right\">{$salary}</td>
            </tr>";
        }

        $totalFormatted = number_format($totalCost, 2, ',', ' ');
        $count = $workers->count();
        $generatedAt = now()->format('d.m.Y H:i');

        return "<!DOCTYPE html>
<html lang=\"pl\">
<head>
    <meta charset=\"UTF-8\">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            background: #fff;
            padding: 30px 40px;
            color: #1a1a1a;
        }
        .header {
            margin-bottom: 25px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .header h1 {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .header .period {
            font-size: 13px;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #555;
            padding: 10px 12px;
            border-bottom: 2px solid #ddd;
            text-align: left;
        }
        th.center { text-align: center; }
        th.right { text-align: right; }
        td {
            padding: 10px 12px;
            font-size: 13px;
            border-bottom: 1px solid #eee;
        }
        td.center { text-align: center; }
        td.right { text-align: right; }
        .summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 12px 18px;
            margin-top: 10px;
        }
        .summary-label {
            font-size: 13px;
            color: #555;
        }
        .summary-value {
            font-size: 16px;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            color: #999;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class=\"header\">
        <h1>Koszty pracowników</h1>
        <div class=\"period\">Okres: {$periodLabel} &middot; Liczba pracowników: {$count}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Pracownik</th>
                <th class=\"center\">Godziny</th>
                <th class=\"right\">Koszt</th>
            </tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
    </table>

    <div class=\"summary\">
        <span class=\"summary-label\">Łączny koszt:</span>
        <span class=\"summary-value\">{$totalFormatted} zł</span>
    </div>

    <div class=\"footer\">Wygenerowano: {$generatedAt}</div>
</body>
</html>";
    }
}
