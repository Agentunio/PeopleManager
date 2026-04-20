import flatpickr from 'flatpickr';
import { Polish } from 'flatpickr/dist/l10n/pl.js';
import 'flatpickr/dist/flatpickr.min.css';
import Swal from 'sweetalert2';
import 'flatpickr/dist/themes/dark.css';
flatpickr.localize(Polish);

$(document).ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    const today = new Date();
    const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

    function formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function ajaxRequest(options) {
        const defaults = {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        return $.ajax($.extend(true, defaults, options));
    }

    function handleAjaxError(xhr, defaultMessage) {
        const errors = xhr.responseJSON?.errors;
        if (errors) {
            showToast.error(Object.values(errors).flat()[0]);
        } else {
            showToast.error(defaultMessage);
        }
    }

    function initFlatpickr(input) {
        flatpickr(input, {
            theme: "dark",
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [formatDate(firstDayOfMonth), formatDate(today)],
            onChange: function(selectedDates, dateStr, instance) {
                const $form = $(instance.element).closest('.date-range-form-inner');
                if (selectedDates.length === 2) {
                    $form.find('input[name="dateFrom"]').val(formatDate(selectedDates[0]));
                    $form.find('input[name="dateTo"]').val(formatDate(selectedDates[1]));
                }
            }
        });
    }

    function initFlatpickrIn($container) {
        $container.find('.flatpickr-range-input').each(function() {
            initFlatpickr(this);
        });
    }

    $('.flatpickr-range-input').each(function() {
        initFlatpickr(this);
    });

    $(document).on('submit', '.date-range-form-inner', function(e) {
        e.preventDefault();

        const $form = $(this);
        const workerId = $form.data('worker-id');
        const dateFrom = $form.find('input[name="dateFrom"]').val();
        const dateTo = $form.find('input[name="dateTo"]').val();

        if (!dateFrom || !dateTo) {
            showToast.error('Wybierz zakres dat');
            return;
        }

        const $hours = $(`#hours-value-${workerId}`);
        const $salary = $(`#salary-value-${workerId}`);

        $hours.text('...');
        $salary.text('...');

        ajaxRequest({
            type: "GET",
            url: $form.data('stats-url'),
            data: { dateFrom, dateTo }
        })
        .done(function(response) {
            $hours.text(response.hours);
            $salary.text(response.salary);
            showToast.success('Dane załadowane');
        })
        .fail(function(xhr) {
            $hours.text('--');
            $salary.text('--');
            handleAjaxError(xhr, 'Wystąpił błąd podczas pobierania danych');
        })
        .always(function() {
                $submitBtn.prop('disabled', false);
        });
    });


    function performSearch() {
        const searchWorker = $('#searchWorker').val();
        const filterStatus = $('#filterStatus').val();

        ajaxRequest({
            type: "GET",
            url: window.workersIndexUrl,
            data: { searchWorker, filterStatus }
        })
            .done(function(response) {
                $('#workers-list').html(response.html);
                $('#pagination-links').html(response.pagination);
                initFlatpickrIn($('#workers-list'));
                updateUrlParams({ searchWorker, filterStatus });
            })
            .fail(function() {
                showToast.error('Wystąpił błąd podczas wyszukiwania');
            });
    }

    function updateUrlParams(params) {
        const urlParams = new URLSearchParams();
        Object.entries(params).forEach(([key, value]) => {
            if (value) urlParams.set(key, value);
        });

        const newUrl = urlParams.toString()
            ? `${window.location.pathname}?${urlParams.toString()}`
            : window.location.pathname;
        window.history.replaceState({}, '', newUrl);
    }

    $("#searchForm").on("submit", function(e) {
        e.preventDefault();
        performSearch();
    });

    $("#clearSearch").on("click", function() {
        $('#searchWorker').val('');
        $('#filterStatus').val('');
        performSearch();
    });

    $(document).on('submit', '.delete-form', function(e) {
        e.preventDefault();

        const $form = $(this);
        const name = $form.data('name');

        Swal.fire({
            title: 'Czy na pewno?',
            text: `Chcesz usunąć pracownika (usuwając pracownika usuniesz wszystkie dane z nim związane): ${name}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e50914',
            cancelButtonColor: '#555',
            confirmButtonText: 'Tak, usuń',
            cancelButtonText: 'Anuluj',
            background: '#1f1f1f',
            color: '#f0f0f0'
        }).then((result) => {
            if (result.isConfirmed) {
                ajaxRequest({
                    type: "DELETE",
                    url: $form.attr('action')
                })
                    .done(function(response) {
                        showToast.success(response.message);
                        $form.closest('.settings-container').fadeOut(300, function() {
                            $(this).remove();
                        });
                    })
                    .fail(function() {
                        showToast.error('Wystąpił błąd podczas usuwania pracownika');
                    });
            }
        });
    });

    $("#addWorkerForm").on("submit", function(e) {
        e.preventDefault();

        const $form = $(this);

        ajaxRequest({
            type: "POST",
            url: $form.attr('action'),
            data: $form.serialize()
        })
            .done(function(response) {
                showToast.success(response.message);
                $('.empty-state').remove();

                const $newCard = $(response.html);
                $('#workers-list').append($newCard);
                initFlatpickrIn($newCard);

                $('#workers-count').text('(' + $('.settings-container[data-worker-id]').length + ')');
                $('#toggle-worker-form').prop('checked', false);
                $form[0].reset();
            })
            .fail(function(xhr) {
                handleAjaxError(xhr, 'Wystąpił błąd podczas dodawania pracownika');
            });
    });

    $(document).on('click', '.btn-generate', function() {
        const $btn = $(this);
        const url = $btn.data('url');

        Swal.fire({
            title: 'Generowanie konta',
            input: 'email',
            inputLabel: 'Adres e-mail pracownika',
            inputPlaceholder: 'jan@example.com',
            showCancelButton: true,
            confirmButtonColor: '#1a73e8',
            cancelButtonColor: '#555',
            confirmButtonText: 'Generuj konto',
            cancelButtonText: 'Anuluj',
            background: '#1f1f1f',
            color: '#f0f0f0',
            inputValidator: (value) => {
                if (!value) return 'Podaj adres e-mail';
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Podaj prawidłowy adres e-mail';
            }
        }).then((result) => {
            if (result.isConfirmed) {
                ajaxRequest({
                    type: 'POST',
                    url: url,
                    data: { email: result.value }
                })
                .done(function(response) {
                    showToast.success(response.message);
                    reloadWorkerCard($btn);
                })
                .fail(function(xhr) {
                    handleAjaxError(xhr, 'Wystąpił błąd podczas generowania konta');
                });
            }
        });
    });

    $(document).on('click', '.btn-pending-account', function() {
        const $btn = $(this);
        const email = $btn.data('email');

        Swal.fire({
            title: 'Konto oczekuje aktywacji',
            html: `<p style="color:#a0a0a0;margin-bottom:12px;">Link aktywacyjny został wysłany na:</p>
                   <p style="color:#f59e0b;font-weight:bold;font-size:1.1em;margin-bottom:16px;">${email}</p>
                   <p style="color:#a0a0a0;">Jeśli pracownik nie otrzymał maila, możesz wysłać link ponownie.</p>`,
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#555',
            confirmButtonText: '<i class="fas fa-paper-plane"></i> Wyślij ponownie',
            cancelButtonText: 'Zamknij',
            background: '#1f1f1f',
            color: '#f0f0f0'
        }).then((result) => {
            if (result.isConfirmed) {
                ajaxRequest({
                    type: 'POST',
                    url: $btn.data('url')
                })
                .done(function(response) {
                    showToast.success(response.message);
                })
                .fail(function(xhr) {
                    handleAjaxError(xhr, 'Wystąpił błąd podczas wysyłania linku');
                });
            }
        });
    });

    $(document).on('click', '.btn-deactivate, .btn-activate', function() {
        const $btn = $(this);
        const isDeactivate = $btn.hasClass('btn-deactivate');
        const action = isDeactivate ? 'dezaktywować' : 'aktywować';

        Swal.fire({
            title: `Czy na pewno chcesz ${action} konto?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: isDeactivate ? '#8B0000' : '#4CAF50',
            cancelButtonColor: '#555',
            confirmButtonText: isDeactivate ? 'Dezaktywuj' : 'Aktywuj',
            cancelButtonText: 'Anuluj',
            background: '#1f1f1f',
            color: '#f0f0f0'
        }).then((result) => {
            if (result.isConfirmed) {
                ajaxRequest({
                    type: 'POST',
                    url: $btn.data('url')
                })
                .done(function(response) {
                    showToast.success(response.message);
                    reloadWorkerCard($btn);
                })
                .fail(function(xhr) {
                    handleAjaxError(xhr, 'Wystąpił błąd');
                });
            }
        });
    });

    function reloadWorkerCard($element) {
        const $card = $element.closest('.settings-container');
        const workerId = $card.data('worker-id');

        ajaxRequest({
            type: 'GET',
            url: window.workersIndexUrl,
            data: {
                searchWorker: '',
                filterStatus: ''
            }
        }).done(function() {
            performSearch();
        });
    }

    $(document).on('submit', '.edit-worker-form', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $card = $form.closest('.settings-container');

        ajaxRequest({
            type: "POST",
            url: $form.attr('action'),
            data: $form.serialize()
        })
            .done(function(response) {
                showToast.success(response.message);
                const $newCard = $(response.html);
                $card.replaceWith($newCard);
                initFlatpickrIn($newCard);
            })
            .fail(function(xhr) {
                handleAjaxError(xhr, 'Wystąpił błąd podczas edycji');
            });
    });
});
