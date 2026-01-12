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
        <a href="{{ route('planner.day.index', $date ?? '2026-01-04') }}" class="settings-back-link">
            <i class="fas fa-arrow-left"></i> Powrót do grafiku dnia
        </a>

        <div class="header">
            <h1><i class="fas fa-calculator"></i> Rozliczenie dnia</h1>
            <p>Rozlicz pracowników za dzień: <strong>{{ $formattedDate ?? '4 stycznia 2026' }}</strong></p>
        </div>

        <form id="settlement-form" action="#" method="POST">
            @csrf
            <input type="hidden" name="date" value="{{ $date ?? '2026-01-04' }}">

            <div class="settlement-container">
                <div class="settlement-shift">
                    <div class="settlement-shift-header shift-morning">
                        <div class="shift-icon">
                            <i class="fas fa-sun"></i>
                        </div>
                        <h2>Zmiana ranna</h2>
                    </div>

                    <div class="settlement-workers">
                        <div class="settlement-worker-card">
                            <div class="worker-info">
                                <span class="worker-name">Jan Kowalski</span>
                            </div>
                            <div class="worker-settlement-fields">
                                <div class="field-group">
                                    <label>Stawka</label>
                                    <select name="morning[1][rate_id]">
                                        <option value="">Wybierz stawkę</option>
                                        <option value="1">Podstawowa - 25 zł/h</option>
                                        <option value="2">Weekendowa - 30 zł/h</option>
                                        <option value="3">Świąteczna - 40 zł/h</option>
                                    </select>
                                </div>
                                <div class="field-group field-time">
                                    <label>Czas pracy</label>
                                    <div class="time-inputs">
                                        <input type="number" name="morning[1][hours]" placeholder="0" min="0" max="23">
                                        <span class="time-separator">h</span>
                                        <input type="number" name="morning[1][minutes]" placeholder="0" min="0" max="59">
                                        <span class="time-separator">min</span>
                                    </div>
                                </div>
                                <div class="field-group field-packages">
                                    <label>Paczki</label>
                                    <input type="number" name="morning[1][packages]" placeholder="0" min="0">
                                </div>
                            </div>
                        </div>

                        <div class="settlement-worker-card">
                            <div class="worker-info">
                                <span class="worker-name">Anna Nowak</span>
                            </div>
                            <div class="worker-settlement-fields">
                                <div class="field-group">
                                    <label>Stawka</label>
                                    <select name="morning[2][rate_id]">
                                        <option value="">Wybierz stawkę</option>
                                        <option value="1">Podstawowa - 25 zł/h</option>
                                        <option value="2">Weekendowa - 30 zł/h</option>
                                        <option value="3">Świąteczna - 40 zł/h</option>
                                    </select>
                                </div>
                                <div class="field-group field-time">
                                    <label>Czas pracy</label>
                                    <div class="time-inputs">
                                        <input type="number" name="morning[2][hours]" placeholder="0" min="0" max="23">
                                        <span class="time-separator">h</span>
                                        <input type="number" name="morning[2][minutes]" placeholder="0" min="0" max="59">
                                        <span class="time-separator">min</span>
                                    </div>
                                </div>
                                <div class="field-group field-packages">
                                    <label>Paczki</label>
                                    <input type="number" name="morning[2][packages]" placeholder="0" min="0">
                                </div>
                            </div>
                        </div>

                        <!-- Pracownik 3 -->
                        <div class="settlement-worker-card">
                            <div class="worker-info">
                                <span class="worker-name">Piotr Wiśniewski</span>
                            </div>
                            <div class="worker-settlement-fields">
                                <div class="field-group">
                                    <label>Stawka</label>
                                    <select name="morning[3][rate_id]">
                                        <option value="">Wybierz stawkę</option>
                                        <option value="1">Podstawowa - 25 zł/h</option>
                                        <option value="2">Weekendowa - 30 zł/h</option>
                                        <option value="3">Świąteczna - 40 zł/h</option>
                                    </select>
                                </div>
                                <div class="field-group field-time">
                                    <label>Czas pracy</label>
                                    <div class="time-inputs">
                                        <input type="number" name="morning[3][hours]" placeholder="0" min="0" max="23">
                                        <span class="time-separator">h</span>
                                        <input type="number" name="morning[3][minutes]" placeholder="0" min="0" max="59">
                                        <span class="time-separator">min</span>
                                    </div>
                                </div>
                                <div class="field-group field-packages">
                                    <label>Paczki</label>
                                    <input type="number" name="morning[3][packages]" placeholder="0" min="0">
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
                    </div>

                    <div class="settlement-workers">
                        <!-- Pracownik 1 -->
                        <div class="settlement-worker-card">
                            <div class="worker-info">
                                <span class="worker-name">Tomasz Dąbrowski</span>
                            </div>
                            <div class="worker-settlement-fields">
                                <div class="field-group">
                                    <label>Stawka</label>
                                    <select name="afternoon[5][rate_id]">
                                        <option value="">Wybierz stawkę</option>
                                        <option value="1">Podstawowa - 25 zł/h</option>
                                        <option value="2">Weekendowa - 30 zł/h</option>
                                        <option value="3">Świąteczna - 40 zł/h</option>
                                    </select>
                                </div>
                                <div class="field-group field-time">
                                    <label>Czas pracy</label>
                                    <div class="time-inputs">
                                        <input type="number" name="afternoon[5][hours]" placeholder="0" min="0" max="23">
                                        <span class="time-separator">h</span>
                                        <input type="number" name="afternoon[5][minutes]" placeholder="0" min="0" max="59">
                                        <span class="time-separator">min</span>
                                    </div>
                                </div>
                                <div class="field-group field-packages">
                                    <label>Paczki</label>
                                    <input type="number" name="afternoon[5][packages]" placeholder="0" min="0">
                                </div>
                            </div>
                        </div>

                        <!-- Pracownik 2 -->
                        <div class="settlement-worker-card">
                            <div class="worker-info">
                                <span class="worker-name">Katarzyna Lewandowska</span>
                            </div>
                            <div class="worker-settlement-fields">
                                <div class="field-group">
                                    <label>Stawka</label>
                                    <select name="afternoon[6][rate_id]">
                                        <option value="">Wybierz stawkę</option>
                                        <option value="1">Podstawowa - 25 zł/h</option>
                                        <option value="2">Weekendowa - 30 zł/h</option>
                                        <option value="3">Świąteczna - 40 zł/h</option>
                                    </select>
                                </div>
                                <div class="field-group field-time">
                                    <label>Czas pracy</label>
                                    <div class="time-inputs">
                                        <input type="number" name="afternoon[6][hours]" placeholder="0" min="0" max="23">
                                        <span class="time-separator">h</span>
                                        <input type="number" name="afternoon[6][minutes]" placeholder="0" min="0" max="59">
                                        <span class="time-separator">min</span>
                                    </div>
                                </div>
                                <div class="field-group field-packages">
                                    <label>Paczki</label>
                                    <input type="number" name="afternoon[6][packages]" placeholder="0" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PRZYCISKI -->
                <div class="settlement-actions">
                    <a href="{{ route('planner.day.index', $date ?? '2026-01-04') }}" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Anuluj
                    </a>
                    <button type="submit" class="btn btn-submit">
                        <i class="fas fa-check"></i> Zapisz rozliczenie
                    </button>
                </div>
            </div>
        </form>
    </main>
</div>
@endsection
