@extends('layouts.app')

@section('title', 'Rozliczenie dnia - Panel administratora')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}">
    <link rel="stylesheet" href="{{ asset('css/planner.css') }}">
    <link rel="stylesheet" href="{{ asset('css/settlement.css') }}">
@endpush

@section('content')
    <div class="admin-panel">
        @include('partials.menu')

        <main class="main-content">
            <a href="{{ route('planner.day.index', $date ?? '---') }}" class="settings-back-link">
                <i class="fas fa-arrow-left"></i> Powrót do grafiku dnia
            </a>

            <div class="header">
                <h1><i class="fas fa-calculator"></i> Rozliczenie dnia</h1>
                <p>Rozlicz pracowników za dzień: <strong>{{ date('d.m.Y', strtotime($date)) ?? '---' }}</strong></p>
            </div>

            <div class="settlement-defaults-section">
                <div class="defaults-header">
                    <div class="defaults-icon">
                        <i class="fas fa-sliders-h"></i>
                    </div>
                    <h2>Ustawienia domyślne</h2>
                    <p class="defaults-hint">Ustaw wartości i kliknij "Zastosuj" aby wypełnić pola wszystkich pracowników danej zmiany</p>
                </div>

                <div class="defaults-content">
                    <div class="defaults-shift-group">
                        <div class="defaults-shift-label shift-morning-label">
                            <i class="fas fa-sun"></i>
                            <span>Zmiana ranna</span>
                        </div>
                        <div class="defaults-fields">
                            <div class="field-group">
                                <span>Domyślna stawka</span>
                                <select id="default-morning-rate">
                                    <option value="">Wybierz stawkę</option>
                                    @include('admin.planner.partials.allpackage')
                                </select>
                            </div>
                            <div class="field-group field-time">
                                <span>Domyślny czas pracy</span>
                                <div class="time-range-inputs">
                                    <div class="time-from">
                                        <span class="time-label">Od</span>
                                        <input type="number" id="default-morning-from-hour" placeholder="00" min="0" max="23">
                                        <span class="time-colon">:</span>
                                        <input type="number" id="default-morning-from-minute" placeholder="00" min="0" max="59" step="5">
                                    </div>
                                    <span class="time-range-separator">—</span>
                                    <div class="time-to">
                                        <span class="time-label">Do</span>
                                        <input type="number" id="default-morning-to-hour" placeholder="00" min="0" max="23">
                                        <span class="time-colon">:</span>
                                        <input type="number" id="default-morning-to-minute" placeholder="00" min="0" max="59" step="5">
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-apply-defaults" data-shift="morning">
                                <i class="fas fa-check"></i> Zastosuj
                            </button>
                        </div>
                    </div>

                    <div class="defaults-shift-group">
                        <div class="defaults-shift-label shift-afternoon-label">
                            <i class="fas fa-cloud-sun"></i>
                            <span>Zmiana popołudniowa</span>
                        </div>
                        <div class="defaults-fields">
                            <div class="field-group">
                                <span>Domyślna stawka</span>
                                <select id="default-afternoon-rate">
                                    <option value="">Wybierz stawkę</option>
                                    @include('admin.planner.partials.allpackage')
                                </select>
                            </div>
                            <div class="field-group field-time">
                                <span>Domyślny czas pracy</span>
                                <div class="time-range-inputs">
                                    <div class="time-from">
                                        <span class="time-label">Od</span>
                                        <input type="number" id="default-afternoon-from-hour" placeholder="00" min="0" max="23">
                                        <span class="time-colon">:</span>
                                        <input type="number" id="default-afternoon-from-minute" placeholder="00" min="0" max="59" step="5">
                                    </div>
                                    <span class="time-range-separator">—</span>
                                    <div class="time-to">
                                        <span class="time-label">Do</span>
                                        <input type="number" id="default-afternoon-to-hour" placeholder="00" min="0" max="23">
                                        <span class="time-colon">:</span>
                                        <input type="number" id="default-afternoon-to-minute" placeholder="00" min="0" max="59" step="5">
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-apply-defaults" data-shift="afternoon">
                                <i class="fas fa-check"></i> Zastosuj
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <form id="settlement-form" action="#" method="POST">
                @csrf

                <div class="settlement-container">
                    <div class="settlement-shift">
                        <div class="settlement-shift-header shift-morning">
                            <div class="shift-icon">
                                <i class="fas fa-sun"></i>
                            </div>
                            <h2>Zmiana ranna</h2>
                            <span class="workers-count" id="morning-workers-count">Liczba pracowników na zmianie wynosiła: {{ $workers_morning->count() }}</span>
                        </div>

                        <div class="settlement-workers" data-shift="morning">
                            <div class="settlement-worker-card">
                                <div class="worker-info">
                                    <span class="worker-name">Jan Kowalski</span>
                                </div>
                                <div class="worker-settlement-fields">
                                    <div class="field-group">
                                        <span>Stawka</span>
                                        <select name="morning[1][rate_id]" class="worker-rate">
                                            <option value="">Wybierz stawkę</option>
                                            <option value="1">Podstawowa - 25 zł/h</option>
                                            <option value="2">Weekendowa - 30 zł/h</option>
                                            <option value="3">Świąteczna - 40 zł/h</option>
                                        </select>
                                    </div>
                                    <div class="field-group field-time">
                                        <span>Czas pracy</span>
                                        <div class="time-range-inputs">
                                            <div class="time-from">
                                                <span class="time-label">Od</span>
                                                <input type="number" name="morning[1][from_hour]" class="worker-from-hour" placeholder="00" min="0" max="23">
                                                <span class="time-colon">:</span>
                                                <input type="number" name="morning[1][from_minute]" class="worker-from-minute" placeholder="00" min="0" max="59" step="5">
                                            </div>
                                            <span class="time-range-separator">—</span>
                                            <div class="time-to">
                                                <span class="time-label">Do</span>
                                                <input type="number" name="morning[1][to_hour]" class="worker-to-hour" placeholder="00" min="0" max="23">
                                                <span class="time-colon">:</span>
                                                <input type="number" name="morning[1][to_minute]" class="worker-to-minute" placeholder="00" min="0" max="59" step="5">
                                            </div>
                                            <div class="time-calculated">
                                                <span class="calculated-hours">0h 0min</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="settlement-shift">
                        <div class="settlement-shift-header shift-afternoon">
                            <div class="shift-icon">
                                <i class="fas fa-cloud-sun"></i>
                            </div>
                            <h2>Zmiana popołudniowa</h2>
                            <span class="workers-count" id="afternoon-workers-count">Liczba pracowników na zmianie wynosiła: {{ $workers_afternoon->count() }}</span>
                        </div>

                        <div class="settlement-workers" data-shift="afternoon">
                            <div class="settlement-worker-card" data-worker-id="5">
                                <div class="worker-info">
                                    <span class="worker-name">Tomasz Dąbrowski</span>
                                </div>
                                <div class="worker-settlement-fields">
                                    <div class="field-group">
                                        <span>Stawka</span>
                                        <select name="afternoon[5][rate_id]" class="worker-rate">
                                            <option value="">Wybierz stawkę</option>
                                            <option value="1">Podstawowa - 25 zł/h</option>
                                            <option value="2">Weekendowa - 30 zł/h</option>
                                            <option value="3">Świąteczna - 40 zł/h</option>
                                        </select>
                                    </div>
                                    <div class="field-group field-time">
                                        <span>Czas pracy</span>
                                        <div class="time-range-inputs">
                                            <div class="time-from">
                                                <span class="time-label">Od</span>
                                                <input type="number" name="afternoon[5][from_hour]" class="worker-from-hour" placeholder="00" min="0" max="23">
                                                <span class="time-colon">:</span>
                                                <input type="number" name="afternoon[5][from_minute]" class="worker-from-minute" placeholder="00" min="0" max="59" step="5">
                                            </div>
                                            <span class="time-range-separator">—</span>
                                            <div class="time-to">
                                                <span class="time-label">Do</span>
                                                <input type="number" name="afternoon[5][to_hour]" class="worker-to-hour" placeholder="00" min="0" max="23">
                                                <span class="time-colon">:</span>
                                                <input type="number" name="afternoon[5][to_minute]" class="worker-to-minute" placeholder="00" min="0" max="59" step="5">
                                            </div>
                                            <div class="time-calculated">
                                                <span class="calculated-hours">0h 0min</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="settlement-worker-card" data-worker-id="6">
                                <div class="worker-info">
                                    <span class="worker-name">Katarzyna Lewandowska</span>
                                </div>
                                <div class="worker-settlement-fields">
                                    <div class="field-group">
                                        <span>Stawka</span>
                                        <select name="afternoon[6][rate_id]" class="worker-rate">
                                            <option value="">Wybierz stawkę</option>
                                            <option value="1">Podstawowa - 25 zł/h</option>
                                            <option value="2">Weekendowa - 30 zł/h</option>
                                            <option value="3">Świąteczna - 40 zł/h</option>
                                        </select>
                                    </div>
                                    <div class="field-group field-time">
                                        <span>Czas pracy</span>
                                        <div class="time-range-inputs">
                                            <div class="time-from">
                                                <span class="time-label">Od</span>
                                                <input type="number" name="afternoon[6][from_hour]" class="worker-from-hour" placeholder="00" min="0" max="23">
                                                <span class="time-colon">:</span>
                                                <input type="number" name="afternoon[6][from_minute]" class="worker-from-minute" placeholder="00" min="0" max="59" step="5">
                                            </div>
                                            <span class="time-range-separator">—</span>
                                            <div class="time-to">
                                                <span class="time-label">Do</span>
                                                <input type="number" name="afternoon[6][to_hour]" class="worker-to-hour" placeholder="00" min="0" max="23">
                                                <span class="time-colon">:</span>
                                                <input type="number" name="afternoon[6][to_minute]" class="worker-to-minute" placeholder="00" min="0" max="59" step="5">
                                            </div>
                                            <div class="time-calculated">
                                                <span class="calculated-hours">0h 0min</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settlement-global-section">
                        <div class="global-section-header">
                            <div class="global-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <h2>Paczki i stawki za paczki</h2>
                        </div>
                        <div class="global-section-content">
                            <div class="global-shifts-grid">
                                <!-- Zmiana ranna -->
                                <div class="global-shift-group">
                                    <div class="global-shift-label shift-morning-label">
                                        <i class="fas fa-sun"></i>
                                        <span>Zmiana ranna</span>
                                    </div>
                                    <div class="global-shift-fields">
                                        <div class="field-group">
                                            <label>Liczba paczek</label>
                                            <input type="number" name="morning_packages" id="morning-packages" placeholder="0" min="0">
                                        </div>
                                        <div class="field-group">
                                            <label>Stawka za paczkę</label>
                                            <select name="morning_package_rate" id="morning-package-rate">
                                                <option value="">Wybierz stawkę</option>
                                                <option value="1">Podstawowa - 0.50 zł</option>
                                                <option value="2">Weekendowa - 0.60 zł</option>
                                                <option value="3">Świąteczna - 0.80 zł</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Zmiana popołudniowa -->
                                <div class="global-shift-group">
                                    <div class="global-shift-label shift-afternoon-label">
                                        <i class="fas fa-cloud-sun"></i>
                                        <span>Zmiana popołudniowa</span>
                                    </div>
                                    <div class="global-shift-fields">
                                        <div class="field-group">
                                            <label>Liczba paczek</label>
                                            <input type="number" name="afternoon_packages" id="afternoon-packages" placeholder="0" min="0">
                                        </div>
                                        <div class="field-group">
                                            <label>Stawka za paczkę</label>
                                            <select name="afternoon_package_rate" id="afternoon-package-rate">
                                                <option value="">Wybierz stawkę</option>
                                                <option value="1">Podstawowa - 0.50 zł</option>
                                                <option value="2">Weekendowa - 0.60 zł</option>
                                                <option value="3">Świąteczna - 0.80 zł</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settlement-actions">
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-check"></i> Zapisz rozliczenie
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/settlement.js') }}"></script>
@endpush
