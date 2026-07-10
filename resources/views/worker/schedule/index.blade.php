@extends('worker.layouts.app')

@section('title', 'Grafik - Strefa Pracownika')
@section('app_class', 'worker-schedule-shell')

@push('styles')
    @vite(['resources/css/worker-schedule.css'])
@endpush

@section('content')
    @php
        $dowAbbr = [
            'Poniedziałek' => 'Pn', 'Wtorek' => 'Wt', 'Środa' => 'Śr', 'Czwartek' => 'Cz',
            'Piątek' => 'Pt', 'Sobota' => 'Sb', 'Niedziela' => 'Nd',
        ];
    @endphp
    <main class="gr-page">
        <header class="gr-hero">
            <div class="gr-hero-text">
                <h1 class="gr-title">
                    Grafik
                    <span class="gr-hero-range">· {{ $weekStart->format('d.m') }} — {{ $weekEnd->format('d.m') }}</span>
                </h1>
                <p class="gr-hero-sub" data-week-summary>&nbsp;</p>
            </div>

            <nav class="gr-weeknav" aria-label="Nawigacja tygodni">
                <a href="{{ route('worker.schedule', ['week' => $prevWeek]) }}" class="gr-weeknav-btn" aria-label="Poprzedni tydzień">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </a>
                <span class="gr-weeknav-label">{{ $weekStart->format('d.m') }} — {{ $weekEnd->format('d.m') }}</span>
                <a href="{{ route('worker.schedule', ['week' => $nextWeek]) }}" class="gr-weeknav-btn" aria-label="Następny tydzień">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </nav>
        </header>

        @if($scheduleStatus['is_active'])
            <div class="gr-alert is-active">
                <div class="gr-alert-main">
                    <span class="gr-mono gr-alert-label">zapisy na grafik</span>
                    <div class="gr-alert-text" data-signup-info>{!! $scheduleStatus['text'] !!}</div>
                </div>
                <span class="gr-alert-pill" data-signup-countdown hidden></span>
            </div>
        @else
            <div class="gr-alert gr-alert-off">
                <div class="gr-alert-main">
                    <span class="gr-mono gr-alert-label">grafik</span>
                    <div class="gr-alert-text">Grafik jest obecnie <strong>Nieaktywny</strong> — zapisy niedostępne.</div>
                </div>
            </div>
        @endif

        <section class="gr-panel">
            <div class="gr-colhead">
                <span class="gr-mono">dzień</span>
                <span class="gr-mono">zmiana rano</span>
                <span class="gr-mono">zmiana popołudnie</span>
            </div>

            <div class="gr-days">
                @foreach($days as $day)
                    @php
                        $dayNum = \Illuminate\Support\Str::before($day['short_date'], ' ');
                        $monShort = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::after($day['short_date'], ' '));
                        $isWeekend = in_array($day['weekday'], ['Sobota', 'Niedziela'], true);
                        $mineAny = $day['assigned_morning'] || $day['assigned_afternoon'] || $day['morning'] || $day['afternoon'];
                    @endphp
                    <div @class([
                            'gr-day',
                            'is-today' => $day['is_today'],
                            'is-weekend' => $isWeekend,
                            'is-mine-day' => $mineAny,
                        ])
                         data-date="{{ $day['date'] }}">
                        <div class="gr-date">
                            <div class="gr-date-box">
                                <span class="gr-date-dow">{{ $dowAbbr[$day['weekday']] ?? \Illuminate\Support\Str::substr($day['weekday'], 0, 2) }}</span>
                                <span class="gr-date-num">{{ $dayNum }}</span>
                            </div>
                            <div class="gr-date-meta">
                                <span class="gr-date-name">{{ $day['weekday'] }}</span>
                                <span class="gr-date-mon">{{ $monShort }}</span>
                            </div>
                            @if($mineAny)
                                <span class="gr-day-tag">Zapisany</span>
                            @endif
                        </div>

                        @include('worker.schedule.partials.slot', ['day' => $day, 'type' => 'morning'])
                        @include('worker.schedule.partials.slot', ['day' => $day, 'type' => 'afternoon'])
                    </div>
                @endforeach
            </div>
        </section>
    </main>

    @php
        $scheduleDaysJs = [];
        foreach ($days as $day) {
            $scheduleDaysJs[$day['date']] = [
                'morning' => $day['morning'] ? '1' : '0',
                'afternoon' => $day['afternoon'] ? '1' : '0',
                'assignedMorning' => $day['assigned_morning'] ? '1' : '0',
                'assignedAfternoon' => $day['assigned_afternoon'] ? '1' : '0',
                'currentWeek' => $day['is_current_week'] ? '1' : '0',
                'isPast' => $day['is_past'] ? '1' : '0',
                'morningFrom' => $day['morning_from'],
                'morningTo' => $day['morning_to'],
                'morningSource' => $day['morning_source'],
                'morningMinutes' => $day['morning_minutes'],
                'afternoonFrom' => $day['afternoon_from'],
                'afternoonTo' => $day['afternoon_to'],
                'afternoonSource' => $day['afternoon_source'],
                'afternoonMinutes' => $day['afternoon_minutes'],
                'morningStatus' => $day['morning_status'],
                'afternoonStatus' => $day['afternoon_status'],
                'morningUnlockMinutes' => $day['morning_unlock_minutes'],
                'morningUnlockLabel' => $day['morning_unlock_label'],
                'afternoonUnlockMinutes' => $day['afternoon_unlock_minutes'],
                'afternoonUnlockLabel' => $day['afternoon_unlock_label'],
            ];
        }
        $scheduleConfigJs = [
            'hoursUrl' => route('worker.schedule.hours', ':date'),
            'availabilityUrl' => route('worker.schedule.availability', ':date'),
            'workerSelfHoursEnabled' => $workerSelfHoursEnabled,
        ];
    @endphp
    <script>
        window.scheduleDays = @json($scheduleDaysJs);
        window.scheduleConfig = @json($scheduleConfigJs);
    </script>
@endsection

@push('scripts')
    @vite(['resources/js/worker-schedule.js'])
@endpush
