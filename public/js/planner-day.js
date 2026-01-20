$(document).ready(function() {
    function initDragAndDrop() {
        $(".worker-card.draggable").draggable({
            helper: "clone",
            revert: "invalid",
            cursor: "grabbing",
            zIndex: 1000,
            opacity: 0.8,
            start: function(event, ui) {
                $(this).addClass('dragging');
                $('.shift-dropzone').addClass('highlight');
            },
            stop: function(event, ui) {
                $(this).removeClass('dragging');
                $('.shift-dropzone').removeClass('highlight');
            }
        });

        $(".shift-dropzone").droppable({
            accept: ".worker-card.draggable",
            hoverClass: "dropzone-hover",
            drop: function(event, ui) {
                const workerId = ui.draggable.data('worker-id');
                const workerName = ui.draggable.find('.worker-name').text();
                const shiftType = $(this).data('shift');

                if ($(this).find(`[data-worker-id="${workerId}"]`).length > 0) {
                    showToast.warning('Ten pracownik jest już przypisany do tej zmiany');
                    return;
                }

                const otherDropzone = $(`.shift-dropzone:not([data-shift="${shiftType}"])`);
                otherDropzone.find(`.assigned-worker[data-worker-id="${workerId}"]`).remove();
                otherDropzone.find(`.hidden-inputs input[data-worker-id="${workerId}"]`).remove();
                updatePlaceholder(otherDropzone);

                addWorkerToShift(workerId, workerName, shiftType, $(this));

                updatePlaceholder($(this));
                updateCounts();

                showToast.success(`${workerName} przypisany do zmiany`);
            }
        });
    }

    function addWorkerToShift(workerId, workerName, shiftType, dropzone) {
        const inputName = shiftType === 'morning' ? 'morning_workers[]' : 'afternoon_workers[]';

        const workerHtml = `
            <div class="assigned-worker" data-worker-id="${workerId}">
                <span class="worker-name">${workerName}</span>
                <button type="button" class="remove-worker" data-worker-id="${workerId}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        const hiddenInput = `<input type="hidden" name="${inputName}" value="${workerId}" data-worker-id="${workerId}">`;

        dropzone.find('.assigned-workers').append(workerHtml);
        dropzone.find('.hidden-inputs').append(hiddenInput);
    }

    function updatePlaceholder(dropzone) {
        const hasWorkers = dropzone.find('.assigned-worker').length > 0;
        dropzone.find('.dropzone-placeholder').toggle(!hasWorkers);
    }

    function updateCounts() {
        const morningCount = $('#morning-shift .assigned-worker').length;
        const afternoonCount = $('#afternoon-shift .assigned-worker').length;

        $('#morning-count').text(morningCount);
        $('#afternoon-count').text(afternoonCount);
        $('#total-assigned').text(morningCount + afternoonCount);
    }

    $(document).on('click', '.remove-worker', function() {
        const dropzone = $(this).closest('.shift-dropzone');
        const workerId = $(this).data('worker-id');

        $(this).closest('.assigned-worker').remove();
        dropzone.find(`.hidden-inputs input[data-worker-id="${workerId}"]`).remove();

        updatePlaceholder(dropzone);
        updateCounts();
    });

    $('#change-availability-btn').on('click', function() {
        $('#availability-modal').fadeIn(200);
    });

    $('#close-modal, #cancel-availability').on('click', function() {
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
