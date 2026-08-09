<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\HtmlExportRenderer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExportDateRangeRequest;
use App\Services\PackageCountExportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class PackageCountExportController extends Controller
{
    public function __construct(
        private readonly PackageCountExportService $exportService,
        private readonly HtmlExportRenderer $renderer,
    ) {}

    public function export(ExportDateRangeRequest $request)
    {
        $validated = $request->validated();

        $startDate = Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $validated['end_date'])->endOfDay();

        $data = $this->exportService->getExportData($startDate, $endDate);
        $periodLabel = $startDate->format('d.m.Y').' - '.$endDate->format('d.m.Y');
        $html = $this->exportService->generateHtml($data, $periodLabel);

        $uniqueSuffix = bin2hex(random_bytes(8));
        $downloadName = 'paczki_'.$startDate->format('Y-m-d').'_'.$endDate->format('Y-m-d');
        $filename = $downloadName.'_'.$uniqueSuffix;

        $tempDir = storage_path('app/temp');
        File::ensureDirectoryExists($tempDir);

        $pdfPath = $tempDir.'/'.$filename.'.pdf';

        try {
            $this->renderer->savePdf($html, $pdfPath, landscape: true);

            return response()->download($pdfPath, $downloadName.'.pdf')->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
            throw $e;
        }
    }
}
