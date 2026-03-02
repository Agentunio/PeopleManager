<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use App\Services\WorkerCostExportService;
use App\Services\WorkerStatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;

class WorkerCostExportController extends Controller
{
    public function __construct(
        private readonly WorkerStatsService $workerStatsService,
        private readonly WorkerCostExportService $exportService,
    ) {}

    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $workers = Worker::select('id', 'first_name', 'last_name')
            ->whereHas('shifts', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('day', [$startDate, $endDate]);
            })->get();

        $workersWithStats = $this->workerStatsService
            ->getStatsForWorkers($workers, $startDate, $endDate);

        $totalCost = $workersWithStats->sum(fn($worker) => $worker->stats['salary']);

        $periodLabel = $startDate->format('d.m.Y') . ' - ' . $endDate->format('d.m.Y');

        $html = $this->exportService->generateHtml($workersWithStats, $totalCost, $periodLabel);

        $uniqueSuffix = bin2hex(random_bytes(8));
        $downloadName = 'pracownicy_' . $startDate->format('Y-m-d') . '_' . $endDate->format('Y-m-d');
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
