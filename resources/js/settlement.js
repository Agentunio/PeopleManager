import { confirmDialog } from './confirm-dialog.js';

document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('daySettlementPage');
    const config = window.settlementConfig || {};

    if (!page) return;

    const packages = Array.isArray(config.packages) ? config.packages : [];
    const packagesById = new Map(packages.map((item) => [String(item.id), Number(item.price) || 0]));
    const defaultPackage = packages.find((item) => item.isDefault);
    const modal = document.getElementById('substituteModal');
    const modalList = document.getElementById('substituteModalList');
    const modalLoading = document.getElementById('substituteModalLoading');
    const modalEmpty = document.getElementById('substituteModalEmpty');
    const closeModalButton = document.getElementById('closeSubstituteModal');
    const substituteTemplate = document.getElementById('substituteWorkerTemplate');
    const settlementForm = document.getElementById('settlement-form');
    const timeInputSelector = '.settlement-time-input[type=time]';

    let currentSubstituteShiftId = null;
    let currentSubstituteShiftType = null;
    let currentAbsentWorkerName = '';
    let modalTrigger = null;

    const moneyFormatter = new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 0 });
    const numberFormatter = new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 2 });

    function notify(type, message) {
        if (window.showToast?.[type]) window.showToast[type](message);
    }

    function initials(name) {
        return String(name)
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0))
            .join('')
            .toUpperCase();
    }

    function timeToMinutes(value) {
        if (!value || !/^\d{2}:\d{2}$/.test(value)) return null;

        const [hours, minutes] = value.split(':').map(Number);

        return (hours * 60) + minutes;
    }

    function trackPartialTimeDigits(event) {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || !input.matches(timeInputSelector)) return;

        if (/^\d$/.test(event.key)) {
            const pendingDigits = input.dataset.pendingTimeDigits || '';
            input.dataset.pendingTimeDigits = (pendingDigits + event.key).slice(-4);
        } else if (event.key === 'Backspace' || event.key === 'Delete') {
            delete input.dataset.pendingTimeDigits;
        }
    }

    function normalizePartialTimeInput(input) {
        const pendingDigits = input.dataset.pendingTimeDigits || '';

        if (input.value || !/^\d{1,2}$/.test(pendingDigits)) return false;

        const hour = Number.parseInt(pendingDigits, 10);

        if (!Number.isInteger(hour) || hour < 0 || hour > 23) return false;

        input.value = String(hour).padStart(2, '0') + ':00';
        delete input.dataset.pendingTimeDigits;
        input.dispatchEvent(new Event('input', { bubbles: true }));

        return true;
    }

    function normalizePartialTimeInputs() {
        settlementForm?.querySelectorAll(timeInputSelector).forEach(normalizePartialTimeInput);
    }

    function formatHours(minutes) {
        return Number.isFinite(minutes) ? numberFormatter.format(minutes / 60) + ' h' : '—';
    }

    function formatMoney(value) {
        return moneyFormatter.format(value) + ' zł';
    }

    function markFieldUpdated(field) {
        field.classList.remove('field-updated');
        window.requestAnimationFrame(() => field.classList.add('field-updated'));
        window.setTimeout(() => field.classList.remove('field-updated'), 520);
    }

    function markWorkerEntryOverridden(card) {
        if (card.dataset.workerEntry !== 'true') return;

        const note = card.querySelector('.worker-entry-note');
        const text = note?.querySelector('.worker-entry-text');

        if (!note || !text) return;

        note.classList.remove('is-worker-entered');
        note.classList.add('is-overridden');
        text.textContent = 'nadpisano samodzielny wpis';
    }

    function updateWorkerTime(card, allowSavedFallback = false) {
        const fromInput = card.querySelector('.worker-from-time');
        const toInput = card.querySelector('.worker-to-time');
        const from = timeToMinutes(fromInput?.value);
        const to = timeToMinutes(toInput?.value);
        const output = card.querySelector('.calculated-hours');

        if (!output) return;

        let minutes = null;

        if (from !== null && to !== null) {
            minutes = to - from;
        } else if (
            allowSavedFallback
            && !fromInput?.value
            && !toInput?.value
            && card.dataset.initialMinutes !== ''
        ) {
            minutes = Number(card.dataset.initialMinutes);
        }

        output.dataset.minutes = Number.isFinite(minutes) ? String(minutes) : '';
        output.textContent = formatHours(minutes);
    }

    function packageRowValue(row) {
        const count = Math.max(0, Number.parseInt(row.querySelector('.package-count-input')?.value || '0', 10) || 0);
        const packageId = row.querySelector('.package-rate')?.value || '';
        const price = packagesById.get(packageId);
        const value = price === undefined ? null : count * price;
        const output = row.querySelector('.package-value');

        if (output) output.textContent = value === null ? '—' : formatMoney(value);

        return { count, value: value || 0 };
    }

    function updatePackageSection(section) {
        let count = 0;
        let value = 0;
        const rows = [...section.querySelectorAll('.package-entry-row')];

        rows.forEach((row) => {
            const rowData = packageRowValue(row);
            count += rowData.count;
            value += rowData.value;
        });

        const countOutput = section.querySelector('.shift-package-count');
        const valueOutput = section.querySelector('.shift-package-value');
        const positionOutput = section.querySelector('.package-position-count');

        if (countOutput) countOutput.textContent = moneyFormatter.format(count);
        if (valueOutput) valueOutput.textContent = formatMoney(value);

        if (positionOutput) {
            const noun = rows.length === 1 ? 'pozycja' : (rows.length < 5 ? 'pozycje' : 'pozycji');
            positionOutput.textContent = rows.length + ' ' + noun;
        }

        return { count, value };
    }

    function updateShiftWorkers(section) {
        const cards = [...section.querySelectorAll('.settlement-worker-card')];
        const activeCards = cards.filter((card) => !card.classList.contains('worker-absent'));
        const countOutput = section.querySelector('.shift-worker-count');
        const applyButton = section.querySelector('.btn-apply-defaults');

        if (countOutput) {
            const strong = document.createElement('strong');
            const noun = cards.length === 1 ? 'pracownik' : 'pracowników';
            strong.textContent = String(cards.length);
            countOutput.replaceChildren(strong, document.createTextNode(' ' + noun));
        }

        const applyCount = applyButton?.querySelector('[data-role="apply-count"]');

        if (applyCount) applyCount.textContent = String(activeCards.length);

        return activeCards;
    }

    function updateSummary() {
        let packageCount = 0;
        let packageValue = 0;
        let workerCount = 0;
        let totalMinutes = 0;
        let missingRates = 0;

        document.querySelectorAll('.settlement-shift-section').forEach((section) => {
            const packageData = updatePackageSection(section);
            packageCount += packageData.count;
            packageValue += packageData.value;

            updateShiftWorkers(section).forEach((card) => {
                workerCount += 1;

                const rawMinutes = card.querySelector('.calculated-hours')?.dataset.minutes;
                const minutes = rawMinutes === '' ? NaN : Number(rawMinutes);
                if (Number.isFinite(minutes)) totalMinutes += minutes;
                if (!card.querySelector('.worker-rate')?.value) missingRates += 1;
            });
        });

        document.getElementById('summaryPackageCount').textContent = moneyFormatter.format(packageCount);
        document.getElementById('summaryPackageValue').textContent = formatMoney(packageValue);
        document.getElementById('summaryHours').textContent = formatHours(totalMinutes);
        document.getElementById('summaryWorkerCount').textContent = String(workerCount);

        const alert = document.getElementById('missingRateAlert');
        const alertText = document.getElementById('missingRateText');

        alert.hidden = missingRates === 0;

        if (missingRates > 0) {
            const subject = missingRates === 1
                ? '1 pracownik nie ma'
                : String(missingRates) + ' pracowników nie ma';
            alertText.textContent = subject + ' przydzielonej stawki. Ustaw domyślną dla zmiany i kliknij „Zastosuj do wszystkich”.';
        }
    }

    function reindexPackageRows(list) {
        const shiftType = list.dataset.shift;

        [...list.querySelectorAll('.package-entry-row')].forEach((row, index) => {
            const count = row.querySelector('.package-count-input');
            const rate = row.querySelector('.package-rate');
            const countLabel = row.querySelector('label[for*="-count"]');
            const rateLabel = row.querySelector('label[for*="-rate"]');
            const countId = 'package-' + shiftType + '-' + index + '-count';
            const rateId = 'package-' + shiftType + '-' + index + '-rate';

            row.dataset.entryIndex = String(index);
            count.name = shiftType + '_package_entries[' + index + '][packages_count]';
            count.id = countId;
            rate.name = shiftType + '_package_entries[' + index + '][package_id]';
            rate.id = rateId;

            if (countLabel) countLabel.htmlFor = countId;
            if (rateLabel) rateLabel.htmlFor = rateId;
        });
    }

    function addPackageRow(shiftType) {
        const list = document.querySelector('.package-entries-list[data-shift="' + shiftType + '"]');
        const source = list?.querySelector('.package-entry-row');

        if (!list || !source) return;

        const row = source.cloneNode(true);
        row.querySelector('.package-count-input').value = '';
        row.querySelector('.package-rate').value = defaultPackage ? String(defaultPackage.id) : '';
        row.querySelector('.package-value').textContent = '—';
        list.appendChild(row);
        reindexPackageRows(list);
        updateSummary();
        row.querySelector('.package-count-input').focus();
    }

    function removePackageRow(button) {
        const row = button.closest('.package-entry-row');
        const list = row?.closest('.package-entries-list');

        if (!row || !list) return;

        if (list.querySelectorAll('.package-entry-row').length === 1) {
            row.querySelector('.package-count-input').value = '';
            row.querySelector('.package-rate').value = '';
            row.querySelector('.package-value').textContent = '—';
        } else {
            row.remove();
        }

        reindexPackageRows(list);
        updateSummary();
    }

    function setAbsent(card, isAbsent) {
        const button = card.querySelector('.btn-absent');
        const status = card.querySelector('.worker-status-input');

        card.classList.toggle('worker-absent', isAbsent);
        button?.classList.toggle('active', isAbsent);
        button?.setAttribute('aria-pressed', isAbsent ? 'true' : 'false');
        if (status) status.value = isAbsent ? 'absent' : 'worked';
        updateSummary();
    }

    async function toggleAbsent(button) {
        const card = button.closest('.settlement-worker-card');
        const shiftId = card?.dataset.shiftId;

        if (!card) return;

        const wasAbsent = card.classList.contains('worker-absent');

        if (!wasAbsent) {
            setAbsent(card, true);
            return;
        }

        const container = card.closest('.settlement-workers');
        const substitute = shiftId
            ? container?.querySelector('.substitute-card[data-substitute-for-shift="' + shiftId + '"]')
            : null;

        if (substitute) {
            const substituteName = substitute.querySelector('.worker-name')?.textContent?.trim() || '';
            const confirmed = await confirmDialog({
                title: 'Usunąć zastępstwo?',
                text: 'Oznaczenie pracownika jako obecnego usunie zastępstwo: ' + substituteName,
                confirmText: 'Tak, usuń zastępstwo',
            });

            if (!confirmed) return;
            substitute.remove();
        }

        setAbsent(card, false);
    }

    function closeSubstituteModal() {
        if (!modal) return;

        modal.hidden = true;
        document.body.style.overflow = '';
        currentSubstituteShiftId = null;
        currentSubstituteShiftType = null;
        currentAbsentWorkerName = '';
        modalTrigger?.focus();
        modalTrigger = null;
    }

    function modalWorkerButton(worker) {
        const button = document.createElement('button');
        const label = document.createElement('span');

        button.type = 'button';
        button.className = 'substitute-modal-item';
        button.dataset.workerId = String(worker.id);
        button.dataset.workerName = (worker.first_name + ' ' + worker.last_name).trim();
        label.textContent = button.dataset.workerName;
        button.append(label);

        return button;
    }

    async function openSubstituteModal(button) {
        const card = button.closest('.settlement-worker-card');

        if (!card?.classList.contains('worker-absent') || !modal || !modalList) return;

        currentSubstituteShiftId = button.dataset.shiftId;
        currentSubstituteShiftType = button.dataset.shift;
        currentAbsentWorkerName = card.querySelector('.worker-name')?.textContent?.trim() || '';
        modalTrigger = button;

        const container = card.closest('.settlement-workers');
        const existing = container?.querySelector(
            '.substitute-card[data-substitute-for-shift="' + currentSubstituteShiftId + '"]'
        );

        if (existing) {
            notify('info', 'Zastępstwo już zostało dodane dla tego pracownika');
            return;
        }

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        modalLoading.hidden = false;
        modalEmpty.hidden = true;
        modalList.hidden = true;
        modalList.replaceChildren();
        closeModalButton?.focus();

        try {
            const url = new URL(config.substitutionUrl, window.location.origin);
            url.searchParams.set('shift_type', currentSubstituteShiftType);

            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) throw new Error('Request failed');

            const workers = await response.json();
            const selector = '.settlement-workers[data-shift="' + currentSubstituteShiftType + '"] .substitute-card';
            const alreadyAdded = new Set(
                [...document.querySelectorAll(selector)].map((item) => item.dataset.workerId)
            );
            const available = workers.filter((worker) => !alreadyAdded.has(String(worker.id)));

            modalLoading.hidden = true;

            if (available.length === 0) {
                modalEmpty.textContent = 'Brak dostępnych pracowników do zastępstwa';
                modalEmpty.hidden = false;
                return;
            }

            available.forEach((worker) => modalList.appendChild(modalWorkerButton(worker)));
            modalList.hidden = false;
            modalList.querySelector('button')?.focus();
        } catch {
            modalLoading.hidden = true;
            modalEmpty.textContent = 'Nie udało się załadować pracowników';
            modalEmpty.hidden = false;
        }
    }

    function setDynamicInput(input, name, value) {
        input.name = name;
        input.value = value ?? '';
    }

    function createSubstituteCard(workerId, workerName) {
        if (!substituteTemplate || !currentSubstituteShiftType || !currentSubstituteShiftId) return;

        const card = substituteTemplate.content.firstElementChild.cloneNode(true);
        const key = workerId + '_' + currentSubstituteShiftType;
        const container = document.querySelector(
            '.settlement-workers[data-shift="' + currentSubstituteShiftType + '"]'
        );

        card.dataset.substituteForShift = currentSubstituteShiftId;
        card.dataset.workerId = workerId;
        card.dataset.initialMinutes = '';

        setDynamicInput(card.querySelector('[data-field="id"]'), 'workers[' + key + '][id]', workerId);
        setDynamicInput(card.querySelector('[data-field="shift_type"]'), 'workers[' + key + '][shift_type]', currentSubstituteShiftType);
        setDynamicInput(card.querySelector('[data-field="status"]'), 'workers[' + key + '][status]', 'worked');
        setDynamicInput(card.querySelector('[data-field="is_substitute"]'), 'workers[' + key + '][is_substitute]', '1');
        setDynamicInput(
            card.querySelector('[data-field="substituted_for_shift_id"]'),
            'workers[' + key + '][substituted_for_shift_id]',
            currentSubstituteShiftId
        );

        const from = card.querySelector('.worker-from-time');
        const to = card.querySelector('.worker-to-time');
        const rate = card.querySelector('.worker-rate');

        from.name = 'workers[' + key + '][from_hour]';
        to.name = 'workers[' + key + '][to_hour]';
        rate.name = 'workers[' + key + '][package]';
        card.querySelector('[data-role="initials"]').textContent = initials(workerName);
        card.querySelector('[data-role="name"]').textContent = workerName;
        card.querySelector('[data-role="substitute-label"]').textContent = 'za ' + currentAbsentWorkerName;

        container.querySelector('.settlement-empty-workers')?.remove();
        container.appendChild(card);
        updateWorkerTime(card);
        updateSummary();
        notify('success', 'Dodano zastępstwo: ' + workerName);
    }

    function applyDefaults(button) {
        const section = button.closest('.settlement-shift-section');

        section?.querySelectorAll('.default-from-time, .default-to-time').forEach(normalizePartialTimeInput);

        const from = section?.querySelector('.default-from-time')?.value || '';
        const to = section?.querySelector('.default-to-time')?.value || '';
        const rate = section?.querySelector('.default-rate')?.value || '';

        section?.querySelectorAll('.settlement-worker-card:not(.worker-absent)').forEach((card) => {
            const fromInput = card.querySelector('.worker-from-time');
            const toInput = card.querySelector('.worker-to-time');
            const rateInput = card.querySelector('.worker-rate');

            if (from) {
                fromInput.value = from;
                markFieldUpdated(fromInput);
            }

            if (to) {
                toInput.value = to;
                markFieldUpdated(toInput);
            }

            if (rate) {
                rateInput.value = rate;
                markFieldUpdated(rateInput);
            }

            markWorkerEntryOverridden(card);
            updateWorkerTime(card, true);
        });

        updateSummary();
        notify('success', 'Zastosowano domyślne wartości dla zmiany');
    }

    document.querySelectorAll('.package-entries-list').forEach(reindexPackageRows);
    document.querySelectorAll('.settlement-worker-card').forEach((card) => updateWorkerTime(card, true));
    updateSummary();

    page.addEventListener('keydown', trackPartialTimeDigits, true);
    page.addEventListener('input', (event) => {
        const card = event.target.closest('.settlement-worker-card');

        if (event.target.matches(timeInputSelector) && event.target.value) {
            delete event.target.dataset.pendingTimeDigits;
        }

        if (event.target.matches('.worker-from-time, .worker-to-time') && card) {
            markWorkerEntryOverridden(card);
            updateWorkerTime(card, true);
        }

        if (event.target.matches('.package-count-input')) {
            event.target.value = event.target.value.replace(/[^0-9]/g, '');
        }

        updateSummary();
    });

    page.addEventListener('change', (event) => {
        if (event.target.matches('.worker-rate, .package-rate')) updateSummary();
    });

    page.addEventListener('click', (event) => {
        const addEntry = event.target.closest('.btn-add-entry');
        const removeEntry = event.target.closest('.btn-remove-entry');
        const absent = event.target.closest('.btn-absent');
        const addSubstitute = event.target.closest('.btn-add-substitute');
        const removeSubstitute = event.target.closest('.btn-remove-substitute');
        const apply = event.target.closest('.btn-apply-defaults');

        if (addEntry) addPackageRow(addEntry.dataset.shift);
        else if (removeEntry) removePackageRow(removeEntry);
        else if (absent) toggleAbsent(absent);
        else if (addSubstitute) openSubstituteModal(addSubstitute);
        else if (removeSubstitute) {
            removeSubstitute.closest('.settlement-worker-card')?.remove();
            updateSummary();
        } else if (apply) applyDefaults(apply);
    });

    document.addEventListener('click', (event) => {
        const submitter = event.target.closest?.('button[type="submit"], input[type="submit"]');

        if (submitter?.form === settlementForm) normalizePartialTimeInputs();
    }, true);

    settlementForm?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') normalizePartialTimeInputs();
    }, true);

    settlementForm?.addEventListener('submit', normalizePartialTimeInputs);

    modalList?.addEventListener('click', (event) => {
        const item = event.target.closest('.substitute-modal-item');

        if (!item) return;

        createSubstituteCard(item.dataset.workerId, item.dataset.workerName);
        closeSubstituteModal();
    });

    closeModalButton?.addEventListener('click', closeSubstituteModal);
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) closeSubstituteModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) closeSubstituteModal();
    });
});