@extends('layouts.app')

@section('title', 'Ustawienia grafiku - Panel administratora')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}">
    <link rel="stylesheet" href="{{ asset('css/planner-settings.css') }}">
@endpush

@section('content')
<div class="admin-panel">
    @include('partials.menu')

    <main class="main-content">
        <a href="{{ route('planner.index') }}" class="settings-back-link">
            <i class="fas fa-arrow-left"></i> Powrót do grafiku
        </a>

        <div class="header">
            <h1><i class="fas fa-calendar-check"></i> Włącz grafik</h1>
            <p>Ustaw okres dostępności grafiku dla pracowników</p>
        </div>

        <form id="availability-form" action="{{ route('planner.schedule.store') }}" method="POST">
            @csrf

            <div class="planner-settings-container">
                <div class="current-status">
                    <div class="current-status-header">
                        <span class="current-status-title">Aktualny status</span>
                        @if($schedule->isActive())
                            <span class="status-badge active">
                                <i class="fas fa-circle"></i> Aktywny
                            </span>
                        @else
                            <span class="status-badge inactive">
                                <i class="fas fa-circle"></i> Nieaktywny
                            </span>
                        @endif
                    </div>
                    <div class="current-status-info">
                        @if($schedule->isActive())
                            @if($schedule->type != 'always')
                            <p>Grafik jest obecnie włączony. Jest aktywny od {{ date('d.m.Y H:m', strtotime($schedule->start_date)) }} do {{ date('d.m.Y H:m', strtotime($schedule->end_date)) }}</p>
                            @else
                                <p>Grafik będzie aktywny do jego manulanego wyłączenia</p>
                            @endif
                        @else
                            <p>Grafik jest obecnie wyłączony. Wybierz opcję poniżej, aby go aktywować.</p>
                        @endif
                    </div>
                </div>

                <label class="availability-option">
                    <input type="radio" name="type" value="range">
                    <div class="availability-option-header">
                        <div class="availability-radio">
                            <div class="availability-radio-inner"></div>
                        </div>
                        <div class="availability-option-icon">
                            <i class="fas fa-calendar-days"></i>
                        </div>
                        <div class="availability-option-content">
                            <div class="availability-option-title">Ustaw zakres dat</div>
                            <div class="availability-option-desc">Grafik będzie aktywny od wybranego dnia i godziny do wybranego dnia i godziny</div>
                        </div>
                    </div>
                    <div class="availability-option-details">
                        <div class="date-range-row">
                            <div class="form-group">
                                <label for="date-from">Data i godzina rozpoczęcia</label>
                                <input type="text" id="date-from" name="start_date" placeholder="Wybierz datę i godzinę">
                            </div>
                            <div class="date-range-separator">do</div>
                            <div class="form-group">
                                <label for="date-to">Data i godzina zakończenia</label>
                                <input type="text" id="date-to" name="end_date" placeholder="Wybierz datę i godzinę">
                            </div>
                        </div>
                    </div>
                </label>

                <label class="availability-option">
                    <input type="radio" name="type" value="week">
                    <div class="availability-option-header">
                        <div class="availability-radio">
                            <div class="availability-radio-inner"></div>
                        </div>
                        <div class="availability-option-icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="availability-option-content">
                            <div class="availability-option-title">Ustaw na tydzień</div>
                            <div class="availability-option-desc">Grafik będzie aktywny przez wybraną liczbę dni od teraz</div>
                        </div>
                    </div>
                    <div class="availability-option-details">
                        <div class="current-status-info" style="color: #aaa;">
                            <i class="fas fa-info-circle" style="color: #e50914; margin-right: 8px;"></i>
                            Grafik będzie aktywny od <strong id="week-start">--</strong> do <strong id="week-end">--</strong>
                        </div>
                        <div class="quick-actions">
                            <label class="quick-action-btn">
                                <input type="radio" name="days" value="7" checked> 7 dni
                            </label>
                            <label class="quick-action-btn">
                                <input type="radio" name="days" value="14"> 14 dni
                            </label>
                            <label class="quick-action-btn">
                                <input type="radio" name="days" value="30"> 30 dni
                            </label>
                        </div>
                    </div>
                </label>

                <label class="availability-option">
                    <input type="radio" name="type" value="always">
                    <div class="availability-option-header">
                        <div class="availability-radio">
                            <div class="availability-radio-inner"></div>
                        </div>
                        <div class="availability-option-icon">
                            <i class="fas fa-infinity"></i>
                        </div>
                        <div class="availability-option-content">
                            <div class="availability-option-title">Grafik dostępny cały czas</div>
                            <div class="availability-option-desc">Grafik będzie aktywny bez ograniczeń czasowych</div>
                        </div>
                    </div>
                    <div class="availability-option-details">
                        <div class="current-status-info" style="color: #aaa;">
                            <i class="fas fa-exclamation-triangle" style="color: #f59e0b; margin-right: 8px;"></i>
                            Grafik pozostanie aktywny dopóki go ręcznie nie wyłączysz
                        </div>
                    </div>
                </label>

                <label class="availability-option">
                    <input type="radio" name="type" value="disabled">
                    <div class="availability-option-header">
                        <div class="availability-radio">
                            <div class="availability-radio-inner"></div>
                        </div>
                        <div class="availability-option-icon">
                            <i class="fas fa-power-off"></i>
                        </div>
                        <div class="availability-option-content">
                            <div class="availability-option-title">Wyłącz grafik</div>
                            <div class="availability-option-desc">Grafik przestanie być aktywny</div>
                        </div>
                    </div>
                    <div class="availability-option-details">
                        <div class="current-status-info" style="color: #aaa;">
                            <i class="fas fa-exclamation-triangle" style="color: #f59e0b; margin-right: 8px;"></i>
                            Grafik pozostanie nieaktywny dopóki go nie wyłączysz ponownie
                        </div>
                    </div>
                </label>

                <div class="settings-actions">
                    <button type="submit" id="save-settings" class="btn btn-submit">
                        <i class="fas fa-check"></i> Zapisz i włącz grafik
                    </button>
                </div>
            </div>
        </form>
    </main>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/planner-settings.js') }}"></script>
@endpush
