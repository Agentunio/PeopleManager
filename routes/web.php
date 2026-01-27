<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DayController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PlannerAvailableController;
use App\Http\Controllers\Admin\PlannerController;
use App\Http\Controllers\Admin\EndDayController;
use App\Http\Controllers\Admin\WorkerController;
use App\Http\Controllers\Guest\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/', [AuthController::class, 'login'])->middleware('check.login.attempts');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth',  'check.user.role:admin'])->group(function () {
    Route::get('/panel', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('pracownicy')->name('workers.')->group(function () {
        Route::get('/', [WorkerController::class, 'index'])->name('index');
        Route::post('/', [WorkerController::class, 'store'])->name('store');
        Route::put('/{worker}', [WorkerController::class, 'update'])->name('update');
        Route::delete('/{worker}', [WorkerController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('ustawienia')->name('settings.')->group(function () {
        Route::get('/', [PackageController::class, 'index'])->name('index');
        Route::post('/stawki', [PackageController::class, 'store'])->name('packages.store');
        Route::put('/stawki/{package}', [PackageController::class, 'update'])->name('packages.update');
        Route::delete('/stawki/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');
    });

    Route::prefix('grafik')->name('planner.')->group(function () {
       Route::get('/', [PlannerController::class, 'index'])->name('index');

        Route::prefix('dostepnosc')->name('schedule.')->group(function () {
            Route::get('/', [PlannerAvailableController::class, 'index'])->name('index');
            Route::post('/', [PlannerAvailableController::class, 'store'])->name('store');
        });


        Route::prefix('/{date}')->name('day.')->group(function () {
            Route::get('/', [DayController::class, 'index'])->name('index');
            Route::get('/rozliczenie', [EndDayController::class, 'index'])->name('end-day');
            Route::post('/dostepnosc-pracownika', [DayController::class, 'storeAvailability'])->name('availability');
            Route::post('zapisz-zmiane', [DayController::class, 'storeShift'])->name('shift');
        });
    });
});
