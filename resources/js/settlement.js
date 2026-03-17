document.addEventListener('DOMContentLoaded', function() {

    let currentSubstituteShiftId = null;
    let currentSubstituteShiftType = null;
    let currentAbsentWorkerName = null;

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
        const fromHour = card.querySelector('.worker-from-hour');
        const toHour = card.querySelector('.worker-to-hour');
        if (!fromHour || !toHour) return;

        const fromMinute = card.querySelector('.worker-from-minute');
        const toMinute = card.querySelector('.worker-to-minute');
        const calculated = card.querySelector('.calculated-hours');

        const diff = calculateTimeDiff(
            fromHour.value, fromMinute ? fromMinute.value : '',
            toHour.value, toMinute ? toMinute.value : ''
        );

        if (diff && calculated) {
            calculated.textContent = `${diff.hours}h ${diff.minutes}min`;
            calculated.classList.add('has-value');
        } else if (calculated) {
            calculated.textContent = '0h 0min';
            calculated.classList.remove('has-value');
        }
    }

    function initTimeListeners(card) {
        if (card.classList.contains('worker-absent')) return;

        const timeInputs = card.querySelectorAll('.worker-from-hour, .worker-from-minute, .worker-to-hour, .worker-to-minute');
        timeInputs.forEach(function(input) {
            input.addEventListener('input', function() {
                updateCalculatedTime(card);
            });
        });

        updateCalculatedTime(card);
    }

    document.querySelectorAll('.settlement-worker-card').forEach(initTimeListeners);

    $(document).on('click', '.btn-absent', function() {
        const btn = this;
        const card = btn.closest('.settlement-worker-card');
        const statusInput = card.querySelector('.worker-status-input');
        const shiftId = card.dataset.shiftId;
        const wasAbsent = card.classList.contains('worker-absent');

        if (wasAbsent) {
            const container = card.closest('.settlement-workers');
            const subCard = shiftId ? container.querySelector(`.substitute-card[data-substitute-for-shift="${shiftId}"]`) : null;

            if (subCard) {
                const subName = subCard.querySelector('.worker-name')?.textContent?.trim() || '';
                Swal.fire({
                    title: 'Usunąć zastępstwo?',
                    text: `Oznaczenie pracownika jako obecnego usunie zastępstwo: ${subName}`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e50914',
                    cancelButtonColor: '#555',
                    confirmButtonText: 'Tak, usuń zastępstwo',
                    cancelButtonText: 'Anuluj',
                    background: '#1f1f1f',
                    color: '#f0f0f0'
                }).then((result) => {
                    if (result.isConfirmed) {
                        subCard.remove();
                        card.classList.remove('worker-absent');
                        btn.classList.remove('active');
                        statusInput.value = 'worked';
                    }
                });
                return;
            }

            card.classList.remove('worker-absent');
            btn.classList.remove('active');
            statusInput.value = 'worked';
        } else {
            card.classList.add('worker-absent');
            btn.classList.add('active');
            statusInput.value = 'absent';
        }
    });

    $(document).on('click', '.btn-add-substitute', function() {
        const card = this.closest('.settlement-worker-card');
        if (!card.classList.contains('worker-absent')) return;

        currentSubstituteShiftId = this.dataset.shiftId;
        currentSubstituteShiftType = this.dataset.shift;
        currentAbsentWorkerName = card.querySelector('.worker-name').textContent.trim();

        const container = card.closest('.settlement-workers');
        const existingSub = container.querySelector(`.substitute-card[data-substitute-for-shift="${currentSubstituteShiftId}"]`);
        if (existingSub) {
            if (window.showToast) {
                showToast.info('Zastępstwo już zostało dodane dla tego pracownika');
            }
            return;
        }

        openSubstituteModal();
    });

    $(document).on('click', '.btn-remove-substitute', function() {
        const card = this.closest('.substitute-card');
        if (card) card.remove();
    });

    function openSubstituteModal() {
        const modal = document.getElementById('substituteModal');
        const loading = document.getElementById('substituteModalLoading');
        const list = document.getElementById('substituteModalList');
        const empty = document.getElementById('substituteModalEmpty');

        modal.style.display = 'flex';
        loading.style.display = 'flex';
        list.style.display = 'none';
        empty.style.display = 'none';
        list.innerHTML = '';

        const url = `/grafik/${window.settlementDate}/zastepstwo-dostepni?shift_type=${currentSubstituteShiftType}`;

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => r.json())
        .then(workers => {
            loading.style.display = 'none';

            const alreadyAdded = new Set();
            document.querySelectorAll(`.settlement-workers[data-shift="${currentSubstituteShiftType}"] .substitute-card`).forEach(el => {
                const idInput = el.querySelector('input[name$="[id]"]');
                if (idInput) alreadyAdded.add(idInput.value);
            });

            const available = workers.filter(w => !alreadyAdded.has(String(w.id)));

            if (available.length === 0) {
                empty.style.display = 'block';
                return;
            }

            list.style.display = 'block';
            list.innerHTML = available.map(w =>
                `<button type="button" class="substitute-modal-item" data-worker-id="${w.id}" data-worker-name="${escapeAttr(w.first_name + ' ' + w.last_name)}">
                    <i class="fas fa-user"></i>
                    <span>${escapeHtml(w.first_name)} ${escapeHtml(w.last_name)}</span>
                </button>`
            ).join('');
        })
        .catch(() => {
            loading.style.display = 'none';
            empty.style.display = 'block';
            empty.textContent = 'Błąd ładowania pracowników';
        });
    }

    $(document).on('click', '.substitute-modal-item', function() {
        const workerId = this.dataset.workerId;
        const workerName = this.dataset.workerName;

        createSubstituteCard(workerId, workerName, currentSubstituteShiftType, currentSubstituteShiftId, currentAbsentWorkerName);
        closeSubstituteModal();
    });

    document.getElementById('closeSubstituteModal').addEventListener('click', closeSubstituteModal);
    document.getElementById('substituteModal').addEventListener('click', function(e) {
        if (e.target === this) closeSubstituteModal();
    });

    function closeSubstituteModal() {
        document.getElementById('substituteModal').style.display = 'none';
        currentSubstituteShiftId = null;
        currentSubstituteShiftType = null;
        currentAbsentWorkerName = null;
    }

    function createSubstituteCard(workerId, workerName, shiftType, absentShiftId, absentWorkerName) {
        const key = `${workerId}_${shiftType}`;
        const container = document.querySelector(`.settlement-workers[data-shift="${shiftType}"]`);

        const packageOptions = (window.settlementPackages || []).map(p =>
            `<option value="${p.id}">${escapeHtml(p.name)}</option>`
        ).join('');

        const html = `
            <div class="settlement-worker-card substitute-card" data-substitute-for-shift="${absentShiftId}">
                <input type="hidden" name="workers[${key}][id]" value="${workerId}">
                <input type="hidden" name="workers[${key}][shift_type]" value="${shiftType}">
                <input type="hidden" name="workers[${key}][status]" value="worked" class="worker-status-input">
                <input type="hidden" name="workers[${key}][is_substitute]" value="1">
                <input type="hidden" name="workers[${key}][substituted_for_shift_id]" value="${absentShiftId}">
                <div class="worker-info">
                    <span class="worker-name">${escapeHtml(workerName)}</span>
                    <div class="worker-actions">
                        <div class="substitute-label">
                            <i class="fas fa-user-check"></i>
                            Zastępstwo za ${escapeHtml(absentWorkerName)}
                        </div>
                        <button type="button" class="btn btn-remove-substitute">
                            <i class="fas fa-times"></i> Usuń zastępstwo
                        </button>
                    </div>
                </div>
                <div class="worker-settlement-fields">
                    <div class="field-group">
                        <span>Stawka</span>
                        <select name="workers[${key}][package]" class="worker-rate">
                            <option value="">Wybierz stawkę</option>
                            ${packageOptions}
                        </select>
                    </div>
                    <div class="field-group field-time">
                        <span>Czas pracy</span>
                        <div class="time-range-inputs">
                            <div class="time-from">
                                <span class="time-label">Od</span>
                                <input type="number" name="workers[${key}][from_hour]" class="worker-from-hour" placeholder="00" min="0" max="23">
                                <span class="time-colon">:</span>
                                <input type="number" name="workers[${key}][from_minute]" class="worker-from-minute" placeholder="00" min="0" max="59">
                            </div>
                            <span class="time-range-separator">—</span>
                            <div class="time-to">
                                <span class="time-label">Do</span>
                                <input type="number" name="workers[${key}][to_hour]" class="worker-to-hour" placeholder="00" min="0" max="23">
                                <span class="time-colon">:</span>
                                <input type="number" name="workers[${key}][to_minute]" class="worker-to-minute" placeholder="00" min="0" max="59">
                            </div>
                            <div class="time-calculated">
                                <span class="calculated-hours">0h 0min</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', html);

        const newCard = container.querySelector(`.substitute-card[data-substitute-for-shift="${absentShiftId}"]`);
        if (newCard) initTimeListeners(newCard);

        if (window.showToast) {
            showToast.success(`Dodano zastępstwo: ${workerName}`);
        }
    }

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
            if (card.classList.contains('worker-absent')) return;

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
        if (window.showToast) {
            showToast.success(`Domyślne wartości zastosowane dla zmiany ${shiftName}`);
        }
    }

    $(document).on('click', '.btn-change-time', function() {
        $(this).closest('.field-time').find('.time-saved').hide();
        $(this).closest('.field-time').find('.time-range-inputs').show();
    });

    document.querySelectorAll('.btn-add-entry').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const shift = this.dataset.shift;
            const list = document.querySelector(`.package-entries-list[data-shift="${shift}"]`);
            if (!list) return;

            const rows = list.querySelectorAll('.package-entry-row');
            const newIndex = rows.length;
            const prefix = shift + '_package_entries';

            const firstRow = rows[0];
            const newRow = firstRow.cloneNode(true);

            const input = newRow.querySelector('input[type="number"]');
            input.name = `${prefix}[${newIndex}][packages_count]`;
            input.value = '';

            const select = newRow.querySelector('select');
            select.name = `${prefix}[${newIndex}][package_id]`;
            select.selectedIndex = 0;

            list.appendChild(newRow);
        });
    });

    $(document).on('click', '.btn-remove-entry', function() {
        const row = this.closest('.package-entry-row');
        const list = row.closest('.package-entries-list');
        const rows = list.querySelectorAll('.package-entry-row');

        if (rows.length <= 1) {
            const input = row.querySelector('input[type="number"]');
            const select = row.querySelector('select');
            if (input) input.value = '';
            if (select) select.selectedIndex = 0;
            return;
        }

        row.remove();

        const shift = list.dataset.shift;
        const prefix = shift + '_package_entries';
        list.querySelectorAll('.package-entry-row').forEach(function(r, i) {
            const input = r.querySelector('input[type="number"]');
            const select = r.querySelector('select');
            if (input) input.name = `${prefix}[${i}][packages_count]`;
            if (select) select.name = `${prefix}[${i}][package_id]`;
        });
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return str.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
});
