<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlannerShiftStatusRequest;
use App\Http\Requests\Admin\PlannerSubstituteStoreRequest;
use App\Models\WorkerShift;
use App\Services\PlannerCalendarService;
use App\Services\PlannerShiftActionService;
use Illuminate\Http\JsonResponse;

class PlannerShiftActionController extends Controller
{
    public function __construct(
        private readonly PlannerShiftActionService $shiftActionService,
        private readonly PlannerCalendarService $calendarService,
    ) {}

    public function updateStatus(
        PlannerShiftStatusRequest $request,
        string $date,
        WorkerShift $workerShift,
    ): JsonResponse {
        $this->ensureDateMatches($date, $workerShift);
        $status = $request->validated('status');
        $this->shiftActionService->updateStatus($workerShift, $status);

        return $this->dayResponse(
            $date,
            $status === 'absent'
                ? 'Pracownik został oznaczony jako niedostępny.'
                : 'Przywrócono dostępność pracownika.',
        );
    }

    public function substituteCandidates(string $date, WorkerShift $workerShift): JsonResponse
    {
        $this->ensureDateMatches($date, $workerShift);

        return response()->json([
            'data' => $this->shiftActionService->substituteCandidates($workerShift)->values(),
        ]);
    }

    public function storeSubstitute(
        PlannerSubstituteStoreRequest $request,
        string $date,
        WorkerShift $workerShift,
    ): JsonResponse {
        $this->ensureDateMatches($date, $workerShift);
        $this->shiftActionService->addSubstitute(
            $workerShift,
            (int) $request->validated('worker_id'),
        );

        return $this->dayResponse($date, 'Zastępstwo zostało przypisane.');
    }

    public function destroy(string $date, WorkerShift $workerShift): JsonResponse
    {
        $this->ensureDateMatches($date, $workerShift);
        $this->shiftActionService->remove($workerShift);

        return $this->dayResponse($date, 'Pracownik został usunięty z grafiku.');
    }

    private function dayResponse(string $date, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'day_html' => view('admin.planner.partials.day-card', [
                'day' => $this->calendarService->forDate($date),
            ])->render(),
        ]);
    }

    private function ensureDateMatches(string $date, WorkerShift $workerShift): void
    {
        abort_unless((string) $workerShift->day === $date, 404);
    }
}
