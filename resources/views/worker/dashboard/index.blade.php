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
        </header>

        <section class="dashboard-grid" aria-label="Panel pracownika">
            <div class="dashboard-main-column">
                <article class="section-card salary-panel">
                    <div class="card-row card-row-top">
                        <span class="mono-label">[ prognoza wynagrodzenia ]</span>
                        <span class="mono-label muted">BRUTTO · MIES.</span>
                    </div>

                    <div class="salary-headline">
                        <div class="salary-value">
                            <span data-salary-value>{{ number_format($stats['salary'], 2, ',', ' ') }}</span> <span class="salary-currency">zł</span>
                        </div>

                        <span @class(['salary-trend', 'is-down' => $salaryTrend && !$salaryTrend['isPositive']])
                              data-salary-trend
                              @if(!$salaryTrend) hidden @endif>
                            @if($salaryTrend)
                                {{ $salaryTrend['isPositive'] ? '↑' : '↓' }} {{ $salaryTrend['percent'] }}% vs {{ $salaryTrend['prev_month_label'] }}
                            @endif
                        </span>
                    </div>

                    <p class="salary-note">
                        za <strong data-salary-hours>{{ $stats['hours'] }}</strong>
                        <span data-salary-period>przepracowanych w tym miesiącu</span>
                    </p>

                    <div class="range-filter">
                        <div class="range-filter-head">
                            <div class="range-filter-title">
                                <span class="mono-label">filtr zakresu</span>
                                <span class="range-filter-dates" data-range-label aria-live="polite"></span>
                            </div>

                            <div class="range-chips">
                                <button type="button" class="range-chip" data-range-preset="week" aria-pressed="false">tydz. 1</button>
                                <button type="button" class="range-chip" data-range-preset="half" aria-pressed="false">pół mies.</button>
                                <button type="button" class="range-chip" data-range-preset="month" aria-pressed="false">cały mies.</button>
                            </div>

                            <button type="button" class="range-toggle" data-range-toggle aria-controls="dashboardRangeCalendar" aria-expanded="false">Pokaż kalendarz</button>
                        </div>

                        <div class="range-calendar" id="dashboardRangeCalendar" data-range-calendar aria-label="Kalendarz zakresu statystyk pracownika"></div>
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
                            <span class="next-shift-pill">{{ $nextShift['in_days_label'] }}</span>
                        @endif
                    </div>

                    <div class="next-shift-body">
                    @if($nextShift)
                        <div class="next-shift-date">
                            <span class="next-shift-weekday">{{ $nextShift['weekday'] }}</span>
                            <span class="next-shift-day">{{ $nextShift['short_date'] }}</span>
                        </div>

                        @foreach($nextShift['entries'] as $entry)
                            <div class="next-shift-slot">
                                <div>
                                    <span class="mono-label">start zmiany</span>
                                    <div class="next-shift-start">{{ $entry['start'] ?? '—' }}</div>
                                </div>
                                <div class="next-shift-kind">
                                    <span class="mono-label">typ zmiany</span>
                                    <div class="next-shift-kind-value">{{ $entry['label'] }}</div>
                                </div>
                            </div>
                        @endforeach

                        @if(count($upcomingShifts))
                            <div class="upcoming-shifts">
                                <span class="mono-label">kolejne zmiany</span>
                                <ul class="upcoming-list">
                                    @foreach($upcomingShifts as $upcoming)
                                        <li class="upcoming-item">
                                            <span class="upcoming-day">{{ $upcoming['weekday_abbr'] }} · {{ $upcoming['short_date'] }}</span>
                                            <span class="upcoming-time">{{ $upcoming['start_label'] ?? '—' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @else
                        <div class="empty-state">
                            <i class="fa-regular fa-calendar-xmark"></i>
                            <p>Brak zaplanowanych zmian</p>
                        </div>
                    @endif
                    </div>

                    <div @class(['signup-notice', 'is-off' => !$signup['is_active']])>
                        <div class="signup-notice-head">
                            <span class="signup-label">zapisy na grafik</span>
                            @if($signup['is_active'] && $signup['countdown_label'])
                                <span class="signup-pill">{{ $signup['countdown_label'] }}</span>
                            @endif
                        </div>

                        @if($signup['is_active'])
                            <p class="signup-deadline">
                                @if($signup['deadline'])
                                    Dostępne do <strong>{{ $signup['deadline'] }}</strong>
                                @else
                                    Zapisy otwarte bezterminowo
                                @endif
                            </p>

                            @if($signup['range_start'] && $signup['range_end'])
                                <p class="signup-range">
                                    Zakres zapisu: {{ $signup['range_start'] }} — {{ $signup['range_end'] }}@if($signup['relative_label']) ({{ $signup['relative_label'] }})@endif
                                </p>
                            @endif

                            <a href="{{ route('worker.schedule') }}" class="signup-btn">Zapisz się →</a>
                        @else
                            <p class="signup-deadline">Grafik wyłączony — zapisy niedostępne.</p>
                            <a href="{{ route('worker.schedule') }}" class="signup-btn is-ghost">Przejdź do grafiku</a>
                        @endif
                    </div>
                </article>
            </aside>
        </section>
    </main>

    <script>
        window.dashboardMonthDays = @json($monthDays);
        window.dashboardCalendar = @json($calendar);
    </script>
@endsection

@push('scripts')
    @vite(['resources/js/worker-dashboard.js'])
@endpush
