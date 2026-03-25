import { validateHoursInputs, setupTimeInputAutopad } from './worker-time-utils.js';

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('dashboardHoursForm');
    var saveBtn = document.getElementById('dashboardSaveHours');
    if (!form || !saveBtn) return;

    var date = form.dataset.date;
    var hoursUrl = form.dataset.hoursUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    setupTimeInputAutopad(form);

    saveBtn.addEventListener('click', function () {
        var groups = form.querySelectorAll('.dash-hours-inputs');
        var payloads = [];
        var hasError = false;

        groups.forEach(function (group) {
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

            payloads.push({ shift_type: shiftType, from_time: result.from, to_time: result.to });
        });

        if (hasError || payloads.length === 0) return;

        saveBtn.disabled = true;
        var promises = payloads.map(function (payload) {
            return $.ajax({
                url: hoursUrl.replace(':date', date),
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                contentType: 'application/json',
                data: JSON.stringify(payload)
            });
        });

        Promise.all(promises)
            .then(function () { showToast.success('Godziny zapisane'); })
            .catch(function (xhr) {
                var msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Wystąpił błąd';
                showToast.error(msg);
            })
            .finally(function () { saveBtn.disabled = false; });
    });
});
