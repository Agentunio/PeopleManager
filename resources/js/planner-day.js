import $ from 'jquery';
import { confirmDialog } from './confirm-dialog.js';

$(document).ready(function() {
    const $morningZone = $('#morning-shift');
    const $afternoonZone = $('#afternoon-shift');

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

    function isWorkerAssignedToShift(workerId, shiftType) {
        return zoneFor(shiftType).find(`.assigned-worker[data-worker-id="${workerId}"]`).length > 0;
    }

    function zoneFor(shiftType) {
        return shiftType === 'morning' ? $morningZone : $afternoonZone;
    }

    function freeShiftsFor(workerId) {
        const availability = getWorkerAvailability(workerId);
        return {
            morning: availability.morning && !isWorkerAssignedToShift(workerId, 'morning'),
            afternoon: availability.afternoon && !isWorkerAssignedToShift(workerId, 'afternoon')
        };
    }

    function buildPoolCard(worker, freeMorning, freeAfternoon) {
        const $card = $(`
            <div class="worker-card">
                <span class="worker-name"></span>
                <div class="worker-card__actions">
                    <button type="button" class="planner-day-add planner-day-add--morning" data-shift="morning">Rano</button>
                    <button type="button" class="planner-day-add planner-day-add--afternoon" data-shift="afternoon">Popo.</button>
                </div>
            </div>
        `);

        $card.attr('data-worker-id', worker.id);
        $card.find('.worker-name').text(worker.name);
        $card.find('[data-shift="morning"]').attr('aria-label', `Przypisz ${worker.name} do zmiany rannej`);
        $card.find('[data-shift="afternoon"]').attr('aria-label', `Przypisz ${worker.name} do zmiany popołudniowej`);
        syncPoolCard($card, freeMorning, freeAfternoon);

        return $card;
    }

    function syncPoolCard($card, freeMorning, freeAfternoon) {
        $card.attr('data-morning', String(freeMorning));
        $card.attr('data-afternoon', String(freeAfternoon));
        $card.find('[data-shift="morning"]').prop('disabled', !freeMorning);
        $card.find('[data-shift="afternoon"]').prop('disabled', !freeAfternoon);
    }

    function refreshPoolCard(workerId) {
        const worker = getWorkerFromData(workerId);
        if (!worker) return;

        const free = freeShiftsFor(workerId);
        const $card = $(`.worker-card[data-worker-id="${workerId}"]`);

        if (!free.morning && !free.afternoon) {
            $card.hide();
        } else if ($card.length === 0) {
            $('#workers-list').append(buildPoolCard(worker, free.morning, free.afternoon));
        } else {
            syncPoolCard($card, free.morning, free.afternoon);
            $card.show();
        }

        updatePoolState();
    }

    function syncPoolWithBoards() {
        $('.assigned-worker').each(function() {
            refreshPoolCard($(this).data('worker-id'));
        });
    }

    function updatePoolState() {
        const visible = $('#workers-list .worker-card').filter(function() {
            return $(this).css('display') !== 'none';
        }).length;

        $('#pool-count').text(visible);
        $('[data-pool-empty]').prop('hidden', visible > 0);
    }

    function addWorkerToShift(workerId, workerName, shiftType, $zone) {
        const $chip = $(`
            <span class="assigned-worker">
                <span class="worker-name"></span>
                <button type="button" class="remove-worker">
                    <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </span>
        `);

        $chip.attr('data-worker-id', workerId);
        $chip.find('.worker-name').text(workerName);
        $chip.find('.remove-worker')
            .attr('data-worker-id', workerId)
            .attr('aria-label', `Usuń ${workerName} ze zmiany`);

        const index = `${workerId}_${shiftType}`;
        const hiddenInput =
            `<input type="hidden" name="workers[${index}][worker_id]" value="${workerId}" data-worker-id="${workerId}">` +
            `<input type="hidden" name="workers[${index}][shift_type]" value="${shiftType}" data-worker-id="${workerId}">`;

        $zone.find('.assigned-workers').append($chip);
        $zone.find('.hidden-inputs').append(hiddenInput);
    }

    function updateEmptyState($zone) {
        const hasWorkers = $zone.find('.assigned-worker').length > 0;
        $zone.find('[data-board-empty]').prop('hidden', hasWorkers);
    }

    // Licznik „Przypisani" pokazuje osoby, które faktycznie wyjdą na zmianę —
    // nieobecni odpadają, zastępcy się liczą (tak samo liczy to DayController).
    function updateCounts() {
        ['morning', 'afternoon'].forEach((shiftType) => {
            $(`#${shiftType}-count`).text(
                zoneFor(shiftType).find('.assigned-worker:not(.worker-absent)').length
            );
        });
    }

    function removeUnavailableWorkers() {
        $('.assigned-worker').each(function() {
            const $assigned = $(this);
            const workerId = $assigned.data('worker-id');
            const $zone = $assigned.closest('[data-shift]');
            const shiftType = $zone.data('shift');

            const shiftId = $assigned.data('shift-id');
            const isSubstitutionPair = !!$assigned.attr('data-substituted-for')
                || (shiftId && $zone.find(`.assigned-worker[data-substituted-for="${shiftId}"]`).length > 0);

            if (!isSubstitutionPair && !isWorkerAvailableForShift(workerId, shiftType)) {
                $assigned.remove();
                $zone.find(`.hidden-inputs input[data-worker-id="${workerId}"]`).remove();
                updateEmptyState($zone);
            }
        });
        updateCounts();
    }

    $(document).on('click', '.planner-day-add', function() {
        const $card = $(this).closest('.worker-card');
        const workerId = $card.data('worker-id');
        const shiftType = $(this).data('shift');
        const worker = getWorkerFromData(workerId);
        if (!worker) return;

        if (!isWorkerAvailableForShift(workerId, shiftType)) {
            showToast.error('Ten pracownik nie jest dostępny na tę zmianę');
            return;
        }

        const $zone = zoneFor(shiftType);

        if ($zone.find(`.assigned-worker[data-worker-id="${workerId}"]`).length > 0) {
            showToast.warning('Ten pracownik jest już przypisany do tej zmiany');
            return;
        }

        addWorkerToShift(workerId, worker.name, shiftType, $zone);
        updateEmptyState($zone);
        updateCounts();
        refreshPoolCard(workerId);

        showToast.success(`${worker.name} przypisany do zmiany`);
    });

    $(document).on('click', '.remove-worker', function(e) {
        e.stopPropagation();

        const $worker = $(this).closest('.assigned-worker');
        const $zone = $(this).closest('[data-shift]');
        const workerId = $(this).data('worker-id');
        const shiftId = $worker.data('shift-id');
        const isAbsent = $worker.hasClass('worker-absent');
        const isSubstitute = $worker.hasClass('worker-substitute');

        function removeWorker(alsoRemoveSubstitute) {
            if (alsoRemoveSubstitute && shiftId) {
                const $subCard = $zone.find(`.assigned-worker[data-substituted-for="${shiftId}"]`);
                if ($subCard.length) {
                    const subWorkerId = $subCard.data('worker-id');
                    $subCard.remove();
                    $zone.find(`.hidden-inputs input[data-worker-id="${subWorkerId}"]`).remove();
                    refreshPoolCard(subWorkerId);
                }
            }

            $worker.remove();
            $zone.find(`.hidden-inputs input[data-worker-id="${workerId}"]`).remove();
            updateEmptyState($zone);
            updateCounts();
            refreshPoolCard(workerId);
        }

        if (isAbsent) {
            const $subCard = shiftId ? $zone.find(`.assigned-worker[data-substituted-for="${shiftId}"]`) : $();
            const subName = $subCard.find('.worker-name').text().trim();

            confirmDialog({
                title: 'Usunąć nieobecnego?',
                text: subName
                    ? `Usunięcie tego pracownika usunie również zastępstwo: ${subName}`
                    : 'Pracownik jest oznaczony jako nieobecny. Czy na pewno chcesz go usunąć z grafiku?',
                confirmText: 'Tak, usuń'
            }).then((confirmed) => {
                if (confirmed) removeWorker(true);
            });
        } else if (isSubstitute) {
            const workerName = $worker.find('.worker-name').text().trim();
            confirmDialog({
                title: 'Usunąć zastępstwo?',
                text: `Czy na pewno chcesz usunąć zastępstwo: ${workerName}?`,
                confirmText: 'Tak, usuń'
            }).then((confirmed) => {
                if (confirmed) removeWorker(false);
            });
        } else {
            removeWorker(false);
        }
    });

    $('.planner-day-time').on('click', function(event) {
        const input = this.querySelector('input');
        if (!input || event.target === input) return;

        if (typeof input.showPicker === 'function') {
            try {
                input.showPicker();
            } catch (error) {
                input.focus();
            }
        } else {
            input.focus();
        }
    });

    const $availabilityModal = $('#availability-modal');
    const availabilityTrigger = document.getElementById('change-availability-btn');
    const closeAvailabilityModal = () => {
        $availabilityModal.stop(true, true).fadeOut(200, () => {
            availabilityTrigger?.focus({ preventScroll: true });
        });
    };

    $('#change-availability-btn').on('click', function() {
        $availabilityModal.stop(true, true).fadeIn(200, () => {
            document.getElementById('close-modal')?.focus({ preventScroll: true });
        });
    });

    $('#close-modal, #cancel-availability').on('click', function(e) {
        e.preventDefault();
        closeAvailabilityModal();
    });

    $('#availability-modal').on('click', function(e) {
        if (e.target === this) {
            closeAvailabilityModal();
        }
    });

    $('#availability-modal').on('keydown', function(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeAvailabilityModal();
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
                    closeAvailabilityModal();
                    $('#workers-list').html(response.html);
                    removeUnavailableWorkers();
                    syncPoolWithBoards();
                    updatePoolState();
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

    $('#save-draft').on('click', function(e) {
        e.preventDefault();
        $('#is-draft-input').val('1');
        $('#schedule-form').submit();
    });

    $('#save-schedule').on('click', function() {
        $('#is-draft-input').val('0');
    });

    updateCounts();
    updatePoolState();
});
