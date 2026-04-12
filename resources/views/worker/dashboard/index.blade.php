@extends('worker.layouts.app')

@section('title', 'Strefa Pracownika')

@push('styles')
    @vite(['resources/css/worker-dashboard.css'])
@endpush

@section('content')
    <div class="worker-content">
        <div class="dashboard-top">
            <div class="worker-header">
                <h1>Cześć, <span class="worker-name">{{ $worker->first_name }} {{ $worker->last_name }}</span></h1>
                <p class="greeting-sub">Twoje podsumowanie</p>
            </div>
            @include('worker.partials.schedule-status')
        </div>

        <div class="dashboard-grid">
                <div class="section-card next-shift">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <h2>Twoja najbliższa zmiana</h2>
                    </div>
                    <div class="section-body">
                        @if($nextShift)
                            <div class="next-shift-card">
                                <div class="next-shift-date">
                                    <span class="next-shift-weekday">{{ $nextShift['weekday'] }}</span>
                                    <span class="next-shift-day">{{ $nextShift['short_date'] }}</span>
                                </div>

                                <div class="next-shift-types">
                                    @if(in_array('morning', $nextShift['shifts']))
                                        <div class="next-shift-card-item morning">
                                            <i class="fa-solid fa-sun"></i>
                                            <span>Zmiana ranna</span>
                                        </div>
                                    @endif
                                    @if(in_array('afternoon', $nextShift['shifts']))
                                        <div class="next-shift-card-item afternoon">
                                            <i class="fa-solid fa-cloud-sun"></i>
                                            <span>Zmiana popołudniowa</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="empty-state">
                                <i class="fa-regular fa-calendar-xmark"></i>
                                <p>Brak zaplanowanych zmian</p>
                            </div>
                        @endif
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
                        @if($lastShift)
                            <div class="last-shift-info">
                                <span class="last-shift-weekday">{{ $lastShift['weekday'] }}</span>
                                <span class="last-shift-date">{{ $lastShift['short_date'] }}</span>
                            </div>

                            <div class="hours-form" id="dashboardHoursForm" data-date="{{ $lastShift['date'] }}" data-hours-url="{{ route('worker.schedule.hours', ':date') }}">
                                @foreach($lastShift['shifts'] as $type => $shift)
                                    <div class="dashboard-shift-group">
                                        <div class="shift-type-badge {{ $type === 'morning' ? 'morning-label' : 'afternoon-label' }}">
                                            <i class="fa-solid {{ $type === 'morning' ? 'fa-sun' : 'fa-cloud-sun' }}"></i>
                                            {{ $type === 'morning' ? 'Zmiana ranna' : 'Zmiana popołudniowa' }}
                                        </div>

                                        <div class="dashboard-shift-content" data-shift-type="{{ $type }}">
                                            @include('worker.dashboard.partials.shift-hours', ['shift' => $shift, 'type' => $type])
                                        </div>
                                    </div>
                                @endforeach

                                @if(collect($lastShift['shifts'])->contains(fn($s) => $s['status'] !== 'absent' && $s['hours_source'] !== 'admin'))
                                    @unless($lastShift['all_blocked'])
                                        <button class="btn-submit-hours" id="dashboardSaveHours">Zapisz godziny</button>
                                    @endunless
                                @endif
                            </div>
                        @else
                            <div class="empty-state">
                                <i class="fa-regular fa-clock"></i>
                                <p>Brak zmian do rozliczenia</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="stat-card hours-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">Przepracowane godziny</span>
                        <span class="stat-value">{{ $stats['hours'] }}</span>
                    </div>
                </div>

                <div class="stat-card salary-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">Przewidywane wynagrodzenie</span>
                        <span class="stat-value">{{ number_format($stats['salary'], 2, ',', ' ') }} zł</span>
                    </div>
                </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/worker-dashboard.js'])
@endpush
