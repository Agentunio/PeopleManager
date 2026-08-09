import { createRangeCalendar, daysInMonth, isoOf, pad2 } from './range-calendar.js';
import { toast } from './notice';

const MONTHS_SHORT = ['sty', 'lut', 'mar', 'kwi', 'maj', 'cze', 'lip', 'sie', 'wrz', 'paź', 'lis', 'gru'];
const DAY_MS = 86_400_000;
const MONEY_FORMATTER = new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
});
const INTEGER_FORMATTER = new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 0 });
const PERCENT_FORMATTER = new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 1 });

function dateToIso(date) {
    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}

function isoToDate(value) {
    return new Date(
        Number(value.slice(0, 4)),
        Number(value.slice(5, 7)) - 1,
        Number(value.slice(8, 10))
    );
}

function addDays(value, amount) {
    const date = isoToDate(value);
    date.setDate(date.getDate() + amount);

    return dateToIso(date);
}

function inclusiveDays(range) {
    const from = Date.UTC(Number(range.from.slice(0, 4)), Number(range.from.slice(5, 7)) - 1, Number(range.from.slice(8, 10)));
    const to = Date.UTC(Number(range.to.slice(0, 4)), Number(range.to.slice(5, 7)) - 1, Number(range.to.slice(8, 10)));

    return Math.round((to - from) / DAY_MS) + 1;
}

function previousRange(range) {
    const days = inclusiveDays(range);
    const to = addDays(range.from, -1);

    return { from: addDays(to, -(days - 1)), to };
}

function formatRange(range, compact = false) {
    const from = isoToDate(range.from);
    const to = isoToDate(range.to);
    const differentYears = from.getFullYear() !== to.getFullYear();
    const separator = compact ? '\u2013' : ' \u2014 ';
    const fromLabel = `${from.getDate()} ${MONTHS_SHORT[from.getMonth()]}${differentYears ? ` ${from.getFullYear()}` : ''}`;
    const toLabel = `${to.getDate()} ${MONTHS_SHORT[to.getMonth()]}${differentYears ? ` ${to.getFullYear()}` : ''}`;

    if (compact || differentYears) return `${fromLabel}${separator}${toLabel}`;

    return `${fromLabel}${separator}${toLabel} ${to.getFullYear()}`;
}

function formatDate(value) {
    const normalized = String(value).slice(0, 10);
    const date = isoToDate(normalized);

    return `${pad2(date.getDate())}.${pad2(date.getMonth() + 1)}.${date.getFullYear()}`;
}

function formatMoney(value) {
    return MONEY_FORMATTER.format(Number(value || 0));
}

function formatInteger(value) {
    return INTEGER_FORMATTER.format(Number(value || 0));
}

function createElement(tagName, className, text) {
    const element = document.createElement(tagName);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;

    return element;
}

function peopleLabel(count) {
    const lastTwo = count % 100;
    const last = count % 10;

    if (count === 1) return '1 osoba na zmianach';
    if (last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14)) return `${count} osoby na zmianach`;

    return `${count} osób na zmianach`;
}

