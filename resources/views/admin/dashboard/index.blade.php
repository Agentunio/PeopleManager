@extends('layouts.app')

@section('title', 'Panel administratora')

@push('styles')
    @vite(['resources/css/dashboard.css'])
@endpush

@section('content')
    <div class="admin-panel">
        @include('partials.menu')

        <main class="main-content">
            <div class="dashboard-header">
                <div class="header-info">
                    <h1>Dashboard</h1>
                    <p>Podsumowanie finansowe i statystyki</p>
                </div>
                <div class="date-range-picker">
                    <div class="picker-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <input type="text" id="dateRangePicker" class="date-input" placeholder="Wybierz zakres dat" readonly>
                    <button type="button" class="btn-refresh" id="refreshData">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card stat-revenue">
                    <div class="stat-icon">
                        <i class="fas fa-arrow-trend-up"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">Przychód</span>
                        <span class="stat-value" id="revenueValue">48 250,00</span>
                        <span class="stat-currency">PLN</span>
                    </div>
                    <div class="stat-indicator positive">
                        <i class="fas fa-caret-up"></i>
                        <span>+12.5%</span>
                    </div>
                </div>

                <div class="stat-card stat-cost">
                    <div class="stat-icon">
                        <i class="fas fa-arrow-trend-down"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">Koszty</span>
                        <span class="stat-value" id="costValue">32 180,00</span>
                        <span class="stat-currency">PLN</span>
                    </div>
                    <div class="stat-indicator negative">
                        <i class="fas fa-caret-up"></i>
                        <span>+8.2%</span>
                    </div>
                </div>

                <div class="stat-card stat-profit">
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">Zysk</span>
                        <span class="stat-value" id="profitValue">16 070,00</span>
                        <span class="stat-currency">PLN</span>
                    </div>
                    <div class="stat-indicator positive">
                        <i class="fas fa-caret-up"></i>
                        <span>+18.3%</span>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-section packages-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <h2>Paczki</h2>
                    </div>
                    <div class="section-content">
                        <div class="packages-stats">
                            <div class="package-stat">
                                <div class="package-stat-header">
                                    <i class="fas fa-sun"></i>
                                    <span>Zmiana ranna</span>
                                </div>
                                <div class="package-stat-value">
                                    <span class="value" id="morningPackages">4 521</span>
                                    <span class="label">paczek</span>
                                </div>
                            </div>

                            <div class="package-stat">
                                <div class="package-stat-header">
                                    <i class="fas fa-cloud-sun"></i>
                                    <span>Zmiana popołudniowa</span>
                                </div>
                                <div class="package-stat-value">
                                    <span class="value" id="afternoonPackages">3 892</span>
                                    <span class="label">paczek</span>
                                </div>
                            </div>
                        </div>

                        <div class="packages-summary">
                            <div class="summary-row highlight">
                                <span class="summary-label">Łącznie paczek:</span>
                                <span class="summary-value" id="totalPackages">8 413</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-section workers-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h2>Koszty pracowników</h2>
                        <span class="workers-count">12 pracowników</span>
                    </div>
                    <div class="section-content">
                        <div class="workers-list-container">
                            <table class="workers-table">
                                <thead>
                                <tr>
                                    <th>Pracownik</th>
                                    <th>Godziny</th>
                                    <th>Koszt</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td class="worker-name">
                                        <span class="worker-avatar">JK</span>
                                        Jan Kowalski
                                    </td>
                                    <td class="worker-hours">168h</td>
                                    <td class="worker-cost">4 200,00 PLN</td>
                                </tr>
                                <tr>
                                    <td class="worker-name">
                                        <span class="worker-avatar">AN</span>
                                        Anna Nowak
                                    </td>
                                    <td class="worker-hours">152h</td>
                                    <td class="worker-cost">3 800,00 PLN</td>
                                </tr>
                                <tr>
                                    <td class="worker-name">
                                        <span class="worker-avatar">PW</span>
                                        Piotr Wiśniewski
                                    </td>
                                    <td class="worker-hours">160h</td>
                                    <td class="worker-cost">4 000,00 PLN</td>
                                </tr>
                                <tr>
                                    <td class="worker-name">
                                        <span class="worker-avatar">MK</span>
                                        Maria Kamińska
                                    </td>
                                    <td class="worker-hours">144h</td>
                                    <td class="worker-cost">3 600,00 PLN</td>
                                </tr>
                                <tr>
                                    <td class="worker-name">
                                        <span class="worker-avatar">TZ</span>
                                        Tomasz Zieliński
                                    </td>
                                    <td class="worker-hours">176h</td>
                                    <td class="worker-cost">4 400,00 PLN</td>
                                </tr>
                                <tr>
                                    <td class="worker-name">
                                        <span class="worker-avatar">KL</span>
                                        Katarzyna Lewandowska
                                    </td>
                                    <td class="worker-hours">136h</td>
                                    <td class="worker-cost">3 400,00 PLN</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="workers-summary">
                            <div class="summary-row highlight">
                                <span class="summary-label">Łączny koszt:</span>
                                <span class="summary-value" id="totalWorkersCost">23 400,00 PLN</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/dashboard.js'])
@endpush
