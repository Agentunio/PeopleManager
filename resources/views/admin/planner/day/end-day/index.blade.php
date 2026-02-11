@extends('layouts.app')

@section('title', 'Rozliczenie dnia - Panel administratora')

@push('styles')
    @vite(['resources/css/settings.css', 'resources/css/planner.css', 'resources/css/settlement.css'])
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
                                        <input type="number" id="default-morning-from-minute" placeholder="00" min="0" max="59">
                                    </div>
                                    <span class="time-range-separator">—</span>
                                    <div class="time-to">
                                        <span class="time-label">Do</span>
                                        <input type="number" id="default-morning-to-hour" placeholder="00" min="0" max="23">
                                        <span class="time-colon">:</span>
                                        <input type="number" id="default-morning-to-minute" placeholder="00" min="0" max="59">
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
                                        <input type="number" id="default-afternoon-from-minute" placeholder="00" min="0" max="59">
                                    </div>
                                    <span class="time-range-separator">—</span>
                                    <div class="time-to">
                                        <span class="time-label">Do</span>
                                        <input type="number" id="default-afternoon-to-hour" placeholder="00" min="0" max="23">
                                        <span class="time-colon">:</span>
                                        <input type="number" id="default-afternoon-to-minute" placeholder="00" min="0" max="59">
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

            <form id="settlement-form" action="{{ route('planner.day.update', $date) }}" method="POST">
                @csrf
                @method('PATCH')

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
                            @forelse($workers_morning as $worker_morning)
                            <div class="settlement-worker-card">
                                <input type="hidden" name="workers[{{ $worker_morning->worker->id }}][id]" value="{{ $worker_morning->worker->id }}"/>
                                <input type="hidden" name="workers[{{ $worker_morning->worker->id }}][shift_type]" value="{{ $worker_morning->shift_type }}">
                                <div class="worker-info">
                                    <span class="worker-name">{{ $worker_morning->worker->first_name }} {{ $worker_morning->worker->last_name }}</span>
                                </div>
                                <div class="worker-settlement-fields">
                                    <div class="field-group">
                                        <span>Stawka</span>
                                        <select name="workers[{{ $worker_morning->worker->id }}][package]" class="worker-rate">
                                            <option value="">Wybierz stawkę</option>
                                            @foreach($packages as $package)
                                                <option value="{{ $package->id }}" @selected($worker_morning->package_id == $package->id)>
                                                    {{ $package->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="field-group field-time">
                                        <span>Czas pracy</span>

                                        @if($worker_morning->minutes)
                                            <div class="time-saved">
                                                <span class="saved-hours">
                                                    Zapisano: {{ floor($worker_morning->minutes / 60) }}h {{ $worker_morning->minutes % 60 }}min
                                                </span>
                                                <button type="button" class="btn btn-small btn-change-time">
                                                    <i class="fas fa-edit"></i> Zmień
                                                </button>
                                            </div>
                                        @endif

                                        <div class="time-range-inputs" @if($worker_morning->minutes) style="display: none;" @endif>
                                        <div class="time-from">
                                                <span class="time-label">Od</span>
                                                <input type="number" name="workers[{{ $worker_morning->worker->id }}][from_hour]" class="worker-from-hour" placeholder="00" min="0" max="23">
                                                <span class="time-colon">:</span>
                                                <input type="number" name="workers[{{ $worker_morning->worker->id }}][from_minute]" class="worker-from-minute" placeholder="00" min="0" max="59">
                                            </div>
                                            <span class="time-range-separator">—</span>
                                            <div class="time-to">
                                                <span class="time-label">Do</span>
                                                <input type="number" name="workers[{{ $worker_morning->worker->id }}][to_hour]" class="worker-to-hour" placeholder="00" min="0" max="23">
                                                <span class="time-colon">:</span>
                                                <input type="number" name="workers[{{ $worker_morning->worker->id }}][to_minute]" class="worker-to-minute" placeholder="00" min="0" max="59">
                                            </div>
                                            <div class="time-calculated">
                                                <span class="calculated-hours">0h 0min</span>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            @empty
                                <p>Brak pracowników</p>
                            @endforelse
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
                            @forelse($workers_afternoon as $worker_afternoon)
                                <div class="settlement-worker-card">
                                    <input type="hidden" name="workers[{{ $worker_afternoon->worker->id }}][id]" value="{{ $worker_afternoon->worker->id }}"/>
                                    <input type="hidden" name="workers[{{ $worker_afternoon->worker->id }}][shift_type]" value="{{ $worker_afternoon->shift_type }}">
                                    <div class="worker-info">
                                        <span class="worker-name">{{ $worker_afternoon->worker->first_name }} {{ $worker_afternoon->worker->last_name }}</span>
                                    </div>
                                    <div class="worker-settlement-fields">
                                        <div class="field-group">
                                            <span>Stawka</span>
                                            <select name="workers[{{ $worker_afternoon->worker->id }}][package]" class="worker-rate">
                                                <option value="">Wybierz stawkę</option>
                                                @foreach($packages as $package)
                                                    <option value="{{ $package->id }}" @selected($worker_afternoon->package_id == $package->id)>
                                                        {{ $package->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="field-group field-time">
                                            <span>Czas pracy</span>

                                            @if($worker_afternoon->minutes)
                                                <div class="time-saved">
                                                    <span class="saved-hours">
                                                        Zapisano: {{ floor($worker_afternoon->minutes / 60) }}h {{ $worker_afternoon->minutes % 60 }}min
                                                    </span>
                                                    <button type="button" class="btn btn-small btn-change-time">
                                                        <i class="fas fa-edit"></i> Zmień
                                                    </button>
                                                </div>
                                            @endif

                                            <div class="time-range-inputs" @if($worker_afternoon->minutes) style="display: none;" @endif>
                                                <div class="time-from">
                                                    <span class="time-label">Od</span>
                                                    <input type="number" name="workers[{{ $worker_afternoon->worker->id }}][from_hour]" class="worker-from-hour" placeholder="00" min="0" max="23">
                                                    <span class="time-colon">:</span>
                                                    <input type="number" name="workers[{{ $worker_afternoon->worker->id }}][from_minute]" class="worker-from-minute" placeholder="00" min="0" max="59">
                                                </div>
                                                <span class="time-range-separator">—</span>
                                                <div class="time-to">
                                                    <span class="time-label">Do</span>
                                                    <input type="number" name="workers[{{ $worker_afternoon->worker->id }}][to_hour]" class="worker-to-hour" placeholder="00" min="0" max="23">
                                                    <span class="time-colon">:</span>
                                                    <input type="number" name="workers[{{ $worker_afternoon->worker->id }}][to_minute]" class="worker-to-minute" placeholder="00" min="0" max="59">
                                                </div>
                                                <div class="time-calculated">
                                                    <span class="calculated-hours">0h 0min</span>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            @empty
                                <p>Brak pracowników</p>
                            @endforelse
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
                                <div class="global-shift-group">
                                    <div class="global-shift-label shift-morning-label">
                                        <i class="fas fa-sun"></i>
                                        <span>Zmiana ranna</span>
                                    </div>
                                    <div class="global-shift-fields">
                                        <div class="field-group">
                                            <label>Liczba paczek</label>
                                            <input type="number" name="morning_packages" id="morning-packages"
                                                   value="{{ $shift_packages_morning->packages_count ?? '' }}"
                                                   placeholder="0" min="0">
                                        </div>
                                        <div class="field-group">
                                            <label>Stawka za paczkę</label>
                                            <select name="morning_package_rate" id="morning-package-rate">
                                                <option value="">Wybierz stawkę</option>
                                                @include('admin.planner.partials.allpackage',  [
                                                    'selected_id' => $shift_packages_morning->package_id ?? null
                                                ])
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="global-shift-group">
                                    <div class="global-shift-label shift-afternoon-label">
                                        <i class="fas fa-cloud-sun"></i>
                                        <span>Zmiana popołudniowa</span>
                                    </div>
                                    <div class="global-shift-fields">
                                        <div class="field-group">
                                            <label>Liczba paczek</label>
                                            <input type="number" name="afternoon_packages" id="afternoon-packages"
                                                   value="{{ $shift_packages_afternoon->packages_count ?? '' }}"
                                                   placeholder="0" min="0">
                                        </div>
                                        <div class="field-group">
                                            <label>Stawka za paczkę</label>
                                            <select name="afternoon_package_rate" id="afternoon-package-rate">
                                                <option value="">Wybierz stawkę</option>
                                                @include('admin.planner.partials.allpackage', [
                                                    'selected_id' => $shift_packages_afternoon->package_id ?? null
                                                ])
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
    @vite(['resources/js/settlement.js'])
@endpush
