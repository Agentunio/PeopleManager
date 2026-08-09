<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsIndexRequest;
use App\Http\Requests\Admin\SettingsUpdateRequest;
use App\Models\AppSetting;
use App\Services\PendingWorkerHoursService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly PendingWorkerHoursService $pendingWorkerHoursService,
    ) {}

    public function index(SettingsIndexRequest $request): View
    {
        $workerSelfHoursEnabled = AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS);
        $attemptedDisable = $workerSelfHoursEnabled && $request->boolean('pending');
        $pendingWorkers = $attemptedDisable
            ? $this->pendingWorkersPage($request->integer('page', 1))
            : collect();

        if ($attemptedDisable && $pendingWorkers->total() === 0) {
            $attemptedDisable = false;
            $pendingWorkers = collect();
        }

        return view('admin.app-settings.index', [
            'workerSelfHoursEnabled' => $workerSelfHoursEnabled,
            'pendingWorkers' => $pendingWorkers,
            'attemptedDisable' => $attemptedDisable,
        ]);
    }

    public function update(SettingsUpdateRequest $request): RedirectResponse|View
    {
        $newValue = $request->boolean('worker_self_hours_enabled');
        $force = $request->boolean('force_disable_with_pending');
        $disabling = ! $newValue;

        if ($disabling && ! $force) {
            $pendingWorkers = $this->pendingWorkersPage();

            if ($pendingWorkers->total() > 0) {
                return view('admin.app-settings.index', [
                    'workerSelfHoursEnabled' => true,
                    'pendingWorkers' => $pendingWorkers,
                    'attemptedDisable' => true,
                ]);
            }
        }

        AppSetting::set(AppSetting::KEY_WORKER_SELF_HOURS, $newValue);

        return redirect()->route('app-settings.index')->with('success', 'Ustawienia zostaly zapisane');
    }

    private function pendingWorkersPage(int $page = 1): LengthAwarePaginator
    {
        return $this->pendingWorkerHoursService
            ->paginate($page)
            ->withPath(route('app-settings.index'))
            ->appends(['pending' => 1]);
    }
}
