<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PackageCountExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;

class PackageCountExportController extends Controller
{
    public function __construct(
        private readonly PackageCountExportService $exportService,
    ) {}

    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $data = $this->exportService->getExportData($startDate, $endDate);
        $periodLabel = $startDate->format('d.m.Y') . ' - ' . $endDate->format('d.m.Y');
        $html = $this->exportService->generateHtml($data, $periodLabel);

        $uniqueSuffix = bin2hex(random_bytes(8));
        $downloadName = 'paczki_' . $startDate->format('Y-m-d') . '_' . $endDate->format('Y-m-d');
        $filename = $downloadName . '_' . $uniqueSuffix;

        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $pdfPath = $tempDir . '/' . $filename . '.pdf';

        try {
            Browsershot::html($html)
                ->setChromePath(config('services.chrome.path'))
                ->noSandbox()
                ->setOption('landscape', true)
                ->format('A4')
                ->margins(10, 10, 10, 10)
                ->save($pdfPath);

            return response()->download($pdfPath, $downloadName . '.pdf')->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
            throw $e;
        }
    }
}
