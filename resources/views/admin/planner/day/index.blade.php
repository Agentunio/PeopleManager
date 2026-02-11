@extends('layouts.app')

@section('title', 'Plan dnia - Panel administratora')

@push('styles')
    @vite(['resources/css/settings.css', 'resources/css/planner.css'])
@endpush

@section('content')
<div class="admin-panel">
    @include('partials.menu')

    <main class="main-content">
        <a href="{{ route('planner.index') }}" class="settings-back-link">
            <i class="fas fa-arrow-left"></i> Powrót do grafiku
        </a>
        <div class="header">
            <h1>Grafik pracy</h1>
            <p>Zarządzaj harmonogramem pracy. Wybierz datę, aby przypisać pracowników do zmian.</p>
        </div>

        <div id="day-view" class="planner-view">
            <div class="day-view-header">
                <h2 id="selected-date-title">Grafik na dzień: <span>{{ date('d.m.Y', strtotime($date)) ?? '--' }}</span></h2>
            </div>

            <form id="schedule-form" action="{{ route('planner.day.shift', $date) }}" method="POST">
            @csrf
            <div class="day-view-content">
                <div class="workers-panel">
                    <div class="workers-panel-header">
                        <h3><i class="fas fa-users"></i> Dostępni pracownicy</h3>
                        <span id="change-availability-btn" class="btn btn-change">
                            <i class="fas fa-user-clock"></i> Zmień dostępność
                        </span>
                    </div>
                    <div class="workers-list" id="workers-list">
                        @include('admin.planner.partials.workeravailability')
                    </div>
                </div>

                <div class="shifts-panel">
                    <div class="shift-box">
                        <div class="shift-header shift-morning">
                            <div class="shift-icon">
                                <i class="fas fa-sun"></i>
                            </div>
                            <div class="shift-title">
                                <h3>Zmiana ranna</h3>
                            </div>
                            <div class="shift-count">
                                Liczba pracowników aktualnie przypisanych <span id="morning-count">0</span>
                            </div>
                        </div>
                        <div class="shift-dropzone" id="morning-shift" data-shift="morning">
                            <div class="dropzone-placeholder" @if($workers_on_shift->where('shift_type', 'morning')->count() > 0) style="display: none;" @endif>
                                <i class="fas fa-user-plus"></i>
                                <span>Przeciągnij pracownika tutaj</span>
                            </div>
                            <div class="assigned-workers">
                                @foreach($workers_on_shift->where('shift_type', 'morning') as $shift)
                                    <div class="assigned-worker draggable" data-worker-id="{{ $shift->worker_id }}">
                                        <span class="worker-name">{{ $shift->worker->first_name }} {{ $shift->worker->last_name }}</span>
                                        <button type="button" class="remove-worker" data-worker-id="{{ $shift->worker_id }}">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            <div class="hidden-inputs">
                                @foreach($workers_on_shift->where('shift_type', 'morning') as $shift)
                                    <input type="hidden" name="workers[{{ $shift->worker_id }}_morning][worker_id]" value="{{ $shift->worker_id }}" data-worker-id="{{ $shift->worker_id }}">
                                    <input type="hidden" name="workers[{{ $shift->worker_id }}_morning][shift_type]" value="morning" data-worker-id="{{ $shift->worker_id }}">
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="shift-box">
                        <div class="shift-header shift-afternoon">
                            <div class="shift-icon">
                                <i class="fas fa-cloud-sun"></i>
                            </div>
                            <div class="shift-title">
                                <h3>Zmiana popołudniowa</h3>
                            </div>
                            <div class="shift-count">
                                Liczba pracowników aktualnie przypisanych <span id="afternoon-count">0</span>
                            </div>
                        </div>

                        <div class="shift-dropzone" id="afternoon-shift" data-shift="afternoon">
                            <div class="dropzone-placeholder" @if($workers_on_shift->where('shift_type', 'afternoon')->count() > 0) style="display: none;" @endif>
                                <i class="fas fa-user-plus"></i>
                                <span>Przeciągnij pracownika tutaj</span>
                            </div>

                            <div class="assigned-workers">
                                @foreach($workers_on_shift->where('shift_type', 'afternoon') as $shift)
                                    <div class="assigned-worker draggable" data-worker-id="{{ $shift->worker_id }}">
                                        <span class="worker-name">{{ $shift->worker->first_name }} {{ $shift->worker->last_name }}</span>
                                        <button type="button" class="remove-worker" data-worker-id="{{ $shift->worker_id }}">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            <div class="hidden-inputs">
                                @foreach($workers_on_shift->where('shift_type', 'afternoon') as $shift)
                                    <input type="hidden" name="workers[{{ $shift->worker_id }}_afternoon][worker_id]" value="{{ $shift->worker_id }}" data-worker-id="{{ $shift->worker_id }}">
                                    <input type="hidden" name="workers[{{ $shift->worker_id }}_afternoon][shift_type]" value="afternoon" data-worker-id="{{ $shift->worker_id }}">
                                @endforeach
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="day-summary">
                <a href="{{ route('planner.day.end-day', $date) }}" id="settle-day" class="btn btn-change">
                    <i class="fas fa-calculator"></i> Rozlicz dzień
                </a>
                <button type="submit" id="save-schedule" class="btn btn-submit">
                    <i class="fas fa-save"></i> Zapisz grafik
                </button>
            </div>
            </form>
        </div>

        <form id="availability-form" action="{{ route('planner.day.availability', $date) }}" method="POST">
            @csrf
            <div id="availability-modal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-user-clock"></i> Zmień dostępność pracowników</h3>
                        <div class="modal-close" id="close-modal">
                            <i class="fas fa-times"></i>
                        </div>
                    </div>
                    <div class="modal-body">
                        <div class="availability-list">
                            @forelse($workers as $worker)
                                @php $availability = $worker->availabilities->first(); @endphp
                                <div class="availability-item">
                                    <input type="hidden" name="workers[{{ $worker->id }}][worker_id]" value="{{ $worker->id }}">
                                    <span class="worker-name">{{ $worker->first_name }} {{ $worker->last_name }}</span>
                                    <div class="availability-toggles">
                                        <div class="toggle-group">
                                            <span class="toggle-label">Ranna</span>
                                            <label class="toggle-switch">
                                                <input name="workers[{{ $worker->id }}][morning_shift]" type="checkbox" data-shift="morning" @checked($availability?->morning_shift)>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                        <div class="toggle-group">
                                            <span class="toggle-label">Popołudniowa</span>
                                            <label class="toggle-switch">
                                                <input name="workers[{{ $worker->id }}][afternoon_shift]" type="checkbox" data-shift="afternoon" @checked($availability?->afternoon_shift)>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                @empty
                                    <p>Brak pracowników</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="btn btn-cancel" id="cancel-availability">Anuluj</div>
                        <button type="submit" class="btn btn-submit" id="save-availability">Zapisz zmiany</button>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>
@endsection

@push('scripts')
    <script>
        let workersData = @json($workersJson);
    </script>
    @vite(['resources/js/planner-day.js'])
@endpush
