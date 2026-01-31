document.addEventListener('DOMContentLoaded', function() {

    function calculateTimeDiff(fromHour, fromMinute, toHour, toMinute) {
        if (fromHour === '' || toHour === '') return null;

        fromHour = parseInt(fromHour) || 0;
        fromMinute = parseInt(fromMinute) || 0;
        toHour = parseInt(toHour) || 0;
        toMinute = parseInt(toMinute) || 0;

        let fromTotal = fromHour * 60 + fromMinute;
        let toTotal = toHour * 60 + toMinute;

        if (toTotal < fromTotal) {
            toTotal += 24 * 60;
        }

        let diffMinutes = toTotal - fromTotal;
        let hours = Math.floor(diffMinutes / 60);
        let minutes = diffMinutes % 60;

        return { hours, minutes };
    }

    function updateCalculatedTime(card) {
        const fromHour = card.querySelector('.worker-from-hour').value;
        const fromMinute = card.querySelector('.worker-from-minute').value;
        const toHour = card.querySelector('.worker-to-hour').value;
        const toMinute = card.querySelector('.worker-to-minute').value;

        const calculated = card.querySelector('.calculated-hours');
        const diff = calculateTimeDiff(fromHour, fromMinute, toHour, toMinute);

        if (diff) {
            calculated.textContent = `${diff.hours}h ${diff.minutes}min`;
            calculated.classList.add('has-value');
        } else {
            calculated.textContent = '0h 0min';
            calculated.classList.remove('has-value');
        }
    }

    document.querySelectorAll('.settlement-worker-card').forEach(function(card) {
        const timeInputs = card.querySelectorAll('.worker-from-hour, .worker-from-minute, .worker-to-hour, .worker-to-minute');

        timeInputs.forEach(function(input) {
            input.addEventListener('input', function() {
                updateCalculatedTime(card);
            });
        });

        updateCalculatedTime(card);
    });

    document.querySelectorAll('.btn-apply-defaults').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const shift = this.dataset.shift;
            applyDefaults(shift);
        });
    });

    function applyDefaults(shift) {
        const defaultRate = document.getElementById(`default-${shift}-rate`).value;
        const defaultFromHour = document.getElementById(`default-${shift}-from-hour`).value;
        const defaultFromMinute = document.getElementById(`default-${shift}-from-minute`).value;
        const defaultToHour = document.getElementById(`default-${shift}-to-hour`).value;
        const defaultToMinute = document.getElementById(`default-${shift}-to-minute`).value;

        const workersContainer = document.querySelector(`.settlement-workers[data-shift="${shift}"]`);
        if (!workersContainer) return;

        const workerCards = workersContainer.querySelectorAll('.settlement-worker-card');

        workerCards.forEach(function(card) {
            if (defaultRate) {
                const rateField = card.querySelector('.worker-rate');
                if (rateField) {
                    rateField.value = defaultRate;
                    rateField.classList.add('field-updated');
                    setTimeout(() => rateField.classList.remove('field-updated'), 500);
                }
            }

            if (defaultFromHour !== '') {
                const field = card.querySelector('.worker-from-hour');
                if (field) {
                    field.value = defaultFromHour;
                    field.classList.add('field-updated');
                    setTimeout(() => field.classList.remove('field-updated'), 500);
                }
            }

            if (defaultFromMinute !== '') {
                const field = card.querySelector('.worker-from-minute');
                if (field) {
                    field.value = defaultFromMinute;
                    field.classList.add('field-updated');
                    setTimeout(() => field.classList.remove('field-updated'), 500);
                }
            }

            if (defaultToHour !== '') {
                const field = card.querySelector('.worker-to-hour');
                if (field) {
                    field.value = defaultToHour;
                    field.classList.add('field-updated');
                    setTimeout(() => field.classList.remove('field-updated'), 500);
                }
            }

            if (defaultToMinute !== '') {
                const field = card.querySelector('.worker-to-minute');
                if (field) {
                    field.value = defaultToMinute;
                    field.classList.add('field-updated');
                    setTimeout(() => field.classList.remove('field-updated'), 500);
                }
            }

            updateCalculatedTime(card);
        });

        const shiftName = shift === 'morning' ? 'rannej' : 'popołudniowej';
        if (typeof showToast !== 'undefined') {
            showToast.success(`Domyślne wartości zastosowane dla zmiany ${shiftName}`);
        }
    }
});
