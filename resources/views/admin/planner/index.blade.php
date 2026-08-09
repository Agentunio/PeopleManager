@extends('layouts.app')

@section('title', 'Grafik - Panel administratora')
@section('body_class', 'planner-index-screen')

@push('styles')
    @vite(['resources/css/planner-index.css'])
@endpush

@section('content')
    <div class="admin-panel">
        @include('partials.menu')

        <main
            class="main-content planner-index-page"
            data-planner-page
            data-open-schedule-dialog="{{ $scheduleHasErrors ? 'true' : 'false' }}"
            data-shift-status-url-template="{{ route('planner.day.shifts.status', ['date' => '__DATE__', 'workerShift' => '__SHIFT__'], false) }}"
            data-substitute-candidates-url-template="{{ route('planner.day.shifts.substitutes.index', ['date' => '__DATE__', 'workerShift' => '__SHIFT__'], false) }}"
            data-substitute-store-url-template="{{ route('planner.day.shifts.substitutes.store', ['date' => '__DATE__', 'workerShift' => '__SHIFT__'], false) }}"
            data-shift-remove-url-template="{{ route('planner.day.shifts.destroy', ['date' => '__DATE__', 'workerShift' => '__SHIFT__'], false) }}"
        >
            <div class="planner-heading">
                <h1>Grafik</h1>
                <div class="planner-heading__actions">
                    <button type="button" class="planner-ghost-button" data-dialog-open="export-dialog">
                        <svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Eksport grafiku
                    </button>
                    <button type="button" class="planner-primary-button" data-dialog-open="schedule-dialog">
                        <span aria-hidden="true">+</span>
                        Włącz nowy grafik
                    </button>
                </div>
            </div>

            <section class="planner-window planner-window--{{ $windowMeta['class'] }}" aria-label="Aktualny stan grafiku">
                <div class="planner-window__main">
                    <span class="planner-window__badge">
                        <span aria-hidden="true"></span>
                        {{ $windowMeta['label'] }}
                    </span>
                    <p>
                        @if($scheduleWindow['range_label'])
                            <strong>{{ $scheduleWindow['range_label'] }}</strong>
                            @if($windowMeta['detail'])
                                <span aria-hidden="true">·</span> {{ $windowMeta['detail'] }}
                            @endif
                        @else
                            {{ $windowMeta['detail'] }}
                        @endif
                    </p>
                </div>

                @if($scheduleWindow['type'] === 'signup' && $scheduleWindow['days_left'] !== null && $scheduleWindow['allows_signup'])
                    <span class="planner-window__countdown">
                        Pozostało {{ max(0, $scheduleWindow['days_left']) }}
                        {{ abs($scheduleWindow['days_left']) === 1 ? 'dzień' : 'dni' }}
                    </span>
                @endif
            </section>

            <div class="planner-toolbar">
                <nav class="planner-month-switcher" aria-label="Wybór miesiąca">
                    <a href="{{ route('planner.index', ['month' => $calendar['prev']]) }}" aria-label="Poprzedni miesiąc">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </a>
                    <span>{{ mb_strtolower($calendar['month']) }} {{ $calendar['year'] }}</span>
                    <a href="{{ route('planner.index', ['month' => $calendar['next']]) }}" aria-label="Następny miesiąc">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </a>
                </nav>

                <div class="planner-status-filters" role="group" aria-label="Filtr statusu dni">
                    @foreach([
                        'all' => ['Wszystkie', $statusCounts['all']],
                        'draft' => ['Szkic', $statusCounts['draft']],
                        'active' => ['Aktywne', $statusCounts['active']],
                        'settled' => ['Rozliczone', $statusCounts['settled']],
                    ] as $status => [$label, $count])
                        <button
                            type="button"
                            class="planner-status-filter {{ $status === 'all' ? 'is-active' : '' }}"
                            data-status-filter="{{ $status }}"
                            aria-pressed="{{ $status === 'all' ? 'true' : 'false' }}"
                        >
                            {{ $label }} <span>{{ $count }}</span>
                        </button>
                    @endforeach
                </div>

                <p class="planner-result-count" aria-live="polite">
                    wynik: <strong data-result-count>{{ $statusCounts['all'] }}</strong> / {{ $statusCounts['all'] }}
                </p>
            </div>

            <section class="planner-day-list" aria-label="Dni grafiku">
                @foreach($days as $day)
                    @include('admin.planner.partials.day-card', ['day' => $day])
                @endforeach
            </section>

            <div class="planner-empty-filter" data-filter-empty hidden>
                <span>[ brak dni dla filtru ]</span>
                <p>Zmień filtr statusu lub miesiąc.</p>
            </div>
        </main>
    </div>

    <div id="planner-person-menu" class="planner-person-menu" data-person-menu hidden>
        <button type="button" data-shift-status="absent" hidden>Oznacz jako niedostępny</button>
        <button type="button" class="is-accent" data-substitute-open hidden>Dodaj zastępstwo</button>
        <button type="button" data-shift-status="worked" hidden>Cofnij niedostępność</button>
        <button type="button" class="is-danger" data-shift-remove>Usuń z grafiku</button>
    </div>

    <dialog id="schedule-dialog" class="planner-dialog" aria-labelledby="schedule-dialog-title">
        <form action="{{ route('planner.schedule.store') }}" method="POST" data-schedule-form>
            @csrf
            <header class="planner-dialog__header">
                <div>
                    <span class="planner-dialog__eyebrow">[ włączenie grafiku ]</span>
                    <h2 id="schedule-dialog-title">Nowy okres zapisów</h2>
                    <p>Pracownicy zobaczą dni zgodnie z wybranym trybem.</p>
                </div>
                <button type="button" class="planner-dialog__close" data-dialog-close>Zamknij</button>
            </header>

            <div class="planner-dialog__body">
                <p class="planner-dialog__intro">
                    Ustaw zakres dni grafiku oraz sposób zarządzania zapisami pracowników.
                </p>

                <fieldset class="planner-fieldset">
                    <legend>Zakres grafiku <span aria-hidden="true">*</span></legend>
                    <div class="planner-date-grid">
                        <label>
                            <span>Od</span>
                            <input type="date" name="start_date" value="{{ old('start_date', $scheduleForm['start_date']) }}" required>
                        </label>
                        <label>
                            <span>Do</span>
                            <input type="date" name="end_date" value="{{ old('end_date', $scheduleForm['end_date']) }}" required>
                        </label>
                    </div>
                    <div class="planner-quick-dates">
                        <button type="button" data-week-preset="1">+ Następny tydzień</button>
                        <button type="button" data-week-preset="2">+ Za 2 tygodnie</button>
                    </div>
                    @error('start_date')<p class="planner-field-error">{{ $message }}</p>@enderror
                    @error('end_date')<p class="planner-field-error">{{ $message }}</p>@enderror
                </fieldset>

                <fieldset class="planner-fieldset">
                    <legend>Zapisy pracowników</legend>
                    <div class="planner-mode-selector">
                        @foreach([
                            'signup' => 'Do deadline',
                            'always' => 'Zawsze otwarte',
                            'admin' => 'Wyłączone',
                        ] as $value => $label)
                            <label>
                                <input type="radio" name="type" value="{{ $value }}" {{ $scheduleType === $value ? 'checked' : '' }}>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('type')<p class="planner-field-error">{{ $message }}</p>@enderror

                    <label class="planner-deadline-field" data-deadline-field>
                        <span>Termin zamknięcia zapisów</span>
                        <input
                            type="datetime-local"
                            name="signup_deadline"
                            value="{{ old('signup_deadline', $scheduleForm['deadline']) }}"
                        >
                    </label>
                    @error('signup_deadline')<p class="planner-field-error">{{ $message }}</p>@enderror

                    <p class="planner-mode-hint" data-mode-hint aria-live="polite"></p>
                </fieldset>
            </div>

            <footer class="planner-dialog__footer">
                <button type="button" class="planner-secondary-button" data-dialog-close>Anuluj</button>
                <button type="submit" class="planner-primary-button">Włącz grafik</button>
            </footer>
        </form>
    </dialog>

    <dialog id="export-dialog" class="planner-dialog planner-dialog--export" aria-labelledby="export-dialog-title">
        <form action="{{ route('planner.export.week') }}" method="POST">
            @csrf
            <header class="planner-dialog__header">
                <div>
                    <span class="planner-dialog__eyebrow">[ eksport ]</span>
                    <h2 id="export-dialog-title">Eksport grafiku</h2>
                    <p>Wybierz tydzień do pobrania.</p>
                </div>
                <button type="button" class="planner-dialog__close" data-dialog-close>Zamknij</button>
            </header>
            <div class="planner-dialog__body">
                <label class="planner-select-field">
                    <span>Tydzień</span>
                    <select name="week_start" required>
                        @foreach($weeks as $week)
                            <option value="{{ $week['value'] }}">{{ $week['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <p class="planner-export-hint">
                    Pobierzesz archiwum ZIP zawierające grafik w formatach PDF oraz PNG.
                </p>
            </div>
            <footer class="planner-dialog__footer">
                <button type="button" class="planner-secondary-button" data-dialog-close>Anuluj</button>
                <button type="submit" class="planner-primary-button">Pobierz tydzień</button>
            </footer>
        </form>
    </dialog>

    <dialog id="substitute-dialog" class="planner-dialog" aria-labelledby="substitute-dialog-title">
        <form data-substitute-form>
            <header class="planner-dialog__header">
                <div>
                    <span class="planner-dialog__eyebrow">[ zastępstwo ]</span>
                    <h2 id="substitute-dialog-title">Wybierz zastępstwo</h2>
                    <p data-substitute-description></p>
                </div>
                <button type="button" class="planner-dialog__close" data-dialog-close>Zamknij</button>
            </header>
            <div class="planner-dialog__body">
                <p class="planner-dialog-status" data-substitute-status aria-live="polite">Ładowanie pracowników…</p>
                <fieldset class="planner-substitute-list" data-substitute-list hidden>
                    <legend class="sr-only">Dostępni pracownicy</legend>
                </fieldset>
            </div>
            <footer class="planner-dialog__footer">
                <button type="button" class="planner-secondary-button" data-dialog-close>Anuluj</button>
                <button type="submit" class="planner-primary-button" data-substitute-submit disabled>Przypisz zastępstwo</button>
            </footer>
        </form>
    </dialog>
@endsection

@push('scripts')
    @vite(['resources/js/planner-index.js'])
@endpush
