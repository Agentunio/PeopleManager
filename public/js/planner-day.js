$(document).ready(function() {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: '#1f1f1f',
        color: '#f0f0f0'
    });

    const showToast = {
        success: (message) => Toast.fire({ icon: 'success', title: message }),
        error: (message) => Toast.fire({ icon: 'error', title: message }),
        warning: (message) => Toast.fire({ icon: 'warning', title: message }),
        info: (message) => Toast.fire({ icon: 'info', title: message })
    };

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

                $(`.shift-dropzone:not([data-shift="${shiftType}"]) [data-worker-id="${workerId}"]`).remove();

                addWorkerToShift(workerId, workerName, shiftType, $(this));

                updatePlaceholder($(this));
                updateCounts();

                showToast.success(`${workerName} przypisany do zmiany`);
            }
        });
    }

    function addWorkerToShift(workerId, workerName, shiftType, dropzone) {
        const workerHtml = `
            <div class="assigned-worker" data-worker-id="${workerId}">
                <span class="worker-name">${workerName}</span>
                <button class="remove-worker" data-worker-id="${workerId}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        dropzone.find('.assigned-workers').append(workerHtml);
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
        $(this).closest('.assigned-worker').remove();
        updatePlaceholder(dropzone);
        updateCounts();
    });

    $('#change-availability-btn').on('click', function() {
        $('#availability-modal').fadeIn(200);
    });

    $('#close-modal, #cancel-availability').on('click', function() {
        $('#availability-modal').fadeOut(200);
    });

    $('#save-availability').on('click', function() {
        $('#availability-modal').fadeOut(200);
        showToast.success('Dostępność została zapisana');
    });

    $('#availability-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).fadeOut(200);
        }
    });

    $('#save-schedule').on('click', function() {
        showToast.success('Grafik został zapisany');
    });

    initDragAndDrop();
});
