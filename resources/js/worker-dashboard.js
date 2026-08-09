import { validateHoursInputs, setupTimeInputAutopad, formatMinutesToHours } from './worker-time-utils.js';
import {
    createRangeCalendar,
    daysInMonth,
    isoOf,
    monthKeyOf,
    pad2,
    shortIso
} from './range-calendar.js';

// Musi dać ten sam wynik co number_format($v, 2, ',', ' ') w Blade.
function formatMoney(value) {
    var parts = value.toFixed(2).split('.');
    return parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ',' + parts[1];
}

document.addEventListener('DOMContentLoaded', function () {
    initHoursForm();
    initRangeFilter();
});

// --- filtr zakresu: mini-kalendarz + presety ------------------------------
function initRangeFilter() {
    var container = document.querySelector('[data-range-calendar]');
    var config = window.dashboardCalendar;
    if (!container || !config) return;

    var cache = Object.assign({}, window.dashboardMonthDays || {});
    var loaded = {};
    var pending = {};
    loaded[config.monthStart.slice(0, 7)] = true;

    var valueEl = document.querySelector('[data-salary-value]');
    var hoursEl = document.querySelector('[data-salary-hours]');
    var periodEl = document.querySelector('[data-salary-period]');
    var labelEl = document.querySelector('[data-range-label]');
    var trendEl = document.querySelector('[data-salary-trend]');
    var trendToken = 0;
    var chips = Array.prototype.slice.call(document.querySelectorAll('[data-range-preset]'));

    var initialView = {
        year: parseInt(config.monthStart.slice(0, 4), 10),
        month: parseInt(config.monthStart.slice(5, 7), 10) - 1,
    };
    var range = { from: config.monthStart, to: config.today };

    // Granice nawigacji — bez nich użytkownik generuje żądania w nieskończoność.
    var minKey = monthKeyOf(initialView.year, initialView.month - 12);
    var maxKey = monthKeyOf(initialView.year, initialView.month + 1);

    function monthBounds(key) {
        var year = parseInt(key.slice(0, 4), 10);
        var month = parseInt(key.slice(5, 7), 10) - 1;
        return { from: key + '-01', to: key + '-' + pad2(daysInMonth(year, month)) };
    }

    // Jeden request na miesiąc; równoległe wywołania współdzielą to samo Promise.
    function fetchMonth(key) {
        if (loaded[key]) return Promise.resolve(true);
        if (pending[key]) return pending[key];

        var bounds = monthBounds(key);

        pending[key] = Promise.resolve(
            $.ajax({ url: config.statsUrl, method: 'GET', data: { from: bounds.from, to: bounds.to } })
        ).then(function (response) {
            Object.assign(cache, (response && response.days) || {});
            loaded[key] = true;
            delete pending[key];
            return true;
        }).catch(function () {
            delete pending[key];
            showToast.error('Nie udało się pobrać danych dla wybranego miesiąca');
            return false;
        });

        return pending[key];
    }

    function loadRange(fromIso, toIso) {
        var cursor = new Date(
            parseInt(fromIso.slice(0, 4), 10),
            parseInt(fromIso.slice(5, 7), 10) - 1,
            1
        );
        var last = new Date(
            parseInt(toIso.slice(0, 4), 10),
            parseInt(toIso.slice(5, 7), 10) - 1,
            1
        );
        var requests = [];

        while (cursor <= last) {
            requests.push(fetchMonth(monthKeyOf(cursor.getFullYear(), cursor.getMonth())));
            cursor.setMonth(cursor.getMonth() + 1);
        }

        return Promise.all(requests).then(function (results) {
            return results.every(Boolean);
        });
    }

    function sumRange(fromIso, toIso) {
        var minutes = 0;
        var salary = 0;

        Object.keys(cache).forEach(function (day) {
            if (day >= fromIso && day <= toIso) {
                minutes += cache[day].minutes;
                salary += cache[day].salary;
            }
        });

        return { minutes: minutes, salary: salary };
    }

    function renderLabel() {
        if (labelEl) labelEl.textContent = shortIso(range.from) + ' — ' + shortIso(range.to);
    }

    function renderTrend(trend) {
        if (!trendEl) return;

        if (!trend) {
            trendEl.hidden = true;
            return;
        }

        trendEl.hidden = false;
        trendEl.classList.toggle('is-down', !trend.isPositive);
        trendEl.textContent = (trend.isPositive ? '↑' : '↓') + ' ' + trend.percent + '% vs ' + trend.prev_month_label;
    }

    // Trend zależy od danych spoza zakresu (poprzedni okres), więc liczy go
    // backend. Token odcina odpowiedzi wyścigujących się żądań.
    function refreshTrend(fromIso, toIso) {
        if (!trendEl) return;

        var token = ++trendToken;

        Promise.resolve(
            $.ajax({ url: config.statsUrl, method: 'GET', data: { from: fromIso, to: toIso } })
        ).then(function (response) {
            if (token !== trendToken) return;
            renderTrend(response && response.salaryTrend);
        }).catch(function () {
            if (token !== trendToken) return;
            renderTrend(null);
        });
    }

    function applyRange() {
        var totals = sumRange(range.from, range.to);

        if (valueEl) valueEl.textContent = formatMoney(totals.salary);
        if (hoursEl) hoursEl.textContent = formatMinutesToHours(totals.minutes);
        if (periodEl) periodEl.textContent = 'przepracowanych w wybranym zakresie';
        renderLabel();
        refreshTrend(range.from, range.to);
    }

    function markChip(active) {
        chips.forEach(function (chip) {
            var isActive = chip === active;
            chip.classList.toggle('is-selected', isActive);
            chip.setAttribute('aria-pressed', String(isActive));
        });
    }

    var calendar = createRangeCalendar({
        container: container,
        initialFrom: range.from,
        initialTo: range.to,
        initialMonth: config.monthStart,
        minMonth: minKey,
        maxMonth: maxKey,
        today: config.today,
        getDayClassNames: function (iso) {
            var entry = cache[iso];
            var classes = [];

            // Kropka: czerwona = nieobecność, zielona = był, szara = zmiana jeszcze przed nami.
            if (entry) {
                classes.push('has-shift');
                if (entry.absent) {
                    classes.push('is-absent');
                } else if (iso <= config.today) {
                    classes.push('is-worked');
                }
            }

            return classes;
        },
        onViewChange: function (key) {
            fetchMonth(key).then(function (loadedSuccessfully) {
                if (loadedSuccessfully) calendar.render();
            });
        },
        onRangePreview: function (nextRange) {
            range = nextRange;
            markChip(null);
            renderLabel();
        },
        onRangeChange: function (nextRange) {
            range = nextRange;
            markChip(null);
            loadRange(range.from, range.to).then(function (loadedSuccessfully) {
                if (loadedSuccessfully) applyRange();
            });
        }
    });
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            var preset = chip.dataset.rangePreset;
            var view = calendar.getView();
            var last = preset === 'week' ? 7
                : preset === 'half' ? 15
                : daysInMonth(view.year, view.month);

            range = {
                from: isoOf(view.year, view.month, 1),
                to: isoOf(view.year, view.month, last),
            };

            markChip(chip);
            calendar.setRange(range.from, range.to);
            loadRange(range.from, range.to).then(function (loadedSuccessfully) {
                if (loadedSuccessfully) applyRange();
            });
        });
    });


    renderLabel();
}

