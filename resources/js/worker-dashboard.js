import { validateHoursInputs, setupTimeInputAutopad } from './worker-time-utils.js';

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('dashboardHoursForm');
    var saveBtn = document.getElementById('dashboardSaveHours');
    if (!form || !saveBtn) return;

    var date = form.dataset.date;
    var hoursUrl = form.dataset.hoursUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    setupTimeInputAutopad(form);
    initEditButtons();
    updateSaveBtnVisibility();

    function initEditButtons() {
        form.querySelectorAll('.dash-edit-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var container = btn.closest('.dashboard-shift-content');
                var savedInfo = container.querySelector('.dash-saved-info');
                var inputs = container.querySelector('.dash-hours-inputs');
                var cancelBtn = container.querySelector('.dash-cancel-btn');
                if (savedInfo) savedInfo.style.display = 'none';
                if (inputs) inputs.style.display = '';
                if (cancelBtn) cancelBtn.style.display = '';
                saveBtn.style.display = '';
            });
        });

        form.querySelectorAll('.dash-cancel-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var container = btn.closest('.dashboard-shift-content');
                var savedInfo = container.querySelector('.dash-saved-info');
                var inputs = container.querySelector('.dash-hours-inputs');
                if (savedInfo) savedInfo.style.display = '';
                if (inputs) inputs.style.display = 'none';
                btn.style.display = 'none';
                updateSaveBtnVisibility();
            });
        });
    }

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
            return $.ajax({
                url: hoursUrl.replace(':date', date),
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                contentType: 'application/json',
                data: JSON.stringify(sub.payload)
            }).then(function (response) {
                if (response.html) {
                    var container = form.querySelector('.dashboard-shift-content[data-shift-type="' + sub.shiftType + '"]');
                    if (container) {
                        container.innerHTML = response.html;
                        setupTimeInputAutopad(container);
                    }
                }
            });
        });

        Promise.all(promises)
            .then(function () {
                initEditButtons();
                updateSaveBtnVisibility();
                showToast.success('Godziny zapisane');
            })
            .catch(function (xhr) {
                var msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Wystąpił błąd';
                showToast.error(msg);
            })
            .finally(function () { saveBtn.disabled = false; });
    });
});
