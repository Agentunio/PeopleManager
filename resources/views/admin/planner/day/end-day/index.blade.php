@extends('layouts.app')

@section('title', 'Rozliczenie dnia - Panel administratora')
@section('body_class', 'settlement-screen')

@push('styles')
    @vite(['resources/css/settlement.css'])
@endpush

@section('mobile_header_action')
    <button type="submit" form="settlement-form" class="settlement-mobile-submit">
        Zatwierdź
    </button>
@endsection

@section('content')
<div class="admin-panel">
    @include('partials.menu')

    <main class="main-content settlement-page" id="daySettlementPage">
        <a href="{{ route('planner.day.index', $date) }}" class="settlement-back">
            <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            Obsada dnia
        </a>

        <header class="settlement-head">
            <div>
                <h1>Rozliczenie dnia</h1>
                <p class="settlement-subtitle">
                    {{ $dateData['heading'] }}
                    <span aria-hidden="true">·</span>
                    <span class="settlement-subtitle__date">{{ $dateData['formatted'] }}</span>
                </p>
            </div>
            @if($isSettled)
                <div class="settlement-status">Dzień rozliczony</div>
            @endif
        </header>

        <form id="settlement-form" class="settlement-form" action="{{ route('planner.day.update', $date) }}" method="POST">
            @csrf
            @method('PATCH')

            <div class="settlement-layout">
                <aside class="settlement-rail" aria-labelledby="settlement-rail-title">
                    <div class="settlement-rail__header">
                        <span class="settlement-mono" id="settlement-rail-title">Podsumowanie dnia</span>
                        <p class="settlement-rail__hint">liczone na bieżąco</p>
                    </div>

                    <div class="settlement-rail__body">
                        <div class="settlement-stat">
                            <span class="settlement-mono settlement-mono--sm">Paczki</span>
                            <strong id="summaryPackageCount">{{ number_format($summary['packageCount'], 0, ',', ' ') }}</strong>
                        </div>
                        <div class="settlement-stat settlement-stat--accent">
                            <span class="settlement-mono settlement-mono--sm">Wartość paczek</span>
                            <strong id="summaryPackageValue">{{ number_format($summary['packageValue'], 0, ',', ' ') }} zł</strong>
                        </div>
                        <div class="settlement-stat">
                            <span class="settlement-mono settlement-mono--sm">Godziny łącznie</span>
                            <strong id="summaryHours">{{ number_format($summary['minutes'] / 60, 1, ',', ' ') }} h</strong>
                        </div>
                        <div class="settlement-stat">
                            <span class="settlement-mono settlement-mono--sm">Rozliczani pracownicy</span>
                            <strong id="summaryWorkerCount">{{ $summary['workerCount'] }}</strong>
                        </div>
                    </div>

                    <div class="settlement-rail__footer">
                        <p
                            class="settlement-alert"
                            id="missingRateAlert"
                            role="status"
                            @if($summary['missingRates'] === 0) hidden @endif
                        >
                            <span id="missingRateText">
                                {{ $summary['missingRates'] }} pracowników nie ma przydzielonej stawki. Ustaw domyślną dla zmiany i kliknij „Zastosuj do wszystkich”.
                            </span>
                        </p>
                        <button type="submit" class="settlement-btn settlement-btn--primary">
                            Zatwierdź rozliczenie
                        </button>
                    </div>
                </aside>

                <div class="settlement-boards">
                    @foreach($shifts as $shiftType => $shift)
                        <section class="settlement-board settlement-shift-section" data-shift="{{ $shiftType }}" aria-labelledby="shift-title-{{ $shiftType }}">
                            <header class="settlement-board__head settlement-board__head--{{ $shiftType }}">
                                <span class="settlement-bar settlement-bar--{{ $shiftType }}" aria-hidden="true"></span>
                                <h2 id="shift-title-{{ $shiftType }}">{{ $shift['label'] }}</h2>
                                <div class="settlement-board__meta">
                                    <span class="settlement-mono settlement-mono--sm">Start</span>
                                    <span class="settlement-board__value">{{ $shift['startTime'] }}</span>
                                </div>
                                <div class="settlement-board__meta">
                                    <span class="settlement-mono settlement-mono--sm">Obsada</span>
                                    <span class="settlement-board__value shift-worker-count">
                                        <strong>{{ count($shift['workers']) }}</strong>
                                        {{ count($shift['workers']) === 1 ? 'pracownik' : 'pracowników' }}
                                    </span>
                                </div>
                            </header>

                            <div class="settlement-block settlement-block--packages">
                                <div class="settlement-block__head">
                                    <span class="settlement-mono settlement-mono--sm">Paczki na zmianie</span>
                                    <span class="package-position-count">
                                        {{ max(count($shift['packageEntries']), 1) }}
                                        {{ count($shift['packageEntries']) === 1 ? 'pozycja' : 'pozycje' }}
                                    </span>
                                    <span class="settlement-block__totals">
                                        <strong class="shift-package-count">0</strong> szt.
                                        <span aria-hidden="true">·</span>
                                        <strong class="shift-package-value">0 zł</strong>
                                    </span>
                                </div>

                                <div class="settlement-grid settlement-grid--package settlement-grid--head" aria-hidden="true">
                                    <span>Liczba paczek</span>
                                    <span>Stawka za paczkę</span>
                                    <span>Wartość</span>
                                    <span></span>
                                </div>

                                <div class="package-entries-list" data-shift="{{ $shiftType }}">
                                    @forelse($shift['packageEntries'] as $index => $entry)
                                        @include('admin.planner.day.end-day.partials.package-row', [
                                            'entry' => $entry,
                                            'index' => $index,
                                            'shiftType' => $shiftType,
                                            'packages' => $packages,
                                        ])
                                    @empty
                                        @include('admin.planner.day.end-day.partials.package-row', [
                                            'entry' => ['count' => '', 'packageId' => null],
                                            'index' => 0,
                                            'shiftType' => $shiftType,
                                            'packages' => $packages,
                                        ])
                                    @endforelse
                                </div>

                                <button type="button" class="settlement-btn settlement-btn--ghost btn-add-entry" data-shift="{{ $shiftType }}">
                                    <svg aria-hidden="true" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    Dodaj pozycję ze stawką
                                </button>
                            </div>

                            <div class="settlement-block settlement-block--defaults">
                                <span class="settlement-mono settlement-mono--sm">Domyślne</span>

                                <div class="settlement-field">
                                    <label for="default-{{ $shiftType }}-from">Od</label>
                                    <input type="time" id="default-{{ $shiftType }}-from" class="settlement-input settlement-time-input default-from-time" value="{{ $shift['startTime'] }}">
                                </div>

                                <div class="settlement-field">
                                    <label for="default-{{ $shiftType }}-to">Do</label>
                                    <input type="time" id="default-{{ $shiftType }}-to" class="settlement-input settlement-time-input default-to-time" value="{{ $shift['defaultEndTime'] }}">
                                </div>

                                <div class="settlement-field settlement-field--rate">
                                    <label for="default-{{ $shiftType }}-rate">Stawka</label>
                                    <select id="default-{{ $shiftType }}-rate" class="settlement-input settlement-select default-rate">
                                        <option value="">— wybierz —</option>
                                        @foreach($packages as $package)
                                            <option value="{{ $package['id'] }}" @selected($package['isDefault'])>
                                                {{ $package['name'] }} · {{ number_format($package['price'], 2, ',', ' ') }} zł
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <button type="button" class="settlement-btn settlement-btn--secondary btn-apply-defaults" data-shift="{{ $shiftType }}">
                                    Zastosuj do wszystkich (<span data-role="apply-count">{{ count($shift['workers']) }}</span>)
                                </button>
                            </div>

                            <div class="settlement-block settlement-block--workers">
                                <div class="settlement-grid settlement-grid--worker settlement-grid--head" aria-hidden="true">
                                    <span>Pracownik</span>
                                    <span>Od</span>
                                    <span>Do</span>
                                    <span>Godziny</span>
                                    <span>Stawka</span>
                                </div>

                                <div class="settlement-workers" data-shift="{{ $shiftType }}">
                                    @forelse($shift['workers'] as $worker)
                                        @include('admin.planner.day.end-day.partials.worker-row', [
                                            'worker' => $worker,
                                            'shiftType' => $shiftType,
                                            'packages' => $packages,
                                        ])
                                    @empty
                                        <div class="settlement-empty-workers">[ brak pracowników na tej zmianie ]</div>
                                    @endforelse
                                </div>
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>
        </form>
    </main>
