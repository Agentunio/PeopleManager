<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WeeklyScheduleExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;

class WeeklyExportController extends Controller
{
    public function __construct(
        private readonly WeeklyScheduleExportService $exportService
    ) {}

    public function export(Request $request)
    {
        $request->validate([
            'week_start' => 'required|date',
        ]);

        $weekStart = Carbon::parse($request->week_start)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $weekLabel = $weekStart->format('d.m') . ' - ' . $weekEnd->format('d.m.Y');

        $weekData = $this->exportService->getWeekData($weekStart);

        $htmlForPdf = $this->exportService->generateHtmlTable($weekData, $weekLabel, true);

        $htmlForPng = $this->exportService->generateHtmlTable($weekData, $weekLabel, false);

        $filename = 'grafik_' . $weekStart->format('Y-m-d');

        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $pdfPath = $tempDir . '/' . $filename . '.pdf';
        $pngPath = $tempDir . '/' . $filename . '.png';

        Browsershot::html($htmlForPdf)
            ->setChromePath(env('BROWSERSHOT_CHROME_PATH', '/usr/bin/chromium-browser'))
            ->setOption('landscape', true)
            ->format('A4')
            ->margins(0, 0, 0, 0)
            ->save($pdfPath);

        Browsershot::html($htmlForPng)
            ->windowSize(1600, 900)
            ->fullPage()
            ->save($pngPath);

        $zipPath = $tempDir . '/' . $filename . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFile($pdfPath, $filename . '.pdf');
            $zip->addFile($pngPath, $filename . '.png');
            $zip->close();
        }

        @unlink($pdfPath);
        @unlink($pngPath);

        return response()->download($zipPath, $filename . '.zip')->deleteFileAfterSend(true);
    }
}