// --- wpisywanie godzin (ostatnia zmiana) ----------------------------------
function initHoursForm() {
    var form = document.getElementById('dashboardHoursForm');
    var saveBtn = document.getElementById('dashboardSaveHours');
    if (!form || !saveBtn) return;

    var date = form.dataset.date;
    var hoursUrl = form.dataset.hoursUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    setupTimeInputAutopad(form);
    updateSaveBtnVisibility();

    form.addEventListener('click', function (event) {
        var editBtn = event.target.closest('.dash-edit-btn');
        var cancelAction = event.target.closest('.dash-cancel-btn');
        var button = editBtn || cancelAction;
        if (!button || !form.contains(button)) return;

        var container = button.closest('.dashboard-shift-content');
        var savedInfo = container.querySelector('.dash-saved-info');
        var inputs = container.querySelector('.dash-hours-inputs');
        var cancelBtn = container.querySelector('.dash-cancel-btn');

        if (editBtn) {
            if (savedInfo) savedInfo.style.display = 'none';
            if (inputs) inputs.style.display = '';
            if (cancelBtn) cancelBtn.style.display = '';
            saveBtn.style.display = '';
            return;
        }

        if (savedInfo) savedInfo.style.display = '';
        if (inputs) inputs.style.display = 'none';
        if (cancelBtn) cancelBtn.style.display = 'none';
        updateSaveBtnVisibility();
    });

    function hasVisibleInputs() {
        var found = false;
        form.querySelectorAll('.dash-hours-inputs').forEach(function (el) {
            if (el.style.display !== 'none') found = true;
        });
        return found;
    }

    function updateSaveBtnVisibility() {
        saveBtn.style.display = hasVisibleInputs() ? '' : 'none';
    }

    saveBtn.addEventListener('click', function () {
        var groups = form.querySelectorAll('.dash-hours-inputs');
        var submissions = [];
        var hasError = false;

        groups.forEach(function (group) {
            if (group.style.display === 'none') return;

            var shiftType = group.dataset.shiftType;
            var shiftLabel = shiftType === 'morning' ? 'Zmiana ranna' : 'Zmiana popołudniowa';
            var fromH = group.querySelector('.dash-from-hour');
            var fromM = group.querySelector('.dash-from-minute');
            var toH = group.querySelector('.dash-to-hour');
            var toM = group.querySelector('.dash-to-minute');

            var result = validateHoursInputs(fromH, fromM, toH, toM);

            if (result.empty) return;

            if (!result.valid) {
                showToast.error(shiftLabel + ': ' + result.error);
                hasError = true;
                return;
            }

            submissions.push({
                shiftType: shiftType,
                payload: { shift_type: shiftType, from_time: result.from, to_time: result.to }
            });
        });

        if (hasError || submissions.length === 0) return;

        saveBtn.disabled = true;
        var promises = submissions.map(function (sub) {
            return Promise.resolve($.ajax({
                url: hoursUrl.replace(':date', date),
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                contentType: 'application/json',
                data: JSON.stringify(sub.payload)
            })).then(function (response) {
                return { response: response, shiftType: sub.shiftType };
            });
        });

        Promise.allSettled(promises)
            .then(function (results) {
                var failed = results.filter(function (result) { return result.status === 'rejected'; });

                results.forEach(function (result) {
                    if (result.status !== 'fulfilled' || !result.value.response.html) return;

                    var container = form.querySelector(
                        '.dashboard-shift-content[data-shift-type="' + result.value.shiftType + '"]'
                    );
                    if (container) {
                        container.innerHTML = result.value.response.html;
                        setupTimeInputAutopad(container);
                    }
                });

                updateSaveBtnVisibility();

                if (failed.length === 0) {
                    showToast.success('Godziny zapisane');
                } else if (failed.length < results.length) {
                    showToast.warning('Część godzin została zapisana. Sprawdź pozostałe pola.');
                } else {
                    var xhr = failed[0].reason;
                    var msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Wystąpił błąd';
                    showToast.error(msg);
                }
            })
            .finally(function () { saveBtn.disabled = false; });
    });
}
