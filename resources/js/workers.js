import $ from 'jquery';
import { confirmDialog } from './confirm-dialog.js';

$(function () {
    const $app = $('#workersApp');

    if (!$app.length) {
        return;
    }

    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const indexUrl = $app.data('index-url');
    const storeUrl = $app.data('store-url');
    const settlementsUrl = $app.data('settlements-url');
    const $drawer = $('#workerDrawer');
    const $drawerBody = $drawer.find('.worker-drawer-body');
    const $drawerBackdrop = $('#workerDrawerBackdrop');
    const $workerForm = $('#workerForm');
    const $accountModal = $('#accountModal');
    let currentWorker = null;
    let accountAction = null;
    let searchTimer = null;
    let workersRequest = null;
    let settlementSearchTimer = null;
    let settlementsRequest = null;
    let settlementsLoaded = false;
    let settlementsInitialized = false;
    let settlementInitialization = null;
    let settlementRangeCalendar = null;
    let selectedWorkerId = null;
    let settlementWorkers = [];
    let settlementPage = 1;
    let settlementSearch = '';
    let drawerReturnFocus = null;
    let accountReturnFocus = null;
    let pickerReturnFocus = null;
    const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function restoreFocus(element) {
        if (!element || !document.contains(element)) return;
        window.setTimeout(() => element.focus(), 0);
    }

    function trapFocus(event, $layer) {
        const focusable = $layer.find(focusableSelector).filter(':visible').toArray();
        if (!focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function ajaxRequest(options) {
        return $.ajax($.extend(true, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            }
        }, options));
    }

    function firstError(xhr, fallback) {
        const errors = xhr.responseJSON?.errors;

        if (errors) {
            return Object.values(errors).flat()[0];
        }

        return xhr.responseJSON?.message || fallback;
    }

    function setBusy($button, busy, busyLabel) {
        if (!$button.length) return;

        if (busy) {
            $button.data('original-label', $button.text()).prop('disabled', true).text(busyLabel);
        } else {
            $button.prop('disabled', false).text($button.data('original-label') || $button.text());
        }
    }

    function parseWorker($row) {
        const json = $row.find('.worker-json').text();

        try {
            return JSON.parse(json);
        } catch (_error) {
            showToast.error('Nie udało się odczytać danych pracownika');
            return null;
        }
    }

    function currentListParams() {
        return {
            searchWorker: $('#searchWorker').val().trim(),
            filterEmployment: $('#filterEmployment').val(),
            filterStudent: $('#filterStudent').val()
        };
    }

    function updateListUrl(params, requestedUrl = indexUrl) {
        if (requestedUrl !== indexUrl) {
            const normalizedUrl = new URL(requestedUrl, window.location.origin);
            normalizedUrl.searchParams.delete('tab');
            window.history.replaceState({}, '', `${normalizedUrl.pathname}${normalizedUrl.search}${normalizedUrl.hash}`);
            return;
        }

        const query = new URLSearchParams();

        Object.entries(params).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) {
                query.set(key, value);
            }
        });

        const nextUrl = query.toString() ? `${indexUrl}?${query}` : indexUrl;
        window.history.replaceState({}, '', nextUrl);
    }

    function loadWorkers(url = indexUrl) {
        if (workersRequest) workersRequest.abort();

        const params = currentListParams();
        const request = ajaxRequest({
            type: 'GET',
            url,
            data: url === indexUrl ? params : undefined
        });
        workersRequest = request;

        return request.done(function (response) {
            $('#workers-list').html(response.html);
            $('#pagination-links').html(response.pagination);
            $('#filteredWorkersCount').text(response.filteredTotal);
            $('#totalWorkersCount').text(response.totalWorkers);
            updateListUrl(params, url);
        }).fail(function (xhr, status) {
            if (status !== 'abort') {
                showToast.error(firstError(xhr, 'Nie udało się pobrać listy pracowników'));
            }
        }).always(function () {
            if (workersRequest === request) workersRequest = null;
        });
    }

    $('#searchForm').on('submit', function (event) {
        event.preventDefault();
        loadWorkers();
    });

    $('#searchWorker').on('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => loadWorkers(), 300);
    });

    $(document).on('click', '.workers-filter-chip', function () {
        const $chip = $(this);
        const name = $chip.data('filter-name');

        $(`#${name}`).val(String($chip.data('filter-value') ?? ''));
        $(`.workers-filter-chip[data-filter-name="${name}"]`)
            .removeClass('is-active')
            .attr('aria-pressed', 'false');
        $chip.addClass('is-active').attr('aria-pressed', 'true');
        loadWorkers();
    });

    $(document).on('click', '#pagination-links a', function (event) {
        event.preventDefault();
        loadWorkers($(this).attr('href'));
    });

    function updateTabUrl(tab) {
        const url = new URL(window.location.href);

        if (tab === 'settlements') {
            url.searchParams.set('tab', 'settlements');
        } else {
            url.searchParams.delete('tab');
        }

        window.history.replaceState({}, '', url);
    }

    function switchTab(tab, syncUrl = true) {
        if (!['list', 'settlements'].includes(tab)) return;

        $('[data-workers-tab]').each(function () {
            const active = $(this).data('workers-tab') === tab;
            $(this)
                .toggleClass('is-active', active)
                .attr('aria-selected', String(active))
                .attr('tabindex', active ? '0' : '-1');
        });
        $('[data-workers-panel]').each(function () {
            $(this).prop('hidden', $(this).data('workers-panel') !== tab);
        });
        $('.workers-desktop-add, .workers-mobile-add').prop('hidden', tab !== 'list');

        if (syncUrl) updateTabUrl(tab);

        if (tab === 'settlements' && !settlementsLoaded && !settlementsRequest) {
            initializeSettlements()
                .then(() => {
                    if (!$('#workersSettlementsPanel').prop('hidden') && !settlementsLoaded && !settlementsRequest) {
                        loadSettlements();
                    }
                })
                .catch(() => {
                    showToast.error('Nie udało się przygotować rozliczeń');
                });
        }
    }

    $('[data-workers-tab]').on('click', function () {
        switchTab($(this).data('workers-tab'));
    });

    $('[data-workers-tab]').on('keydown', function (event) {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

        const tabs = $('[data-workers-tab]').toArray();
        const currentIndex = tabs.indexOf(this);
        let nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : currentIndex;
        if (event.key === 'ArrowLeft') nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
        if (event.key === 'ArrowRight') nextIndex = (currentIndex + 1) % tabs.length;

        event.preventDefault();
        $(tabs[nextIndex]).trigger('click').trigger('focus');
    });

    function fillRadio(name, value) {
        $workerForm.find(`input[name="${name}"][value="${value ? 1 : 0}"]`).prop('checked', true);
    }

    function resetWorkerForm() {
        $workerForm[0].reset();
        $workerForm.attr('action', storeUrl);
        $('#workerFormMethod').val('POST');
        fillRadio('is_employed', true);
        fillRadio('is_student', false);
    }

    function updateAccountBox(worker) {
        const $box = $('#workerAccountBox');
        const $action = $('#workerAccountAction');
        const $toggle = $('#workerAccountToggle');
        const account = worker?.account;

        if (!worker || !account) {
            $box.prop('hidden', true);
            return;
        }

        $box.prop('hidden', false).toggleClass('is-active', account.state === 'active');
        $('#workerAccountLabel').text('konto w systemie');
        $action.prop('hidden', false);
        $toggle.prop('hidden', account.state !== 'active').text('Dezaktywuj konto');

        if (account.state === 'active') {
            $('#workerAccountDescription').text(account.email || 'Konto aktywne');
            $action.text('Wyślij link resetujący');
        } else if (account.state === 'pending') {
            $('#workerAccountDescription').text(account.email || 'Oczekuje na aktywację');
            $action.text('Wyślij ponownie');
        } else if (account.state === 'inactive') {
            $('#workerAccountDescription').text(account.email || 'Konto nieaktywne');
            $action.text('Aktywuj konto');
        } else {
            $('#workerAccountDescription').text('Pracownik nie ma jeszcze konta');
            $action.text('Generuj konto');
        }
    }

    function openWorkerDrawer(worker = null) {
        drawerReturnFocus = document.activeElement;
        currentWorker = worker;
        resetWorkerForm();
        $drawerBody.scrollTop(0);

        if (worker) {
            $workerForm.attr('action', worker.updateUrl);
            $('#workerFormMethod').val('PUT');
            $('#workerDrawerTitle').text('Edytuj pracownika');
            $('#workerDrawerSubtitle').text(`${worker.firstName} ${worker.lastName}`);
            $('#workerSubmitButton').text('Zapisz zmiany');
            $('#deleteWorkerButton').prop('hidden', false);
            $('#workerFirstName').val(worker.firstName);
            $('#workerLastName').val(worker.lastName);
            $('#workerPhone').val(worker.phone || '');
            $('#workerAddress').val(worker.address || '');
            $('#workerDob').val(worker.dateOfBirth || '');
            $('#workerContractFrom').val(worker.contractFrom || '');
            $('#workerContractTo').val(worker.contractTo || '');
            fillRadio('is_employed', worker.isEmployed);
            fillRadio('is_student', worker.isStudent);
            updateAccountBox(worker);
        } else {
            $('#workerDrawerTitle').text('Dodaj pracownika');
            $('#workerDrawerSubtitle').text('Wprowadź dane nowego pracownika');
            $('#workerSubmitButton').text('Dodaj pracownika');
            $('#deleteWorkerButton').prop('hidden', true);
            $('#workerAccountBox').prop('hidden', true);
        }

        $drawerBackdrop.prop('hidden', false);
        $drawer.prop('inert', false).addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('is-layer-open');
        window.setTimeout(() => $('#workerFirstName').trigger('focus'), 220);
    }

    function closeWorkerDrawer() {
        if (!$drawer.hasClass('is-open')) return;

        $drawer.prop('inert', true).removeClass('is-open').attr('aria-hidden', 'true');
        $drawerBackdrop.prop('hidden', true);
        $('body').removeClass('is-layer-open');
        currentWorker = null;
        restoreFocus(drawerReturnFocus);
        drawerReturnFocus = null;
    }

    $('.js-open-worker-drawer').on('click', () => openWorkerDrawer());
    $('.js-close-worker-drawer').on('click', closeWorkerDrawer);
    $drawerBackdrop.on('click', function () {
        closeWorkerDrawer();
        closeSettlementWorkerPicker();
    });

    $(document).on('click', '.js-edit-worker', function () {
        const worker = parseWorker($(this).closest('.worker-row'));
        if (worker) openWorkerDrawer(worker);
    });

    $workerForm.on('submit', function (event) {
        event.preventDefault();

        if (!$workerForm[0].checkValidity()) {
            $workerForm[0].reportValidity();
            return;
        }

        const $submit = $('#workerSubmitButton');
        setBusy($submit, true, 'Zapisywanie…');

        ajaxRequest({
            type: 'POST',
            url: $workerForm.attr('action'),
            data: $workerForm.serialize()
        }).done(function (response) {
            showToast.success(response.message);
            closeWorkerDrawer();
            loadWorkers();
            settlementsLoaded = false;
        }).fail(function (xhr) {
            showToast.error(firstError(xhr, 'Nie udało się zapisać pracownika'));
        }).always(function () {
            setBusy($submit, false);
        });
    });

    $('#deleteWorkerButton').on('click', function () {
        if (!currentWorker) return;

        confirmDialog({
            title: 'Usunąć pracownika?',
            text: `${currentWorker.firstName} ${currentWorker.lastName} oraz wszystkie powiązane dane zostaną usunięte.`,
            confirmText: 'Usuń pracownika'
        }).then(function (confirmed) {
            if (!confirmed) return;

            ajaxRequest({
                type: 'DELETE',
                url: currentWorker.deleteUrl
            }).done(function (response) {
                showToast.success(response.message);
                closeWorkerDrawer();
                loadWorkers();
                settlementsLoaded = false;
            }).fail(function (xhr) {
                showToast.error(firstError(xhr, 'Nie udało się usunąć pracownika'));
            });
        });
    });

    function suggestedEmail(worker) {
        const normalize = (value) => value
            .toLocaleLowerCase('pl-PL')
            .replace(/ł/g, 'l')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z]/g, '');
        const firstName = normalize(worker.firstName);
        const lastName = normalize(worker.lastName);

        return firstName && lastName ? `${firstName.charAt(0)}.${lastName}@d-paper.pl` : '';
    }

    function openAccountModal(worker, mode) {
        if (mode === 'create' && !worker.dateOfBirth) {
            showToast.error('Najpierw uzupełnij datę urodzenia pracownika');
            return;
        }

        accountReturnFocus = document.activeElement;
        currentWorker = worker;
        accountAction = mode;
        const $email = $('#accountEmail');
        const $warning = $('#accountModalWarning');
        const email = worker.account.email || suggestedEmail(worker);

        $('#accountModalTitle').text(`${worker.firstName} ${worker.lastName}`);
        $email.val(email).prop('readonly', mode !== 'create');
        $warning.prop('hidden', true);

        if (mode === 'create') {
            $('#accountModalText').text('Wyślemy wiadomość aktywacyjną z jednorazowym linkiem do ustawienia hasła.');
            $('#accountModalSubmit').text('Wyślij link aktywacyjny');
        } else if (mode === 'regenerate') {
            $('#accountModalText').text('Ponownie wyślemy link aktywacyjny na zapisany adres e-mail.');
            $('#accountModalSubmit').text('Wyślij ponownie');
        } else {
            $('#accountModalText').text('Wyślemy bezpieczny, jednorazowy link do ustawienia nowego hasła.');
            $('#accountModalSubmit').text('Wyślij link resetujący');
            $warning.text('Obecne hasło pozostanie aktywne do czasu ustawienia nowego hasła przez pracownika.').prop('hidden', false);
        }

        $accountModal.prop('hidden', false);
        $('body').addClass('is-layer-open');
        window.setTimeout(() => $email.trigger('focus'), 50);
    }

    function closeAccountModal() {
        if ($accountModal.prop('hidden')) return;

        $accountModal.prop('hidden', true);
        accountAction = null;
        if (!$drawer.hasClass('is-open')) $('body').removeClass('is-layer-open');
        restoreFocus(accountReturnFocus);
        accountReturnFocus = null;
    }

    $('[data-close-account-modal]').on('click', closeAccountModal);

    $(document).on('click', '.js-account-direct', function () {
        const worker = parseWorker($(this).closest('.worker-row'));
        if (worker) openAccountModal(worker, 'create');
    });

    $('#workerAccountAction').on('click', function () {
        if (!currentWorker) return;

        const state = currentWorker.account.state;
        if (state === 'active') openAccountModal(currentWorker, 'reset');
        else if (state === 'pending') openAccountModal(currentWorker, 'regenerate');
        else if (state === 'missing') openAccountModal(currentWorker, 'create');
        else toggleWorkerAccount(currentWorker);
    });

    $('#workerAccountToggle').on('click', function () {
        if (!currentWorker) return;

        confirmDialog({
            title: 'Dezaktywować konto?',
            text: `${currentWorker.firstName} ${currentWorker.lastName} straci dostęp do strefy pracownika. Konto można później aktywować ponownie.`,
            confirmText: 'Dezaktywuj konto'
        }).then(function (confirmed) {
            if (!confirmed) return;

            toggleWorkerAccount(currentWorker);
        });
    });

    function toggleWorkerAccount(worker) {
        ajaxRequest({ type: 'POST', url: worker.account.toggleUrl })
            .done(function (response) {
                showToast.success(response.message);
                closeWorkerDrawer();
                loadWorkers();
            })
            .fail(function (xhr) {
                showToast.error(firstError(xhr, 'Nie udało się zmienić statusu konta'));
            });
    }

    $('#accountModalSubmit').on('click', function () {
        if (!currentWorker || !accountAction) return;

        const email = $('#accountEmail').val().trim();
        if (!email || !$('#accountEmail')[0].checkValidity()) {
            $('#accountEmail')[0].reportValidity();
            return;
        }

        const urls = {
            create: currentWorker.account.createUrl,
            regenerate: currentWorker.account.regenerateUrl,
            reset: currentWorker.account.passwordResetUrl
        };
        const $submit = $(this);
        setBusy($submit, true, 'Wysyłanie…');

        ajaxRequest({
            type: 'POST',
            url: urls[accountAction],
            data: accountAction === 'create' ? { email } : undefined
        }).done(function (response) {
            showToast.success(response.message);
            closeAccountModal();
            closeWorkerDrawer();
            loadWorkers();
        }).fail(function (xhr) {
            showToast.error(firstError(xhr, 'Nie udało się wysłać wiadomości'));
        }).always(function () {
            setBusy($submit, false);
        });
    });

    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function parseDate(value) {
        const [year, month, day] = value.split('-').map(Number);
        return new Date(year, month - 1, day);
    }

    function formatMoney(value) {
        return Number(value || 0).toLocaleString('pl-PL', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatMinutes(minutes) {
        const total = Number(minutes || 0);
        const hours = Math.floor(total / 60);
        const rest = total % 60;

        if (!rest) return `${hours}h`;
        if (!hours) return `${rest}min`;
        return `${hours}h ${rest}min`;
    }

    function formatSettlementDate(value) {
        return parseDate(value).toLocaleDateString('pl-PL', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    function renderSettlementRangeLabel(range) {
        $('#settlementRangeLabel').text(
            `${formatSettlementDate(range.from)} — ${formatSettlementDate(range.to)}`
        );
    }

    function markSettlementPreset(activePreset) {
        document.querySelectorAll('[data-settlement-preset]').forEach((button) => {
            const isActive = button.dataset.settlementPreset === activePreset;
            button.classList.toggle('is-selected', isActive);
            button.setAttribute('aria-pressed', String(isActive));
        });
    }

    const today = new Date();
    const todayIso = formatDate(today);
    let settlementRange = {
        from: formatDate(new Date(today.getFullYear(), today.getMonth(), 1)),
        to: todayIso
    };

    function initializeSettlements() {
        if (settlementsInitialized) return Promise.resolve();
        if (settlementInitialization) return settlementInitialization;

        settlementInitialization = import('./range-calendar.js')
            .then(({ createRangeCalendar }) => {
                settlementRangeCalendar = createRangeCalendar({
                    container: document.getElementById('settlementRangeCalendar'),
                    initialFrom: settlementRange.from,
                    initialTo: settlementRange.to,
                    initialMonth: todayIso,
                    maxDate: todayIso,
                    maxMonth: todayIso.slice(0, 7),
                    today: todayIso,
                    onRangePreview(nextRange) {
                        markSettlementPreset(null);
                        renderSettlementRangeLabel(nextRange);
                    },
                    onRangeChange(nextRange) {
                        settlementRange = nextRange;
                        markSettlementPreset(null);
                        renderSettlementRangeLabel(settlementRange);
                        loadSettlements(selectedWorkerId);
                    }
                });

                renderSettlementRangeLabel(settlementRange);
                bindSettlementEvents();
                settlementsInitialized = true;
            })
            .catch((error) => {
                settlementInitialization = null;
                throw error;
            });

        return settlementInitialization;
    }

    function setSettlementRange(from, to, preset) {
        settlementRange = { from: formatDate(from), to: formatDate(to) };
        settlementRangeCalendar.setView(settlementRange.to);
        settlementRangeCalendar.setRange(settlementRange.from, settlementRange.to);
        markSettlementPreset(preset);
        renderSettlementRangeLabel(settlementRange);
        loadSettlements(selectedWorkerId);
    }

    function bindSettlementEvents() {
        $('[data-settlement-preset]').on('click', function () {
            const preset = $(this).data('settlement-preset');
            const end = new Date();
            let start = new Date(end);

            if (preset === 'week') {
                const day = end.getDay() || 7;
                start.setDate(end.getDate() - day + 1);
            } else if (preset === 'month') {
                start = new Date(end.getFullYear(), end.getMonth(), 1);
            }

            setSettlementRange(start, end, preset);
        });

        $(document).on('click', '.settlement-worker-option', function () {
            selectedWorkerId = Number($(this).data('worker-id'));
            closeSettlementWorkerPicker();
            loadSettlements(selectedWorkerId, settlementPage);
        });

        $('#settlementWorkerSearch').on('input', function () {
            settlementSearch = $(this).val().trim();
            window.clearTimeout(settlementSearchTimer);
            settlementSearchTimer = window.setTimeout(function () {
                selectedWorkerId = null;
                settlementPage = 1;
                loadSettlements(null, settlementPage);
            }, 300);
        });

        $(document).on('click', '#settlementPagination button[data-settlement-page]', function () {
            selectedWorkerId = null;
            settlementPage = Number($(this).data('settlement-page'));
            loadSettlements(null, settlementPage);
        });

        $('#openSettlementWorkerPicker').on('click', openSettlementWorkerPicker);
        $('#closeSettlementWorkerPicker').on('click', closeSettlementWorkerPicker);
        $(window).on('resize', syncSettlementPickerAccessibility);
    }

    function createWorkerOption(worker) {
        const button = document.createElement('button');
        const name = document.createElement('span');
        const stats = document.createElement('span');
        const hours = document.createElement('span');
        const salary = document.createElement('small');

        button.type = 'button';
        button.className = 'settlement-worker-option';
        button.dataset.workerId = String(worker.id);
        button.dataset.workerName = worker.name.toLocaleLowerCase('pl-PL');
        button.classList.toggle('is-active', worker.id === selectedWorkerId);
        name.textContent = worker.name;
        hours.textContent = worker.hours;
        salary.textContent = `${formatMoney(worker.salary)} zł`;
        stats.append(hours, salary);
        button.append(name, stats);

        return button;
    }

    function renderSettlementWorkers(workers) {
        const list = document.getElementById('settlementWorkerList');

        if (!workers.length) {
            const empty = document.createElement('p');
            empty.className = 'settlement-worker-empty';
            empty.textContent = '[ brak wyników ]';
            list.replaceChildren(empty);
            return;
        }

        list.replaceChildren(...workers.map(createWorkerOption));
    }

    function createSettlementPaginationButton(label, page, disabled, ariaLabel) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.dataset.settlementPage = String(page);
        button.disabled = disabled;
        button.setAttribute('aria-label', ariaLabel);
        return button;
    }

    function renderSettlementPagination(pagination) {
        const navigation = document.getElementById('settlementPagination');
        const currentPage = Number(pagination?.currentPage || 1);
        const lastPage = Number(pagination?.lastPage || 1);

        if (lastPage <= 1) {
            navigation.replaceChildren();
            navigation.hidden = true;
            return;
        }

        const previous = createSettlementPaginationButton(
            '‹',
            currentPage - 1,
            currentPage <= 1,
            'Poprzednia strona pracowników'
        );
        const status = document.createElement('span');
        status.textContent = `${currentPage} / ${lastPage}`;
        status.setAttribute('aria-live', 'polite');
        const next = createSettlementPaginationButton(
            '›',
            currentPage + 1,
            currentPage >= lastPage,
            'Następna strona pracowników'
        );

        navigation.replaceChildren(previous, status, next);
        navigation.hidden = false;
    }

    function renderEmptySettlementDetail() {
        $('#settlementWorkerName, #settlementMobileWorkerName').text('Brak wyników');
        $('#settlementMorningHours, #settlementAfternoonHours').text('0h');
        $('#settlementSalary').text('0,00');
        document.getElementById('settlementDays').replaceChildren();
    }

    function createDayCell(day) {
        const date = parseDate(day.date);
        const weekdays = ['Nd', 'Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'Sb'];
        const cell = document.createElement('div');
        const weekday = document.createElement('small');
        const dayNumber = document.createElement('strong');
        const slots = document.createElement('div');
        const morning = document.createElement('div');
        const afternoon = document.createElement('div');
        const hasWork = day.morningMinutes > 0 || day.afternoonMinutes > 0;

        cell.className = 'settlement-day';
        cell.classList.toggle('is-weekend', [0, 6].includes(date.getDay()));
        cell.classList.toggle('has-work', hasWork);
        weekday.textContent = weekdays[date.getDay()];
        dayNumber.textContent = String(date.getDate());
        slots.className = 'settlement-day-slots';
        morning.className = `settlement-day-slot is-morning${day.morningMinutes ? ' has-hours' : ''}`;
        afternoon.className = `settlement-day-slot is-afternoon${day.afternoonMinutes ? ' has-hours' : ''}`;
        morning.textContent = day.morningMinutes ? formatMinutes(day.morningMinutes) : '·';
        afternoon.textContent = day.afternoonMinutes ? formatMinutes(day.afternoonMinutes) : '·';
        slots.append(morning, afternoon);

        if (day.absent) {
            const absence = document.createElement('div');
            absence.className = 'settlement-day-absence';
            absence.textContent = 'NB';
            slots.append(absence);
        }

        cell.append(weekday, dayNumber, slots);
        return cell;
    }

    function renderSettlement(response) {
        settlementWorkers = response.workers || [];
        const pagination = response.pagination || {};
        const totalWorkers = Number(pagination.total ?? settlementWorkers.length);
        settlementPage = Number(pagination.currentPage || 1);
        selectedWorkerId = response.selected?.id ?? null;
        renderSettlementWorkers(settlementWorkers);
        renderSettlementPagination(pagination);

        if (!response.selected) {
            if (!settlementSearch && totalWorkers === 0) {
                $('#settlementsLayout').prop('hidden', true);
                $('#settlementsEmpty').prop('hidden', false);
                return;
            }

            $('#settlementsLayout').prop('hidden', false);
            $('#settlementsEmpty').prop('hidden', true);
            renderEmptySettlementDetail();
            return;
        }

        $('#settlementsLayout').prop('hidden', false);
        $('#settlementsEmpty').prop('hidden', true);
        $('#settlementWorkerName, #settlementMobileWorkerName').text(response.selected.name);
        $('#settlementMorningHours').text(response.selected.byShift.morning.hours);
        $('#settlementAfternoonHours').text(response.selected.byShift.afternoon.hours);
        $('#settlementSalary').text(formatMoney(response.selected.salary));

        const days = document.getElementById('settlementDays');
        days.replaceChildren(...response.selected.days.map(createDayCell));
    }

    function loadSettlements(workerId = null, page = settlementPage) {
        if (settlementsRequest) settlementsRequest.abort();

        $('#settlementLoading').prop('hidden', false);
        const request = ajaxRequest({
            type: 'GET',
            url: settlementsUrl,
            data: {
                workerId: workerId || undefined,
                dateFrom: settlementRange.from,
                dateTo: settlementRange.to,
                page,
                searchWorker: settlementSearch || undefined
            }
        });
        settlementsRequest = request;

        request.done(function (response) {
            settlementsLoaded = true;
            renderSettlement(response);
        }).fail(function (xhr, status) {
            if (status !== 'abort') {
                showToast.error(firstError(xhr, 'Nie udało się pobrać rozliczenia'));
            }
        }).always(function () {
            if (settlementsRequest !== request) return;

            $('#settlementLoading').prop('hidden', true);
            settlementsRequest = null;
        });
    }



    function openSettlementWorkerPicker() {
        if (window.matchMedia('(min-width: 769px)').matches) return;
        pickerReturnFocus = document.activeElement;
        $('#settlementWorkerPicker').addClass('is-open');
        syncSettlementPickerAccessibility();
        $drawerBackdrop.prop('hidden', false);
        $('body').addClass('is-layer-open');
        window.setTimeout(() => $('#settlementWorkerSearch').trigger('focus'), 220);
    }

    function closeSettlementWorkerPicker() {
        const $picker = $('#settlementWorkerPicker');
        const wasOpen = $picker.hasClass('is-open');
        $picker.removeClass('is-open');
        syncSettlementPickerAccessibility();
        if (!$drawer.hasClass('is-open')) {
            $drawerBackdrop.prop('hidden', true);
            $('body').removeClass('is-layer-open');
        }
        if (wasOpen) restoreFocus(pickerReturnFocus);
        pickerReturnFocus = null;
    }

    function syncSettlementPickerAccessibility() {
        const $picker = $('#settlementWorkerPicker');
        const isMobile = window.matchMedia('(max-width: 768px)').matches;
        const isOpen = $picker.hasClass('is-open');

        if (!isMobile) {
            $picker.prop('inert', false).removeAttr('aria-hidden role aria-modal');
            return;
        }

        $picker
            .prop('inert', !isOpen)
            .attr('aria-hidden', String(!isOpen))
            .attr('role', 'dialog')
            .attr('aria-modal', 'true');
    }


    syncSettlementPickerAccessibility();

    const initialTab = $app.data('active-tab');
    switchTab(initialTab === 'settlements' ? 'settlements' : 'list', false);

    $(document).on('keydown', function (event) {
        if (event.key === 'Tab' && !$('.swal2-container:visible').length) {
            if (!$accountModal.prop('hidden')) trapFocus(event, $accountModal);
            else if ($drawer.hasClass('is-open')) trapFocus(event, $drawer);
            else if ($('#settlementWorkerPicker').hasClass('is-open')) {
                trapFocus(event, $('#settlementWorkerPicker'));
            }
            return;
        }

        if (event.key !== 'Escape') return;

        if (!$accountModal.prop('hidden')) closeAccountModal();
        else if ($drawer.hasClass('is-open')) closeWorkerDrawer();
        else closeSettlementWorkerPicker();
    });
});
