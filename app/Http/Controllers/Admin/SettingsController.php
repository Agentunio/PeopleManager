<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsUpdateRequest;
use App\Models\AppSetting;
use App\Models\WorkerShift;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.app-settings.index', [
            'workerSelfHoursEnabled' => AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS),
            'pendingWorkers' => collect(),
            'attemptedDisable' => false,
        ]);
    }

    public function update(SettingsUpdateRequest $request): RedirectResponse|View
    {
        $newValue = $request->boolean('worker_self_hours_enabled');
        $force = $request->boolean('force_disable_with_pending');
        $disabling = !$newValue;

        if ($disabling && !$force) {
            $pending = WorkerShift::published()
                ->where('hours_source', 'worker')
                ->whereNull('substituted_for_shift_id')
                ->whereNotNull('worker_from_time')
                ->with('worker:id,first_name,last_name')
                ->get(['id', 'worker_id', 'day', 'shift_type']);

            if ($pending->isNotEmpty()) {
                return view('admin.app-settings.index', [
                    'workerSelfHoursEnabled' => true,
                    'pendingWorkers' => $this->formatPendingWorkers($pending),
                    'attemptedDisable' => true,
                ]);
            }
        }

        AppSetting::set(AppSetting::KEY_WORKER_SELF_HOURS, $newValue);

        return redirect()->route('app-settings.index')->with('success', 'Ustawienia zostaly zapisane');
    }

    private function formatPendingWorkers(Collection $pending): Collection
    {
        return $pending->map(fn (WorkerShift $shift) => [
            'name' => trim(($shift->worker?->first_name ?? '') . ' ' . ($shift->worker?->last_name ?? '')),
            'date' => Carbon::parse($shift->day)->translatedFormat('d.m.Y'),
            'shift' => $shift->shift_type === 'morning' ? 'Poranna' : 'Popoludniowa',
        ]);
    }
}
