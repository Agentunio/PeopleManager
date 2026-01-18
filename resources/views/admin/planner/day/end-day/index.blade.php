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
            <p>Rozlicz pracowników za dzień: <strong>{{ date($date, strtotime($date)) ?? '---' }}</strong></p>
        </div>

        <!-- USTAWIENIA DOMYŚLNE - POZA FORMULARZEM -->
        <div class="settlement-defaults-section">
            <div class="defaults-header">
                <div class="defaults-icon">
                    <i class="fas fa-sliders-h"></i>
                </div>
                <h2>Ustawienia domyślne</h2>
                <p class="defaults-hint">Ustaw wartości i kliknij "Zastosuj" aby wypełnić pola wszystkich pracowników danej zmiany</p>
            </div>

            <div class="defaults-content">
                <!-- Domyślne dla zmiany rannej -->
                <div class="defaults-shift-group">
                    <div class="defaults-shift-label shift-morning-label">
                        <i class="fas fa-sun"></i>
                        <span>Zmiana ranna</span>
                    </div>
                    <div class="defaults-fields">
                        <div class="field-group">
                            <label>Domyślna stawka</label>
                            <select id="default-morning-rate">
                                <option value="">Wybierz stawkę</option>
                                <option value="1">Podstawowa - 25 zł/h</option>
                                <option value="2">Weekendowa - 30 zł/h</option>
                                <option value="3">Świąteczna - 40 zł/h</option>
                            </select>
                        </div>
                        <div class="field-group field-time">
                            <label>Domyślny czas pracy</label>
                            <div class="time-inputs">
                                <input type="number" id="default-morning-hours" placeholder="0" min="0" max="23">
                                <span class="time-separator">h</span>
                                <input type="number" id="default-morning-minutes" placeholder="0" min="0" max="59">
                                <span class="time-separator">min</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-apply-defaults" data-shift="morning">
                            <i class="fas fa-check"></i> Zastosuj
                        </button>
                    </div>
                </div>

                <!-- Domyślne dla zmiany popołudniowej -->
                <div class="defaults-shift-group">
                    <div class="defaults-shift-label shift-afternoon-label">
                        <i class="fas fa-cloud-sun"></i>
                        <span>Zmiana popołudniowa</span>
                    </div>
                    <div class="defaults-fields">
                        <div class="field-group">
                            <label>Domyślna stawka</label>
                            <select id="default-afternoon-rate">
                                <option value="">Wybierz stawkę</option>
                                <option value="1">Podstawowa - 25 zł/h</option>
                                <option value="2">Weekendowa - 30 zł/h</option>
                                <option value="3">Świąteczna - 40 zł/h</option>
                            </select>
                        </div>
                        <div class="field-group field-time">
                            <label>Domyślny czas pracy</label>
                            <div class="time-inputs">
                                <input type="number" id="default-afternoon-hours" placeholder="0" min="0" max="23">
                                <span class="time-separator">h</span>
                                <input type="number" id="default-afternoon-minutes" placeholder="0" min="0" max="59">
                                <span class="time-separator">min</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-apply-defaults" data-shift="afternoon">
                            <i class="fas fa-check"></i> Zastosuj
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORMULARZ -->
        <form id="settlement-form" action="#" method="POST">
            @csrf

            <div class="settlement-container">
                <!-- GLOBALNE PACZKI I STAWKI -->
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
                                            <option value="1">Podstawowa - 25 zł/h</option>
                                            <option value="2">Weekendowa - 30 zł/h</option>
                                            <option value="3">Świąteczna - 40 zł/h</option>
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
                                            <option value="1">Podstawowa - 25 zł/h</option>
                                            <option value="2">Weekendowa - 30 zł/h</option>
                                            <option value="3">Świąteczna - 40 zł/h</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ZMIANA RANNA -->
                <div class="settlement-shift">
                    <div class="settlement-shift-header shift-morning">
                        <div class="shift-icon">
                            <i class="fas fa-sun"></i>
                        </div>
                        <h2>Zmiana ranna</h2>
                        <span class="workers-count" id="morning-workers-count">3 pracowników</span>
                    </div>

                    <div class="settlement-workers" data-shift="morning">
                        <!-- Pracownik 1 -->
                        <div class="settlement-worker-card" data-worker-id="1">
                            <div class="worker-info">
                                <span class="worker-name">Jan Kowalski</span>
                            </div>
                            <div class="worker-settlement-fields">
                                <div class="field-group">
                                    <label>Stawka</label>
                                    <select name="morning[1][rate_id]" class="worker-rate">
                                        <option value="">Wybierz stawkę</option>
                                        <option value="1">Podstawowa - 25 zł/h</option>
                                        <option value="2">Weekendowa - 30 zł/h</option>
                                        <option value="3">Świąteczna - 40 zł/h</option>
                                    </select>
                                </div>
                                <div class="field-group field-time">
                                    <label>Czas pracy</label>
                                    <div class="time-inputs">
                                        <input type="number" name="morning[1][hours]" class="worker-hours" placeholder="0" min="0" max="23">
                                        <span class="time-separator">h</span>
                                        <input type="number" name="morning[1][minutes]" class="worker-minutes" placeholder="0" min="0" max="59">
                                        <span class="time-separator">min</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pracownik 2 -->
                        <div class="settlement-worker-card" data-worker-id="2">
                            <div class="worker-info">
                                <span class="worker-name">Anna Nowak</span>
                            </div>
                            <div class="worker-settlement-fields">
                                <div class="field-group">
                                    <label>Stawka</label>
                                    <select name="morning[2][rate_id]" class="worker-rate">
                                        <option value="">Wybierz stawkę</option>
                                        <option value="1">Podstawowa - 25 zł/h</option>
                                        <option value="2">Weekendowa - 30 zł/h</option>
                                        <option value="3">Świąteczna - 40 zł/h</option>
                                    </select>
                                </div>
                                <div class="field-group field-time">
                                    <label>Czas pracy</label>
                                    <div class="time-inputs">
                                        <input type="number" name="morning[2][hours]" class="worker-hours" placeholder="0" min="0" max="23">
                                        <span class="time-separator">h</span>
                                        <input type="number" name="morning[2][minutes]" class="worker-minutes" placeholder="0" min="0" max="59">
                                        <span class="time-separator">min</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pracownik 3 -->
                        <div class="settlement-worker-card" data-worker-id="3">
                            <div class="worker-info">
                                <span class="worker-name">Piotr Wiśniewski</span>
                            </div>
                            <div class="worker-settlement-fields">
                                <div class="field-group">
                                    <label>Stawka</label>
                                    <select name="morning[3][rate_id]" class="worker-rate">
                                        <option value="">Wybierz stawkę</option>
                                        <option value="1">Podstawowa - 25 zł/h</option>
                                        <option value="2">Weekendowa - 30 zł/h</option>
                                        <option value="3">Świąteczna - 40 zł/h</option>
                                    </select>
                                </div>
                                <div class="field-group field-time">
                                    <label>Czas pracy</label>
                                    <div class="time-inputs">
                                        <input type="number" name="morning[3][hours]" class="worker-hours" placeholder="0" min="0" max="23">
                                        <span class="time-separator">h</span>
                                        <input type="number" name="morning[3][minutes]" class="worker-minutes" placeholder="0" min="0" max="59">
                                        <span class="time-separator">min</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ZMIANA POPOŁUDNIOWA -->
                <div class="settlement-shift">
                    <div class="settlement-shift-header shift-afternoon">
                        <div class="shift-icon">
                            <i class="fas fa-cloud-sun"></i>
                        </div>
                        <h2>Zmiana popołudniowa</h2>
                        <span class="workers-count" id="afternoon-workers-count">2 pracowników</span>
                    </div>

                    <div class="settlement-workers" data-shift="afternoon">
                        <!-- Pracownik 1 -->
                        <div class="settlement-worker-card" data-worker-id="5">
                            <div class="worker-info">
                                <span class="worker-name">Tomasz Dąbrowski</span>
                            </div>
                            <div class="worker-settlement-fields">
                                <div class="field-group">
                                    <label>Stawka</label>
                                    <select name="afternoon[5][rate_id]" class="worker-rate">
                                        <option value="">Wybierz stawkę</option>
                                        <option value="1">Podstawowa - 25 zł/h</option>
                                        <option value="2">Weekendowa - 30 zł/h</option>
                                        <option value="3">Świąteczna - 40 zł/h</option>
                                    </select>
                                </div>
                                <div class="field-group field-time">
                                    <label>Czas pracy</label>
                                    <div class="time-inputs">
                                        <input type="number" name="afternoon[5][hours]" class="worker-hours" placeholder="0" min="0" max="23">
                                        <span class="time-separator">h</span>
                                        <input type="number" name="afternoon[5][minutes]" class="worker-minutes" placeholder="0" min="0" max="59">
                                        <span class="time-separator">min</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pracownik 2 -->
                        <div class="settlement-worker-card" data-worker-id="6">
                            <div class="worker-info">
                                <span class="worker-name">Katarzyna Lewandowska</span>
                            </div>
                            <div class="worker-settlement-fields">
                                <div class="field-group">
                                    <label>Stawka</label>
                                    <select name="afternoon[6][rate_id]" class="worker-rate">
                                        <option value="">Wybierz stawkę</option>
                                        <option value="1">Podstawowa - 25 zł/h</option>
                                        <option value="2">Weekendowa - 30 zł/h</option>
                                        <option value="3">Świąteczna - 40 zł/h</option>
                                    </select>
                                </div>
                                <div class="field-group field-time">
                                    <label>Czas pracy</label>
                                    <div class="time-inputs">
                                        <input type="number" name="afternoon[6][hours]" class="worker-hours" placeholder="0" min="0" max="23">
                                        <span class="time-separator">h</span>
                                        <input type="number" name="afternoon[6][minutes]" class="worker-minutes" placeholder="0" min="0" max="59">
                                        <span class="time-separator">min</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PRZYCISKI -->
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Obsługa przycisków "Zastosuj domyślne"
    document.querySelectorAll('.btn-apply-defaults').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const shift = this.dataset.shift;
            applyDefaults(shift);
        });
    });

    function applyDefaults(shift) {
        const rateSelect = document.getElementById(`default-${shift}-rate`);
        const hoursInput = document.getElementById(`default-${shift}-hours`);
        const minutesInput = document.getElementById(`default-${shift}-minutes`);

        const defaultRate = rateSelect.value;
        const defaultHours = hoursInput.value;
        const defaultMinutes = minutesInput.value;

        // Znajdź kontener pracowników dla danej zmiany
        const workersContainer = document.querySelector(`.settlement-workers[data-shift="${shift}"]`);
        
        if (!workersContainer) return;

        // Zastosuj wartości do wszystkich pracowników w tej zmianie
        const workerCards = workersContainer.querySelectorAll('.settlement-worker-card');
        
        workerCards.forEach(function(card) {
            // Stawka
            if (defaultRate) {
                const rateField = card.querySelector('.worker-rate');
                if (rateField) {
                    rateField.value = defaultRate;
                    // Animacja podświetlenia
                    rateField.classList.add('field-updated');
                    setTimeout(() => rateField.classList.remove('field-updated'), 500);
                }
            }

            // Godziny
            if (defaultHours) {
                const hoursField = card.querySelector('.worker-hours');
                if (hoursField) {
                    hoursField.value = defaultHours;
                    hoursField.classList.add('field-updated');
                    setTimeout(() => hoursField.classList.remove('field-updated'), 500);
                }
            }

            // Minuty
            if (defaultMinutes) {
                const minutesField = card.querySelector('.worker-minutes');
                if (minutesField) {
                    minutesField.value = defaultMinutes;
                    minutesField.classList.add('field-updated');
                    setTimeout(() => minutesField.classList.remove('field-updated'), 500);
                }
            }
        });

        // Pokaż powiadomienie
        const shiftName = shift === 'morning' ? 'rannej' : 'popołudniowej';
        if (typeof showToast !== 'undefined') {
            showToast.success(`Domyślne wartości zastosowane dla zmiany ${shiftName}`);
        }
    }
});
</script>
@endpush
