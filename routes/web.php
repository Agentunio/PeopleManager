<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DayController;
use App\Http\Controllers\Admin\EndDayController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PackageCountExportController;
use App\Http\Controllers\Admin\PlannerAvailableController;
use App\Http\Controllers\Admin\PlannerController;
use App\Http\Controllers\Admin\PlannerShiftActionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\WeeklyExportController;
use App\Http\Controllers\Admin\WorkerAccountController;
use App\Http\Controllers\Admin\WorkerController;
use App\Http\Controllers\Admin\WorkerCostExportController;
use App\Http\Controllers\Guest\AccountActivationController;
use App\Http\Controllers\Guest\AuthController;
use App\Http\Controllers\Guest\NewPasswordController;
use App\Http\Controllers\Guest\PasswordResetLinkController;
use App\Http\Controllers\Worker\DashboardController as WorkerDashboardController;
use App\Http\Controllers\Worker\ScheduleController as WorkerScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/', [AuthController::class, 'login'])->middleware('check.login.attempts');

    Route::get('/zapomniane-haslo', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/zapomniane-haslo', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset-email')->name('password.email');
    Route::get('/reset-hasla/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-hasla', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset-update')->name('password.update');

    Route::get('/aktywacja/{token}', [AccountActivationController::class, 'show'])->name('account.activate');
    Route::post('/aktywacja/{token}/verify', [AccountActivationController::class, 'verify'])->middleware('throttle:10,15')->name('account.verify');
    Route::post('/aktywacja/{token}/activate', [AccountActivationController::class, 'activate'])->middleware('throttle:10,15')->name('account.activate.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth',  'check.user.role:admin'])->group(function () {
    Route::get('/panel', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/panel/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::post('/panel/eksport-kosztow', [WorkerCostExportController::class, 'export'])
        ->middleware('throttle:exports')
        ->name('dashboard.export.costs');
    Route::post('/panel/eksport-paczek', [PackageCountExportController::class, 'export'])
        ->middleware('throttle:exports')
        ->name('dashboard.export.packages');

    Route::prefix('pracownicy')->name('workers.')->group(function () {
        Route::get('/', [WorkerController::class, 'index'])->name('index');
        Route::post('/', [WorkerController::class, 'store'])->name('store');
        Route::get('/rozliczenia/dane', [WorkerController::class, 'settlements'])->name('settlements');
        Route::put('/{worker}', [WorkerController::class, 'update'])->name('update');
        Route::delete('/{worker}', [WorkerController::class, 'destroy'])->name('destroy');

        Route::post('/{worker}/account', [WorkerAccountController::class, 'store'])->name('account.store');
        Route::post('/{worker}/account/regenerate', [WorkerAccountController::class, 'regenerateLink'])->name('account.regenerate');
        Route::post('/{worker}/account/password-reset', [WorkerAccountController::class, 'sendPasswordResetLink'])
            ->middleware('throttle:admin-password-reset')
            ->name('account.password-reset');
        Route::post('/{worker}/account/toggle', [WorkerAccountController::class, 'toggle'])->name('account.toggle');
    });

    Route::prefix('stawki')->name('settings.')->group(function () {
        Route::get('/', [PackageController::class, 'index'])->name('index');
        Route::post('/', [PackageController::class, 'store'])->name('packages.store');
        Route::put('/{package}', [PackageController::class, 'update'])->name('packages.update');
        Route::delete('/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');
        Route::post('/domyslna', [PackageController::class, 'setDefault'])->name('packages.default');
    });

    Route::prefix('ustawienia')->name('app-settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::post('/', [SettingsController::class, 'update'])->name('update');
    });

    Route::prefix('grafik')->name('planner.')->group(function () {
        Route::get('/', [PlannerController::class, 'index'])->name('index');
        Route::post('/eksport-tygodnia', [WeeklyExportController::class, 'export'])
            ->middleware('throttle:exports')
            ->name('export.week');

        Route::post('/dostepnosc', [PlannerAvailableController::class, 'store'])->name('schedule.store');

        Route::prefix('/{date}')->where(['date' => '\d{4}-\d{2}-\d{2}'])->name('day.')->group(function () {
            Route::get('/', [DayController::class, 'index'])->name('index');
            Route::get('/rozliczenie', [EndDayController::class, 'index'])->name('end-day');
            Route::get('/zastepstwo-dostepni', [EndDayController::class, 'availableForSubstitution'])->name('substitution.available');
            Route::post('/dostepnosc-pracownika', [DayController::class, 'storeAvailability'])->name('availability');
            Route::post('zapisz-zmiane', [DayController::class, 'storeShift'])->name('shift');
            Route::patch('/rozliczenie', [EndDayController::class, 'update'])->name('update');

            Route::prefix('zmiany/{workerShift}')
                ->where(['workerShift' => '\\d+'])
                ->name('shifts.')
                ->group(function () {
                    Route::patch('/status', [PlannerShiftActionController::class, 'updateStatus'])->name('status');
                    Route::get('/zastepcy', [PlannerShiftActionController::class, 'substituteCandidates'])->name('substitutes.index');
                    Route::post('/zastepstwo', [PlannerShiftActionController::class, 'storeSubstitute'])->name('substitutes.store');
                    Route::delete('/', [PlannerShiftActionController::class, 'destroy'])->name('destroy');
                });
        });
    });
});

Route::middleware(['auth', 'check.user.role:worker'])->prefix('strefa-pracownika')->name('worker.')->group(function () {
    Route::get('/', [WorkerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/statystyki', [WorkerDashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/grafik/{week?}', [WorkerScheduleController::class, 'index'])->name('schedule')
        ->where('week', '\d{2}-\d{2}-\d{4}');
    Route::post('/grafik/dostepnosc/{date}', [WorkerScheduleController::class, 'storeAvailability'])->name('schedule.availability')
        ->where('date', '\d{4}-\d{2}-\d{2}');
    Route::post('/grafik/godziny/{date}', [WorkerScheduleController::class, 'storeHours'])->name('schedule.hours')
        ->where('date', '\d{4}-\d{2}-\d{2}');
});
