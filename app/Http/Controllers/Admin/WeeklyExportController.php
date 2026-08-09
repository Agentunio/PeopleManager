<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\HtmlExportRenderer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WeeklyExportRequest;
use App\Services\WeeklyScheduleExportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class WeeklyExportController extends Controller
{
    public function __construct(
        private readonly WeeklyScheduleExportService $exportService,
        private readonly HtmlExportRenderer $renderer,
    ) {}

    public function export(WeeklyExportRequest $request)
    {
        $validated = $request->validated();

        $weekStart = Carbon::createFromFormat('Y-m-d', $validated['week_start'])->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $weekLabel = $weekStart->format('d.m').' - '.$weekEnd->format('d.m.Y');

        $weekData = $this->exportService->getWeekData($weekStart);

        $htmlForPdf = $this->exportService->generateHtmlTable($weekData, $weekLabel, true);

        $htmlForPng = $this->exportService->generateHtmlTable($weekData, $weekLabel, false);

        $uniqueSuffix = bin2hex(random_bytes(8));
        $filename = 'grafik_'.$weekStart->format('Y-m-d').'_'.$uniqueSuffix;

        $tempDir = storage_path('app/temp');
        File::ensureDirectoryExists($tempDir);

        $pdfPath = $tempDir.'/'.$filename.'.pdf';
        $pngPath = $tempDir.'/'.$filename.'.png';
        $zipPath = $tempDir.'/'.$filename.'.zip';

        try {
            $this->renderer->saveWeeklyAssets($htmlForPdf, $htmlForPng, $pdfPath, $pngPath);

            $zip = new ZipArchive;
            $zipResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($zipResult !== true) {
                throw new RuntimeException('Nie udało się utworzyć archiwum eksportu.');
            }

            $baseName = 'grafik_'.$weekStart->format('Y-m-d');
            $pdfAdded = $zip->addFile($pdfPath, $baseName.'.pdf');
            $pngAdded = $zip->addFile($pngPath, $baseName.'.png');
            $zipClosed = $zip->close();

            if (! $pdfAdded || ! $pngAdded || ! $zipClosed) {
                throw new RuntimeException('Nie udało się zapisać plików w archiwum eksportu.');
            }

            return response()->download($zipPath, $baseName.'.zip')->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            throw $exception;
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