</div>

<div class="settlement-modal-overlay" id="substituteModal" hidden>
    <div class="settlement-modal" role="dialog" aria-modal="true" aria-labelledby="substituteModalTitle">
        <div class="settlement-modal__header">
            <div>
                <span class="settlement-mono settlement-mono--sm">Zastępstwo</span>
                <h2 id="substituteModalTitle">Wybierz pracownika</h2>
            </div>
            <button type="button" class="settlement-modal__close" id="closeSubstituteModal" aria-label="Zamknij">
                <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="settlement-modal__body">
            <div class="settlement-modal__note" id="substituteModalLoading">Ładowanie…</div>
            <div class="settlement-modal__list" id="substituteModalList"></div>
            <div class="settlement-modal__note" id="substituteModalEmpty" hidden>
                Brak dostępnych pracowników do zastępstwa
            </div>
        </div>
    </div>
</div>

<template id="substituteWorkerTemplate">
    <div class="settlement-worker-card settlement-grid settlement-grid--worker substitute-card" data-worker-entry="false">
        <input type="hidden" data-field="id">
        <input type="hidden" data-field="shift_type">
        <input type="hidden" data-field="status" value="worked" class="worker-status-input">
        <input type="hidden" data-field="is_substitute" value="1">
        <input type="hidden" data-field="substituted_for_shift_id">

        <div class="worker-identity">
            <span class="worker-avatar" data-role="initials" aria-hidden="true"></span>
            <span class="worker-name" data-role="name"></span>
            <span class="worker-badge worker-badge--substitute">
                zastępstwo <span data-role="substitute-label"></span>
            </span>
            <span class="worker-actions">
                <button type="button" class="worker-action btn-remove-substitute" title="Usuń zastępstwo" aria-label="Usuń zastępstwo">
                    <svg aria-hidden="true" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </span>
        </div>

        <input type="time" class="settlement-input settlement-time-input worker-from-time" aria-label="Godzina rozpoczęcia">
        <input type="time" class="settlement-input settlement-time-input worker-to-time" aria-label="Godzina zakończenia">
        <span class="calculated-hours" data-minutes="">—</span>
        <select class="settlement-input settlement-select worker-rate" aria-label="Stawka pracownika">
            <option value="">— wybierz stawkę —</option>
            @foreach($packages as $package)
                <option value="{{ $package['id'] }}">
                    {{ $package['name'] }} · {{ number_format($package['price'], 2, ',', ' ') }} zł
                </option>
            @endforeach
        </select>
    </div>
</template>
@endsection

@push('scripts')
    <script>
        window.settlementConfig = @json($settlementConfig);
    </script>
    @vite(['resources/js/settlement.js'])
@endpush
