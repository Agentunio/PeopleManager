import {
    buildTimeString,
    validateHoursInputs,
    formatMinutesToHours,
    setupTimeInputAutopad,
    padTime
} from './worker-time-utils.js';

document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.getElementById('shiftModalOverlay');

    function getDayData(dateStr) {
        return window.scheduleDays[dateStr];
    }

    document.querySelectorAll('.cal-day:not(.clickable)').forEach(function (day) {
        day.addEventListener('click', function () {
            if (this.classList.contains('locked')) {
                showToast.error('Nie można edytować dostępności dla przeszłych dni');
            } else if (this.classList.contains('out-of-schedule')) {
                showToast.error('Ten dzień wykracza poza zakres aktywnego grafiku');
            } else {
                showToast.error('Grafik jest nieaktywny');
            }
        });
    });

    if (!overlay || !window.scheduleConfig) return;

    var hoursUrl = window.scheduleConfig.hoursUrl;
    var availabilityUrl = window.scheduleConfig.availabilityUrl;

    var modalDate = document.getElementById('shiftModalDate');
    var modalTitle = document.getElementById('shiftModalTitle');
    var closeBtn = document.getElementById('shiftModalClose');
    var cancelBtn = document.getElementById('shiftModalCancel');
    var saveBtn = document.getElementById('shiftModalSave');
    var morningCheckbox = document.getElementById('shiftMorning');
    var afternoonCheckbox = document.getElementById('shiftAfternoon');
    var morningOption = morningCheckbox.closest('.shift-option');
    var afternoonOption = afternoonCheckbox.closest('.shift-option');
    var hoursSection = document.getElementById('shiftHoursSection');

    function buildShiftEls(prefix) {
        return {
            group: document.getElementById(prefix + 'HoursGroup'),
            inputs: document.getElementById(prefix + 'HoursInputs'),
            adminInfo: document.getElementById(prefix + 'AdminInfo'),
            adminHours: document.getElementById(prefix + 'AdminHours'),
            absentInfo: document.getElementById(prefix + 'AbsentInfo'),
            savedInfo: document.getElementById(prefix + 'SavedInfo'),
            savedTimes: document.getElementById(prefix + 'SavedTimes'),
            cancelBtn: document.getElementById(prefix + 'CancelBtn'),
            timeNote: document.getElementById(prefix + 'TimeNote'),
            fromH: document.getElementById(prefix + 'FromHour'),
            fromM: document.getElementById(prefix + 'FromMinute'),
            toH: document.getElementById(prefix + 'ToHour'),
            toM: document.getElementById(prefix + 'ToMinute'),
            editBtn: document.getElementById(prefix + 'EditBtn'),
        };
    }

    var shifts = {
        morning: buildShiftEls('morning'),
        afternoon: buildShiftEls('afternoon'),
    };

    var months = [
        'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca',
        'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'
    ];

    var selectedDate = null;
    var selectedDayEl = null;

    setupTimeInputAutopad(overlay);

    [morningOption, afternoonOption].forEach(function (option) {
        option.addEventListener('click', function (e) {
            if (option.classList.contains('assigned')) {
                e.preventDefault();
                if (selectedDayEl && selectedDayEl.classList.contains('today')) {
                    showToast.error('Nie można edytować dostępności dla dnia dzisiejszego');
                } else {
                    showToast.error('Zostałeś przypisany na tę zmianę i nie możesz się wypisać');
                }
            }
        });
    });

    function setOptionLocked(option, checkbox, locked) {
        if (locked) {
            checkbox.disabled = true;
            option.classList.add('assigned');
        } else {
            checkbox.disabled = false;
            option.classList.remove('assigned');
        }
    }

    function parseTimeValue(str) {
        if (!str) return null;
        var parts = str.split(':');
        return { hour: parseInt(parts[0], 10), minute: parseInt(parts[1], 10) };
    }

    function setTimeInputsDisabled(s, disabled) {
        s.fromH.disabled = disabled;
        s.fromM.disabled = disabled;
        s.toH.disabled = disabled;
        s.toM.disabled = disabled;
    }

    function clearTimeInputs(s) {
        s.fromH.value = '';
        s.fromM.value = '';
        s.toH.value = '';
        s.toM.value = '';
    }

    function prefillTimeInputs(s, fromStr, toStr) {
        var from = parseTimeValue(fromStr);
        var to = parseTimeValue(toStr);
        s.fromH.value = from ? padTime(from.hour) : '';
        s.fromM.value = from ? padTime(from.minute) : '';
        s.toH.value = to ? padTime(to.hour) : '';
        s.toM.value = to ? padTime(to.minute) : '';
    }

    function setupHoursGroup(s, data) {
        var selfHoursEnabled = window.scheduleConfig.workerSelfHoursEnabled;
        s.cancelBtn.style.display = 'none';

        if (!data.assigned) {
            s.group.style.display = 'none';
            return false;
        }

        var hasReadOnlyContent = data.status === 'absent'
            || data.source === 'admin'
            || (data.source === 'worker' && data.from && data.to);

        if (!selfHoursEnabled && !hasReadOnlyContent) {
            s.group.style.display = 'none';
            return false;
        }

        s.group.style.display = '';

        if (data.status === 'absent') {
            s.absentInfo.style.display = '';
            s.adminInfo.style.display = 'none';
            s.savedInfo.style.display = 'none';
            s.inputs.style.display = 'none';
            s.timeNote.style.display = 'none';
            setTimeInputsDisabled(s, true);
            return true;
        }

        s.absentInfo.style.display = 'none';

        if (data.source === 'admin') {
            s.adminInfo.style.display = '';
            s.savedInfo.style.display = 'none';
            s.inputs.style.display = 'none';
            s.timeNote.style.display = 'none';
            s.adminHours.textContent = data.minutes ? formatMinutesToHours(parseInt(data.minutes)) : '—';
            setTimeInputsDisabled(s, true);
            return true;
        }

        s.adminInfo.style.display = 'none';

        if (data.source === 'worker' && data.from && data.to) {
            s.savedInfo.style.display = '';
            s.savedTimes.textContent = data.from + ' — ' + data.to;
            s.inputs.style.display = 'none';
            s.timeNote.style.display = 'none';
            prefillTimeInputs(s, data.from, data.to);

            if (selfHoursEnabled) {
                s.editBtn.style.display = '';
                setTimeInputsDisabled(s, false);
            } else {
                s.editBtn.style.display = 'none';
                setTimeInputsDisabled(s, true);
            }
            return true;
        }

        if (!selfHoursEnabled) {
            s.group.style.display = 'none';
            return false;
        }

        s.savedInfo.style.display = 'none';
        s.inputs.style.display = '';
        setTimeInputsDisabled(s, false);
        clearTimeInputs(s);

        if (data.isToday) {
            var now = new Date();
            var currentMins = now.getHours() * 60 + now.getMinutes();
            var allowedFrom = data.unlockMinutes;
            var label = data.unlockLabel;

            if (currentMins < allowedFrom) {
                setTimeInputsDisabled(s, true);
                s.timeNote.textContent = 'Godziny można wpisać po ' + label;
                s.timeNote.style.display = '';
                return true;
            }
        }

        s.timeNote.style.display = 'none';
        return true;
    }

    ['morning', 'afternoon'].forEach(function (type) {
        var s = shifts[type];

        s.editBtn.addEventListener('click', function () {
            s.savedInfo.style.display = 'none';
            s.inputs.style.display = '';
            s.cancelBtn.style.display = '';
        });

        s.cancelBtn.addEventListener('click', function () {
            s.inputs.style.display = 'none';
            s.cancelBtn.style.display = 'none';
            s.savedInfo.style.display = '';
        });
    });

    document.querySelectorAll('.cal-day.clickable').forEach(function (day) {
        day.addEventListener('click', function () {
            selectedDate = this.dataset.date;
            selectedDayEl = this;
            var dayData = getDayData(selectedDate);
            var d = new Date(selectedDate + 'T00:00:00');
            modalDate.textContent = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();

            var assignedMorning = dayData.assignedMorning === '1';
            var assignedAfternoon = dayData.assignedAfternoon === '1';
            var isToday = this.classList.contains('today');
            var isPast = dayData.isPast === '1';
            var isCurrentWeek = dayData.currentWeek === '1';

            morningCheckbox.checked = dayData.morning === '1';
            afternoonCheckbox.checked = dayData.afternoon === '1';

            var isPastDay = isPast && !isToday;

            if (isPastDay) {
                setOptionLocked(morningOption, morningCheckbox, true);
                setOptionLocked(afternoonOption, afternoonCheckbox, true);
            } else {
                setOptionLocked(morningOption, morningCheckbox, assignedMorning || isToday);
                setOptionLocked(afternoonOption, afternoonCheckbox, assignedAfternoon || isToday);
            }

            var showHours = isCurrentWeek && (isPast || isToday) && (assignedMorning || assignedAfternoon);

            var hasAnyHoursToShow = false;
            if (showHours) {
                var morningVisible = setupHoursGroup(shifts.morning, {
                    assigned: assignedMorning,
                    source: dayData.morningSource,
                    status: dayData.morningStatus,
                    from: dayData.morningFrom,
                    to: dayData.morningTo,
                    minutes: dayData.morningMinutes,
                    isToday: isToday,
                    shiftType: 'morning',
                    unlockMinutes: dayData.morningUnlockMinutes,
                    unlockLabel: dayData.morningUnlockLabel
                });
                var afternoonVisible = setupHoursGroup(shifts.afternoon, {
                    assigned: assignedAfternoon,
                    source: dayData.afternoonSource,
                    status: dayData.afternoonStatus,
                    from: dayData.afternoonFrom,
                    to: dayData.afternoonTo,
                    minutes: dayData.afternoonMinutes,
                    isToday: isToday,
                    shiftType: 'afternoon',
                    unlockMinutes: dayData.afternoonUnlockMinutes,
                    unlockLabel: dayData.afternoonUnlockLabel
                });
                hasAnyHoursToShow = morningVisible || afternoonVisible;
            } else {
                shifts.morning.group.style.display = 'none';
                shifts.afternoon.group.style.display = 'none';
            }

            hoursSection.style.display = hasAnyHoursToShow ? '' : 'none';

            var selfHoursEnabled = window.scheduleConfig.workerSelfHoursEnabled;
            var allAdminApproved = showHours
                && (!assignedMorning || dayData.morningSource === 'admin' || dayData.morningStatus === 'absent')
                && (!assignedAfternoon || dayData.afternoonSource === 'admin' || dayData.afternoonStatus === 'absent');

            var readonly = isPastDay || ((assignedMorning && assignedAfternoon) || isToday);
            var hideAvailabilitySave = readonly && !showHours;
            var noHoursSubmitPossible = !selfHoursEnabled || allAdminApproved;
            var hideSaveCompletely = hideAvailabilitySave && noHoursSubmitPossible;

            saveBtn.style.display = hideSaveCompletely ? 'none' : '';

            if (isPastDay && showHours) {
                modalTitle.textContent = 'Wpisz godziny pracy';
                saveBtn.style.display = (allAdminApproved || !selfHoursEnabled) ? 'none' : '';
            } else if (readonly && showHours) {
                modalTitle.textContent = 'Twoje zmiany';
                if (!selfHoursEnabled) {
                    saveBtn.style.display = 'none';
                }
            } else if (readonly) {
                modalTitle.textContent = 'Twoje zmiany';
                saveBtn.style.display = 'none';
            } else {
                modalTitle.textContent = 'Zapisz się na zmianę';
            }

            overlay.classList.add('active');
        });
    });

    function closeModal() {
        overlay.classList.remove('active');
        selectedDate = null;
        selectedDayEl = null;
    }

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    function submitHours(s, shiftType) {
        var fromTime = buildTimeString(s.fromH, s.fromM);
        var toTime = buildTimeString(s.toH, s.toM);

        if (!fromTime || !toTime || s.fromH.disabled) return Promise.resolve(null);

        return $.ajax({
            url: hoursUrl.replace(':date', selectedDate),
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            contentType: 'application/json',
            data: JSON.stringify({
                shift_type: shiftType,
                from_time: fromTime,
                to_time: toTime,
            }),
        });
    }

    function submitAvailability() {
        return $.ajax({
            url: availabilityUrl.replace(':date', selectedDate),
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            contentType: 'application/json',
            data: JSON.stringify({
                morning_shift: morningCheckbox.checked,
                afternoon_shift: afternoonCheckbox.checked,
            }),
        });
    }

    function canSubmitShift(dateStr, type) {
        if (!window.scheduleConfig.workerSelfHoursEnabled) return false;
        var dayData = getDayData(dateStr);
        var s = shifts[type];
        return dayData[type + 'Source'] !== 'admin'
            && dayData[type + 'Status'] !== 'absent'
            && !s.fromH.disabled;
    }

    saveBtn.addEventListener('click', function () {
        if (!selectedDate) return;

        var dayData = getDayData(selectedDate);
        var isPast = dayData.isPast === '1';
        var isToday = selectedDayEl.classList.contains('today');
        var isCurrentWeek = dayData.currentWeek === '1';
        var assignedMorning = dayData.assignedMorning === '1';
        var assignedAfternoon = dayData.assignedAfternoon === '1';
        var shouldSubmitHours = window.scheduleConfig.workerSelfHoursEnabled && isCurrentWeek && (isPast || isToday);

        if (shouldSubmitHours) {
            var shiftChecks = [
                { type: 'morning', assigned: assignedMorning, label: 'Zmiana ranna' },
                { type: 'afternoon', assigned: assignedAfternoon, label: 'Zmiana popołudniowa' },
            ];

            for (var i = 0; i < shiftChecks.length; i++) {
                var check = shiftChecks[i];
                if (check.assigned && canSubmitShift(selectedDate, check.type)) {
                    var s = shifts[check.type];
                    var val = validateHoursInputs(s.fromH, s.fromM, s.toH, s.toM);
                    if (!val.valid) {
                        showToast.error(check.label + ': ' + val.error);
                        return;
                    }
                }
            }
        }

        saveBtn.disabled = true;

        var hoursPromises = [];
        if (shouldSubmitHours) {
            [{ type: 'morning', assigned: assignedMorning },
             { type: 'afternoon', assigned: assignedAfternoon }].forEach(function (item) {
                if (!item.assigned || !canSubmitShift(selectedDate, item.type)) return;
                var s = shifts[item.type];
                var result = validateHoursInputs(s.fromH, s.fromM, s.toH, s.toM);
                if (result.empty) return;

                hoursPromises.push(
                    submitHours(s, item.type).then(function () {
                        var dayData = getDayData(selectedDate);
                        dayData[item.type + 'From'] = result.from;
                        dayData[item.type + 'To'] = result.to;
                        dayData[item.type + 'Source'] = 'worker';
                    })
                );
            });
        }

        var skipAvailability = isPast || isToday;
        var availabilityPromise = skipAvailability
            ? Promise.resolve(null)
            : submitAvailability().then(function () {
                var morning = morningCheckbox.checked;
                var afternoon = afternoonCheckbox.checked;

                var dayData = getDayData(selectedDate);
                dayData.morning = morning ? '1' : '0';
                dayData.afternoon = afternoon ? '1' : '0';

                var badges = selectedDayEl.querySelector('.shift-badges');
                while (badges.firstChild) {
                    badges.removeChild(badges.firstChild);
                }

                if (morning) {
                    var morningBadge = document.createElement('span');
                    morningBadge.className = 'shift-badge morning-badge';
                    morningBadge.textContent = 'R';
                    badges.appendChild(morningBadge);
                }
                if (afternoon) {
                    var afternoonBadge = document.createElement('span');
                    afternoonBadge.className = 'shift-badge afternoon-badge';
                    afternoonBadge.textContent = 'P';
                    badges.appendChild(afternoonBadge);
                }
            });

        Promise.all([availabilityPromise].concat(hoursPromises))
            .then(function () {
                closeModal();
                showToast.success('Zapisano');
            })
            .catch(function (xhr) {
                var message = (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Wystąpił błąd';
                showToast.error(message);
            })
            .finally(function () {
                saveBtn.disabled = false;
            });
    });
});
