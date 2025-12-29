$(document).ready(function () {
    // Flatpickr date range configuration
    const formatDate = (date) => {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

    const todayString = formatDate(today);
    const firstDayString = formatDate(firstDay);

    const rangeInputs = document.querySelectorAll('.flatpickr-range-input');

    rangeInputs.forEach(input => {
        flatpickr(input, {
            theme: "dark",
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [firstDayString, todayString],
            onChange: function(selectedDates, dateStr, instance) {
                const form = instance.element.closest('.date-range-form-inner');
                if (!form) return;
                const fromInput = form.querySelector('input[name="dateFrom"]');
                const toInput = form.querySelector('input[name="dateTo"]');
                if (selectedDates.length === 2 && fromInput && toInput) {
                    fromInput.value = formatDate(selectedDates[0]);
                    toInput.value = formatDate(selectedDates[1]);
                }
            }
        });
    });

    // Search form handling
    $("#searchForm").on("submit", function(e) {
        e.preventDefault();
        performSearch();
    });

    $("#clearSearch").on("click", function() {
        $('#searchWorker').val('');
        $('#filterStatus').val('');
        performSearch();
    });

    function performSearch() {
        const searchWorker = $('#searchWorker').val();
        const filterStatus = $('#filterStatus').val();

        $.ajax({
            type: "GET",
            url: window.workersIndexUrl,
            data: {
                searchWorker: searchWorker,
                filterStatus: filterStatus
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.status === 'success') {
                    $('#workers-list').html(response.html);

                    const params = new URLSearchParams();
                    if (searchWorker) params.set('searchWorker', searchWorker);
                    if (filterStatus) params.set('filterStatus', filterStatus);
                    const newUrl = params.toString()
                        ? `${window.location.pathname}?${params.toString()}`
                        : window.location.pathname;
                    window.history.replaceState({}, '', newUrl);
                }
            },
            error: function(xhr) {
                showToast.error('Wystąpił błąd podczas wyszukiwania');
            }
        });
    }

    // Delete worker handling
    $(document).on('submit', '.delete-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const name = form.data('name');
        const url = form.attr('action');

        Swal.fire({
            title: 'Czy na pewno?',
            text: `Chcesz usunąć pracownika: ${name}?`,
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
                $.ajax({
                    type: "DELETE",
                    url: url,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (response) {
                        if (response.status === 'success') {
                            showToast.success(response.message);
                            form.closest('.settings-container').fadeOut(300, function() {
                                $(this).remove();
                            });
                        }
                    },
                    error: function (xhr) {
                        showToast.error('Wystąpił błąd podczas usuwania pracownika');
                    }
                });
            }
        });
    });

    // Add worker form handling
    $("#addWorkerForm").on("submit", function (e) {
        e.preventDefault();
        const form = $(this);

        $.ajax({
            type: "POST",
            url: form.attr('action'),
            data: form.serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (response.status === 'success') {
                    showToast.success(response.message);

                    $('.empty-state').remove();

                    $('#workers-list').append(response.html);

                    const count = $('.settings-container[data-worker-id]').length;
                    $('#workers-count').text('(' + count + ')');

                    $('#toggle-worker-form').prop('checked', false);
                    form[0].reset();
                }
            },
            error: function (xhr) {
                let errors = xhr.responseJSON?.errors;
                if (errors) {
                    showToast.error(Object.values(errors).flat()[0]);
                } else {
                    showToast.error('Wystąpił błąd podczas dodawania pracownika');
                }
            }
        });
    });

    // Edit worker form handling
    $(document).on('submit', '.edit-worker-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const card = form.closest('.settings-container');
        const url = form.attr('action');

        $.ajax({
            type: "POST",
            url: url,
            data: form.serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.status === 'success') {
                    showToast.success(response.message);
                    card.replaceWith(response.html);
                }
            },
            error: function(xhr) {
                let errors = xhr.responseJSON?.errors;
                if (errors) {
                    showToast.error(Object.values(errors).flat()[0]);
                } else {
                    showToast.error('Wystąpił błąd podczas edycji');
                }
            }
        });
    });
});
