export function padTime(n) {
    return n < 10 ? '0' + n : '' + n;
}

export function buildTimeString(hourInput, minuteInput) {
    var h = hourInput.value.trim();
    if (h === '') return null;
    var hour = parseInt(h, 10);
    if (isNaN(hour) || hour < 0 || hour > 23) return null;
    var m = minuteInput.value.trim();
    var minute = (m === '' || isNaN(parseInt(m, 10))) ? 0 : parseInt(m, 10);
    if (minute < 0 || minute > 59) return null;
    return padTime(hour) + ':' + padTime(minute);
}

export function validateHoursInputs(fromH, fromM, toH, toM) {
    var fh = fromH.value.trim();
    var th = toH.value.trim();
    var bothEmpty = fh === '' && th === '';
    if (bothEmpty) return { valid: true, empty: true };

    if (fh === '' || th === '') {
        return { valid: false, error: 'Wypełnij obie godziny (Od i Do)' };
    }

    var fromTime = buildTimeString(fromH, fromM);
    var toTime = buildTimeString(toH, toM);

    if (!fromTime) {
        return { valid: false, error: 'Nieprawidłowa godzina rozpoczęcia (0-23, minuty 0-59)' };
    }
    if (!toTime) {
        return { valid: false, error: 'Nieprawidłowa godzina zakończenia (0-23, minuty 0-59)' };
    }
    if (fromTime >= toTime) {
        return { valid: false, error: 'Godzina zakończenia musi być późniejsza niż rozpoczęcia' };
    }

    return { valid: true, empty: false, from: fromTime, to: toTime };
}

export function formatMinutesToHours(minutes) {
    var h = Math.floor(minutes / 60);
    var m = minutes % 60;
    var result = '';
    if (h > 0) result += h + 'h';
    if (m > 0) result += (h > 0 ? ' ' : '') + m + 'min';
    return result || '0h';
}

export function setupTimeInputAutopad(container) {
    container.querySelectorAll('input[type="number"]').forEach(function (input) {
        input.addEventListener('blur', function () {
            if (this.value !== '' && !isNaN(parseInt(this.value, 10))) {
                var val = parseInt(this.value, 10);
                this.value = padTime(val);
            }
        });
    });
}
