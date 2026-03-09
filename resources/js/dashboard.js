document.addEventListener('DOMContentLoaded', function() {
    const dataUrl = '/panel/data';
    const exportUrl = '/panel/eksport-kosztow';
    const LONG_PRESS_MS = 500;

    const comparison = {
        startDate: null,
        endDate: null,
        isActive: false,
        isSelecting: false,
    };

    let longPressTimer = null;
    let longPressTriggered = false;
    let primarySelected = false;

    const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

    const dateRangePicker = flatpickr("#dateRangePicker", {
        mode: "range",
        dateFormat: "d.m.Y",
        locale: "pl",
        defaultDate: [
            new Date(new Date().getFullYear(), new Date().getMonth(), 1),
            new Date()
        ],
        onChange: function(selectedDates) {
            if (selectedDates.length === 2) {
                primarySelected = true;
                clearComparison(false);
                fetchDashboardData(selectedDates[0], selectedDates[1]);
            }
        },
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            paintComparisonDay(dayElem);
            if (primarySelected) {
                attachComparisonEvents(dayElem);
            }
        }
    });

    const dismissBtn = document.getElementById('comparisonDismiss');
    if (dismissBtn) {
        dismissBtn.addEventListener('click', function() {
            clearComparison(true);
        });
    }

    const clearBtn = document.getElementById('clearDateRange');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            resetToDefault();
        });
    }

    const exportBtn = document.getElementById('exportWorkerCosts');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            exportWorkerCostsPdf();
        });
    }

    initComparisonTip();


    function paintComparisonDay(dayElem) {
        dayElem.classList.remove('comparison-start', 'comparison-end', 'comparison-inRange');

        if (!comparison.startDate) return;

        const dayTime = dayElem.dateObj.getTime();
        const startTime = comparison.startDate.getTime();

        if (!comparison.endDate) {
            if (isSameDay(dayElem.dateObj, comparison.startDate)) {
                dayElem.classList.add('comparison-start', 'comparison-end');
            }
            return;
        }

        const endTime = comparison.endDate.getTime();

        if (isSameDay(dayElem.dateObj, comparison.startDate)) {
            dayElem.classList.add('comparison-start');
        } else if (isSameDay(dayElem.dateObj, comparison.endDate)) {
            dayElem.classList.add('comparison-end');
        } else if (dayTime > startTime && dayTime < endTime) {
            dayElem.classList.add('comparison-inRange');
        }
    }

    function attachComparisonEvents(dayElem) {
        dayElem.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            handleComparisonClick(dayElem.dateObj);
        });

        if (isTouchDevice) {
            dayElem.addEventListener('touchstart', function(e) {
                longPressTriggered = false;
                longPressTimer = setTimeout(function() {
                    longPressTriggered = true;
                    handleComparisonClick(dayElem.dateObj);
                    if (navigator.vibrate) navigator.vibrate(50);
                }, LONG_PRESS_MS);
            }, { passive: true });

            dayElem.addEventListener('touchend', function(e) {
                clearTimeout(longPressTimer);
                if (longPressTriggered) {
                    e.preventDefault();
                    longPressTriggered = false;
                }
            });

            dayElem.addEventListener('touchmove', function() {
                clearTimeout(longPressTimer);
                longPressTriggered = false;
            }, { passive: true });
        }
    }

    function handleComparisonClick(date) {
        if (!primarySelected) return;

        if (comparison.isActive) {
            clearComparison(true);
            return;
        }

        if (!comparison.isSelecting) {
            comparison.startDate = new Date(date);
            comparison.isSelecting = true;
            dateRangePicker.redraw();
            return;
        }

        comparison.endDate = new Date(date);

        if (comparison.endDate < comparison.startDate) {
            const temp = comparison.startDate;
            comparison.startDate = comparison.endDate;
            comparison.endDate = temp;
        }

        comparison.isSelecting = false;
        comparison.isActive = true;

        dateRangePicker.redraw();
        showComparisonBadge();

        const primaryDates = dateRangePicker.selectedDates;
        if (primaryDates.length === 2) {
            fetchDashboardData(primaryDates[0], primaryDates[1]);
        }
    }

    function clearComparison(refetch) {
        comparison.startDate = null;
        comparison.endDate = null;
        comparison.isActive = false;
        comparison.isSelecting = false;

        dateRangePicker.redraw();
        hideComparisonBadge();
        removeComparisonUI();
        toggleExportButton(false);

        if (refetch) {
            const primaryDates = dateRangePicker.selectedDates;
            if (primaryDates.length === 2) {
                fetchDashboardData(primaryDates[0], primaryDates[1]);
            }
        }
    }

    function isSameDay(a, b) {
        return a.getFullYear() === b.getFullYear() &&
               a.getMonth() === b.getMonth() &&
               a.getDate() === b.getDate();
    }


    function resetToDefault() {
        primarySelected = false;
        clearComparison(false);

        const defaultStart = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
        const defaultEnd = new Date();

        dateRangePicker.setDate([defaultStart, defaultEnd], true);
        window.location.reload();
    }

    function exportWorkerCostsPdf() {
        const primaryDates = dateRangePicker.selectedDates;
        if (primaryDates.length !== 2) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = exportUrl;
        form.style.display = 'none';

        const csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf) {
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = csrf.content;
            form.appendChild(tokenInput);
        }

        const startInput = document.createElement('input');
        startInput.type = 'hidden';
        startInput.name = 'start_date';
        startInput.value = formatDate(primaryDates[0]);
        form.appendChild(startInput);

        const endInput = document.createElement('input');
        endInput.type = 'hidden';
        endInput.name = 'end_date';
        endInput.value = formatDate(primaryDates[1]);
        form.appendChild(endInput);

        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    function toggleExportButton(hasComparison) {
        const btn = document.getElementById('exportWorkerCosts');
        if (btn) btn.style.display = hasComparison ? 'none' : '';
    }

    function initComparisonTip() {
        const tipText = document.getElementById('comparisonTipText');
        if (!tipText) return;

        if (isTouchDevice) {
            tipText.textContent = 'Wybierz okres w kalendarzu, a następnie przytrzymaj dzień aby wybrać okres porównawczy';
        } else {
            tipText.textContent = 'Wybierz okres w kalendarzu, a następnie kliknij PPM na dzień aby wybrać okres porównawczy';
        }
    }

    function showComparisonBadge() {
        const badge = document.getElementById('comparisonBadge');
        const datesEl = document.getElementById('comparisonBadgeDates');

        if (badge && datesEl && comparison.startDate && comparison.endDate) {
            const primaryDates = dateRangePicker.selectedDates;
            const primaryLabel = primaryDates.length === 2
                ? formatDisplayDate(primaryDates[0]) + ' - ' + formatDisplayDate(primaryDates[1])
                : '...';
            const compLabel = formatDisplayDate(comparison.startDate) + ' - ' + formatDisplayDate(comparison.endDate);
            datesEl.textContent = primaryLabel + ' vs ' + compLabel;
            badge.style.display = 'flex';
        }
    }

    function hideComparisonBadge() {
        const badge = document.getElementById('comparisonBadge');
        if (badge) badge.style.display = 'none';
    }

    function formatDisplayDate(date) {
        const d = String(date.getDate()).padStart(2, '0');
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const y = date.getFullYear();
        return `${d}.${m}.${y}`;
    }


    function formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function formatNumber(value, decimals = 2) {
        return Number(value).toLocaleString('pl-PL', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function formatInteger(value) {
        return Number(value).toLocaleString('pl-PL', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    function setLoading(isLoading) {
        const loader = document.getElementById('dashboardLoading');
        const content = document.getElementById('dashboardContent');

        if (isLoading) {
            loader.style.display = 'flex';
            content.classList.add('is-loading');
        } else {
            loader.style.display = 'none';
            content.classList.remove('is-loading');
        }
    }

    function renderIndicator(change, type) {
        const wrapper = document.getElementById('indicator' + capitalize(type));
        if (!wrapper) return;

        if (!change) {
            wrapper.innerHTML = '';
            return;
        }

        const isPositive = change.isPositive;
        let cssClass;
        if (type === 'cost') {
            cssClass = isPositive ? 'negative' : 'positive';
        } else {
            cssClass = isPositive ? 'positive' : 'negative';
        }

        wrapper.innerHTML = `
            <div class="stat-indicator ${cssClass}">
                <i class="fas fa-caret-${isPositive ? 'up' : 'down'}"></i>
                <span>${isPositive ? '+' : '-'}${change.percent}%</span>
            </div>
        `;
    }

    function renderComparisonInCard(type, compValue) {
        const contentEl = document.querySelector(`.stat-${type} .stat-content`);
        if (!contentEl) return;

        const existing = contentEl.querySelector('.stat-comparison-row');
        if (existing) existing.remove();

        const row = document.createElement('div');
        row.className = 'stat-comparison-row';
        row.innerHTML = `<span class="vs-label">vs</span> <span class="vs-value">${formatNumber(compValue)} PLN</span>`;
        contentEl.appendChild(row);
    }

    function renderBreakdown(containerId, breakdown) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (!breakdown || breakdown.length === 0) {
            container.innerHTML = '<div class="breakdown-empty">Brak danych</div>';
            return;
        }

        container.innerHTML = breakdown.map(item =>
            `<div class="breakdown-row">
                <span class="breakdown-name">${escapeHtml(item.name)}</span>
                <span class="breakdown-value">${formatInteger(item.packages)}</span>
            </div>`
        ).join('');
    }

    function renderPackageComparison(compStats) {
        const morningEl = document.getElementById('morningPackages');
        const afternoonEl = document.getElementById('afternoonPackages');

        addComparisonSpan(morningEl, compStats.morning.packages);
        addComparisonSpan(afternoonEl, compStats.afternoon.packages);
        renderTotalPackagesComparison(compStats.total.packages);
    }

    function renderTotalPackagesComparison(compValue) {
        removeTotalPackagesComparison();

        const summary = document.querySelector('.packages-summary');
        if (!summary) return;

        const row = document.createElement('div');
        row.className = 'summary-row comparison-summary';
        row.innerHTML = `
            <span class="summary-label">vs Łącznie paczek:</span>
            <span class="summary-value">${formatInteger(compValue)}</span>
        `;
        summary.appendChild(row);
    }

    function removeTotalPackagesComparison() {
        const existing = document.querySelector('.packages-summary .comparison-summary');
        if (existing) existing.remove();
    }

    function addComparisonSpan(el, compValue) {
        if (!el) return;
        removeComparisonSpan(el);

        const row = document.createElement('div');
        row.className = 'package-comparison-row';
        row.innerHTML = `<span class="vs-label">vs</span> <span class="vs-value">${formatInteger(compValue)} paczek</span>`;
        el.closest('.package-stat').appendChild(row);
    }

    function removeComparisonSpan(el) {
        if (!el) return;
        const stat = el.closest('.package-stat');
        if (stat) {
            const existing = stat.querySelector('.package-comparison-row');
            if (existing) existing.remove();
        }
    }

    function renderWorkersSummaryComparison(compTotalCost) {
        const summary = document.querySelector('.workers-summary');
        if (!summary) return;

        removeWorkersSummaryComparison();

        const row = document.createElement('div');
        row.className = 'summary-row comparison-summary';
        row.innerHTML = `
            <span class="summary-label">vs Łączny koszt:</span>
            <span class="summary-value">${formatNumber(compTotalCost)} zł</span>
        `;
        summary.appendChild(row);
    }

    function removeWorkersSummaryComparison() {
        const existing = document.querySelector('.workers-summary .comparison-summary');
        if (existing) existing.remove();
    }

    function removeComparisonUI() {
        document.querySelectorAll('.stat-comparison-row').forEach(el => el.remove());
        document.querySelectorAll('.package-comparison-row').forEach(el => el.remove());

        removeTotalPackagesComparison();
        removeWorkersSummaryComparison();
    }

    function renderWorkers(workers) {
        const tbody = document.getElementById('workersTableBody');
        const countEl = document.getElementById('workersCount');
        const totalCostEl = document.getElementById('totalWorkersCost');

        if (!tbody) return;

        if (workers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: #888; padding: 20px;">Brak pracowników</td></tr>';
            if (countEl) countEl.textContent = '0 pracowników';
            if (totalCostEl) totalCostEl.textContent = '0,00 zł';
            return;
        }

        let totalCost = 0;
        tbody.innerHTML = workers.map(worker => {
            totalCost += worker.salary;
            const hoursDisplay = (worker.hours === '0' || worker.hours === '0h 0min') ? 'Brak danych' : escapeHtml(worker.hours);
            const costDisplay = worker.salary > 0 ? `${formatNumber(worker.salary)} zł` : 'Brak danych';
            return `<tr>
                <td class="worker-name">${escapeHtml(worker.name)}</td>
                <td class="worker-hours">${hoursDisplay}</td>
                <td class="worker-cost">${costDisplay}</td>
            </tr>`;
        }).join('');

        if (countEl) countEl.textContent = `${workers.length} pracowników`;
        if (totalCostEl) totalCostEl.textContent = `${formatNumber(totalCost)} zł`;
    }

    function updateDashboard(data) {
        document.getElementById('statRevenue').textContent = formatNumber(data.totalRevenue);
        document.getElementById('statCost').textContent = formatNumber(data.totalCost);
        document.getElementById('statProfit').textContent = formatNumber(data.totalProfit);

        if (data.changes) {
            renderIndicator(data.changes.revenue, 'revenue');
            renderIndicator(data.changes.cost, 'cost');
            renderIndicator(data.changes.profit, 'profit');
        } else {
            renderIndicator(null, 'revenue');
            renderIndicator(null, 'cost');
            renderIndicator(null, 'profit');
        }

        renderBreakdown('morningBreakdown', data.packageStats.morning.breakdown);
        document.getElementById('morningPackages').textContent = formatInteger(data.packageStats.morning.packages);
        renderBreakdown('afternoonBreakdown', data.packageStats.afternoon.breakdown);
        document.getElementById('afternoonPackages').textContent = formatInteger(data.packageStats.afternoon.packages);
        document.getElementById('totalPackages').textContent = formatInteger(data.packageStats.total.packages);

        renderWorkers(data.workers);

        removeComparisonUI();

        const hasComparison = !!data.comparison;
        toggleExportButton(hasComparison);

        if (data.comparison) {
            renderComparisonInCard('revenue', data.comparison.totalRevenue);
            renderComparisonInCard('cost', data.comparison.totalCost);
            renderComparisonInCard('profit', data.comparison.totalProfit);
            renderPackageComparison(data.comparison.packageStats);
        }
    }

    function fetchDashboardData(startDate, endDate) {
        setLoading(true);

        const params = new URLSearchParams({
            start_date: formatDate(startDate),
            end_date: formatDate(endDate)
        });

        if (comparison.isActive && comparison.startDate && comparison.endDate) {
            params.append('compare_start_date', formatDate(comparison.startDate));
            params.append('compare_end_date', formatDate(comparison.endDate));
        }

        fetch(`${dataUrl}?${params}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Błąd pobierania danych');
            return response.json();
        })
        .then(data => {
            updateDashboard(data);
        })
        .catch(error => {
            console.error('Dashboard fetch error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Nie udało się pobrać danych',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        })
        .finally(() => {
            setLoading(false);
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
});
