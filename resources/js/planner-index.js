import { confirmDialog } from './confirm-dialog.js';
import { toast } from './notice';

document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-planner-page]');

    if (!page) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const substituteDialog = document.getElementById('substitute-dialog');
    const substituteForm = substituteDialog?.querySelector('[data-substitute-form]');
    const substituteList = substituteDialog?.querySelector('[data-substitute-list]');
    const substituteStatus = substituteDialog?.querySelector('[data-substitute-status]');
    const substituteDescription = substituteDialog?.querySelector('[data-substitute-description]');
    const substituteSubmit = substituteDialog?.querySelector('[data-substitute-submit]');
    const personMenu = document.getElementById('planner-person-menu');
    const absentStatusAction = personMenu?.querySelector('[data-shift-status="absent"]');
    const workedStatusAction = personMenu?.querySelector('[data-shift-status="worked"]');
    const substituteAction = personMenu?.querySelector('[data-substitute-open]');
    const removeAction = personMenu?.querySelector('[data-shift-remove]');
    const actionUrlTemplates = {
        status: page.dataset.shiftStatusUrlTemplate ?? '',
        substituteCandidates: page.dataset.substituteCandidatesUrlTemplate ?? '',
        substituteStore: page.dataset.substituteStoreUrlTemplate ?? '',
        remove: page.dataset.shiftRemoveUrlTemplate ?? '',
    };
    let lastDialogTrigger = null;
    let substituteStoreUrl = null;
    let substituteRequestSequence = 0;
    let substituteSubmitting = false;
    let openPersonMenu = null;
    let openPersonMenuTrigger = null;

    const firstValidationMessage = (payload, fallback) => {
        const errors = payload?.errors;

        if (errors && typeof errors === 'object') {
            const firstError = Object.values(errors).flat().find(Boolean);

            if (firstError) {
                return firstError;
            }
        }

        return payload?.message || fallback;
    };

    const requestJson = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...options.headers,
            },
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(firstValidationMessage(payload, 'Nie udało się wykonać operacji.'));
        }

        return payload;
    };

    const closePersonMenu = (restoreFocus = false) => {
        if (!openPersonMenu) {
            return;
        }

        const trigger = openPersonMenuTrigger;
        openPersonMenu.hidden = true;
        trigger?.setAttribute('aria-expanded', 'false');
        openPersonMenu = null;
        openPersonMenuTrigger = null;

        if (restoreFocus && trigger?.isConnected) {
            trigger.focus();
        }
    };

    const positionPersonMenu = (trigger) => {
        if (!personMenu) {
            return;
        }

        if (window.matchMedia('(max-width: 640px)').matches) {
            personMenu.style.removeProperty('top');
            personMenu.style.removeProperty('right');
            personMenu.style.removeProperty('bottom');
            personMenu.style.removeProperty('left');

            return;
        }

        const triggerBounds = trigger.getBoundingClientRect();
        const viewportPadding = 8;
        const preferredTop = triggerBounds.bottom + 6;
        const top = Math.min(preferredTop, window.innerHeight - personMenu.offsetHeight - viewportPadding);
        const left = Math.max(
            viewportPadding,
            Math.min(triggerBounds.left, window.innerWidth - personMenu.offsetWidth - viewportPadding),
        );

        personMenu.style.top = `${Math.max(viewportPadding, top)}px`;
        personMenu.style.right = 'auto';
        personMenu.style.bottom = 'auto';
        personMenu.style.left = `${left}px`;
    };

    const actionCardFor = (trigger) => trigger.closest('[data-planner-day-card]')
        ?? document.getElementById(trigger.dataset.sourceCardId ?? '');

    const buildActionUrl = (template, card, trigger) => {
        const date = card.dataset.day ?? '';
        const shiftId = trigger.dataset.shiftId ?? '';

        if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d+$/.test(shiftId) || !template) {
            return '';
        }

        return template
            .replace('__DATE__', encodeURIComponent(date))
            .replace('__SHIFT__', encodeURIComponent(shiftId));
    };

    const configurePersonMenu = (trigger) => {
        if (!personMenu) {
            return false;
        }

        const card = actionCardFor(trigger);

        if (!card) {
            return false;
        }

        const state = trigger.dataset.personState;
        const canAddSubstitute = state === 'unavailable' && trigger.dataset.hasSubstitute !== 'true';
        const actions = [absentStatusAction, workedStatusAction, substituteAction, removeAction];
        const urls = {
            status: buildActionUrl(actionUrlTemplates.status, card, trigger),
            substituteCandidates: buildActionUrl(actionUrlTemplates.substituteCandidates, card, trigger),
            substituteStore: buildActionUrl(actionUrlTemplates.substituteStore, card, trigger),
            remove: buildActionUrl(actionUrlTemplates.remove, card, trigger),
        };

        if (!urls.status || !urls.remove || (canAddSubstitute && (!urls.substituteCandidates || !urls.substituteStore))) {
            return false;
        }

        actions.forEach((action) => {
            if (action) {
                action.dataset.sourceCardId = card.id;
                action.dataset.shiftId = trigger.dataset.shiftId;
            }
        });

        if (absentStatusAction) {
            absentStatusAction.hidden = state !== 'ok';
            absentStatusAction.dataset.url = urls.status;
        }

        if (workedStatusAction) {
            workedStatusAction.hidden = state !== 'unavailable';
            workedStatusAction.dataset.url = urls.status;
        }

        if (substituteAction) {
            substituteAction.hidden = !canAddSubstitute;
            substituteAction.dataset.candidatesUrl = urls.substituteCandidates;
            substituteAction.dataset.storeUrl = urls.substituteStore;
            substituteAction.dataset.personName = trigger.dataset.personName ?? '';
        }

        if (removeAction) {
            removeAction.dataset.url = urls.remove;
        }

        return true;
    };
    const replaceDayCard = (sourceElement, html) => {
        const currentCard = sourceElement.closest('[data-planner-day-card]');

        if (!currentCard || typeof html !== 'string') {
            return null;
        }

        const responseDocument = new DOMParser().parseFromString(html, 'text/html');
        const replacement = responseDocument.body.firstElementChild;

        if (!replacement) {
            return null;
        }

        currentCard.replaceWith(replacement);
        applyStatusFilter();

        return replacement;
    };

    const focusAfterCardReplacement = (replacement, shiftId) => {
        const matchingTrigger = [...replacement.querySelectorAll('[data-person-menu-trigger]')]
            .find((button) => button.dataset.shiftId === shiftId);
        const fallback = replacement.querySelector('.planner-day-card__date-link, a, button');
        const focusTarget = matchingTrigger ?? fallback;

        if (focusTarget instanceof HTMLElement) {
            focusTarget.focus({ preventScroll: true });
        }
    };

    const showDialog = (dialog, trigger = null) => {
        if (!(dialog instanceof HTMLDialogElement)) {
            return;
        }

        lastDialogTrigger = trigger;

        if (!dialog.open) {
            dialog.showModal();
        }
    };

    const closeDialog = (dialog) => {
        if (dialog === substituteDialog && substituteSubmitting) {
            return;
        }

        if (dialog instanceof HTMLDialogElement && dialog.open) {
            dialog.close();
        }
    };

    document.querySelectorAll('[data-dialog-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            showDialog(document.getElementById(trigger.dataset.dialogOpen), trigger);
        });
    });

    document.querySelectorAll('.planner-dialog').forEach((dialog) => {
        dialog.addEventListener('cancel', (event) => {
            if (dialog === substituteDialog && substituteSubmitting) {
                event.preventDefault();
            }
        });

        dialog.addEventListener('click', (event) => {
            if (
                event.target === dialog
                && (dialog !== substituteDialog || !substituteSubmitting)
            ) {
                closeDialog(dialog);
            }
        });

        dialog.addEventListener('close', () => {
            if (dialog === substituteDialog) {
                substituteRequestSequence += 1;
                substituteStoreUrl = null;
            }

            if (lastDialogTrigger?.isConnected) {
                lastDialogTrigger.focus();
            }

            lastDialogTrigger = null;
        });

        dialog.querySelectorAll('[data-dialog-close]').forEach((button) => {
            button.addEventListener('click', () => closeDialog(dialog));
        });
    });

    if (page.dataset.openScheduleDialog === 'true') {
        showDialog(document.getElementById('schedule-dialog'));
    }

    const scheduleForm = document.querySelector('[data-schedule-form]');
    const deadlineField = scheduleForm?.querySelector('[data-deadline-field]');
    const deadlineInput = deadlineField?.querySelector('input');
    const modeHint = scheduleForm?.querySelector('[data-mode-hint]');
    const modeHints = {
        signup: 'Pracownicy mogą zapisywać się do wskazanego terminu.',
        always: 'Pracownicy mogą zapisywać się przez cały wybrany okres.',
        admin: 'Zapisy pracowników są wyłączone. Obsady dokonuje wyłącznie administrator.',
    };

    const updateScheduleMode = () => {
        const selectedMode = scheduleForm?.querySelector('input[name="type"]:checked')?.value ?? 'signup';
        const usesDeadline = selectedMode === 'signup';

        if (deadlineField) {
            deadlineField.hidden = !usesDeadline;
        }

        if (deadlineInput) {
            deadlineInput.required = usesDeadline;
            deadlineInput.disabled = !usesDeadline;
        }

        if (modeHint) {
            modeHint.textContent = modeHints[selectedMode] ?? '';
        }
    };

    scheduleForm?.querySelectorAll('input[name="type"]').forEach((radio) => {
        radio.addEventListener('change', updateScheduleMode);
    });
    updateScheduleMode();

    const toLocalDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    };

    scheduleForm?.querySelectorAll('[data-week-preset]').forEach((button) => {
        button.addEventListener('click', () => {
            const weeksAhead = Number(button.dataset.weekPreset);
            const today = new Date();
            const daysUntilMonday = (8 - today.getDay()) % 7 || 7;
            const startDate = new Date(today);
            startDate.setHours(12, 0, 0, 0);
            startDate.setDate(today.getDate() + daysUntilMonday + ((weeksAhead - 1) * 7));
            const endDate = new Date(startDate);
            endDate.setDate(startDate.getDate() + 6);

            scheduleForm.elements.start_date.value = toLocalDate(startDate);
            scheduleForm.elements.end_date.value = toLocalDate(endDate);
        });
    });

    let activeFilter = 'all';

    function applyStatusFilter() {
        const cards = [...document.querySelectorAll('[data-planner-day-card]')];
        const statusCounts = {
            all: cards.length,
            draft: 0,
            active: 0,
            settled: 0,
        };

        let visibleCount = 0;

        cards.forEach((card) => {
            const visible = activeFilter === 'all' || card.dataset.status === activeFilter;
            card.hidden = !visible;
            visibleCount += visible ? 1 : 0;

            if (Object.hasOwn(statusCounts, card.dataset.status)) {
                statusCounts[card.dataset.status] += 1;
            }
        });

        const counter = document.querySelector('[data-result-count]');
        const emptyState = document.querySelector('[data-filter-empty]');

        document.querySelectorAll('[data-status-filter]').forEach((button) => {
            const count = button.querySelector('span');

            if (count) {
                count.textContent = String(statusCounts[button.dataset.statusFilter] ?? 0);
            }
        });

        if (counter) {
            counter.textContent = String(visibleCount);
        }

        if (emptyState) {
            emptyState.hidden = visibleCount > 0;
        }
    }

    document.querySelectorAll('[data-status-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.statusFilter;

            document.querySelectorAll('[data-status-filter]').forEach((filterButton) => {
                const active = filterButton === button;
                filterButton.classList.toggle('is-active', active);
                filterButton.setAttribute('aria-pressed', String(active));
            });

            applyStatusFilter();
        });
    });

    const runCardAction = async (trigger, method, body, successFallback) => {
        const card = actionCardFor(trigger);
        const shiftId = trigger.dataset.shiftId ?? '';

        if (!card || card.getAttribute('aria-busy') === 'true') {
            return;
        }

        const actionButtons = card.querySelectorAll(
            '[data-shift-status], [data-shift-remove], [data-substitute-open]',
        );
        card.setAttribute('aria-busy', 'true');
        actionButtons.forEach((button) => {
            button.disabled = true;
        });

        try {
            const payload = await requestJson(trigger.dataset.url, {
                method,
                body: body ? JSON.stringify(body) : undefined,
            });

            const replacement = replaceDayCard(card, payload.day_html);

            if (!replacement) {
                throw new Error('Nie udało się odświeżyć dnia po wykonaniu operacji.');
            }

            focusAfterCardReplacement(replacement, shiftId);
            toast.success(payload.message || successFallback);
        } catch (error) {
            toast.error(error.message);
        } finally {
            if (card.isConnected) {
                card.removeAttribute('aria-busy');
                actionButtons.forEach((button) => {
                    button.disabled = false;
                });
            }
        }
    };

    document.addEventListener('click', async (event) => {
        const menuTrigger = event.target.closest('[data-person-menu-trigger]');

        if (menuTrigger) {
            event.preventDefault();
            event.stopPropagation();
            const shouldOpen = Boolean(personMenu?.hidden || openPersonMenuTrigger !== menuTrigger);
            closePersonMenu();

            if (personMenu && shouldOpen && configurePersonMenu(menuTrigger)) {
                personMenu.hidden = false;
                positionPersonMenu(menuTrigger);
                menuTrigger.setAttribute('aria-expanded', 'true');
                openPersonMenu = personMenu;
                openPersonMenuTrigger = menuTrigger;
                personMenu.querySelector('button:not([hidden]):not(:disabled)')?.focus();
            }

            return;
        }

        const statusTrigger = event.target.closest('[data-shift-status]');

        if (statusTrigger) {
            event.preventDefault();
            if (statusTrigger.dataset.shiftStatus === 'worked') {
                const confirmed = await confirmDialog({
                    title: 'Cofn\u0105\u0107 niedost\u0119pno\u015b\u0107?',
                    text: 'Je\u015bli pracownik ma zast\u0119pstwo, zostanie ono r\u00f3wnie\u017c usuni\u0119te.',
                    confirmText: 'Cofnij niedost\u0119pno\u015b\u0107',
                });

                if (!confirmed) {
                    closePersonMenu(true);
                    return;
                }
            }

            closePersonMenu(true);
            await runCardAction(
                statusTrigger,
                'PATCH',
                { status: statusTrigger.dataset.shiftStatus },
                'Status pracownika został zmieniony.',
            );

            return;
        }

        const removeTrigger = event.target.closest('[data-shift-remove]');

        if (removeTrigger) {
            event.preventDefault();
            const confirmed = await confirmDialog({
                title: 'Usun\u0105\u0107 pracownika z grafiku?',
                text: 'Powi\u0105zane zast\u0119pstwo r\u00f3wnie\u017c zostanie usuni\u0119te.',
                confirmText: 'Usu\u0144 z grafiku',
            });

            if (!confirmed) {
                closePersonMenu(true);
                return;
            }

            closePersonMenu(true);
            await runCardAction(removeTrigger, 'DELETE', null, 'Pracownik został usunięty z grafiku.');

            return;
        }

        const substituteTrigger = event.target.closest('[data-substitute-open]');

        if (substituteTrigger) {
            event.preventDefault();
            const sourceMenuTrigger = openPersonMenuTrigger;
            closePersonMenu();
            const requestSequence = ++substituteRequestSequence;
            substituteStoreUrl = substituteTrigger.dataset.storeUrl;
            substituteDescription.textContent = `Zastępstwo za: ${substituteTrigger.dataset.personName}`;
            substituteStatus.textContent = 'Ładowanie pracowników…';
            substituteStatus.hidden = false;
            substituteList.hidden = true;
            substituteList.replaceChildren();
            substituteSubmit.disabled = true;
            substituteForm.dataset.sourceCardId = actionCardFor(substituteTrigger)?.id ?? '';
            showDialog(substituteDialog, sourceMenuTrigger);

            try {
                const payload = await requestJson(substituteTrigger.dataset.candidatesUrl);

                if (requestSequence !== substituteRequestSequence || !substituteDialog.open) {
                    return;
                }

                const candidates = Array.isArray(payload.data) ? payload.data : [];

                candidates.forEach((candidate) => {
                    const label = document.createElement('label');
                    label.className = 'planner-substitute-option';

                    const input = document.createElement('input');
                    input.type = 'radio';
                    input.name = 'worker_id';
                    input.value = String(candidate.id);

                    const name = document.createElement('strong');
                    name.textContent = candidate.name;

                    const availability = document.createElement('em');
                    availability.textContent = candidate.is_available ? 'zadeklarowana dostępność' : 'brak deklaracji';

                    label.append(input, name, availability);
                    substituteList.append(label);
                });

                substituteStatus.textContent = candidates.length
                    ? 'Wybierz pracownika z listy.'
                    : 'Brak pracowników, których można przypisać do tej zmiany.';
                substituteList.hidden = candidates.length === 0;
            } catch (error) {
                if (requestSequence !== substituteRequestSequence || !substituteDialog.open) {
                    return;
                }

                substituteStatus.textContent = error.message;
                toast.error(error.message);
            }

            return;
        }

        if (!event.target.closest('[data-person-menu], .pm-dialog-backdrop')) {
            closePersonMenu();
        }

        const card = event.target.closest('[data-planner-day-card]');

        if (card && !event.target.closest('a, button, input, select, textarea, label, [data-person-menu]')) {
            window.location.assign(card.dataset.dayUrl);
        }
    });

    window.addEventListener('resize', () => {
        if (openPersonMenuTrigger) {
            positionPersonMenu(openPersonMenuTrigger);
        }
    });
    substituteList?.addEventListener('change', () => {
        substituteSubmit.disabled = !substituteForm.querySelector('input[name="worker_id"]:checked');
    });

    substituteForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const selectedWorker = substituteForm.querySelector('input[name="worker_id"]:checked');

        if (!selectedWorker || !substituteStoreUrl) {
            return;
        }

        substituteSubmit.disabled = true;
        substituteSubmitting = true;
        substituteDialog.setAttribute('aria-busy', 'true');
        const sourceCard = document.getElementById(substituteForm.dataset.sourceCardId);
        sourceCard?.setAttribute('aria-busy', 'true');

        try {
            const payload = await requestJson(substituteStoreUrl, {
                method: 'POST',
                body: JSON.stringify({ worker_id: Number(selectedWorker.value) }),
            });
            const focusFallbackTrigger = lastDialogTrigger;
            const focusShiftId = lastDialogTrigger?.dataset.shiftId ?? '';
            substituteSubmitting = false;
            lastDialogTrigger = null;
            closeDialog(substituteDialog);

            const replacement = sourceCard
                ? replaceDayCard(sourceCard, payload.day_html)
                : null;

            if (!replacement) {
                if (focusFallbackTrigger?.isConnected) {
                    focusFallbackTrigger.focus({ preventScroll: true });
                }

                throw new Error('Nie udało się odświeżyć dnia po przypisaniu zastępstwa.');
            }

            focusAfterCardReplacement(replacement, focusShiftId);
            toast.success(payload.message || 'Zastępstwo zostało przypisane.');
        } catch (error) {
            substituteSubmit.disabled = false;
            toast.error(error.message);
        } finally {
            substituteSubmitting = false;
            substituteDialog.removeAttribute('aria-busy');
            sourceCard?.removeAttribute('aria-busy');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePersonMenu(true);
        }
    });
});
