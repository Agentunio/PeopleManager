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
                    <button type="button" class="btn-clear" id="clearDateRange" title="Resetuj do domyślnego zakresu">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="comparison-tip" id="comparisonTip">
                        <button type="button" class="btn-tip" aria-label="Informacja o porównywaniu">
                            <i class="fas fa-info-circle"></i>
                        </button>
                        <div class="tip-popover">
                            <span id="comparisonTipText"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="comparison-badge" id="comparisonBadge" style="display: none;">
                <span>Porównanie okresu:</span>
                <span class="comparison-badge-dates" id="comparisonBadgeDates"></span>
                <button type="button" class="comparison-dismiss" id="comparisonDismiss" title="Usuń porównanie">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div id="dashboardContent">
                <div class="dashboard-loading" id="dashboardLoading" style="display: none;">
                    <div class="loading-spinner">
                        <i class="fas fa-circle-notch fa-spin"></i>
                        <span>Ładowanie danych...</span>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card stat-revenue">
                        <div class="stat-icon">
                            <i class="fas fa-arrow-trend-up"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Przychód</span>
                            <span class="stat-value" id="statRevenue">{{ number_format($totalRevenue, 2, ',', ' ') }}</span>
                            <span class="stat-currency">PLN</span>
                        </div>
                        <div class="stat-indicator-wrapper" id="indicatorRevenue">
                            @if($changes['revenue'])
                                <div class="stat-indicator {{ $changes['revenue']['isPositive'] ? 'positive' : 'negative' }}">
                                    <i class="fas fa-caret-{{ $changes['revenue']['isPositive'] ? 'up' : 'down' }}"></i>
                                    <span>{{ $changes['revenue']['isPositive'] ? '+' : '-' }}{{ $changes['revenue']['percent'] }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="stat-card stat-cost">
                        <div class="stat-icon">
                            <i class="fas fa-arrow-trend-down"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Koszty</span>
                            <span class="stat-value" id="statCost">{{ number_format($totalCost, 2, ',', ' ') }}</span>
                            <span class="stat-currency">PLN</span>
                        </div>
                        <div class="stat-indicator-wrapper" id="indicatorCost">
                            @if($changes['cost'])
                                <div class="stat-indicator {{ $changes['cost']['isPositive'] ? 'negative' : 'positive' }}">
                                    <i class="fas fa-caret-{{ $changes['cost']['isPositive'] ? 'up' : 'down' }}"></i>
                                    <span>{{ $changes['cost']['isPositive'] ? '+' : '-' }}{{ $changes['cost']['percent'] }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="stat-card stat-profit">
                        <div class="stat-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Zysk</span>
                            <span class="stat-value" id="statProfit">{{ number_format($totalProfit, 2, ',', ' ') }}</span>
                            <span class="stat-currency">PLN</span>
                        </div>
                        <div class="stat-indicator-wrapper" id="indicatorProfit">
                            @if($changes['profit'])
                                <div class="stat-indicator {{ $changes['profit']['isPositive'] ? 'positive' : 'negative' }}">
                                    <i class="fas fa-caret-{{ $changes['profit']['isPositive'] ? 'up' : 'down' }}"></i>
                                    <span>{{ $changes['profit']['isPositive'] ? '+' : '-' }}{{ $changes['profit']['percent'] }}%</span>
                                </div>
                            @endif
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
                                <div class="package-stat" id="morningPackageStat">
                                    <div class="package-stat-header">
                                        <i class="fas fa-sun"></i>
                                        <span>Zmiana ranna</span>
                                    </div>
                                    <div class="package-breakdown" id="morningBreakdown">
                                        @forelse($packageStats['morning']['breakdown'] as $item)
                                            <div class="breakdown-row">
                                                <span class="breakdown-name">{{ $item['name'] }}</span>
                                                <span class="breakdown-value">{{ number_format($item['packages'], 0, ',', ' ') }}</span>
                                            </div>
                                        @empty
                                            <div class="breakdown-empty">Brak danych</div>
                                        @endforelse
                                    </div>
                                    <div class="package-stat-total">
                                        <span class="total-label">Łącznie:</span>
                                        <span class="total-value" id="morningPackages">{{ number_format($packageStats['morning']['packages'], 0, ',', ' ') }}</span>
                                    </div>
                                </div>

                                <div class="package-stat" id="afternoonPackageStat">
                                    <div class="package-stat-header">
                                        <i class="fas fa-cloud-sun"></i>
                                        <span>Zmiana popołudniowa</span>
                                    </div>
                                    <div class="package-breakdown" id="afternoonBreakdown">
                                        @forelse($packageStats['afternoon']['breakdown'] as $item)
                                            <div class="breakdown-row">
                                                <span class="breakdown-name">{{ $item['name'] }}</span>
                                                <span class="breakdown-value">{{ number_format($item['packages'], 0, ',', ' ') }}</span>
                                            </div>
                                        @empty
                                            <div class="breakdown-empty">Brak danych</div>
                                        @endforelse
                                    </div>
                                    <div class="package-stat-total">
                                        <span class="total-label">Łącznie:</span>
                                        <span class="total-value" id="afternoonPackages">{{ number_format($packageStats['afternoon']['packages'], 0, ',', ' ') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="packages-summary">
                                <div class="summary-row highlight">
                                    <span class="summary-label">Suma wszystkich paczek:</span>
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
                            <span class="workers-count" id="workersCount">{{ count($workers) }} pracowników</span>
                            <button type="button" class="btn-export" id="exportWorkerCosts" title="Eksportuj do PDF">
                                <i class="fas fa-file-pdf"></i>
                            </button>
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
                                    <tbody id="workersTableBody">
                                    @forelse($workers as $worker)
                                        <tr>
                                            <td class="worker-name">
                                                {{ $worker->first_name }} {{ $worker->last_name  }}
                                            </td>
                                            <td class="worker-hours">{{ $worker->stats['totalMinutes'] > 0 ? $worker->stats['hours'] : 'Brak danych' }}</td>
                                            <td class="worker-cost">{{ $worker->stats['salary'] > 0 ? number_format($worker->stats['salary'], 2, ',', '') . ' zł' : 'Brak danych' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" style="text-align: center; color: #888; padding: 20px;">Brak pracowników</td></tr>
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
            </div>
        </main>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/dashboard.js'])
@endpush
