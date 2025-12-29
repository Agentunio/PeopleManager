@extends('layouts.app')

@section('title', 'Ustawienia grafiku - Panel administratora')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}">
    <link rel="stylesheet" href="{{ asset('css/planner.css') }}">
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

        <div class="planner-settings-container">
            <!-- AKTUALNY STATUS -->
            <div class="current-status">
                <div class="current-status-header">
                    <span class="current-status-title">Aktualny status</span>
                    <span class="status-badge inactive">
                        <i class="fas fa-circle"></i> Nieaktywny
                    </span>
                </div>
                <div class="current-status-info">
                    Grafik jest obecnie wyłączony. Wybierz opcję poniżej, aby go aktywować.
                </div>
            </div>

            <!-- OPCJA 1: ZAKRES DAT -->
            <div class="availability-option" data-option="range">
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
                            <input type="text" id="date-from" placeholder="Wybierz datę i godzinę">
                        </div>
                        <div class="date-range-separator">do</div>
                        <div class="form-group">
                            <label for="date-to">Data i godzina zakończenia</label>
                            <input type="text" id="date-to" placeholder="Wybierz datę i godzinę">
                        </div>
                    </div>
                </div>
            </div>

            <!-- OPCJA 2: TYDZIEŃ OD TERAZ -->
            <div class="availability-option" data-option="week">
                <div class="availability-option-header">
                    <div class="availability-radio">
                        <div class="availability-radio-inner"></div>
                    </div>
                    <div class="availability-option-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="availability-option-content">
                        <div class="availability-option-title">Ustaw na tydzień</div>
                        <div class="availability-option-desc">Grafik będzie aktywny przez 7 dni od teraz</div>
                    </div>
                </div>
                <div class="availability-option-details">
                    <div class="current-status-info" style="color: #aaa;">
                        <i class="fas fa-info-circle" style="color: #e50914; margin-right: 8px;"></i>
                        Grafik będzie aktywny od <strong id="week-start">--</strong> do <strong id="week-end">--</strong>
                    </div>
                    <div class="quick-actions">
                        <button type="button" class="quick-action-btn" data-days="7">7 dni</button>
                        <button type="button" class="quick-action-btn" data-days="14">14 dni</button>
                        <button type="button" class="quick-action-btn" data-days="30">30 dni</button>
                    </div>
                </div>
            </div>

            <!-- OPCJA 3: ZAWSZE AKTYWNY -->
            <div class="availability-option" data-option="always">
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
            </div>

            <!-- PRZYCISKI AKCJI -->
            <div class="settings-actions">
                <a href="{{ route('planner.index') }}" class="btn btn-cancel">
                    <i class="fas fa-times"></i> Anuluj
                </a>
                <button type="button" id="save-settings" class="btn btn-submit" disabled>
                    <i class="fas fa-check"></i> Zapisz i włącz grafik
                </button>
            </div>
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/planner-settings.js') }}"></script>
@endpush
