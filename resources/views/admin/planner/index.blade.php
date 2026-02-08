@extends('layouts.app')

@section('title', 'Grafik - Panel administratora')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}">
    <link rel="stylesheet" href="{{ asset('css/planner.css') }}">
@endpush

@section('content')
    <div class="admin-panel">
        @include('partials.menu')

        <main class="main-content">
            <div class="header">
                <h1>Grafik pracy</h1>
                <p>Zarządzaj harmonogramem pracy. Wybierz datę, aby przypisać pracowników do zmian.</p>
                <a href="{{ route('planner.schedule.index') }}" class="btn btn-change">
                    <i class="fas fa-calendar-check"></i> Włącz grafik
                </a>
            </div>

            <div class="planner-calendar">
                <div class="calendar-navigation">
                    <a href="{{ route('planner.index', ['month' => $calendar['prev']]) }}" class="calendar-nav-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <h2 class="calendar-title">{{ $calendar['month'] }} {{ $calendar['year'] }}</h2>
                    <a href="{{ route('planner.index', ['month' => $calendar['next']]) }}" class="calendar-nav-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>

                <div class="calendar-weekdays">
                    <div class="weekday">Poniedziałek</div>
                    <div class="weekday">Wtorek</div>
                    <div class="weekday">Środa</div>
                    <div class="weekday">Czwartek</div>
                    <div class="weekday">Piątek</div>
                    <div class="weekday">Sobota</div>
                    <div class="weekday">Niedziela</div>
                </div>

                <div class="calendar-grid">
                    @for($i = 1; $i < $calendar['startDay']; $i++)
                        <div class="calendar-day calendar-day-empty"></div>
                    @endfor

                    @for($day = 1; $day <= $calendar['days']; $day++)
                        @php
                            $date = $start->copy()->setDay($day)->toDateString();
                            $isToday = $date === $calendar['today'];
                            $isSettled = in_array($date, $settled);
                            $dayShifts = $shifts[$date] ?? [];
                        @endphp

                        <a href="{{ route('planner.day.index', $date) }}" class="calendar-day {{ $isToday ? 'calendar-day-today' : '' }} {{ $isSettled ? 'calendar-day-settled' : '' }}">
                            <div class="day-header">
                                <span class="day-number">{{ $day }}</span>
                                @if($isSettled)
                                    <span class="day-settled-badge" title="Rozliczony">
                                    <i class="fas fa-check"></i>
                                </span>
                                @endif
                            </div>

                            <div class="day-shifts">
                                <div class="shift-section shift-morning">
                                    <div class="shift-label">
                                        <i class="fas fa-sun"></i>
                                        <span>Ranna</span>
                                    </div>
                                    <div class="shift-workers">
                                        @forelse($dayShifts['morning'] ?? [] as $worker)
                                            <div class="worker-name">{{ $worker }}</div>
                                        @empty
                                            <div class="no-workers">Brak przypisanych pracowników</div>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="shift-section shift-afternoon">
                                    <div class="shift-label">
                                        <i class="fas fa-cloud-sun"></i>
                                        <span>Popołudniowa</span>
                                    </div>
                                    <div class="shift-workers">
                                        @forelse($dayShifts['afternoon'] ?? [] as $worker)
                                            <div class="worker-name">{{ $worker }}</div>
                                        @empty
                                            <div class="no-workers">Brak przypisanych pracowników</div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endfor
                </div>

                <div class="calendar-legend">
                    <div class="legend-item">
                        <span class="legend-icon legend-today"></span>
                        <span>Dzisiaj</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-icon legend-settled"></span>
                        <span>Rozliczony</span>
                    </div>
                </div>
            </div>
        </main>
    </div>
@endsection
