<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\HtmlExportRenderer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExportDateRangeRequest;
use App\Services\WorkerCostExportService;
use App\Services\WorkerStatsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class WorkerCostExportController extends Controller
{
    public function __construct(
        private readonly WorkerStatsService $workerStatsService,
        private readonly WorkerCostExportService $exportService,
        private readonly HtmlExportRenderer $renderer,
    ) {}

    public function export(ExportDateRangeRequest $request)
    {
        $validated = $request->validated();

        $startDate = Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $validated['end_date'])->endOfDay();

        $workersWithStats = $this->workerStatsService->getCostExportRows($startDate, $endDate);

        $totalCost = $workersWithStats->sum(fn ($worker) => $worker->stats['salary']);

        $periodLabel = $startDate->format('d.m.Y').' - '.$endDate->format('d.m.Y');

        $html = $this->exportService->generateHtml($workersWithStats, $totalCost, $periodLabel);

        $uniqueSuffix = bin2hex(random_bytes(8));
        $downloadName = 'pracownicy_'.$startDate->format('Y-m-d').'_'.$endDate->format('Y-m-d');
        $filename = $downloadName.'_'.$uniqueSuffix;

        $tempDir = storage_path('app/temp');
        File::ensureDirectoryExists($tempDir);

        $pdfPath = $tempDir.'/'.$filename.'.pdf';

        try {
            $this->renderer->savePdf($html, $pdfPath);

            return response()->download($pdfPath, $downloadName.'.pdf')->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
            throw $e;
        }
    }
}
