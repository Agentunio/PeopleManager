document.addEventListener('DOMContentLoaded', function() {
    const dataUrl = '/panel/data';

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
                fetchDashboardData(selectedDates[0], selectedDates[1]);
            }
        }
    });

    const refreshBtn = document.getElementById('refreshData');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            const dates = dateRangePicker.selectedDates;
            if (dates.length === 2) {
                fetchDashboardData(dates[0], dates[1]);
            }
        });
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

        const icon = refreshBtn?.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-spin', isLoading);
        }
    }

    function renderIndicator(change, type) {
        const wrapper = document.getElementById('indicator' + type.charAt(0).toUpperCase() + type.slice(1));
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
            return `<tr>
                <td class="worker-name">${worker.name}</td>
                <td class="worker-hours">${worker.hours}</td>
                <td class="worker-cost">${formatNumber(worker.salary)} zł</td>
            </tr>`;
        }).join('');

        if (countEl) countEl.textContent = `${workers.length} pracowników`;
        if (totalCostEl) totalCostEl.textContent = `${formatNumber(totalCost)} zł`;
    }

    function updateDashboard(data) {
        document.getElementById('statRevenue').textContent = formatNumber(data.totalRevenue);
        document.getElementById('statCost').textContent = formatNumber(data.totalCost);
        document.getElementById('statProfit').textContent = formatNumber(data.totalProfit);

        renderIndicator(data.changes.revenue, 'revenue');
        renderIndicator(data.changes.cost, 'cost');
        renderIndicator(data.changes.profit, 'profit');

        document.getElementById('morningPackages').textContent = formatInteger(data.packageStats.morning.packages);
        document.getElementById('afternoonPackages').textContent = formatInteger(data.packageStats.afternoon.packages);
        document.getElementById('totalPackages').textContent = formatInteger(data.packageStats.total.packages);

        renderWorkers(data.workers);
    }

    function fetchDashboardData(startDate, endDate) {
        setLoading(true);

        const params = new URLSearchParams({
            start_date: formatDate(startDate),
            end_date: formatDate(endDate)
        });

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
});
