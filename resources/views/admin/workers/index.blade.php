@extends('layouts.app')

@section('title', 'Pracownicy - Panel administratora')

@push('styles')
    @vite(['resources/css/settings.css', 'resources/css/workers.css'])
@endpush

@section('content')
<div class="admin-panel">
    @include('partials.menu')

    <main class="main-content">

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
            @include('admin.workers.partials.list', ['workers' => $workers])
        </div>

        <div id="pagination-links">
            {{ $workers->withQueryString()->links() }}
        </div>
    </main>
</div>
@endsection

@push('scripts')
    <script>
        window.workersIndexUrl = "{{ route('workers.index') }}";
    </script>
    @vite(['resources/js/workers.js'])
@endpush
