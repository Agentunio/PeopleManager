@extends('worker.layouts.app')

@section('title', 'Strefa Pracownika')

@push('styles')
    @vite(['resources/css/worker-dashboard.css'])
@endpush

@section('content')
    <div class="worker-content">
        <div class="dashboard-top">
            <div class="worker-header">
                <h1>Cześć, <span class="worker-name">Jan Kowalski</span></h1>
                <p class="greeting-sub">Twoje podsumowanie</p>
            </div>
            <div class="schedule-status">
                <i class="fa-solid fa-circle"></i>
                <span>Grafik: <strong>Nieaktywny</strong></span>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="col-left">
                <div class="section-card next-shift">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <h2>Najbliższa zmiana</h2>
                    </div>
                    <div class="section-body">
                        <div class="empty-state">
                            <i class="fa-regular fa-calendar-xmark"></i>
                            <p>Brak zaplanowanych zmian</p>
                        </div>
                    </div>
                </div>

                <div class="section-card enter-hours">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </div>
                        <h2>Wpisz godziny z ostatniej zmiany</h2>
                    </div>
                    <div class="section-body">
                        <div class="empty-state">
                            <i class="fa-regular fa-clock"></i>
                            <p>Brak zmian do rozliczenia</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-right">
                <div class="stat-card hours-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">Przepracowane godziny</span>
                        <span class="stat-value">0h 0min</span>
                    </div>
                </div>

                <div class="stat-card salary-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">Przewidywane wynagrodzenie</span>
                        <span class="stat-value">0,00 zł</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
