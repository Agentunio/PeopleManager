$(document).ready(function() {
    const $dropzones = $('.shift-dropzone');
    let isTapMode = false;
    let selectedWorkers = [];

    function isTouchDevice() {
        return ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    }

    function shouldUseTapMode() {
        return window.innerWidth <= 1100 || isTouchDevice();
    }

    function initMode() {
        const newTapMode = shouldUseTapMode();

        if (newTapMode !== isTapMode) {
            isTapMode = newTapMode;

            if (isTapMode) {
                enableTapMode();
            } else {
                disableTapMode();
            }
        }
    }

    function enableTapMode() {
        console.log('Enabling tap mode');
        $('body').addClass('tap-mode-active');

        $('.worker-card.draggable').each(function() {
            if ($(this).data('ui-draggable')) {
                $(this).draggable('disable');
            }
        });

        if ($('.selection-info-bar').length === 0) {
            const infoBar = `
                <div class="selection-info-bar">
                    <div>
                        <span class="selection-count">Zaznaczono: <strong id="selected-count">0</strong> pracowników</span>
                        <span class="selection-hint">Kliknij na zmianę, aby przypisać</span>
                    </div>
                    <button type="button" class="btn-clear-selection">
                        <i class="fas fa-times"></i> Wyczyść
                    </button>
                </div>
            `;
            $('body').append(infoBar);
        }

        if ($('.tap-mode-instruction').length === 0) {
            const instruction = `
                <div class="tap-mode-instruction">
                    <i class="fas fa-hand-pointer"></i>
                    Zaznacz pracowników, następnie kliknij na zmianę
                </div>
            `;
            $('.workers-panel-header').after(instruction);
        }
    }

    function disableTapMode() {
        console.log('Disabling tap mode');
        $('body').removeClass('tap-mode-active selection-active');

        $('.worker-card.draggable').each(function() {
            if ($(this).data('ui-draggable')) {
                $(this).draggable('enable');
            }
        });

        clearSelection();

        $('.selection-info-bar').removeClass('show');
    }

    function getWorkerFromData(workerId) {
        return workersData.find(w => w.id == workerId);
    }

    function getWorkerAvailability(workerId) {
        const worker = getWorkerFromData(workerId);
        if (!worker) {
            return { morning: false, afternoon: false };
        }
        return {
            morning: !!worker.morning,
            afternoon: !!worker.afternoon
        };
    }

    function isWorkerAvailableForShift(workerId, shiftType) {
        const availability = getWorkerAvailability(workerId);
        return shiftType === 'morning' ? availability.morning : availability.afternoon;
    }

    function canWorkerBeOnBothShifts(workerId) {
        const availability = getWorkerAvailability(workerId);
        return availability.morning && availability.afternoon;
    }

    function isWorkerAssignedToShift(workerId, shiftType) {
        const dropzoneId = shiftType === 'morning' ? '#morning-shift' : '#afternoon-shift';
        return $(dropzoneId).find(`.assigned-worker[data-worker-id="${workerId}"]`).length > 0;
    }

    function toggleWorkerSelection(workerId) {
        const index = selectedWorkers.indexOf(workerId);

        if (index > -1) {
            selectedWorkers.splice(index, 1);
            $(`.worker-card[data-worker-id="${workerId}"]`).removeClass('selected');
        } else {
            selectedWorkers.push(workerId);
            $(`.worker-card[data-worker-id="${workerId}"]`).addClass('selected');
        }

        updateSelectionUI();
    }

    function clearSelection() {
        selectedWorkers = [];
        $('.worker-card').removeClass('selected');
        updateSelectionUI();
    }

    function updateSelectionUI() {
        const count = selectedWorkers.length;
        $('#selected-count').text(count);

        if (count > 0) {
            $('.selection-info-bar').addClass('show');
            $('body').addClass('selection-active');
        } else {
            $('.selection-info-bar').removeClass('show');
            $('body').removeClass('selection-active');
        }
    }

    function assignSelectedWorkersToShift(shiftType) {
        if (selectedWorkers.length === 0) {
            showToast.warning('Najpierw zaznacz pracowników');
            return;
        }

        const $dropzone = $(`#${shiftType}-shift`);
        let assignedCount = 0;
        let errors = [];

        selectedWorkers.forEach(workerId => {
            const worker = getWorkerFromData(workerId);
            if (!worker) return;

            if (!isWorkerAvailableForShift(workerId, shiftType)) {
                errors.push(`${worker.name} - niedostępny na tę zmianę`);
                return;
            }

            if ($dropzone.find(`.assigned-worker[data-worker-id="${workerId}"]`).length > 0) {
                errors.push(`${worker.name} - już przypisany`);
                return;
            }

            if (!canWorkerBeOnBothShifts(workerId)) {
                const $otherDropzone = $dropzones.not($dropzone);
                $otherDropzone.find(`.assigned-worker[data-worker-id="${workerId}"]`).remove();
                $otherDropzone.find(`.hidden-inputs input[data-worker-id="${workerId}"]`).remove();
                updatePlaceholder($otherDropzone);
            }

            addWorkerToShift(workerId, worker.name, shiftType, $dropzone);
            updateWorkerCardVisibility(workerId);
            assignedCount++;
        });

        updatePlaceholder($dropzone);
        updateCounts();
        clearSelection();

        if (assignedCount > 0) {
            showToast.success(`Przypisano ${assignedCount} pracowników`);
        }

        if (errors.length > 0) {
            setTimeout(() => {
                showToast.warning(errors[0]);
            }, 500);
        }
    }

    function restoreWorkerCard(workerId) {
        const worker = getWorkerFromData(workerId);
        if (!worker) return;

        const assignedMorning = isWorkerAssignedToShift(workerId, 'morning');
        const assignedAfternoon = isWorkerAssignedToShift(workerId, 'afternoon');

        const freeMorning = worker.morning && !assignedMorning;
        const freeAfternoon = worker.afternoon && !assignedAfternoon;

        if (!freeMorning && !freeAfternoon) return;

        let $workerCard = $(`.worker-card[data-worker-id="${workerId}"]`);

        if ($workerCard.length === 0) {
            let badges = '';
            if (freeMorning) badges += '<span class="badge badge-morning">R</span>';
            if (freeAfternoon) badges += '<span class="badge badge-afternoon">P</span>';

            const cardHtml = `
            <div class="worker-card draggable" data-worker-id="${workerId}" data-morning="${freeMorning}" data-afternoon="${freeAfternoon}">
                <span class="worker-name">${worker.name}</span>
                <div class="worker-availability-badges">
                    ${badges}
                </div>
            </div>
        `;
            $('#workers-list').append(cardHtml);
            initDragAndDrop();
        } else {
            $workerCard.show();
            $workerCard.attr('data-morning', freeMorning);
            $workerCard.attr('data-afternoon', freeAfternoon);

            const $badges = $workerCard.find('.worker-availability-badges');
            $badges.empty();
            if (freeMorning) $badges.append('<span class="badge badge-morning">R</span>');
            if (freeAfternoon) $badges.append('<span class="badge badge-afternoon">P</span>');
        }
    }

    function updateWorkerCardVisibility(workerId) {
        const $workerCard = $(`.worker-card[data-worker-id="${workerId}"]`);
        const availability = getWorkerAvailability(workerId);
        const assignedMorning = isWorkerAssignedToShift(workerId, 'morning');
        const assignedAfternoon = isWorkerAssignedToShift(workerId, 'afternoon');

        const freeMorning = availability.morning && !assignedMorning;
        const freeAfternoon = availability.afternoon && !assignedAfternoon;

        if (!freeMorning && !freeAfternoon) {
            $workerCard.hide();
            // Remove from selection if hidden
            const index = selectedWorkers.indexOf(workerId);
            if (index > -1) {
                selectedWorkers.splice(index, 1);
                updateSelectionUI();
            }
            return;
        }

        if ($workerCard.length === 0) {
            restoreWorkerCard(workerId);
        } else {
            $workerCard.show();
            $workerCard.attr('data-morning', freeMorning);
            $workerCard.attr('data-afternoon', freeAfternoon);
            $workerCard.find('.badge-morning').toggle(freeMorning);
            $workerCard.find('.badge-afternoon').toggle(freeAfternoon);
        }
    }

    function removeUnavailableWorkers() {
        $('.assigned-worker').each(function() {
            const $assigned = $(this);
            const workerId = $assigned.data('worker-id');
            const $dropzone = $assigned.closest('.shift-dropzone');
            const shiftType = $dropzone.data('shift');

            if (!isWorkerAvailableForShift(workerId, shiftType)) {
                $assigned.remove();
                $dropzone.find(`.hidden-inputs input[data-worker-id="${workerId}"]`).remove();
                updatePlaceholder($dropzone);
            }
        });
        updateCounts();
    }

    function initDragAndDrop() {
        $(".worker-card.draggable").draggable({
            helper: "clone",
            revert: "invalid",
            revertDuration: 100,
            cursor: "grabbing",
            zIndex: 1000,
            opacity: 0.9,
            disabled: isTapMode,
            start: function(event, ui) {
                $(this).addClass('dragging');
                $dropzones.addClass('highlight');
            },
            stop: function(event, ui) {
                $(this).removeClass('dragging');
                $dropzones.removeClass('highlight');
            }
        });

        initDropzones();
    }

    function initDropzones() {
        $dropzones.droppable({
            accept: ".worker-card.draggable, .assigned-worker.draggable",
            hoverClass: "dropzone-hover",
            tolerance: "pointer",
            drop: function(event, ui) {
                if (isTapMode) return;

                const $dragged = ui.draggable;
                const $currentDropzone = $(this);
                const shiftType = $currentDropzone.data('shift');

                let workerId, workerName;

                if ($dragged.hasClass('worker-card')) {
                    workerId = $dragged.data('worker-id');
                    workerName = $dragged.find('.worker-name').text();
                } else if ($dragged.hasClass('assigned-worker')) {
                    workerId = $dragged.data('worker-id');
                    workerName = $dragged.find('.worker-name').text();

                    const $oldDropzone = $dragged.closest('.shift-dropzone');
                    if ($oldDropzone.length && $oldDropzone[0] !== $currentDropzone[0]) {
                        $dragged.remove();
                        $oldDropzone.find(`.hidden-inputs input[data-worker-id="${workerId}"]`).remove();
                        updatePlaceholder($oldDropzone);
                    } else if ($oldDropzone[0] === $currentDropzone[0]) {
                        return;
                    }
                }

                if (!isWorkerAvailableForShift(workerId, shiftType)) {
                    showToast.error('Ten pracownik nie jest dostępny na tę zmianę');
                    return;
                }

                if ($currentDropzone.find(`.assigned-worker[data-worker-id="${workerId}"]`).length > 0) {
                    showToast.warning('Ten pracownik jest już przypisany do tej zmiany');
                    return;
                }

                if (!canWorkerBeOnBothShifts(workerId)) {
                    const $otherDropzone = $dropzones.not($currentDropzone);
                    $otherDropzone.find(`.assigned-worker[data-worker-id="${workerId}"]`).remove();
                    $otherDropzone.find(`.hidden-inputs input[data-worker-id="${workerId}"]`).remove();
                    updatePlaceholder($otherDropzone);
                }

                addWorkerToShift(workerId, workerName, shiftType, $currentDropzone);
                updatePlaceholder($currentDropzone);
                updateCounts();
                updateWorkerCardVisibility(workerId);

                showToast.success(`${workerName} przypisany do zmiany`);
            }
        });
    }

    function makeAssignedWorkerDraggable($element) {
        $element.draggable({
            helper: "clone",
            revert: "invalid",
            revertDuration: 100,
            cursor: "grabbing",
            zIndex: 1000,
            opacity: 0.9,
            disabled: isTapMode,
            start: function(event, ui) {
                $(this).addClass('dragging');
                $dropzones.addClass('highlight');
            },
            stop: function(event, ui) {
                $(this).removeClass('dragging');
                $dropzones.removeClass('highlight');
            }
        });
    }

    function addWorkerToShift(workerId, workerName, shiftType, $dropzone) {
        const $workerElement = $(`
        <div class="assigned-worker draggable" data-worker-id="${workerId}">
            <span class="worker-name">${workerName}</span>
            <button type="button" class="remove-worker" data-worker-id="${workerId}">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `);

        const index = `${workerId}_${shiftType}`;
        const hiddenInput =
            `<input type="hidden" name="workers[${index}][worker_id]" value="${workerId}" data-worker-id="${workerId}">` +
            `<input type="hidden" name="workers[${index}][shift_type]" value="${shiftType}" data-worker-id="${workerId}">`;

        $dropzone.find('.assigned-workers').append($workerElement);
        $dropzone.find('.hidden-inputs').append(hiddenInput);

        if (!isTapMode) {
            makeAssignedWorkerDraggable($workerElement);
        }
    }

    function updatePlaceholder($dropzone) {
        const hasWorkers = $dropzone.find('.assigned-worker').length > 0;
        $dropzone.find('.dropzone-placeholder').toggle(!hasWorkers);
    }

    function updateCounts() {
        const morningCount = $('#morning-shift .assigned-worker').length;
        const afternoonCount = $('#afternoon-shift .assigned-worker').length;

        $('#morning-count').text(morningCount);
        $('#afternoon-count').text(afternoonCount);
        $('#total-assigned').text(morningCount + afternoonCount);
    }

    $(document).on('click', '.worker-card', function(e) {
        if (!isTapMode) return;

        e.preventDefault();
        e.stopPropagation();

        const workerId = $(this).data('worker-id');
        toggleWorkerSelection(workerId);
    });

    $(document).on('click', '.shift-dropzone', function(e) {
        if (!isTapMode) return;
        if ($(e.target).closest('.remove-worker').length) return;
        if ($(e.target).closest('.assigned-worker').length) return;

        const shiftType = $(this).data('shift');
        assignSelectedWorkersToShift(shiftType);
    });

    $(document).on('click', '.btn-clear-selection', function() {
        clearSelection();
    });

    $(document).on('click', '.remove-worker', function(e) {
        e.stopPropagation();

        const $dropzone = $(this).closest('.shift-dropzone');
        const workerId = $(this).data('worker-id');

        $(this).closest('.assigned-worker').remove();
        $dropzone.find(`.hidden-inputs input[data-worker-id="${workerId}"]`).remove();

        updatePlaceholder($dropzone);
        updateCounts();
        restoreWorkerCard(workerId);
    });

    $('#change-availability-btn').on('click', function() {
        $('#availability-modal').fadeIn(200);
    });

    $('#close-modal, #cancel-availability').on('click', function(e) {
        e.preventDefault();
        $('#availability-modal').fadeOut(200);
    });

    $('#availability-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).fadeOut(200);
        }
    });

    $('#availability-form').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const url = form.attr('action');

        $.ajax({
            url: url,
            method: 'POST',
            data: form.serialize(),
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.success) {
                    workersData = response.workers;
                    showToast.success(response.message);
                    $('#availability-modal').fadeOut(200);
                    $('#workers-list').html(response.html);
                    clearSelection();
                    initDragAndDrop();
                    removeUnavailableWorkers();

                    initMode();
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    const firstError = Object.values(errors)[0][0];
                    showToast.error(firstError);
                } else {
                    showToast.error('Wystąpił błąd podczas zapisywania');
                }
            }
        });
    });

    let resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            initMode();
        }, 250);
    });

    initDragAndDrop();
    updateCounts();
    initMode();

});
