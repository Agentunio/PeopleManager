@extends('worker.layouts.app')

@section('title', 'Grafik - Strefa Pracownika')

@push('styles')
    @vite(['resources/css/worker-schedule.css'])
@endpush

@section('content')
    <div class="worker-content schedule-page">
        <div class="worker-header" style="margin-bottom: 24px;">
            <h1><i class="fa-solid fa-calendar-days"></i> Grafik</h1>
            <p class="greeting-sub">Twój tygodniowy harmonogram</p>
        </div>

        <div class="week-nav">
            <button class="week-nav-btn" id="prevWeek">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <span class="week-nav-label" id="weekLabel">3 - 9 marca 2025</span>
            <button class="week-nav-btn" id="nextWeek">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>

        <div class="calendar-grid">
            {{-- Poniedziałek --}}
            <div class="cal-day" data-date="2025-03-03">
                <div class="cal-day-header">
                    <span class="cal-day-weekday">Poniedziałek</span>
                    <span class="cal-day-date">3 mar</span>
                </div>

                <div class="cal-shift-section">
                    <div class="cal-shift-label morning-label">
                        <i class="fa-solid fa-sun"></i> Zmiana ranna
                    </div>
                    <div class="cal-worker mine">
                        <span class="worker-dot mine-dot"></span>
                        Jan Kowalski <span class="you-tag">(Ty)</span>
                    </div>
                    <div class="cal-worker">
                        <span class="worker-dot"></span>
                        Anna Kamińska
                    </div>
                </div>

                <div class="cal-shift-section">
                    <div class="cal-shift-label afternoon-label">
                        <i class="fa-solid fa-cloud-sun"></i> Zmiana popołudniowa
                    </div>
                    <div class="cal-worker">
                        <span class="worker-dot"></span>
                        Piotr Mazur
                    </div>
                </div>
            </div>

            {{-- Wtorek --}}
            <div class="cal-day" data-date="2025-03-04">
                <div class="cal-day-header">
                    <span class="cal-day-weekday">Wtorek</span>
                    <span class="cal-day-date">4 mar</span>
                </div>
                <div class="cal-day-empty">
                    <i class="fa-regular fa-calendar"></i>
                    <span>Brak zmian</span>
                </div>
            </div>

            {{-- Środa - dzisiaj --}}
            <div class="cal-day today" data-date="2025-03-05">
                <div class="cal-day-header">
                    <span class="cal-day-weekday">Środa</span>
                    <span class="cal-day-date">5 mar</span>
                    <span class="today-badge">Dziś</span>
                </div>

                <div class="cal-shift-section">
                    <div class="cal-shift-label afternoon-label">
                        <i class="fa-solid fa-cloud-sun"></i> Zmiana popołudniowa
                    </div>
                    <div class="cal-worker mine">
                        <span class="worker-dot mine-dot"></span>
                        Jan Kowalski <span class="you-tag">(Ty)</span>
                    </div>
                    <div class="cal-worker">
                        <span class="worker-dot"></span>
                        Marek Wiśniewski
                    </div>
                </div>
            </div>

            {{-- Czwartek --}}
            <div class="cal-day" data-date="2025-03-06">
                <div class="cal-day-header">
                    <span class="cal-day-weekday">Czwartek</span>
                    <span class="cal-day-date">6 mar</span>
                </div>

                <div class="cal-shift-section">
                    <div class="cal-shift-label morning-label">
                        <i class="fa-solid fa-sun"></i> Zmiana ranna
                    </div>
                    <div class="cal-worker">
                        <span class="worker-dot"></span>
                        Piotr Mazur
                    </div>
                    <div class="cal-worker">
                        <span class="worker-dot"></span>
                        Anna Kamińska
                    </div>
                </div>
            </div>

            {{-- Piątek --}}
            <div class="cal-day" data-date="2025-03-07">
                <div class="cal-day-header">
                    <span class="cal-day-weekday">Piątek</span>
                    <span class="cal-day-date">7 mar</span>
                </div>
                <div class="cal-day-empty">
                    <i class="fa-regular fa-calendar"></i>
                    <span>Brak zmian</span>
                </div>
            </div>

            {{-- Sobota --}}
            <div class="cal-day" data-date="2025-03-08">
                <div class="cal-day-header">
                    <span class="cal-day-weekday">Sobota</span>
                    <span class="cal-day-date">8 mar</span>
                </div>

                <div class="cal-shift-section">
                    <div class="cal-shift-label morning-label">
                        <i class="fa-solid fa-sun"></i> Zmiana ranna
                    </div>
                    <div class="cal-worker mine">
                        <span class="worker-dot mine-dot"></span>
                        Jan Kowalski <span class="you-tag">(Ty)</span>
                    </div>
                </div>

                <div class="cal-shift-section">
                    <div class="cal-shift-label afternoon-label">
                        <i class="fa-solid fa-cloud-sun"></i> Zmiana popołudniowa
                    </div>
                    <div class="cal-worker">
                        <span class="worker-dot"></span>
                        Marek Wiśniewski
                    </div>
                    <div class="cal-worker">
                        <span class="worker-dot"></span>
                        Piotr Mazur
                    </div>
                </div>
            </div>

            {{-- Niedziela --}}
            <div class="cal-day" data-date="2025-03-09">
                <div class="cal-day-header">
                    <span class="cal-day-weekday">Niedziela</span>
                    <span class="cal-day-date">9 mar</span>
                </div>
                <div class="cal-day-empty">
                    <i class="fa-regular fa-calendar"></i>
                    <span>Brak zmian</span>
                </div>
            </div>
        </div>

        <div class="schedule-legend">
            <div class="legend-item">
                <span class="worker-dot mine-dot"></span> Twoja zmiana
            </div>
            <div class="legend-item">
                <span class="today-badge legend-today">Dziś</span> Dzisiejszy dzień
            </div>
        </div>
    </div>

    {{-- Popup zapisu na zmianę --}}
    <div class="shift-modal-overlay" id="shiftModalOverlay">
        <div class="shift-modal">
            <div class="shift-modal-header">
                <h3 id="shiftModalTitle">Zapisz się na zmianę</h3>
                <button class="shift-modal-close" id="shiftModalClose">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="shift-modal-body">
                <p class="shift-modal-date" id="shiftModalDate">5 marca 2025</p>
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
            </div>
            <div class="shift-modal-footer">
                <button class="btn-cancel" id="shiftModalCancel">Anuluj</button>
                <button class="btn-save" id="shiftModalSave">Zapisz</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/worker-schedule.js'])
@endpush
