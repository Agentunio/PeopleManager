<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Services\PlannerCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlannerController extends Controller
{
    public function __construct(
        private readonly PlannerCalendarService $calendarService,
    ) {}

    public function index(Request $request): View
    {
        $date = $this->resolveDate($request->query('month'));
        $days = $this->calendarService->forMonth($date);
        $dayCollection = collect($days);
        $schedule = Schedule::getCurrent();
        $scheduleWindow = $schedule?->toAdminWindowArray() ?? Schedule::emptyAdminWindow();
        $defaultStart = now()->addWeek()->startOfWeek();
        $defaultDeadline = $defaultStart->copy()->subDays(3)->setTime(23, 59);

        if ($defaultDeadline->lte(now())) {
            $defaultStart->addWeek();
            $defaultDeadline = $defaultStart->copy()->subDays(3)->setTime(23, 59);
        }

        return view('admin.planner.index', [
            'days' => $days,
            'settled' => $dayCollection->where('status', 'settled')->pluck('date')->all(),
            'statusCounts' => [
                'all' => count($days),
                'draft' => $dayCollection->where('status', 'draft')->count(),
                'active' => $dayCollection->where('status', 'active')->count(),
                'settled' => $dayCollection->where('status', 'settled')->count(),
            ],
            'scheduleWindow' => $scheduleWindow,
            'scheduleType' => old('type', in_array($scheduleWindow['type'], ['signup', 'always', 'admin'], true)
                ? $scheduleWindow['type']
                : 'signup'),
            'scheduleHasErrors' => $this->scheduleHasErrors(),
            'windowMeta' => $this->windowMeta($scheduleWindow),
            'scheduleForm' => [
                'start_date' => $scheduleWindow['start_date'] ?? $defaultStart->toDateString(),
                'end_date' => $scheduleWindow['end_date'] ?? $defaultStart->copy()->addDays(13)->toDateString(),
                'deadline' => $scheduleWindow['deadline_input']
                    ?? $defaultDeadline->format('Y-m-d\TH:i'),
            ],
            'weeks' => $this->getWeeksForMonth($date),
            'calendar' => [
                'month' => ucfirst($date->locale('pl')->translatedFormat('F')),
                'year' => $date->year,
                'prev' => $date->copy()->subMonth()->format('Y-m'),
                'next' => $date->copy()->addMonth()->format('Y-m'),
            ],
        ]);
    }

    private function scheduleHasErrors(): bool
    {
        $errors = session('errors');

        return $errors !== null
            && $errors->hasAny(['type', 'signup_deadline', 'start_date', 'end_date']);
    }

    /**
     * Presentation meta for the schedule window banner.
     *
     * @return array{class: string, label: string, detail: string|null}
     */
    private function windowMeta(array $scheduleWindow): array
    {
        return match ($scheduleWindow['type']) {
            'signup' => [
                'class' => $scheduleWindow['allows_signup'] ? 'active' : 'closed',
                'label' => $scheduleWindow['allows_signup'] ? 'Aktywne okno zapisów' : 'Okno zapisów zamknięte',
                'detail' => $scheduleWindow['deadline'] ? 'deadline '.$scheduleWindow['deadline'] : null,
            ],
            'always' => [
                'class' => $scheduleWindow['allows_signup'] ? 'active' : 'closed',
                'label' => $scheduleWindow['allows_signup'] ? 'Zapisy zawsze otwarte' : 'Okno zapisów zamknięte',
                'detail' => $scheduleWindow['allows_signup'] ? 'bez terminu' : 'okres zakończony',
            ],
            'admin' => [
                'class' => 'admin',
                'label' => 'Tylko administrator',
                'detail' => 'zapisy pracowników wyłączone',
            ],
            default => [
                'class' => 'disabled',
                'label' => 'Grafik wyłączony',
                'detail' => 'uruchom nowy okres, aby rozpocząć planowanie',
            ],
        };
    }

    private function resolveDate(?string $month): Carbon
    {
        try {
            if ($month) {
                return Carbon::createFromFormat('!Y-m', $month);
            }

            return Carbon::now()->startOfMonth();
        } catch (\Exception) {
            return Carbon::now()->startOfMonth();
        }
    }

    private function getWeeksForMonth(Carbon $date): array
    {
        $weeks = [];
        $end = $date->copy()->endOfMonth();
        $currentWeekStart = $date->copy()->startOfMonth()->startOfWeek();

        while ($currentWeekStart <= $end) {
            $weekEnd = $currentWeekStart->copy()->endOfWeek();
            $weeks[] = [
                'start' => $currentWeekStart->copy(),
                'end' => $weekEnd,
                'label' => $currentWeekStart->format('d.m').' - '.$weekEnd->format('d.m.Y'),
                'value' => $currentWeekStart->format('Y-m-d'),
            ];
            $currentWeekStart->addWeek();
        }

        return $weeks;
    }
}
