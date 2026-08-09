@extends('layouts.app')

@section('title', 'Plan dnia - Panel administratora')
@section('body_class', 'planner-day-screen')

@push('styles')
    @vite(['resources/css/planner-day.css'])
@endpush

@section('content')
<div class="admin-panel">
    @include('partials.menu')

    <main class="main-content">
        <a href="{{ route('planner.index') }}" class="planner-day-back">
            <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            Powrót do grafiku
        </a>

        <header class="planner-day-head">
            <div>
                <h1>Grafik pracy</h1>
                <p class="planner-day-subtitle">
                    Grafik na dzień <span class="planner-day-subtitle__date">{{ date('d.m.Y', strtotime($date)) ?? '--' }}</span>
                </p>
            </div>
            @if($isDraft)
                <div class="planner-day-draft-banner">Grafik jest szkicem!</div>
            @endif
        </header>

        <form id="schedule-form" class="planner-day-form" action="{{ route('planner.day.shift', $date) }}" method="POST">
            @csrf
            <div class="planner-day-layout">
                <aside class="planner-day-pool" aria-label="Dostępni pracownicy">
                    <div class="planner-day-pool__header">
                        <span class="planner-day-mono">Dostępni pracownicy</span>
                        <div class="planner-day-pool__meta">
                            <p class="planner-day-pool__count">
                                <span id="pool-count" class="planner-day-pool__count-value"></span> do przydzielenia
                            </p>
                            <button type="button" id="change-availability-btn" class="planner-day-pool__edit">
                                Zmień dostępność
                            </button>
                        </div>
                    </div>
                    <div class="planner-day-pool__body">
                        <div id="workers-list" class="planner-day-pool__cards">
                            @include('admin.planner.partials.workeravailability')
                        </div>
                        <div class="planner-day-pool__empty" data-pool-empty hidden>[ pula pusta ]</div>
                    </div>
                </aside>

                <div class="planner-day-boards">
                    @foreach([
                        'morning' => 'Zmiana ranna',
                        'afternoon' => 'Zmiana popołudniowa',
                    ] as $shiftType => $shiftLabel)
                        @php($onShift = $workers_on_shift->where('shift_type', $shiftType))
                        <section class="planner-day-board" aria-labelledby="board-title-{{ $shiftType }}">
                            <header class="planner-day-board__head">
                                <span class="planner-day-bar planner-day-bar--{{ $shiftType }}" aria-hidden="true"></span>
                                <h2 id="board-title-{{ $shiftType }}">{{ $shiftLabel }}</h2>
                                <div class="planner-day-board__meta">
                                    <span class="planner-day-mono planner-day-mono--sm" id="start-label-{{ $shiftType }}">Rozpoczęcie</span>
                                    <span class="planner-day-time">
                                        <svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="9"></circle>
                                            <polyline points="12 7 12 12 15 14"></polyline>
                                        </svg>
                                        <input
                                            type="time"
                                            id="{{ $shiftType }}_start_time"
                                            name="{{ $shiftType }}_start_time"
                                            value="{{ $shiftStartTimes[$shiftType] ?? '' }}"
                                            aria-labelledby="start-label-{{ $shiftType }} board-title-{{ $shiftType }}"
                                        >
                                    </span>
                                </div>
                                <div class="planner-day-board__meta">
                                    <span class="planner-day-mono planner-day-mono--sm">Przypisani</span>
                                    <span class="planner-day-count"><span id="{{ $shiftType }}-count">{{ $assignedCounts[$shiftType] }}</span></span>
                                </div>
                            </header>
                            <div class="planner-day-board__body" id="{{ $shiftType }}-shift" data-shift="{{ $shiftType }}">
                                <div class="assigned-workers">
                                    @foreach($onShift as $shift)
                                        <span
                                            class="assigned-worker {{ $shift->status === 'absent' ? 'worker-absent' : '' }} {{ $shift->substituted_for_shift_id ? 'worker-substitute' : '' }}"
                                            data-worker-id="{{ $shift->worker_id }}"
                                            data-shift-id="{{ $shift->id }}"
                                            data-substituted-for="{{ $shift->substituted_for_shift_id }}"
                                        >
                                            <span class="worker-name">{{ $shift->worker->first_name }} {{ $shift->worker->last_name }}</span>
                                            <button type="button" class="remove-worker" data-worker-id="{{ $shift->worker_id }}" aria-label="Usuń {{ $shift->worker->first_name }} {{ $shift->worker->last_name }} ze zmiany">
                                                <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                                </svg>
                                            </button>
                                        </span>
                                    @endforeach
                                </div>
                                <div class="planner-day-board__empty" data-board-empty @if($onShift->isNotEmpty()) hidden @endif>
                                    [ brak przypisanych — dodaj z listy po lewej ]
                                </div>
                                <div class="hidden-inputs">
                                    @foreach($onShift as $shift)
                                        <input type="hidden" name="workers[{{ $shift->worker_id }}_{{ $shiftType }}][worker_id]" value="{{ $shift->worker_id }}" data-worker-id="{{ $shift->worker_id }}">
                                        <input type="hidden" name="workers[{{ $shift->worker_id }}_{{ $shiftType }}][shift_type]" value="{{ $shiftType }}" data-worker-id="{{ $shift->worker_id }}">
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>

            <div class="planner-day-actions">
                <a href="{{ route('planner.day.end-day', $date) }}" class="planner-day-btn planner-day-btn--ghost">
                    Rozlicz dzień
                </a>
                <input type="hidden" name="is_draft" id="is-draft-input" value="0">
                <button type="button" id="save-draft" class="planner-day-btn planner-day-btn--secondary">
                    Zapisz jako szkic
                </button>
                <button type="submit" id="save-schedule" class="planner-day-btn planner-day-btn--primary">
                    Zapisz grafik
                </button>
            </div>
        </form>

        <form id="availability-form" action="{{ route('planner.day.availability', $date) }}" method="POST">
            @csrf
            <div id="availability-modal" class="modal-overlay">
                <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="availability-modal-title">
                    <div class="modal-header">
                        <h3 id="availability-modal-title">Zmień dostępność pracowników</h3>
                        <button type="button" class="modal-close" id="close-modal" aria-label="Zamknij okno dostępności">
                            <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            @forelse($workers as $worker)
                                @php($availability = $worker->availabilities->first())
                                <div class="availability-item">
                                    <input type="hidden" name="workers[{{ $worker->id }}][worker_id]" value="{{ $worker->id }}">
                                    <span class="worker-name">{{ $worker->first_name }} {{ $worker->last_name }}</span>
                                    <div class="availability-toggles">
                                        <div class="toggle-group">
                                            <span class="toggle-label">Ranna</span>
                                            <label class="toggle-switch" aria-label="Dostępność {{ $worker->first_name }} {{ $worker->last_name }}: zmiana ranna">
                                                <input name="workers[{{ $worker->id }}][morning_shift]" type="checkbox" data-shift="morning" @checked($availability?->morning_shift)>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                        <div class="toggle-group">
                                            <span class="toggle-label">Popołudniowa</span>
                                            <label class="toggle-switch" aria-label="Dostępność {{ $worker->first_name }} {{ $worker->last_name }}: zmiana popołudniowa">
                                                <input name="workers[{{ $worker->id }}][afternoon_shift]" type="checkbox" data-shift="afternoon" @checked($availability?->afternoon_shift)>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="availability-empty">[ brak pracowników ]</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="planner-day-btn planner-day-btn--secondary" id="cancel-availability">Anuluj</button>
                        <button type="submit" class="planner-day-btn planner-day-btn--primary">Zapisz zmiany</button>
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
