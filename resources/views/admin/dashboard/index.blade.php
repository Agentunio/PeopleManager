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
                        <span class="stat-value">{{ number_format($totalRevenue, 2, ',', ' ') }}</span>
                        <span class="stat-currency">PLN</span>
                    </div>
                    @if($changes['revenue'])
                        <div class="stat-indicator {{ $changes['revenue']['isPositive'] ? 'positive' : 'negative' }}">
                            <i class="fas fa-caret-{{ $changes['revenue']['isPositive'] ? 'up' : 'down' }}"></i>
                            <span>{{ $changes['revenue']['isPositive'] ? '+' : '-' }}{{ $changes['revenue']['percent'] }}%</span>
                        </div>
                    @endif
                </div>

                <div class="stat-card stat-cost">
                    <div class="stat-icon">
                        <i class="fas fa-arrow-trend-down"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">Koszty</span>
                        <span class="stat-value">{{ number_format($totalCost, 2, ',', ' ') }}</span>
                        <span class="stat-currency">PLN</span>
                    </div>
                    @if($changes['cost'])
                        <div class="stat-indicator {{ $changes['cost']['isPositive'] ? 'negative' : 'positive' }}">
                            <i class="fas fa-caret-{{ $changes['cost']['isPositive'] ? 'up' : 'down' }}"></i>
                            <span>{{ $changes['cost']['isPositive'] ? '+' : '-' }}{{ $changes['cost']['percent'] }}%</span>
                        </div>
                    @endif
                </div>

                <div class="stat-card stat-profit">
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">Zysk</span>
                        <span class="stat-value">{{ number_format($totalProfit, 2, ',', ' ') }}</span>
                        <span class="stat-currency">PLN</span>
                    </div>
                    @if($changes['profit'])
                        <div class="stat-indicator {{ $changes['profit']['isPositive'] ? 'positive' : 'negative' }}">
                            <i class="fas fa-caret-{{ $changes['profit']['isPositive'] ? 'up' : 'down' }}"></i>
                            <span>{{ $changes['profit']['isPositive'] ? '+' : '-' }}{{ $changes['profit']['percent'] }}%</span>
                        </div>
                    @endif
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
                                    <span class="value" id="morningPackages">{{ number_format($packageStats['morning']['packages'], 0, ',', ' ') }}</span>
                                    <span class="label">paczek</span>
                                </div>
                            </div>

                            <div class="package-stat">
                                <div class="package-stat-header">
                                    <i class="fas fa-cloud-sun"></i>
                                    <span>Zmiana popołudniowa</span>
                                </div>
                                <div class="package-stat-value">
                                    <span class="value" id="afternoonPackages">{{ number_format($packageStats['afternoon']['packages'], 0, ',', ' ') }}</span>
                                    <span class="label">paczek</span>
                                </div>
                            </div>
                        </div>

                        <div class="packages-summary">
                            <div class="summary-row highlight">
                                <span class="summary-label">Łącznie paczek:</span>
                                <span class="summary-value" id="totalPackages">{{ number_format($packageStats['total']['packages'], 0, ',', ' ') }}</span>
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
                        <span class="workers-count">{{ count($workers) }} pracowników</span>
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
                                @forelse($workers as $worker)
                                    <tr>
                                        <td class="worker-name">
                                            {{ $worker->first_name }} {{ $worker->last_name  }}
                                        </td>
                                        <td class="worker-hours">{{ $worker->stats['hours'] }}</td>
                                        <td class="worker-cost">{{ number_format($worker->stats['salary'] ?? 0, 2, ',', '') }} zł</td>
                                    </tr>
                                @empty
                                    <p>Brak pracowników</p>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="workers-summary">
                            <div class="summary-row highlight">
                                <span class="summary-label">Łączny koszt:</span>
                                <span class="summary-value" id="totalWorkersCost">{{ number_format($totalCost ?? 0, 2, ',', '') }} zł</span>
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
