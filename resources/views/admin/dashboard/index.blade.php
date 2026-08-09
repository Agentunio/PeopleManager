@extends('layouts.app')

@section('title', 'Pulpit administratora')
@section('body_class', 'admin-dashboard-page')

@push('styles')
    @vite(['resources/css/dashboard.css'])
@endpush

@section('content')
    <div class="admin-panel">
        @include('partials.menu')

        <main class="main-content dashboard-main">
            <header class="dashboard-heading">
                <h1>Pulpit</h1>
                <p class="dashboard-heading-summary" data-dashboard-heading aria-live="polite"></p>
            </header>

            <div class="dashboard-content" id="dashboardContent" data-data-url="{{ route('dashboard.data') }}" data-settlement-url-template="{{ route('planner.day.end-day', ':date') }}">
                <div class="dashboard-loading" id="dashboardLoading" hidden aria-live="polite">
                    <span class="dashboard-spinner" aria-hidden="true"></span>
                    <span>Ładowanie danych…</span>
                </div>

                <div class="dashboard-layout">
                    <section class="dashboard-card dashboard-filter-card" aria-labelledby="dashboardFilterLabel">
                        <button type="button" class="filter-summary-toggle" data-calendar-toggle aria-controls="adminDashboardCalendar" aria-expanded="true">
                            <span class="filter-summary-copy">
                                <span class="dashboard-mono-label" id="dashboardFilterLabel">[ filtr pulpitu ]</span>
                                <span class="filter-range" data-range-display></span>
                            </span>
                            <svg class="filter-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9" /></svg>
                        </button>

                        <span class="filter-range-meta">
                            <span data-range-days></span>
                            <span aria-hidden="true">·</span>
                            <button type="button" class="comparison-trigger" data-comparison-trigger>por. z poprz. okresem</button>
                        </span>

                        <fieldset class="dashboard-filter-group">
                            <legend class="dashboard-mono-label">zmiana</legend>
                            <div class="shift-segmented" id="shiftToggle">
                                <button type="button" class="shift-toggle-btn is-active" data-shift="total" aria-pressed="true">Wszystkie</button>
                                <button type="button" class="shift-toggle-btn" data-shift="morning" aria-pressed="false">Rano</button>
                                <button type="button" class="shift-toggle-btn" data-shift="afternoon" aria-pressed="false">Popołudnie</button>
                            </div>
                        </fieldset>

                        <div class="dashboard-filter-group">
                            <span class="dashboard-mono-label">zakres</span>
                            <div class="dashboard-presets" aria-label="Szybki wybór zakresu">
                                <button type="button" class="dashboard-preset" data-range-preset="today" aria-pressed="false">Dziś</button>
                                <button type="button" class="dashboard-preset" data-range-preset="week" aria-pressed="false">Tydz.</button>
                                <button type="button" class="dashboard-preset" data-range-preset="half" aria-pressed="false">Pół mies.</button>
                                <button type="button" class="dashboard-preset" data-range-preset="month" aria-pressed="false">Cały mies.</button>
                            </div>
                        </div>

                        <div class="range-calendar admin-range-calendar" id="adminDashboardCalendar" data-range-calendar aria-label="Kalendarz zakresu danych pulpitu"></div>
                    </section>

                    <div class="dashboard-summary-column">
                        <section class="dashboard-kpi-grid" aria-label="Podsumowanie finansowe">
                            @foreach([
                                ['key' => 'Revenue', 'label' => 'przychód', 'value' => $totalRevenue, 'accent' => false],
                                ['key' => 'Cost', 'label' => 'koszt', 'value' => $totalCost, 'accent' => false],
                                ['key' => 'Profit', 'label' => 'zysk', 'value' => $totalProfit, 'accent' => true],
                            ] as $metric)
                                <article class="dashboard-card dashboard-kpi-card">
                                    <div class="dashboard-kpi-head">
                                        <span @class(['dashboard-mono-label', 'is-accent' => $metric['accent']])>[ {{ $metric['label'] }} ]</span>
                                        <span class="dashboard-delta" id="indicator{{ $metric['key'] }}"></span>
                                    </div>
                                    <p class="dashboard-kpi-value"><span id="stat{{ $metric['key'] }}">{{ number_format($metric['value'], 2, ',', ' ') }}</span><span class="dashboard-kpi-unit">zł</span></p>
                                </article>
                            @endforeach
                        </section>

                        <section class="dashboard-card workers-card" aria-labelledby="workersTitle">
                            <div class="dashboard-card-heading workers-heading">
                                <div>
                                    <h2 class="dashboard-mono-label" id="workersTitle">[ koszt pracowników ]</h2>
                                    <p class="workers-count" id="workersCount">{{ count($workers) }} osób na zmianach</p>
                                </div>
                                <button type="button" class="dashboard-export" data-export-url="{{ route('dashboard.export.costs') }}">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6" /><path d="M9 13h6M9 17h4" /></svg>
                                    PDF
                                </button>
                            </div>
                            <div class="workers-table-wrap">
                                <table class="workers-table">
                                    <thead><tr><th scope="col">Pracownik</th><th scope="col">Godziny</th><th scope="col">Nieob.</th><th scope="col">Koszt</th></tr></thead>
                                    <tbody id="workersTableBody"></tbody>
                                </table>
                            </div>
                            <nav
                                class="dashboard-worker-pagination"
                                id="workersPagination"
                                aria-label="Paginacja koszt&oacute;w pracownik&oacute;w"
                                hidden
                            ></nav>
                        </section>
                    </div>
                </div>

                <section class="dashboard-card packages-card" aria-labelledby="packagesTitle">
                    <div class="dashboard-card-heading">
                        <div>
                            <h2 class="dashboard-mono-label" id="packagesTitle">[ paczki ]</h2>
                            <p class="packages-total"><span id="totalPackages">{{ number_format($packageStats['total']['packages'], 0, ',', ' ') }}</span><span>szt.</span></p>
                        </div>
                        <button type="button" class="dashboard-export" data-export-url="{{ route('dashboard.export.packages') }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6" /><path d="M9 13h6M9 17h4" /></svg>
                            PDF
                        </button>
                    </div>
                    <div class="packages-table-wrap">
                        <table class="packages-table">
                            <thead><tr><th scope="col">Stawka</th><th scope="col">Ilość paczek</th></tr></thead>
                            <tbody id="packagesTableBody"></tbody>
                            <tfoot><tr><th scope="row">Razem</th><td><span id="packagesTableTotal">{{ number_format($packageStats['total']['packages'], 0, ',', ' ') }}</span><span class="table-unit">szt.</span></td></tr></tfoot>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>window.dashboardData = @json($dashboardData);</script>
@endsection

@push('scripts')
    @vite(['resources/js/dashboard.js'])
@endpush
