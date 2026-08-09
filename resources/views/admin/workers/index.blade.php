@extends('layouts.app')

@section('title', 'Pracownicy - Panel administratora')
@section('body_class', 'workers-page')

@push('styles')
    @vite(['resources/css/workers.css'])
@endpush

@section('mobile_header_action')
    <button type="button" class="workers-mobile-add js-open-worker-drawer" aria-label="Dodaj pracownika">
        <span class="workers-add-icon" aria-hidden="true">+</span>
        <span>Dodaj</span>
    </button>
@endsection

@section('content')
<div
    data-active-tab='{{ $activeTab }}'
    class="admin-panel workers-admin-panel"
    id="workersApp"
    data-index-url="{{ route('workers.index') }}"
    data-store-url="{{ route('workers.store') }}"
    data-settlements-url="{{ route('workers.settlements') }}"
>
    @include('partials.menu')

    <main class="main-content workers-content">
        <header class="workers-heading">
            <div>
                <h1>Pracownicy</h1>
            </div>
            <button type="button" class="workers-primary-button workers-desktop-add js-open-worker-drawer">
                <span class="workers-add-icon" aria-hidden="true">+</span>
                <span>Dodaj pracownika</span>
            </button>
        </header>

        <nav class="workers-tabs" role="tablist" aria-label="Sekcje strony pracowników">
            <button type="button" class="workers-tab is-active" id="workersListTab" role="tab" data-workers-tab="list" aria-controls="workersListPanel" aria-selected="true">
                Lista pracowników
            </button>
            <button type="button" class="workers-tab" id="workersSettlementsTab" role="tab" data-workers-tab="settlements" aria-controls="workersSettlementsPanel" aria-selected="false" tabindex="-1">
                Rozliczenia
            </button>
        </nav>

        <section class="workers-tab-panel" id="workersListPanel" role="tabpanel" aria-labelledby="workersListTab" data-workers-panel="list">
            <form id="searchForm" class="workers-toolbar" action="{{ route('workers.index') }}" method="get">
                <label class="workers-search">
                    <span class="sr-only">Szukaj pracownika</span>
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m20 20-3.5-3.5"></path>
                    </svg>
                    <input
                        type="search"
                        id="searchWorker"
                        name="searchWorker"
                        placeholder="Szukaj po imieniu lub nazwisku…"
                        value="{{ request('searchWorker') }}"
                        autocomplete="off"
                    >
                </label>

                <div class="workers-filter-block">
                    <span class="workers-filter-label">Status zatrudnienia</span>
                    <div class="workers-filter-group" role="group" aria-label="Status zatrudnienia">
                        @foreach(['' => 'Wszyscy', '1' => 'Zatrudnieni', '0' => 'Niezatrudnieni'] as $value => $label)
                            <button
                                type="button"
                                class="workers-filter-chip {{ (string) request('filterEmployment', '') === (string) $value ? 'is-active' : '' }}"
                                data-filter-name="filterEmployment"
                                data-filter-value="{{ $value }}"
                                aria-pressed="{{ (string) request('filterEmployment', '') === (string) $value ? 'true' : 'false' }}"
                            >{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="workers-filter-block">
                    <span class="workers-filter-label">Status ucznia</span>
                    <div class="workers-filter-group" role="group" aria-label="Status ucznia">
                        @foreach(['' => 'Wszyscy', '1' => 'Uczeń', '0' => 'Nie jest uczniem'] as $value => $label)
                            <button
                                type="button"
                                class="workers-filter-chip {{ (string) request('filterStudent', '') === (string) $value ? 'is-active' : '' }}"
                                data-filter-name="filterStudent"
                                data-filter-value="{{ $value }}"
                                aria-pressed="{{ (string) request('filterStudent', '') === (string) $value ? 'true' : 'false' }}"
                            >{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <input type="hidden" id="filterEmployment" name="filterEmployment" value="{{ request('filterEmployment') }}">
                <input type="hidden" id="filterStudent" name="filterStudent" value="{{ request('filterStudent') }}">

                <div class="workers-result-count" aria-live="polite">
                    wynik: <strong id="filteredWorkersCount">{{ $filteredTotal }}</strong> / <span id="totalWorkersCount">{{ $totalWorkers }}</span>
                </div>
            </form>

            <div id="workers-list">
                @include('admin.workers.partials.list', ['workers' => $workers])
            </div>

            <div id="pagination-links">
                {{ $workers->links() }}
            </div>
        </section>

        <section class="workers-tab-panel" id="workersSettlementsPanel" role="tabpanel" aria-labelledby="workersSettlementsTab" data-workers-panel="settlements" hidden>
            <div class="settlements-empty" id="settlementsEmpty" hidden>
                <span class="workers-mono-label">[ brak pracowników ]</span>
                <p>Dodaj pracownika, aby wyświetlić rozliczenia.</p>
            </div>

            <div class="settlements-layout" id="settlementsLayout">
                <button type="button" class="settlement-mobile-selector" id="openSettlementWorkerPicker">
                    <span>
                        <small>pracownik</small>
                        <strong id="settlementMobileWorkerName">Wybierz pracownika</strong>
                    </span>
                    <span aria-hidden="true">⌄</span>
                </button>

                <aside class="settlement-worker-picker" id="settlementWorkerPicker" aria-label="Wybór pracownika">
                    <div class="settlement-picker-heading">
                        <span class="workers-mono-label">pracownicy</span>
                        <button type="button" class="settlement-picker-close" id="closeSettlementWorkerPicker" aria-label="Zamknij wybór">Zamknij</button>
                    </div>
                    <label class="settlement-worker-search">
                        <span class="sr-only">Szukaj w rozliczeniach</span>
                        <input type="search" id="settlementWorkerSearch" placeholder="Szukaj pracownika…" autocomplete="off">
                    </label>
                    <div class="settlement-worker-list" id="settlementWorkerList"></div>
                    <nav
                        class="settlement-pagination"
                        id="settlementPagination"
                        aria-label="Paginacja pracowników w rozliczeniach"
                        hidden
                    ></nav>
                </aside>

                <div class="settlement-detail">
                    <h2 id="settlementWorkerName">Wybierz pracownika</h2>

                    <div class="range-filter settlement-range-filter">
                        <div class="range-filter-head">
                            <div class="range-filter-title">
                                <span class="workers-mono-label">okres</span>
                                <span class="range-filter-dates" id="settlementRangeLabel" aria-live="polite"></span>
                            </div>

                            <div class="range-chips" aria-label="Szybki wybór okresu">
                                <button type="button" class="range-chip" data-settlement-preset="today" aria-pressed="false">Dzień</button>
                                <button type="button" class="range-chip" data-settlement-preset="week" aria-pressed="false">Bieżący tydzień</button>
                                <button type="button" class="range-chip is-selected" data-settlement-preset="month" aria-pressed="true">Od początku miesiąca</button>
                            </div>

                            <button type="button" class="range-toggle" data-range-toggle aria-controls="settlementRangeCalendar" aria-expanded="false">Pokaż kalendarz</button>
                        </div>

                        <div class="range-calendar" id="settlementRangeCalendar" aria-label="Kalendarz zakresu rozliczenia"></div>
                    </div>

                    <div class="settlement-loading" id="settlementLoading" hidden>Ładowanie rozliczenia…</div>

                    <div class="settlement-summary">
                        <article class="settlement-summary-card is-morning">
                            <span class="settlement-card-label"><i></i><span class="settlement-label-prefix">zmiana </span>rano</span>
                            <span class="settlement-card-value">
                                <strong id="settlementMorningHours">0h</strong>
                                <small>godz.</small>
                            </span>
                        </article>
                        <article class="settlement-summary-card is-afternoon">
                            <span class="settlement-card-label"><i></i><span class="settlement-label-prefix">zmiana </span>popołudnie</span>
                            <span class="settlement-card-value">
                                <strong id="settlementAfternoonHours">0h</strong>
                                <small>godz.</small>
                            </span>
                        </article>
                        <article class="settlement-summary-card is-salary">
                            <span class="settlement-card-label settlement-salary-label"><span>wynagrodzenie</span> <span>brutto</span></span>
                            <span class="settlement-card-value">
                                <strong id="settlementSalary">0,00</strong>
                                <small>zł</small>
                            </span>
                        </article>
                    </div>

                    <section class="settlement-daily-card">
                        <header>
                            <span class="workers-mono-label">dzienna rozpiska</span>
                            <div class="settlement-legend">
                                <span class="is-morning"><i></i> rano</span>
                                <span class="is-afternoon"><i></i> popołudnie</span>
                                <span class="is-absence"><i></i> nieobecność</span>
                            </div>
                        </header>
                        <div class="settlement-days" id="settlementDays"></div>
                    </section>
                </div>
            </div>
        </section>
    </main>

    <div class="workers-drawer-backdrop" id="workerDrawerBackdrop" hidden></div>
    <aside class="worker-drawer" id="workerDrawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="workerDrawerTitle" inert>
        <form id="workerForm" action="{{ route('workers.store') }}" method="post">
            @csrf
            <input type="hidden" name="_method" id="workerFormMethod" value="POST">

            <header class="worker-drawer-header">
                <div>
                    <h2 id="workerDrawerTitle">Dodaj pracownika</h2>
                    <p id="workerDrawerSubtitle">Wprowadź dane nowego pracownika</p>
                </div>
                <button type="button" class="workers-ghost-button js-close-worker-drawer">Zamknij</button>
            </header>

            <div class="worker-drawer-body">
                <section class="worker-form-section">
                    <span class="workers-mono-label">dane osobowe</span>
                    <div class="worker-form-grid">
                        <label class="worker-field">
                            <span>Imię <b>*</b></span>
                            <input type="text" name="first_name" id="workerFirstName" maxlength="255" required>
                        </label>
                        <label class="worker-field">
                            <span>Nazwisko <b>*</b></span>
                            <input type="text" name="last_name" id="workerLastName" maxlength="255" required>
                        </label>
                        <label class="worker-field">
                            <span>Telefon</span>
                            <input type="tel" name="phone" id="workerPhone">
                        </label>
                        <label class="worker-field">
                            <span>Data urodzenia</span>
                            <input type="date" name="date_of_birth" id="workerDob">
                        </label>
                        <label class="worker-field is-wide">
                            <span>Adres</span>
                            <input type="text" name="address" id="workerAddress">
                        </label>
                    </div>
                </section>

                <section class="worker-form-section">
                    <span class="workers-mono-label">status</span>
                    <div class="worker-form-grid">
                        <fieldset class="worker-toggle-field">
                            <legend>Status zatrudnienia</legend>
                            <div>
                                <label><input type="radio" name="is_employed" value="0"><span>Nie</span></label>
                                <label><input type="radio" name="is_employed" value="1" checked><span>Tak</span></label>
                            </div>
                        </fieldset>
                        <fieldset class="worker-toggle-field">
                            <legend>Status ucznia</legend>
                            <div>
                                <label><input type="radio" name="is_student" value="0" checked><span>Nie</span></label>
                                <label><input type="radio" name="is_student" value="1"><span>Tak</span></label>
                            </div>
                        </fieldset>
                    </div>
                </section>

                <section class="worker-form-section">
                    <span class="workers-mono-label">umowa</span>
                    <div class="worker-form-grid">
                        <label class="worker-field"><span>Umowa od</span><input type="date" name="contract_from" id="workerContractFrom"></label>
                        <label class="worker-field"><span>Umowa do</span><input type="date" name="contract_to" id="workerContractTo"></label>
                    </div>
                </section>

                <section class="worker-account-box" id="workerAccountBox" hidden>
                    <div>
                        <span class="workers-mono-label" id="workerAccountLabel">konto w systemie</span>
                        <strong id="workerAccountDescription"></strong>
                    </div>
                    <div class="worker-account-actions">
                        <button type="button" class="workers-ghost-button" id="workerAccountAction"></button>
                        <button type="button" class="workers-ghost-button" id="workerAccountToggle" hidden></button>
                    </div>
                </section>
            </div>

            <footer class="worker-drawer-footer">
                <button type="button" class="workers-danger-button" id="deleteWorkerButton" hidden>Usuń pracownika</button>
                <div>
                    <button type="button" class="workers-ghost-button js-close-worker-drawer">Anuluj</button>
                    <button type="submit" class="workers-primary-button" id="workerSubmitButton">Dodaj pracownika</button>
                </div>
            </footer>
        </form>
    </aside>

    <div class="workers-modal-layer" id="accountModal" hidden>
        <div class="workers-modal-backdrop" data-close-account-modal></div>
        <section class="workers-modal" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle">
            <header>
                <div>
                    <span class="workers-mono-label">[ konto pracownika ]</span>
                    <h2 id="accountModalTitle">Konto pracownika</h2>
                </div>
                <button type="button" class="workers-ghost-button" data-close-account-modal>Zamknij</button>
            </header>
            <div class="workers-modal-body">
                <p id="accountModalText"></p>
                <label class="worker-field">
                    <span>Adres e-mail <b>*</b></span>
                    <input type="email" id="accountEmail" required>
                </label>
                <div class="workers-account-warning" id="accountModalWarning" hidden></div>
            </div>
            <footer>
                <button type="button" class="workers-ghost-button" data-close-account-modal>Anuluj</button>
                <button type="button" class="workers-primary-button" id="accountModalSubmit">Wyślij link</button>
            </footer>
        </section>
    </div>
</div>
@endsection

@push('scripts')
    @vite(['resources/js/workers.js'])
@endpush
