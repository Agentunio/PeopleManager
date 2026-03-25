import {
    buildTimeString,
    validateHoursInputs,
    formatMinutesToHours,
    setupTimeInputAutopad,
    padTime,
    SHIFT_HOURS_AVAILABLE_FROM,
    SHIFT_HOURS_LABELS
} from './worker-time-utils.js';

document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.getElementById('shiftModalOverlay');
    var scheduleConfig = document.getElementById('scheduleConfig');

    document.querySelectorAll('.cal-day:not(.clickable)').forEach(function (day) {
        day.addEventListener('click', function () {
            if (this.classList.contains('locked')) {
                showToast.error('Nie można edytować dostępności dla przeszłych dni');
            } else if (this.classList.contains('out-of-schedule')) {
                showToast.error('Ten dzień wykracza poza zakres aktywnego grafiku');
            } else {
                showToast.error('Grafik jest nieaktywny. Zapisywanie na zmiany jest wyłączone');
            }
        });
    });

    if (!overlay || !scheduleConfig) return;

    var hoursUrl = scheduleConfig.dataset.hoursUrl;
    var availabilityUrl = scheduleConfig.dataset.availabilityUrl;

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
    var morningHoursGroup = document.getElementById('morningHoursGroup');
    var afternoonHoursGroup = document.getElementById('afternoonHoursGroup');
    var morningHoursInputs = document.getElementById('morningHoursInputs');
    var afternoonHoursInputs = document.getElementById('afternoonHoursInputs');
    var morningAdminInfo = document.getElementById('morningAdminInfo');
    var afternoonAdminInfo = document.getElementById('afternoonAdminInfo');
    var morningAdminHours = document.getElementById('morningAdminHours');
    var afternoonAdminHours = document.getElementById('afternoonAdminHours');
    var morningTimeNote = document.getElementById('morningTimeNote');
    var afternoonTimeNote = document.getElementById('afternoonTimeNote');
    var morningAbsentInfo = document.getElementById('morningAbsentInfo');
    var afternoonAbsentInfo = document.getElementById('afternoonAbsentInfo');

    var morningFromHour = document.getElementById('morningFromHour');
    var morningFromMinute = document.getElementById('morningFromMinute');
    var morningToHour = document.getElementById('morningToHour');
    var morningToMinute = document.getElementById('morningToMinute');
    var afternoonFromHour = document.getElementById('afternoonFromHour');
    var afternoonFromMinute = document.getElementById('afternoonFromMinute');
    var afternoonToHour = document.getElementById('afternoonToHour');
    var afternoonToMinute = document.getElementById('afternoonToMinute');

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

    function setTimeInputsDisabled(fromH, fromM, toH, toM, disabled) {
        fromH.disabled = disabled;
        fromM.disabled = disabled;
        toH.disabled = disabled;
        toM.disabled = disabled;
    }

    function clearTimeInputs(fromH, fromM, toH, toM) {
        fromH.value = '';
        fromM.value = '';
        toH.value = '';
        toM.value = '';
    }

    function prefillTimeInputs(fromH, fromM, toH, toM, fromStr, toStr) {
        var from = parseTimeValue(fromStr);
        var to = parseTimeValue(toStr);
        fromH.value = from ? padTime(from.hour) : '';
        fromM.value = from ? padTime(from.minute) : '';
        toH.value = to ? padTime(to.hour) : '';
        toM.value = to ? padTime(to.minute) : '';
    }

    function setupHoursGroup(groupEl, inputsEl, adminInfoEl, adminHoursEl, absentInfoEl, timeNoteEl, fromH, fromM, toH, toM, data) {
        if (!data.assigned) {
            groupEl.style.display = 'none';
            return;
        }

        groupEl.style.display = '';

        if (data.status === 'absent') {
            absentInfoEl.style.display = '';
            adminInfoEl.style.display = 'none';
            inputsEl.style.display = 'none';
            timeNoteEl.style.display = 'none';
            setTimeInputsDisabled(fromH, fromM, toH, toM, true);
            return;
        }

        absentInfoEl.style.display = 'none';

        if (data.source === 'admin') {
            adminInfoEl.style.display = '';
            inputsEl.style.display = 'none';
            timeNoteEl.style.display = 'none';
            adminHoursEl.textContent = data.minutes ? formatMinutesToHours(parseInt(data.minutes)) : '—';
            setTimeInputsDisabled(fromH, fromM, toH, toM, true);
            return;
        }

        adminInfoEl.style.display = 'none';
        inputsEl.style.display = '';
        setTimeInputsDisabled(fromH, fromM, toH, toM, false);

        if (data.from && data.to) {
            prefillTimeInputs(fromH, fromM, toH, toM, data.from, data.to);
        } else {
            clearTimeInputs(fromH, fromM, toH, toM);
        }

        if (data.isToday) {
            var now = new Date();
            var currentMins = now.getHours() * 60 + now.getMinutes();
            var allowedFrom = SHIFT_HOURS_AVAILABLE_FROM[data.shiftType];
            var label = SHIFT_HOURS_LABELS[data.shiftType];

            if (currentMins < allowedFrom) {
                setTimeInputsDisabled(fromH, fromM, toH, toM, true);
                timeNoteEl.textContent = 'Godziny można wpisać po ' + label;
                timeNoteEl.style.display = '';
                return;
            }
        }

        timeNoteEl.style.display = 'none';
    }

    document.querySelectorAll('.cal-day.clickable').forEach(function (day) {
        day.addEventListener('click', function () {
            selectedDate = this.dataset.date;
            selectedDayEl = this;
            var d = new Date(selectedDate + 'T00:00:00');
            modalDate.textContent = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();

            var assignedMorning = this.dataset.assignedMorning === '1';
            var assignedAfternoon = this.dataset.assignedAfternoon === '1';
            var isToday = this.classList.contains('today');
            var isPast = this.dataset.isPast === '1';
            var isCurrentWeek = this.dataset.currentWeek === '1';

            morningCheckbox.checked = this.dataset.morning === '1';
            afternoonCheckbox.checked = this.dataset.afternoon === '1';

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
                setupHoursGroup(morningHoursGroup, morningHoursInputs, morningAdminInfo, morningAdminHours, morningAbsentInfo, morningTimeNote, morningFromHour, morningFromMinute, morningToHour, morningToMinute, {
                    assigned: assignedMorning,
                    source: this.dataset.morningSource,
                    status: this.dataset.morningStatus,
                    from: this.dataset.morningFrom,
                    to: this.dataset.morningTo,
                    minutes: this.dataset.morningMinutes,
                    isToday: isToday,
                    shiftType: 'morning'
                });
                setupHoursGroup(afternoonHoursGroup, afternoonHoursInputs, afternoonAdminInfo, afternoonAdminHours, afternoonAbsentInfo, afternoonTimeNote, afternoonFromHour, afternoonFromMinute, afternoonToHour, afternoonToMinute, {
                    assigned: assignedAfternoon,
                    source: this.dataset.afternoonSource,
                    status: this.dataset.afternoonStatus,
                    from: this.dataset.afternoonFrom,
                    to: this.dataset.afternoonTo,
                    minutes: this.dataset.afternoonMinutes,
                    isToday: isToday,
                    shiftType: 'afternoon'
                });
                hasAnyHoursToShow = true;
            } else {
                morningHoursGroup.style.display = 'none';
                afternoonHoursGroup.style.display = 'none';
            }

            hoursSection.style.display = hasAnyHoursToShow ? '' : 'none';

            var allAdminApproved = showHours
                && (!assignedMorning || this.dataset.morningSource === 'admin' || this.dataset.morningStatus === 'absent')
                && (!assignedAfternoon || this.dataset.afternoonSource === 'admin' || this.dataset.afternoonStatus === 'absent');

            var readonly = isPastDay || ((assignedMorning && assignedAfternoon) || isToday);
            var hideAvailabilitySave = readonly && !showHours;
            var hideSaveCompletely = hideAvailabilitySave && allAdminApproved;

            saveBtn.style.display = hideSaveCompletely ? 'none' : '';

            if (isPastDay && showHours) {
                modalTitle.textContent = 'Wpisz godziny pracy';
                saveBtn.style.display = allAdminApproved ? 'none' : '';
            } else if (readonly && showHours) {
                modalTitle.textContent = 'Twoje zmiany';
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

    function submitHours(shiftType, fromHourInput, fromMinuteInput, toHourInput, toMinuteInput) {
        var fromTime = buildTimeString(fromHourInput, fromMinuteInput);
        var toTime = buildTimeString(toHourInput, toMinuteInput);

        if (!fromTime || !toTime || fromHourInput.disabled) return Promise.resolve(null);

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

    saveBtn.addEventListener('click', function () {
        if (!selectedDate) return;

        var dayEl = selectedDayEl;
        var isPast = dayEl.dataset.isPast === '1';
        var isToday = dayEl.classList.contains('today');
        var isCurrentWeek = dayEl.dataset.currentWeek === '1';
        var assignedMorning = dayEl.dataset.assignedMorning === '1';
        var assignedAfternoon = dayEl.dataset.assignedAfternoon === '1';
        var shouldSubmitHours = isCurrentWeek && (isPast || isToday);

        if (shouldSubmitHours) {
            if (assignedMorning && dayEl.dataset.morningSource !== 'admin' && dayEl.dataset.morningStatus !== 'absent' && !morningFromHour.disabled) {
                var mVal = validateHoursInputs(morningFromHour, morningFromMinute, morningToHour, morningToMinute);
                if (!mVal.valid) {
                    showToast.error('Zmiana ranna: ' + mVal.error);
                    return;
                }
            }
            if (assignedAfternoon && dayEl.dataset.afternoonSource !== 'admin' && dayEl.dataset.afternoonStatus !== 'absent' && !afternoonFromHour.disabled) {
                var aVal = validateHoursInputs(afternoonFromHour, afternoonFromMinute, afternoonToHour, afternoonToMinute);
                if (!aVal.valid) {
                    showToast.error('Zmiana popołudniowa: ' + aVal.error);
                    return;
                }
            }
        }

        saveBtn.disabled = true;

        var hoursPromises = [];
        if (shouldSubmitHours) {
            if (assignedMorning && dayEl.dataset.morningSource !== 'admin' && dayEl.dataset.morningStatus !== 'absent' && !morningFromHour.disabled) {
                var mResult = validateHoursInputs(morningFromHour, morningFromMinute, morningToHour, morningToMinute);
                if (!mResult.empty) {
                    hoursPromises.push(
                        submitHours('morning', morningFromHour, morningFromMinute, morningToHour, morningToMinute)
                            .then(function () {
                                dayEl.dataset.morningFrom = mResult.from;
                                dayEl.dataset.morningTo = mResult.to;
                                dayEl.dataset.morningSource = 'worker';
                            })
                    );
                }
            }
            if (assignedAfternoon && dayEl.dataset.afternoonSource !== 'admin' && dayEl.dataset.afternoonStatus !== 'absent' && !afternoonFromHour.disabled) {
                var aResult = validateHoursInputs(afternoonFromHour, afternoonFromMinute, afternoonToHour, afternoonToMinute);
                if (!aResult.empty) {
                    hoursPromises.push(
                        submitHours('afternoon', afternoonFromHour, afternoonFromMinute, afternoonToHour, afternoonToMinute)
                            .then(function () {
                                dayEl.dataset.afternoonFrom = aResult.from;
                                dayEl.dataset.afternoonTo = aResult.to;
                                dayEl.dataset.afternoonSource = 'worker';
                            })
                    );
                }
            }
        }

        var skipAvailability = isPast || isToday;
        var availabilityPromise = skipAvailability
            ? Promise.resolve(null)
            : submitAvailability().then(function () {
                var morning = morningCheckbox.checked;
                var afternoon = afternoonCheckbox.checked;

                dayEl.dataset.morning = morning ? '1' : '0';
                dayEl.dataset.afternoon = afternoon ? '1' : '0';

                var badges = dayEl.querySelector('.shift-badges');
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
