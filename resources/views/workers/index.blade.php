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


        <div class="settings-container">
            <h2>Lista pracowników</h2>

            <form class="worker-search-bar" action="{{ route('workers.index') }}" method="get">
                <div class="form-group">
                    <label for="searchWorker" class="form-label">Wyszukaj użytkownika</label>
                    <input type="text" id="searchWorker" name="searchWorker" class="form-input" placeholder="Wpisz imię lub nazwisko...">
                </div>

                <div class="form-group">
                    <label for="filterStatus" class="form-label">Status zatrudnienia</label>
                    <select id="filterStatus" name="filterStatus" class="form-input">
                        <option value="">Wszystkie</option>
                        <option value="1">Tak</option>
                        <option value="0">Nie</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" name="searchSubmit" class="btn btn-submit">
                        <i class="fas fa-search"></i>
                        Szukaj
                    </button>
                </div>
            </form>
        </div>

        @forelse($workers as $worker)
        <div class="settings-container">
            <div class="settings-section">
                <div class="package-header-row">
                    <h2>{{ $worker->first_name }} {{ $worker->last_name }}</h2>
                    <div class="package-actions">
                        <form action="{{ route('workers.destroy', $worker) }}" method="post" class="delete-form" data-name="{{ $worker->first_name }} {{ $worker->last_name }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-delete" type="submit">
                                <i class="fas fa-trash-alt"></i>
                                Usuń
                            </button>
                        </form>
                    </div>
                </div>

                <div class="worker-info-grid">
                    <div class="worker-info-item">
                        <span class="label">Telefon</span>
                        <span class="value">{{ $worker->phone ?? 'Brak' }}</span>
                    </div>
                    <div class="worker-info-item">
                        <span class="label">Adres</span>
                        <span class="value">{{ $worker->address ?? 'Brak' }}</span>
                    </div>
                    <div class="worker-info-item">
                        <span class="label">Status ucznia</span>
                        <span class="value">{{ $worker->is_student ? 'Tak' : 'Nie' }}</span>
                    </div>
                    <div class="worker-info-item">
                        <span class="label">Status zatrudnienia</span>
                        <span class="value">{{ $worker->is_employed ? 'Tak' : 'Nie' }}</span>
                    </div>

                    <div class="worker-info-item">
                        <span class="label">Data urodzenia</span>
                        <span class="value">{{ $worker->date_of_birth ? $worker->date_of_birth->format('Y-m-d') : 'Brak' }}</span>
                    </div>
                    <div class="worker-info-item">
                        <span class="label">Umowa</span>
                        <span class="value">
                            @if($worker->contract_from && $worker->contract_to)
                                {{ $worker->contract_from->format('Y-m-d') }} do {{ $worker->contract_to->format('Y-m-d') }}
                            @else
                                Brak
                            @endif
                        </span>
                    </div>
                </div>

                <label for="toggle-edit-worker-{{ $worker->id }}" class="btn btn-change">
                    <i class="fas fa-edit"></i>
                    Edytuj Dane
                </label>

                <hr class="section-separator">

                <div class="current-amount">
                    <div class="financial-stats-stack">
                        <div class="amount-info">
                            <span class="amount-label">Godziny (w zakresie):</span>
                            <span class="amount-value" id="hours-value-{{ $worker->id }}">42.5</span>
                        </div>
                        <div class="amount-info">
                            <span class="amount-label">Wynagrodzenie (w zakresie):</span>
                            <span class="amount-value" id="salary-value-{{ $worker->id }}">850.00</span>
                            <span class="currency">PLN</span>
                        </div>
                    </div>
                    <div class="financial-controls">
                        <label for="toggle-range-{{ $worker->id }}" class="btn btn-change">
                            <i class="fas fa-calendar-alt"></i>
                            Zmień zakres
                        </label>
                    </div>
                </div>

                <input type="checkbox" id="toggle-range-{{ $worker->id }}">

                <div class="edit-form date-range-form">
                    <form class="date-range-form-inner" id="date-range-form-{{ $worker->id }}">
                        <input type="hidden" name="worker_id_range" value="{{ $worker->id }}">
                        <div class="form-group flatpickr-form-group">
                            <label for="flatpickr-range-{{ $worker->id }}" class="form-label">Wybierz zakres dat</label>
                            <input type="text" id="flatpickr-range-{{ $worker->id }}" class="form-input flatpickr-range-input"
                                   placeholder="Kliknij, aby wybrać zakres..." readonly="readonly">
                        </div>
                        <input type="date" id="date-from-{{ $worker->id }}" name="dateFrom" class="form-input hidden-date-input" required>
                        <input type="date" id="date-to-{{ $worker->id }}" name="dateTo" class="form-input hidden-date-input" required>
                        <div class="form-actions">
                            <button type="button" class="btn btn-submit" onclick="alert('Filtrowanie...')">
                                <i class="fas fa-filter"></i>
                                Filtruj
                            </button>
                        </div>
                    </form>
                </div>


                <input type="checkbox" id="toggle-edit-worker-{{ $worker->id }}">

                <div class="edit-form">
                    <form action="{{ route('workers.update', $worker) }}" method="post">
                        @csrf
                        @method('PUT')

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="edit-workerFirstName-{{ $worker->id }}" class="form-label">Imię</label>
                                <input type="text" id="edit-workerFirstName-{{ $worker->id }}" name="first_name" class="form-input" value="{{ $worker->first_name }}" required>
                            </div>
                            <div class="form-group">
                                <label for="edit-workerLastName-{{ $worker->id }}" class="form-label">Nazwisko</label>
                                <input type="text" id="edit-workerLastName-{{ $worker->id }}" name="last_name" class="form-input" value="{{ $worker->last_name }}" required>
                            </div>
                            <div class="form-group">
                                <label for="edit-workerPhone-{{ $worker->id }}" class="form-label">Numer telefonu</label>
                                <input type="tel" id="edit-workerPhone-{{ $worker->id }}" name="phone" class="form-input" value="{{ $worker->phone }}">
                            </div>
                            <div class="form-group">
                                <label for="edit-workerAddress-{{ $worker->id }}" class="form-label">Miejsce zamieszkania</label>
                                <input type="text" id="edit-workerAddress-{{ $worker->id }}" name="address" class="form-input" value="{{ $worker->address }}">
                            </div>
                            <div class="form-group">
                                <label for="edit-workerDob-{{ $worker->id }}" class="form-label">Data urodzenia</label>
                                <input type="date" id="edit-workerDob-{{ $worker->id }}" name="date_of_birth" class="form-input" value="{{ $worker->date_of_birth ? $worker->date_of_birth->format('Y-m-d') : '' }}">
                            </div>
                            <div class="form-group">
                                <label for="edit-workerStudentStatus-{{ $worker->id }}" class="form-label">Status ucznia</label>
                                <select id="edit-workerStudentStatus-{{ $worker->id }}" name="is_student" class="form-input">
                                    <option value="0" {{ !$worker->is_student ? 'selected' : '' }}>Nie</option>
                                    <option value="1" {{ $worker->is_student ? 'selected' : '' }}>Tak</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="edit-workerEmploymentStatus-{{ $worker->id }}" class="form-label">Status zatrudnienia</label>
                                <select id="edit-workerEmploymentStatus-{{ $worker->id }}" name="is_employed" class="form-input">
                                    <option value="1" {{ $worker->is_employed ? 'selected' : '' }}>Tak</option>
                                    <option value="0" {{ !$worker->is_employed ? 'selected' : '' }}>Nie</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="edit-workerContractFrom-{{ $worker->id }}" class="form-label">Umowa od daty</label>
                                <input type="date" id="edit-workerContractFrom-{{ $worker->id }}" name="contract_from" class="form-input" value="{{ $worker->contract_from ? $worker->contract_from->format('Y-m-d') : '' }}">
                            </div>
                            <div class="form-group">
                                <label for="edit-workerContractTo-{{ $worker->id }}" class="form-label">Umowa do daty</label>
                                <input type="date" id="edit-workerContractTo-{{ $worker->id }}" name="contract_to" class="form-input" value="{{ $worker->contract_to ? $worker->contract_to->format('Y-m-d') : '' }}">
                            </div>
                        </div>

                        <div class="form-actions">
                            <label for="toggle-edit-worker-{{ $worker->id }}" class="btn btn-cancel">Anuluj</label>
                            <button type="submit" class="btn btn-submit">Zatwierdź Zmiany</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
        @empty
        <div class="settings-container">
            <div class="settings-section">
                <h2>Brak pracowników</h2>
            </div>
        </div>
        @endforelse
    </main>
