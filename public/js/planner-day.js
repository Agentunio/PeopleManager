$(document).ready(function() {
    const $dropzones = $('.shift-dropzone');

    function getWorkerAvailability(workerId) {
        const $workerCard = $(`.worker-card[data-worker-id="${workerId}"]`);
        if ($workerCard.length === 0) {
            return { morning: false, afternoon: false };
        }
        return {
            morning: $workerCard.data('morning') === true || $workerCard.data('morning') === 'true',
            afternoon: $workerCard.data('afternoon') === true || $workerCard.data('afternoon') === 'true'
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

    function updateWorkerCardVisibility(workerId) {
        const $workerCard = $(`.worker-card[data-worker-id="${workerId}"]`);
        if ($workerCard.length === 0) return;

        const availability = getWorkerAvailability(workerId);
        const assignedMorning = isWorkerAssignedToShift(workerId, 'morning');
        const assignedAfternoon = isWorkerAssignedToShift(workerId, 'afternoon');

        if (availability.morning && availability.afternoon) {
            if (assignedMorning && assignedAfternoon) {
                $workerCard.hide();
            } else {
                $workerCard.show();
                $workerCard.find('.badge-morning').toggle(!assignedMorning);
                $workerCard.find('.badge-afternoon').toggle(!assignedAfternoon);
            }
        } else {
            if (assignedMorning || assignedAfternoon) {
                $workerCard.hide();
            } else {
                $workerCard.show();
            }
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

        const hiddenInput =
            `<input type="hidden" name="workers[${workerId}][worker_id]" value="${workerId}" data-worker-id="${workerId}">` +
            `<input type="hidden" name="workers[${workerId}][shift_type]" value="${shiftType}" data-worker-id="${workerId}">`;

        $dropzone.find('.assigned-workers').append($workerElement);
        $dropzone.find('.hidden-inputs').append(hiddenInput);

        makeAssignedWorkerDraggable($workerElement);
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

    $(document).on('click', '.remove-worker', function(e) {
        e.stopPropagation();

        const $dropzone = $(this).closest('.shift-dropzone');
        const workerId = $(this).data('worker-id');

        $(this).closest('.assigned-worker').remove();
        $dropzone.find(`.hidden-inputs input[data-worker-id="${workerId}"]`).remove();

        updatePlaceholder($dropzone);
        updateCounts();
        updateWorkerCardVisibility(workerId);
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

    initDragAndDrop();

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
                    showToast.success(response.message);
                    $('#availability-modal').fadeOut(200);
                    $('#workers-list').html(response.html);
                    initDragAndDrop();
                    removeUnavailableWorkers();
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
});
