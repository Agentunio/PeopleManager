@extends('layouts.app')

@section('title', 'Panel administratora')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/system.css') }}">
@endpush

@section('content')
<div class="admin-panel">
    @include('partials.menu')

    <main class="main-content">
        <div class="content-grid">
            <div class="card" id="podsumowanie">
                <h2>Podsumowanie</h2>
                <div class="card-content">
                    <div class="summary-item">
                        <p class="summary-label">Przepracowane godziny (mc):</p>
                        <p class="summary-value">1,240 h</p>
                    </div>
                    <div class="summary-item">
                        <p class="summary-label">Dostarczone paczki:</p>
                        <p class="summary-value">8,921</p>
                    </div>
                    <div class="summary-item">
                        <p class="summary-label">Przychód:</p>
                        <p class="summary-value">150,200 PLN</p>
                    </div>
                </div>
                <a href="#" class="card-link">Zobacz więcej &rarr;</a>
            </div>
            <div class="card" id="podsumowanie">
                <h2>Podsumowanie dnia</h2>
                <div class="card-content">
                    <div class="summary-item">
                        <p class="summary-label">Przepracowane godziny (mc):</p>
                        <p class="summary-value">1,240 h</p>
                    </div>
                    <div class="summary-item">
                        <p class="summary-label">Dostarczone paczki:</p>
                        <p class="summary-value">8,921</p>
                    </div>
                    <div class="summary-item">
                        <p class="summary-label">Data</p>
                        <p class="summary-value">29.09.2025</p>
                    </div>
                </div>
                <a href="#" class="card-link">Zobacz więcej &rarr;</a>
            </div>
            <div class="card" id="pracownicy">
                <h2>Pracownicy</h2>
                <div class="card-content">
                    <ul class="employee-list">
                        @forelse($workers as $worker)
                        <li>
                            <span>{{ $worker->first_name }} {{ $worker->last_name }}</span>
                            <span>{{ $worker->is_employed ? 'Zatrudniony' : 'Niezatrudniony' }}</span>
                        </li>
                        @empty
                        <li>
                            <span>Brak pracowników</span>
                        </li>
                        @endforelse
                    </ul>
                </div>
                <a href="{{ route('workers.index') }}" class="card-link">Zarządzaj pracownikami &rarr;</a>
            </div>
            <div class="card" id="grafik">
                <h2>Grafik</h2>
                <div class="card-content">
                    <p>Podgląd najbliższych zmian i zaplanowanych zadań.</p>
                    <div class="schedule-item">
                        <strong>Dziś (26.09):</strong> 8:00 - 16:00 - Zmiana A
                    </div>
                    <div class="schedule-item">
                        <strong>Jutro (27.09):</strong> 10:00 - 18:00 - Zmiana B
                    </div>
                </div>
                <a href="#" class="card-link">Otwórz grafik &rarr;</a>
            </div>
            <div class="card" id="ustawienia">
                <h2>Ustawienia</h2>
                <div class="card-content">
                    <p>Zarządzaj ustawieniami konta, powiadomieniami i integracjami systemu.</p>
                </div>
                <a href="{{ route('settings.index') }}" class="card-link">Przejdź do ustawień &rarr;</a>
            </div>
        </div>
    </main>
</div>
@endsection
