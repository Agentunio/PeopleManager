@extends('worker.layouts.app')

@section('title', 'Strefa Pracownika')
@section('app_class', 'worker-dashboard-shell')

@push('styles')
    @vite(['resources/css/worker-dashboard.css'])
@endpush

@section('content')
    <main class="worker-dashboard-page">
        <header class="dashboard-hero">
            <div class="worker-header">
                <h1>
                    Cześć,
                    <span class="worker-name">{{ $worker->first_name }}</span><span class="worker-last-name"> {{ $worker->last_name }}</span>
                </h1>
                <p class="greeting-sub">
                    @if($nextShift && collect($nextShift['start_labels'])->filter()->isNotEmpty())
                        Najbliższy start:
                        <strong>{{ collect($nextShift['start_labels'])->filter()->first() }}</strong>
                    @else
                        Twoje podsumowanie pracy
                    @endif
                </p>
            </div>

            @include('worker.partials.schedule-status')
        </header>

        <section class="dashboard-grid" aria-label="Panel pracownika">
            <div class="dashboard-main-column">
                <article class="section-card salary-panel">
                    <div class="card-row card-row-top">
                        <span class="mono-label">[ prognoza wynagrodzenia ]</span>
                        <span class="mono-label muted">BRUTTO · MIES.</span>
                    </div>

                    <div class="salary-value">
                        {{ number_format($stats['salary'], 2, ',', ' ') }}
                        <span>zł</span>
                    </div>

                    <p class="salary-note">
                        za <strong>{{ $stats['hours'] }}</strong> przepracowanych w tym miesiącu
                    </p>

                    <div class="stats-strip">
                        <div class="stat-card hours-card">
                            <span class="stat-label">Przepracowane godziny</span>
                            <span class="stat-value">{{ $stats['hours'] }}</span>
                        </div>
                        <div class="stat-card salary-card">
                            <span class="stat-label">Stawka rozliczenia</span>
                            <span class="stat-value">{{ number_format($stats['salary'], 2, ',', ' ') }} zł</span>
                        </div>
                    </div>
                </article>

                @if($showLastShiftCard)
                    <article class="section-card enter-hours">
                        <div class="card-row card-row-top">
                            <div>
                                <span class="mono-label">[ {{ $workerSelfHoursEnabled ? 'wpisz godziny — ostatnia zmiana' : 'ostatnia zmiana' }} ]</span>
                                @if($lastShift)
                                    <h2>{{ $lastShift['weekday'] }}, {{ $lastShift['short_date'] }}</h2>
                                @endif
                            </div>

                            @if($workerSelfHoursEnabled)
                                <div class="dashboard-status-pill">
                                    <span class="status-dot"></span>
                                    <span>do potwierdzenia</span>
                                </div>
                            @endif
                        </div>

                        @if($lastShift)
                            <div class="hours-form" id="dashboardHoursForm" data-date="{{ $lastShift['date'] }}" data-hours-url="{{ route('worker.schedule.hours', ':date') }}">
                                @foreach($lastShift['shifts'] as $type => $shift)
                                    <div class="dashboard-shift-group">
                                        <div class="shift-type-badge {{ $type === 'morning' ? 'morning-label' : 'afternoon-label' }}">
                                            <span>{{ $type === 'morning' ? 'Zmiana ranna' : 'Zmiana popołudniowa' }}</span>
                                            @if($shift['start_label'])
                                                <span class="shift-start-label">
                                                    Start: {{ $shift['start_label'] }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="dashboard-shift-content" data-shift-type="{{ $type }}">
                                            @include('worker.dashboard.partials.shift-hours', ['shift' => $shift, 'type' => $type, 'workerSelfHoursEnabled' => $workerSelfHoursEnabled])
                                        </div>
                                    </div>
                                @endforeach

                                @if($workerSelfHoursEnabled && collect($lastShift['shifts'])->contains(fn($s) => $s['status'] !== 'absent' && $s['hours_source'] !== 'admin'))
                                    @unless($lastShift['all_blocked'])
                                        <button class="btn-submit-hours" id="dashboardSaveHours">Potwierdź godziny</button>
                                    @endunless
                                @endif
                            </div>
                        @endif
                    </article>
                @endif
            </div>

            <aside class="dashboard-side-column">
                <article class="section-card next-shift">
                    <div class="card-row card-row-top">
                        <span class="mono-label">[ następna zmiana ]</span>
                        @if($nextShift)
                            <span class="next-shift-pill">{{ $nextShift['weekday'] }}</span>
                        @endif
                    </div>

                    @if($nextShift)
                        <div class="next-shift-date">
                            <span class="next-shift-weekday">{{ $nextShift['weekday'] }}</span>
                            <span class="next-shift-day">{{ $nextShift['short_date'] }}</span>
                        </div>

                        <div class="next-shift-types">
                            @if(in_array('morning', $nextShift['shifts']))
                                <div class="next-shift-card-item morning">
                                    <span>Zmiana ranna</span>
                                    @if($nextShift['start_labels']['morning'])
                                        <span class="shift-start-label">
                                            Start: {{ $nextShift['start_labels']['morning'] }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                            @if(in_array('afternoon', $nextShift['shifts']))
                                <div class="next-shift-card-item afternoon">
                                    <span>Zmiana popołudniowa</span>
                                    @if($nextShift['start_labels']['afternoon'])
                                        <span class="shift-start-label">
                                            Start: {{ $nextShift['start_labels']['afternoon'] }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="empty-state">
                            <i class="fa-regular fa-calendar-xmark"></i>
                            <p>Brak zaplanowanych zmian</p>
                        </div>
                    @endif
                </article>

                <article class="section-card schedule-panel">
                    <span class="mono-label">[ status grafiku ]</span>
                    @include('worker.partials.schedule-status')
                    <a href="{{ route('worker.schedule') }}" class="schedule-link">Przejdź do grafiku →</a>
                </article>
            </aside>
        </section>
    </main>
@endsection

@push('scripts')
    @vite(['resources/js/worker-dashboard.js'])
@endpush
