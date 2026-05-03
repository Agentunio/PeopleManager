@extends('worker.layouts.app')

@section('title', 'Grafik - Strefa Pracownika')

@push('styles')
    @vite(['resources/css/worker-schedule.css'])
@endpush

@section('content')
    <div class="worker-content schedule-page">
        <div class="dashboard-top">
            <div class="worker-header">
                <h1><i class="fa-solid fa-calendar-days"></i> Grafik</h1>
                <p class="greeting-sub">Twój tygodniowy harmonogram</p>
            </div>
            @include('worker.partials.schedule-status')
        </div>

        <div class="week-nav">
            <a href="{{ route('worker.schedule', ['week' => $prevWeek]) }}" class="week-nav-btn">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <span class="week-nav-label">
                {{ $weekStart->format('j') }} - {{ $weekEnd->format('j') }}
                {{ $weekEnd->translatedFormat('F') }}
                {{ $weekEnd->format('Y') }}
            </span>
            <a href="{{ route('worker.schedule', ['week' => $nextWeek]) }}" class="week-nav-btn">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>

        <div class="calendar-grid">
            @foreach($days as $day)
                <div @class([
                        'cal-day',
                        'today' => $day['is_today'],
                        'clickable' => $day['is_clickable'],
                        'locked' => $day['is_past'] && !$day['is_today'] && !$day['can_input_hours'],
                        'out-of-schedule' => $scheduleStatus['is_active'] && !$day['is_past'] && !$day['in_schedule'],
                    ])
                     data-date="{{ $day['date'] }}">
                    <div class="cal-day-header">
                        <span class="cal-day-weekday">{{ $day['weekday'] }}</span>
                        <span class="cal-day-date">{{ $day['short_date'] }}</span>
                        <div class="shift-badges">
                            @if($day['morning'])
                                <span class="shift-badge morning-badge">R</span>
                            @endif
                            @if($day['afternoon'])
                                <span class="shift-badge afternoon-badge">P</span>
                            @endif
                        </div>
                    </div>

                    @if(count($day['assigned_workers']) > 0)
                        @php
                            $morningWorkers = array_filter($day['assigned_workers'], fn($w) => $w['shift_type'] === 'morning');
                            $afternoonWorkers = array_filter($day['assigned_workers'], fn($w) => $w['shift_type'] === 'afternoon');
                        @endphp

                        @if(count($morningWorkers) > 0)
                            <div class="cal-shift-section">
                                <div class="cal-shift-label morning-label">
                                    <i class="fa-solid fa-sun"></i> Zmiana ranna
                                    @if($day['morning_start_label'])
                                        <span class="shift-start-label"><span class="shift-start-label-text">Godzina rozpoczęcia zmiany:</span> <span class="shift-start-label-time">{{ $day['morning_start_label'] }}</span></span>
                                    @endif
                                </div>
                                @foreach($morningWorkers as $w)
                                    <div @class(['cal-worker', 'mine' => $w['is_me']])>
                                        <span @class(['worker-dot', 'mine-dot' => $w['is_me']])></span>
                                        {{ $w['name'] }}
                                        @if($w['is_me'])
                                            <span class="you-tag">Ty</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if(count($afternoonWorkers) > 0)
                            <div class="cal-shift-section">
                                <div class="cal-shift-label afternoon-label">
                                    <i class="fa-solid fa-cloud-sun"></i> Zmiana popołudniowa
                                    @if($day['afternoon_start_label'])
                                        <span class="shift-start-label"><span class="shift-start-label-text">Godzina rozpoczęcia zmiany:</span> <span class="shift-start-label-time">{{ $day['afternoon_start_label'] }}</span></span>
                                    @endif
                                </div>
                                @foreach($afternoonWorkers as $w)
                                    <div @class(['cal-worker', 'mine' => $w['is_me']])>
                                        <span @class(['worker-dot', 'mine-dot' => $w['is_me']])></span>
                                        {{ $w['name'] }}
                                        @if($w['is_me'])
                                            <span class="you-tag">Ty</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="cal-day-empty">
                            <i class="fa-regular fa-calendar"></i>
                            <span>Brak zmian</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="schedule-legend">
            <div class="legend-item">
                <span class="legend-today-box"></span> Dzień dzisiejszy
            </div>
            <div class="legend-item">
                <span class="shift-badge morning-badge">R</span> Zapisany na zmianę ranną
            </div>
            <div class="legend-item">
                <span class="shift-badge afternoon-badge">P</span> Zapisany na zmianę popołudniową
            </div>
            <div class="legend-item">
                <span class="worker-dot mine-dot"></span> Twoja zmiana
            </div>
        </div>
    </div>

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
        ];
    @endphp
    <script>
        window.scheduleDays = @json($scheduleDaysJs);
        window.scheduleConfig = @json($scheduleConfigJs);
    </script>

    @if($canOpenModal)
        <div class="shift-modal-overlay" id="shiftModalOverlay">
            <div class="shift-modal">
                <div class="shift-modal-header">
                    <h3 id="shiftModalTitle">Zapisz się na zmianę</h3>
                    <button class="shift-modal-close" id="shiftModalClose">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="shift-modal-body">
                    <p class="shift-modal-date" id="shiftModalDate"></p>
                    <div class="shift-options">
                        <label class="shift-option">
                            <input type="checkbox" name="morning_shift" id="shiftMorning">
                            <div class="shift-option-content morning-opt">
                                <i class="fa-solid fa-sun"></i>
                                <div>
                                    <strong>Zmiana ranna</strong>
                                    <span>Rano</span>
                                </div>
                            </div>
                        </label>
                        <label class="shift-option">
                            <input type="checkbox" name="afternoon_shift" id="shiftAfternoon">
                            <div class="shift-option-content afternoon-opt">
                                <i class="fa-solid fa-cloud-sun"></i>
                                <div>
                                    <strong>Zmiana popołudniowa</strong>
                                    <span>Popołudnie</span>
                                </div>
                            </div>
                        </label>
                    </div>

                    <div class="shift-hours-section" id="shiftHoursSection" style="display: none;">
                        <div class="shift-hours-divider"></div>
                        <h4 class="shift-hours-title">Godziny pracy</h4>

                        <div class="shift-hours-group" id="morningHoursGroup" style="display: none;">
                            <div class="shift-hours-label morning-label">
                                <i class="fa-solid fa-sun"></i> Zmiana ranna
                            </div>
                            <div class="shift-hours-absent-info" id="morningAbsentInfo" style="display: none;">
                                <i class="fa-solid fa-user-slash"></i>
                                <span>Nieobecność</span>
                            </div>
                            <div class="shift-hours-admin-info" id="morningAdminInfo" style="display: none;">
                                <i class="fa-solid fa-check-circle"></i>
                                <span>Zatwierdzone: <strong id="morningAdminHours"></strong></span>
                            </div>
                            <div class="shift-hours-saved-info" id="morningSavedInfo" style="display: none;">
                                <div class="shift-hours-saved-text">
                                    <i class="fa-solid fa-clock"></i>
                                    Twoje godziny zostały zapisane.
                                </div>
                                <div class="shift-hours-saved-times" id="morningSavedTimes"></div>
                                <div class="shift-hours-saved-status">
                                    Oczekuje na akceptację administratora
                                </div>
                                <button type="button" class="shift-hours-edit-btn" id="morningEditBtn">
                                    Edytuj
                                </button>
                            </div>
                            <div class="shift-hours-inputs" id="morningHoursInputs">
                                <div class="shift-hours-field">
                                    <label>Od</label>
                                    <div class="shift-hours-time-pair">
                                        <input type="number" id="morningFromHour" placeholder="00" min="0" max="23">
                                        <span class="time-colon">:</span>
                                        <input type="number" id="morningFromMinute" placeholder="00" min="0" max="59">
                                    </div>
                                </div>
                                <span class="shift-hours-separator">—</span>
                                <div class="shift-hours-field">
                                    <label>Do</label>
                                    <div class="shift-hours-time-pair">
                                        <input type="number" id="morningToHour" placeholder="00" min="0" max="23">
                                        <span class="time-colon">:</span>
                                        <input type="number" id="morningToMinute" placeholder="00" min="0" max="59">
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="shift-hours-cancel-btn" id="morningCancelBtn" style="display: none;">Anuluj</button>
                            <div class="shift-hours-time-note" id="morningTimeNote" style="display: none;"></div>
                        </div>

                        <div class="shift-hours-group" id="afternoonHoursGroup" style="display: none;">
                            <div class="shift-hours-label afternoon-label">
                                <i class="fa-solid fa-cloud-sun"></i> Zmiana popołudniowa
                            </div>
                            <div class="shift-hours-absent-info" id="afternoonAbsentInfo" style="display: none;">
                                <i class="fa-solid fa-user-slash"></i>
                                <span>Nieobecność</span>
                            </div>
                            <div class="shift-hours-admin-info" id="afternoonAdminInfo" style="display: none;">
                                <i class="fa-solid fa-check-circle"></i>
                                <span>Zatwierdzone: <strong id="afternoonAdminHours"></strong></span>
                            </div>
                            <div class="shift-hours-saved-info" id="afternoonSavedInfo" style="display: none;">
                                <div class="shift-hours-saved-text">
                                    <i class="fa-solid fa-clock"></i>
                                    Twoje godziny zostały zapisane.
                                </div>
                                <div class="shift-hours-saved-times" id="afternoonSavedTimes"></div>
                                <div class="shift-hours-saved-status">
                                    Oczekuje na akceptację administratora
                                </div>
                                <button type="button" class="shift-hours-edit-btn" id="afternoonEditBtn">
                                    Edytuj
                                </button>
                            </div>
                            <div class="shift-hours-inputs" id="afternoonHoursInputs">
                                <div class="shift-hours-field">
                                    <label>Od</label>
                                    <div class="shift-hours-time-pair">
                                        <input type="number" id="afternoonFromHour" placeholder="00" min="0" max="23">
                                        <span class="time-colon">:</span>
                                        <input type="number" id="afternoonFromMinute" placeholder="00" min="0" max="59">
                                    </div>
                                </div>
                                <span class="shift-hours-separator">—</span>
                                <div class="shift-hours-field">
                                    <label>Do</label>
                                    <div class="shift-hours-time-pair">
                                        <input type="number" id="afternoonToHour" placeholder="00" min="0" max="23">
                                        <span class="time-colon">:</span>
                                        <input type="number" id="afternoonToMinute" placeholder="00" min="0" max="59">
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="shift-hours-cancel-btn" id="afternoonCancelBtn" style="display: none;">Anuluj</button>
                            <div class="shift-hours-time-note" id="afternoonTimeNote" style="display: none;"></div>
                        </div>
                    </div>
                </div>
                <div class="shift-modal-footer">
                    <button class="btn-cancel" id="shiftModalCancel">Anuluj</button>
                    <button class="btn-save" id="shiftModalSave">Zapisz</button>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @vite(['resources/js/worker-schedule.js'])
@endpush
