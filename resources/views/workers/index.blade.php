@extends('layouts.app')

@section('title', 'Pracownicy - Panel administratora')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}">
    <link rel="stylesheet" href="{{ asset('css/workers.css') }}">
@endpush

@section('content')
<div class="admin-panel">
    @include('partials.menu')

    <main class="main-content">
        @include('partials.alerts')

        <div class="header">
            <h1>Pracownicy</h1>
            <p>Zarządzaj listą pracowników, ich danymi oraz sprawdź ich wynagrodzenie.</p>
            <label for="toggle-worker-form" class="toggle-btn btn btn-change">
                <i class="fa-solid fa-plus"></i> Dodaj Nowego Pracownika
            </label>
        </div>

        <input type="checkbox" id="toggle-worker-form">

        <div class="edit-form">
            <h2>Dodaj Nowego Pracownika</h2>

            <form id="addWorkerForm" action="{{ route('workers.store') }}" method="post">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label for="workerFirstName" class="form-label">Imię</label>
                        <input type="text" id="workerFirstName" name="first_name" class="form-input" placeholder="np. Jan" required>
                    </div>

                    <div class="form-group">
                        <label for="workerLastName" class="form-label">Nazwisko</label>
                        <input type="text" id="workerLastName" name="last_name" class="form-input" placeholder="np. Kowalski" required>
                    </div>

                    <div class="form-group">
                        <label for="workerPhone" class="form-label">Numer telefonu</label>
                        <input type="tel" id="workerPhone" name="phone" class="form-input" placeholder="np. 123 456 789">
                    </div>

                    <div class="form-group">
                        <label for="workerAddress" class="form-label">Miejsce zamieszkania</label>
                        <input type="text" id="workerAddress" name="address" class="form-input" placeholder="np. Warszawa, ul. Prosta 1">
                    </div>

                    <div class="form-group">
                        <label for="workerDob" class="form-label">Data urodzenia</label>
                        <input type="date" id="workerDob" name="date_of_birth" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="workerStudentStatus" class="form-label">Status ucznia</label>
                        <select id="workerStudentStatus" name="is_student" class="form-input">
                            <option value="0">Nie</option>
                            <option value="1">Tak</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="workerEmploymentStatus" class="form-label">Status zatrudnienia</label>
                        <select id="workerEmploymentStatus" name="is_employed" class="form-input">
                            <option value="1">Tak</option>
                            <option value="0">Nie</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="workerContractFrom" class="form-label">Umowa od daty</label>
                        <input type="date" id="workerContractFrom" name="contract_from" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="workerContractTo" class="form-label">Umowa do daty</label>
                        <input type="date" id="workerContractTo" name="contract_to" class="form-input">
                    </div>
                </div>

                <div class="form-actions">
                    <label for="toggle-worker-form" class="btn btn-cancel toggle-btn">Anuluj</label>
                    <button type="submit" id="workerSubmit" class="btn btn-submit">Zapisz Pracownika</button>
                </div>
            </form>
        </div>


        <div class="settings-container" id="search-container">
            <h2>Lista pracowników</h2>

            <form id="searchForm" class="worker-search-bar" action="{{ route('workers.index') }}" method="get">
                <div class="form-group">
                    <label for="searchWorker" class="form-label">Wyszukaj użytkownika</label>
                    <input type="text" id="searchWorker" name="searchWorker" class="form-input" placeholder="Wpisz imię lub nazwisko..." value="{{ request('searchWorker') }}">
                </div>

                <div class="form-group">
                    <label for="filterStatus" class="form-label">Status zatrudnienia</label>
                    <select id="filterStatus" name="filterStatus" class="form-input">
                        <option value="">Wszystkie</option>
                        <option value="1" {{ request('filterStatus') === '1' ? 'selected' : '' }}>Tak</option>
                        <option value="0" {{ request('filterStatus') === '0' ? 'selected' : '' }}>Nie</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-submit">
                        <i class="fas fa-search"></i>
                        Szukaj
                    </button>
                    <button type="button" id="clearSearch" class="btn btn-cancel">
                        <i class="fas fa-times"></i>
                        Wyczyść
                    </button>
                </div>
            </form>
        </div>

        <div id="workers-list">
            @include('workers.partials.list', ['workers' => $workers])
        </div>
    </main>
</div>

@push('scripts')
<script src="{{ asset('js/workers.js') }}"></script>
<script>
$(document).ready(function () {

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
            url: "{{ route('workers.index') }}",
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
</script>
@endpush
@endsection