document.addEventListener('DOMContentLoaded', () => {
    const dashboardContent = document.getElementById('dashboardContent');
    const calendarContainer = document.querySelector('[data-range-calendar]');

    if (!dashboardContent || !calendarContainer || !window.dashboardData) return;

    const modalBackground = dashboardContent.closest('.admin-panel') || dashboardContent;

    const dataUrl = dashboardContent.dataset.dataUrl;
    const settlementUrlTemplate = dashboardContent.dataset.settlementUrlTemplate;
    const filterCard = document.querySelector('.dashboard-filter-card');
    const calendarToggle = document.querySelector('[data-calendar-toggle]');
    const comparisonTrigger = document.querySelector('[data-comparison-trigger]');
    const rangeDisplay = document.querySelector('[data-range-display]');
    const rangeDays = document.querySelector('[data-range-days]');
    const heading = document.querySelector('[data-dashboard-heading]');
    const loading = document.getElementById('dashboardLoading');
    const absenceDetails = new WeakMap();
    const todayDate = new Date();
    const today = dateToIso(todayDate);
    const monthStart = isoOf(todayDate.getFullYear(), todayDate.getMonth(), 1);

    let currentShift = 'total';
    let dashboardData = window.dashboardData;
    let workerPage = Number(dashboardData.workerPagination?.currentPage || 1);
    let primaryRange = { from: monthStart, to: today };
    let manualComparison = null;
    let selectionTarget = 'primary';
    let requestSequence = 0;
    let requestController = null;
    let activeAbsence = null;
    let calendar = null;

    function comparisonRange() {
        return manualComparison || previousRange(primaryRange);
    }

    function presetRange(name) {
        const year = todayDate.getFullYear();
        const month = todayDate.getMonth();

        if (name === 'today') return { from: today, to: today };

        if (name === 'week') {
            const weekday = (todayDate.getDay() + 6) % 7;
            return { from: addDays(today, -weekday), to: today };
        }

        if (name === 'half') {
            return {
                from: isoOf(year, month, 1),
                to: isoOf(year, month, Math.min(15, daysInMonth(year, month)))
            };
        }

        return {
            from: isoOf(year, month, 1),
            to: isoOf(year, month, daysInMonth(year, month))
        };
    }

    function rangesEqual(first, second) {
        return first.from === second.from && first.to === second.to;
    }

    function updatePresetState() {
        document.querySelectorAll('[data-range-preset]').forEach((button) => {
            const active = rangesEqual(primaryRange, presetRange(button.dataset.rangePreset));
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
    }

    function updateFilterCopy(preview = false) {
        const count = inclusiveDays(primaryRange);
        rangeDisplay.textContent = formatRange(primaryRange);
        rangeDays.textContent = `${count} ${count === 1 ? 'dzień' : 'dni'}`;

        const shiftLabel = currentShift === 'total'
            ? 'wszystkie zmiany'
            : currentShift === 'morning' ? 'tylko rano' : 'tylko popołudnie';
        heading.textContent = `${formatRange(primaryRange)} · ${shiftLabel}`;

        comparisonTrigger.classList.toggle('is-manual', Boolean(manualComparison));
        filterCard.classList.toggle('is-comparison-selecting', selectionTarget === 'comparison');

        if (selectionTarget === 'comparison') {
            comparisonTrigger.textContent = preview ? 'wybierz datę końcową · anuluj' : 'wybierz okres porównawczy · anuluj';
            comparisonTrigger.setAttribute('aria-label', 'Anuluj wybór okresu porównawczego');
        } else if (manualComparison) {
            comparisonTrigger.textContent = `por. z ${formatRange(manualComparison, true)} · resetuj`;
            comparisonTrigger.setAttribute('aria-label', 'Przywróć automatyczne porównanie z poprzednim okresem');
        } else {
            comparisonTrigger.textContent = 'por. z poprz. okresem';
            comparisonTrigger.setAttribute('aria-label', 'Wybierz własny okres porównawczy');
        }

        updatePresetState();
    }

    function setCalendarOpen(open) {
        filterCard.classList.toggle('is-calendar-open', open);
        calendarToggle.setAttribute('aria-expanded', String(open));
    }

    function renderIndicator(change, key, invertColor = false) {
        const wrapper = document.getElementById(`indicator${key}`);
        wrapper.replaceChildren();

        if (!change) return;

        const isFavorable = invertColor ? !change.isPositive : change.isPositive;
        const pill = createElement('span', `dashboard-delta-pill ${isFavorable ? 'is-positive' : 'is-negative'}`);
        pill.textContent = `${change.isPositive ? '↑' : '↓'} ${PERCENT_FORMATTER.format(Number(change.percent))}%`;
        wrapper.append(pill);
    }

    function mergeBreakdowns(stats) {
        if (currentShift !== 'total') return stats[currentShift]?.breakdown || [];

        const merged = new Map();
        ['morning', 'afternoon'].forEach((shift) => {
            (stats[shift]?.breakdown || []).forEach((item) => {
                merged.set(item.name, (merged.get(item.name) || 0) + Number(item.packages || 0));
            });
        });

        return Array.from(merged, ([name, packages]) => ({ name, packages }))
            .sort((first, second) => second.packages - first.packages);
    }

    function renderPackages(stats) {
        const tbody = document.getElementById('packagesTableBody');
        const total = currentShift === 'total' ? stats.total.packages : stats[currentShift].packages;
        const rows = mergeBreakdowns(stats);
        tbody.replaceChildren();

        if (rows.length === 0) {
            const row = document.createElement('tr');
            const cell = createElement('td', 'packages-empty', 'Brak danych');
            cell.colSpan = 2;
            row.append(cell);
            tbody.append(row);
        } else {
            rows.forEach((item) => {
                const row = document.createElement('tr');
                const name = createElement('th', null, item.name);
                const value = createElement('td', null, formatInteger(item.packages));
                const unit = createElement('span', 'table-unit', 'szt.');
                name.scope = 'row';
                value.append(unit);
                row.append(name, value);
                tbody.append(row);
            });
        }

        document.getElementById('totalPackages').textContent = formatInteger(total);
        document.getElementById('packagesTableTotal').textContent = formatInteger(total);
    }

    function workersForShift(workers) {
        return workers.map((worker) => {
            if (currentShift === 'total') return worker;
            const shift = worker.byShift?.[currentShift];
            if (!shift) return null;

            return {
                name: worker.name,
                hours: shift.hours,
                salary: shift.salary,
                totalMinutes: shift.totalMinutes,
                absences: shift.absences,
                absentDays: shift.absentDays
            };
        }).filter((worker) => worker !== null)
            .sort((first, second) => Number(second.salary) - Number(first.salary));
    }

    function renderAbsenceCell(cell, worker) {
        if (!worker.absences) {
            cell.append(createElement('span', 'no-absences', '0'));
            return;
        }

        const button = createElement('button', 'absence-badge');
        const count = createElement('span', 'absence-count', String(worker.absences));
        button.type = 'button';
        button.dataset.mobileLabel = `${worker.absences} nieob.`;
        button.setAttribute('aria-label', `Pokaż ${worker.absences} nieobecności pracownika ${worker.name}`);
        button.setAttribute('aria-expanded', 'false');
        absenceDetails.set(button, worker.absentDays || []);
        button.append(count);
        cell.append(button);
    }

    function renderWorkers(workers, pagination) {
        closeAbsence();

        const tbody = document.getElementById('workersTableBody');
        const rows = workersForShift(workers);
        tbody.replaceChildren();
        document.getElementById('workersCount').textContent = peopleLabel(Number(pagination?.total ?? rows.length));

        if (rows.length === 0) {
            const row = document.createElement('tr');
            const cell = createElement('td', 'workers-empty', 'Brak pracowników w wybranym zakresie');
            cell.colSpan = 4;
            row.append(cell);
            tbody.append(row);
            return;
        }

        rows.forEach((worker) => {
            const row = document.createElement('tr');
            const name = createElement('td', 'worker-name', worker.name);
            const hours = createElement('td', 'worker-hours', worker.hours);
            const absences = createElement('td', 'worker-absences');
            const cost = createElement('td', 'worker-cost', `${formatMoney(worker.salary)} zł`);
            renderAbsenceCell(absences, worker);
            row.append(name, hours, absences, cost);
            tbody.append(row);
        });
    }

    function createWorkerPaginationButton(label, page, disabled, ariaLabel) {
        const button = createElement('button', 'dashboard-worker-page-button', label);
        button.type = 'button';
        button.dataset.workerPage = String(page);
        button.disabled = disabled;
        button.setAttribute('aria-label', ariaLabel);

        return button;
    }

    function renderWorkerPagination(pagination) {
        const navigation = document.getElementById('workersPagination');
        const currentPage = Number(pagination?.currentPage || 1);
        const lastPage = Number(pagination?.lastPage || 1);

        navigation.replaceChildren();
        navigation.hidden = lastPage <= 1;
        if (lastPage <= 1) return;

        const previous = createWorkerPaginationButton(
            '\u2039',
            currentPage - 1,
            currentPage <= 1,
            'Poprzednia strona koszt\u00f3w pracownik\u00f3w'
        );
        const status = createElement('span', 'dashboard-worker-page-status', `${currentPage} / ${lastPage}`);
        const next = createWorkerPaginationButton(
            '\u203a',
            currentPage + 1,
            currentPage >= lastPage,
            'Nast\u0119pna strona koszt\u00f3w pracownik\u00f3w'
        );
        status.setAttribute('aria-live', 'polite');
        navigation.append(previous, status, next);
    }

    function renderDashboard() {
        const totals = currentShift === 'total'
            ? { revenue: dashboardData.totalRevenue, cost: dashboardData.totalCost, profit: dashboardData.totalProfit }
            : dashboardData.byShift[currentShift];
        const changes = currentShift === 'total' ? dashboardData.changes : dashboardData.changes?.byShift?.[currentShift];

        document.getElementById('statRevenue').textContent = formatMoney(totals.revenue);
        document.getElementById('statCost').textContent = formatMoney(totals.cost);
        document.getElementById('statProfit').textContent = formatMoney(totals.profit);
        renderIndicator(changes?.revenue, 'Revenue');
        renderIndicator(changes?.cost, 'Cost', true);
        renderIndicator(changes?.profit, 'Profit');
        renderPackages(dashboardData.packageStats);
        renderWorkers(dashboardData.workers || [], dashboardData.workerPagination);
        renderWorkerPagination(dashboardData.workerPagination);
        updateFilterCopy();
    }

    function setLoading(active) {
        loading.hidden = !active;
        dashboardContent.classList.toggle('is-loading', active);
        dashboardContent.setAttribute('aria-busy', String(active));
    }

    async function fetchDashboardData() {
        const sequence = ++requestSequence;
        const comparison = comparisonRange();
        const parameters = new URLSearchParams({
            start_date: primaryRange.from,
            end_date: primaryRange.to,
            compare_start_date: comparison.from,
            compare_end_date: comparison.to,
            page: String(workerPage),
            shift: currentShift
        });
        const controller = new AbortController();

        requestController?.abort();
        requestController = controller;
        setLoading(true);

        try {
            const response = await fetch(`${dataUrl}?${parameters}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal
            });
            if (!response.ok) throw new Error(`Dashboard request failed with ${response.status}`);

            const data = await response.json();
            if (sequence !== requestSequence) return;

            dashboardData = data;
            workerPage = Number(data.workerPagination?.currentPage || 1);
            renderDashboard();
        } catch (error) {
            if (error.name === 'AbortError' || sequence !== requestSequence) return;
            console.error('Dashboard fetch error:', error);
            toast.error('Nie udało się pobrać danych', { timer: 3000 });
        } finally {
            if (sequence === requestSequence) {
                requestController = null;
                setLoading(false);
            }
        }
    }

    function syncCalendarRange(range) {
        const month = range.to.slice(0, 7);
        if (calendar.getView().key !== month) calendar.setView(month);
        calendar.setRange(range.from, range.to);
    }

    function selectPrimaryRange(range, syncCalendar = true) {
        primaryRange = range;
        manualComparison = null;
        workerPage = 1;
        selectionTarget = 'primary';
        if (syncCalendar) syncCalendarRange(range);
        updateFilterCopy();
        fetchDashboardData();
    }

    function toggleManualComparison() {
        if (selectionTarget === 'comparison') {
            selectionTarget = 'primary';
            syncCalendarRange(primaryRange);
            updateFilterCopy();
            return;
        }

        if (manualComparison) {
            manualComparison = null;
            updateFilterCopy();
            fetchDashboardData();
            return;
        }

        selectionTarget = 'comparison';
        calendar.setRange(primaryRange.from, primaryRange.to);
        setCalendarOpen(true);
        updateFilterCopy();
        calendarContainer.querySelector('.cal-day:not([disabled])')?.focus();
    }

    function submitExport(button) {
        const form = document.createElement('form');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        form.method = 'POST';
        form.action = button.dataset.exportUrl;
        form.hidden = true;

        [['_token', csrf], ['start_date', primaryRange.from], ['end_date', primaryRange.to]].forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value || '';
            form.append(input);
        });

        document.body.append(form);
        form.submit();
        form.remove();
    }

    function buildSettlementUrl(day) {
        return settlementUrlTemplate
            .replace(':date', encodeURIComponent(day))
            .replace('%3Adate', encodeURIComponent(day));
    }

    function dialogItem(entry) {
        const day = String(typeof entry === 'string' ? entry : entry.day).slice(0, 10);
        const substitute = typeof entry === 'object' ? entry.substitute : null;
        const link = createElement('a', 'absence-item');
        link.href = buildSettlementUrl(day);
        link.append(
            createElement('span', 'absence-item-label', 'Nieobecność'),
            createElement('span', 'absence-item-label', 'Zastępstwo'),
            createElement('span', 'absence-item-value', formatDate(day)),
            createElement('span', `absence-item-substitute${substitute ? '' : ' is-empty'}`, substitute || 'brak')
        );

        return link;
    }

    function closeAbsence(restoreFocus = false) {
        if (!activeAbsence) return;

        const { trigger, dialog, overlay, mobile } = activeAbsence;
        trigger.setAttribute('aria-expanded', 'false');
        dialog.remove();
        overlay?.remove();
        document.body.classList.remove('has-dashboard-dialog');
        if (mobile) modalBackground.inert = false;
        activeAbsence = null;
        if (restoreFocus && trigger.isConnected) trigger.focus();
    }

    function trapDialogFocus(event) {
        if (!activeAbsence?.mobile || event.key !== 'Tab') return;

        const focusable = Array.from(activeAbsence.dialog.querySelectorAll('button, a[href]'));
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function openAbsence(trigger) {
        if (activeAbsence?.trigger === trigger) {
            closeAbsence(true);
            return;
        }
        closeAbsence();

        const entries = absenceDetails.get(trigger) || [];
        const mobile = window.matchMedia('(max-width: 768px)').matches;
        const dialog = createElement('div', mobile ? 'absence-popover absence-sheet' : 'absence-popover');
        const head = createElement('div', 'absence-dialog-head');
        const title = createElement('span', 'absence-dialog-title', 'Nieobecności');
        const close = createElement('button', 'absence-close', '×');
        const list = createElement('div', 'absence-list');
        const overlay = mobile ? createElement('div', 'absence-overlay') : null;

        close.type = 'button';
        close.setAttribute('aria-label', 'Zamknij szczegóły nieobecności');
        close.addEventListener('click', () => closeAbsence(true));
        entries.forEach((entry) => list.append(dialogItem(entry)));
        head.append(title, close);
        dialog.append(head, list);
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-label', 'Szczegóły nieobecności');
        if (mobile) dialog.setAttribute('aria-modal', 'true');

        trigger.setAttribute('aria-expanded', 'true');
        if (mobile) {
            overlay.addEventListener('click', () => closeAbsence(true));
            document.body.append(overlay, dialog);
            document.body.classList.add('has-dashboard-dialog');
            modalBackground.inert = true;
        } else {
            trigger.closest('.worker-absences').append(dialog);
        }

        activeAbsence = { trigger, dialog, overlay, mobile };
        close.focus();
    }

    calendar = createRangeCalendar({
        container: calendarContainer,
        initialFrom: primaryRange.from,
        initialTo: primaryRange.to,
        initialMonth: primaryRange.to.slice(0, 7),
        today,
        onRangePreview() {
            if (selectionTarget === 'comparison') updateFilterCopy(true);
        },
        onRangeChange(range) {
            if (selectionTarget === 'comparison') {
                manualComparison = range;
                selectionTarget = 'primary';
                syncCalendarRange(primaryRange);
                updateFilterCopy();
                fetchDashboardData();
                comparisonTrigger.focus();
                return;
            }

            selectPrimaryRange(range, false);
        }
    });

    setCalendarOpen(!window.matchMedia('(max-width: 768px)').matches);
    renderDashboard();

    calendarToggle.addEventListener('click', () => {
        setCalendarOpen(!filterCard.classList.contains('is-calendar-open'));
    });

    comparisonTrigger.addEventListener('click', toggleManualComparison);

    document.getElementById('shiftToggle').addEventListener('click', (event) => {
        const button = event.target.closest('[data-shift]');
        if (!button || button.dataset.shift === currentShift) return;

        currentShift = button.dataset.shift;
        document.querySelectorAll('[data-shift]').forEach((item) => {
            const active = item === button;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-pressed', String(active));
        });
        workerPage = 1;
        updateFilterCopy();
        fetchDashboardData();
    });

    document.querySelector('.dashboard-presets').addEventListener('click', (event) => {
        const button = event.target.closest('[data-range-preset]');
        if (!button) return;
        selectPrimaryRange(presetRange(button.dataset.rangePreset));
    });

    document.querySelectorAll('[data-export-url]').forEach((button) => {
        button.addEventListener('click', () => submitExport(button));
    });

    document.getElementById('workersPagination').addEventListener('click', (event) => {
        const button = event.target.closest('[data-worker-page]');
        if (!button || button.disabled) return;

        const requestedPage = Number(button.dataset.workerPage);
        if (!Number.isInteger(requestedPage) || requestedPage < 1 || requestedPage === workerPage) return;

        workerPage = requestedPage;
        fetchDashboardData();
    });

    document.getElementById('workersTableBody').addEventListener('click', (event) => {
        const trigger = event.target.closest('.absence-badge');
        if (trigger) openAbsence(trigger);
    });

    document.addEventListener('click', (event) => {
        if (!activeAbsence || activeAbsence.mobile) return;
        if (!activeAbsence.dialog.contains(event.target) && !activeAbsence.trigger.contains(event.target)) closeAbsence();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && activeAbsence) {
            closeAbsence(true);
            return;
        }
        trapDialogFocus(event);
    });
});
