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

        $uniqueSuffix = bin2hex(random_bytes(8));
        $filename = 'grafik_' . $weekStart->format('Y-m-d') . '_' . $uniqueSuffix;

        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $pdfPath = $tempDir . '/' . $filename . '.pdf';
        $pngPath = $tempDir . '/' . $filename . '.png';
        $zipPath = $tempDir . '/' . $filename . '.zip';

        try {
            Browsershot::html($htmlForPdf)
                ->setChromePath(config('services.chrome.path'))
                ->noSandbox()
                ->setOption('landscape', true)
                ->format('A4')
                ->margins(0, 0, 0, 0)
                ->save($pdfPath);

            Browsershot::html($htmlForPng)
                ->setChromePath(config('services.chrome.path'))
                ->noSandbox()
                ->windowSize(1600, 900)
                ->fullPage()
                ->save($pngPath);

            $zip = new \ZipArchive();

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $zip->addFile($pdfPath, 'grafik_' . $weekStart->format('Y-m-d') . '.pdf');
                $zip->addFile($pngPath, 'grafik_' . $weekStart->format('Y-m-d') . '.png');
                $zip->close();
            }

            return response()->download($zipPath, 'grafik_' . $weekStart->format('Y-m-d') . '.zip')->deleteFileAfterSend(true);
        } finally {
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
            if (file_exists($pngPath)) {
                unlink($pngPath);
            }
        }
    }
}