</div>

@push('scripts')
<script src="{{ asset('js/workers.js') }}"></script>
<script>
    $(document).ready(function () {
        $('.delete-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const name = $(this).data('name');
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
                                form.closest('.settings-container').fadeOut();
                            }
                        },
                        error: function (xhr) {
                            let errors = xhr.responseJSON?.errors;
                            if (errors) {
                                let firstError = Object.values(errors).flat()[0];
                                showToast.error(firstError);
                            } else {
                                showToast.error('Wystąpił błąd podczas usuwania pracownika');
                            }
                        }
                    });
                }
            });
        });

        $("#addWorkerForm").on("submit", function (e) {
            e.preventDefault();
            const form = $("#addWorkerForm");

            $.ajax({
                type: "POST",
                url: "{{ route('workers.store') }}",
                data: $(this).serialize(),
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    if (response.status === 'success') {
                        showToast.success(response.message);
                        $('#toggle-worker-form').prop('checked', false);
                        form[0].reset();
                    }
                },
                error: function (xhr) {
                    let errors = xhr.responseJSON?.errors;
                    if (errors) {
                        let firstError = Object.values(errors).flat()[0];
                        showToast.error(firstError);
                    } else {
                        showToast.error('Wystąpił błąd podczas dodawania pracownika');
                    }
                }
            });
        });
    });
</script>
@endpush
@endsection
