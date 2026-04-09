@extends('layouts.app')

@section('title', 'Ustawienia grafiku - Panel administratora')

@push('styles')
    @vite(['resources/css/settings.css', 'resources/css/planner-settings.css'])
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
            <p>Ustaw termin zamknięcia zapisów oraz zakres dni grafiku</p>
        </div>

        <form id="availability-form" action="{{ route('planner.schedule.store') }}" method="POST" novalidate>
            @csrf

            <div class="planner-settings-container">
                <div class="current-status">
                    <div class="current-status-header">
                        <span class="current-status-title">Aktualny status</span>
                        @if($schedule && $schedule->isActive())
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
                        @if($schedule && $schedule->isActive())
                            @if($schedule->type === 'signup')
                                <p>Grafik jest włączony. Zapisy do <strong>{{ $schedule->signup_deadline->format('d.m.Y H:i') }}</strong>, zakres dni: <strong>{{ $schedule->start_date->format('d.m.Y') }} – {{ $schedule->end_date->format('d.m.Y') }}</strong></p>
                            @else
                                <p>Grafik będzie aktywny do jego ręcznego wyłączenia</p>
                            @endif
                        @else
                            <p>Grafik jest obecnie wyłączony. Wybierz opcję poniżej, aby go aktywować.</p>
                        @endif
                    </div>
                </div>

                <label class="availability-option">
                    <input type="radio" name="type" value="signup" {{ old('type', $schedule?->type) === 'signup' ? 'checked' : '' }}>
                    <div class="availability-option-header">
                        <div class="availability-radio">
                            <div class="availability-radio-inner"></div>
                        </div>
                        <div class="availability-option-icon">
                            <i class="fas fa-calendar-days"></i>
                        </div>
                        <div class="availability-option-content">
                            <div class="availability-option-title">Ustaw zapisy i zakres dni</div>
                            <div class="availability-option-desc">Pracownicy zapisują się do wybranego terminu na wybrany zakres dni</div>
                        </div>
                    </div>
                    <div class="availability-option-details">
                        <div class="form-group">
                            <label for="signup-deadline">Zapisy aktywne do (data i godzina)</label>
                            <input type="text" id="signup-deadline" name="signup_deadline" placeholder="Wybierz datę i godzinę" value="{{ old('signup_deadline', $schedule?->signup_deadline?->format('Y-m-d H:i')) }}">
                        </div>
                        <div class="date-range-row">
                            <div class="form-group">
                                <label for="range-start">Początek zakresu dni</label>
                                <input type="text" id="range-start" name="start_date" placeholder="Wybierz datę" value="{{ old('start_date', $schedule?->start_date?->format('Y-m-d')) }}">
                            </div>
                            <div class="date-range-separator">do</div>
                            <div class="form-group">
                                <label for="range-end">Koniec zakresu dni</label>
                                <input type="text" id="range-end" name="end_date" placeholder="Wybierz datę" value="{{ old('end_date', $schedule?->end_date?->format('Y-m-d')) }}">
                            </div>
                        </div>
                        <div class="quick-actions">
                            <button type="button" class="quick-action-btn" id="quick-next-week">
                                <i class="fas fa-forward"></i> Następny tydzień
                            </button>
                            <button type="button" class="quick-action-btn" id="quick-in-two-weeks">
                                <i class="fas fa-forward-fast"></i> Za 2 tygodnie
                            </button>
                        </div>
                        @error('signup_deadline')<div class="form-error">{{ $message }}</div>@enderror
                        @error('start_date')<div class="form-error">{{ $message }}</div>@enderror
                        @error('end_date')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </label>

                <label class="availability-option">
                    <input type="radio" name="type" value="always" {{ old('type', $schedule?->type) === 'always' ? 'checked' : '' }}>
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
                    <input type="radio" name="type" value="disabled" {{ old('type', $schedule?->type ?? 'disabled') === 'disabled' ? 'checked' : '' }}>
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
                            Grafik pozostanie nieaktywny dopóki go nie włączysz ponownie
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
    @vite(['resources/js/planner-settings.js'])
@endpush
