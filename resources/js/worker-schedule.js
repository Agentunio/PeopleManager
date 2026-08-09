import {
    buildTimeString,
    validateHoursInputs,
    setupTimeInputAutopad,
} from './worker-time-utils.js';

document.addEventListener('DOMContentLoaded', function () {
    // --- baner zapisów: format jak w design + licznik dni do deadline ------
    (function () {
        var info = document.querySelector('[data-signup-info]');
        if (!info) return;
        var t = info.textContent;
        var dl = t.match(/(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{2})/);       // deadline z godziną
        if (!dl) return;
        var rg = t.match(/(\d{1,2}\.\d{1,2})\.\d{4}\s*[–-]\s*(\d{1,2}\.\d{1,2})\.\d{4}/); // zakres zapisu
        var wk = t.match(/\((bieżący|następny) tydzień\)/);

        var pad = function (n) { return String(n).padStart(2, '0'); };
        var deadlineStr = pad(dl[1]) + '.' + pad(dl[2]) + ', ' + pad(dl[4]) + ':' + dl[5];
        var html = 'Dostępne do <strong>' + deadlineStr + '</strong>';
        if (rg) {
            html += ' · <span>zakres zapisu ' + rg[1] + ' — ' + rg[2] + (wk ? ' (' + wk[1] + ' tydzień)' : '') + '</span>';
        }
        info.innerHTML = html;

        var pill = document.querySelector('[data-signup-countdown]');
        if (pill) {
            var now = new Date();
            var deadline = new Date(+dl[3], +dl[2] - 1, +dl[1], +dl[4], +dl[5]);
            if (deadline > now) {
                var dMid = new Date(+dl[3], +dl[2] - 1, +dl[1]);
                var nMid = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                var daysLeft = Math.round((dMid - nMid) / 86400000);
                pill.textContent = daysLeft <= 0 ? 'OSTATNI DZIEŃ'
                    : (daysLeft === 1 ? 'POZOSTAŁ 1 DZIEŃ' : 'POZOSTAŁO ' + daysLeft + ' DNI');
                pill.hidden = false;
            }
        }
    })();

    var root = document.querySelector('.gr-days');
    var config = window.scheduleConfig;
    var days = window.scheduleDays;
    if (!root || !config || !days) return;

    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    // --- helpers -----------------------------------------------------------
    function ctx(el) {
        var slot = el.closest('.gr-slot');
        var dayEl = el.closest('.gr-day');
        return { slot: slot, dayEl: dayEl, type: slot.dataset.type, date: dayEl.dataset.date };
    }

    function post(url, payload) {
        return $.ajax({
            url: url,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
            contentType: 'application/json',
            data: JSON.stringify(payload),
        });
    }

    function fail(xhr) {
        var message = (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Wystąpił błąd';
        showToast.error(message);
    }

    // Blokada czasowa dla dnia dzisiejszego (przed godziną startu zmiany).
    function applyUnlockGating(slot) {
        var form = slot.querySelector('.gr-hours-form');
        if (!form) return;
        var note = form.querySelector('.gr-hours-note');
        var save = form.querySelector('.gr-hours-save');
        var inputs = form.querySelectorAll('input');

        if (slot.closest('.gr-day').classList.contains('is-today')) {
            var unlock = parseInt(slot.dataset.startMinutes || '0', 10);
            var now = new Date();
            var current = now.getHours() * 60 + now.getMinutes();
            if (unlock && current < unlock) {
                inputs.forEach(function (i) { i.disabled = true; });
                if (save) save.disabled = true;
                note.textContent = 'Godziny można wpisać po ' + (slot.dataset.startLabel || '');
                note.hidden = false;
                return;
            }
        }

        inputs.forEach(function (i) { i.disabled = false; });
        if (save) save.disabled = false;
        note.hidden = true;
    }

    // --- availability (Zapisz się / Wypisz się) ----------------------------
    function submitAvailability(date, type, value, btn) {
        var day = days[date] || {};
        var otherType = type === 'morning' ? 'afternoon' : 'morning';
        var otherValue = day[otherType] === '1';

        var payload = {
            morning_shift: type === 'morning' ? value : otherValue,
            afternoon_shift: type === 'afternoon' ? value : otherValue,
        };

        if (btn) btn.disabled = true;

        post(config.availabilityUrl.replace(':date', date), payload)
            .then(function () { window.location.reload(); })
            .catch(function (xhr) { if (btn) btn.disabled = false; fail(xhr); });
    }

    // --- hours -------------------------------------------------------------
    function submitHours(slot, date, type, btn) {
        var form = slot.querySelector('.gr-hours-form');
        var fromH = form.querySelector('.gr-h-from-h');
        var fromM = form.querySelector('.gr-h-from-m');
        var toH = form.querySelector('.gr-h-to-h');
        var toM = form.querySelector('.gr-h-to-m');

        if (fromH.disabled) return;

        var validation = validateHoursInputs(fromH, fromM, toH, toM);
        if (!validation.valid) {
            showToast.error(validation.error);
            return;
        }
        if (validation.empty) {
            showToast.error('Wypełnij godziny (start i koniec)');
            return;
        }

        btn.disabled = true;

        post(config.hoursUrl.replace(':date', date), {
            shift_type: type,
            from_time: buildTimeString(fromH, fromM),
            to_time: buildTimeString(toH, toM),
        })
            .then(function () { window.location.reload(); })
            .catch(function (xhr) { btn.disabled = false; fail(xhr); });
    }

    // --- delegated events --------------------------------------------------
    root.addEventListener('click', function (e) {
        var signup = e.target.closest('.gr-signup');
        if (signup) {
            var cSu = ctx(signup);
            submitAvailability(cSu.date, cSu.type, true, signup);
            return;
        }

        var unsign = e.target.closest('.gr-unsign');
        if (unsign) {
            var cUn = ctx(unsign);
            submitAvailability(cUn.date, cUn.type, false, unsign);
            return;
        }

        var toggle = e.target.closest('.gr-hours-toggle');
        if (toggle) {
            var slotT = toggle.closest('.gr-slot');
            var formT = slotT.querySelector('.gr-hours-form');
            if (formT.hidden) {
                formT.hidden = false;
                toggle.textContent = 'Anuluj';
                applyUnlockGating(slotT);
            } else {
                formT.hidden = true;
                toggle.textContent = toggle.dataset.label;
            }
            return;
        }

        var save = e.target.closest('.gr-hours-save');
        if (save) {
            var cS = ctx(save);
            submitHours(cS.slot, cS.date, cS.type, save);
            return;
        }
    });

    setupTimeInputAutopad(root);

    // --- roster collapse (+N osób) ----------------------------------------
    function pluralPeople(n) {
        if (n === 1) return 'osoba';
        return n < 5 ? 'osoby' : 'osób';
    }

    document.querySelectorAll('.gr-roster').forEach(function (roster) {
        var names = Array.prototype.slice.call(roster.querySelectorAll('.gr-name'));
        var max = 4;
        if (names.length <= max) return;

        var extra = names.length - max;
        var collapse = function () {
            names.forEach(function (n, i) { n.classList.toggle('is-hidden', i >= max); });
            more.textContent = '+' + extra + ' ' + pluralPeople(extra);
        };
        var more = document.createElement('button');
        more.type = 'button';
        more.className = 'gr-roster-more';
        var expanded = false;
        more.addEventListener('click', function () {
            expanded = !expanded;
            if (expanded) {
                names.forEach(function (n) { n.classList.remove('is-hidden'); });
                more.textContent = 'pokaż mniej';
            } else {
                collapse();
            }
        });
        collapse();
        roster.appendChild(more);
    });

    // --- podsumowanie tygodnia (twoich zmian / total) ----------------------
    var summary = document.querySelector('[data-week-summary]');
    if (summary) {
        var mine = 0;
        var total = 0;
        Object.keys(days).forEach(function (d) {
            var x = days[d];
            total += 2;
            if (x.assignedMorning === '1') mine++;
            if (x.assignedAfternoon === '1') mine++;
        });
        summary.innerHTML = 'w tym tygodniu twoich zmian: <strong>' + mine + '</strong> / ' + total;
    }
});
