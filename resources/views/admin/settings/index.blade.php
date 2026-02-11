@extends('layouts.app')

@section('title', 'Stawki - Panel administratora')

@push('styles')
    @vite(['resources/css/settings.css'])
@endpush

@section('content')
<div class="admin-panel">
    @include('partials.menu')

    <main class="main-content">

        <div class="header">
            <h1>Stawki</h1>
            <p>Zarządzaj stawkami, wprowadź własne stawki</p>

            <label for="toggle-package-form" class="toggle-btn btn btn-change">
                <i class="fa-solid fa-plus"></i> Nowa stawka
            </label>
        </div>

        <input type="checkbox" @if($errors->any() || session('success')) checked @endif id="toggle-package-form">

        <div class="edit-form">
            <h2>Dodaj nowe stawki</h2>

            <form id="packageForm" action="{{ route('settings.packages.store') }}" method="post">
                @csrf
                <div class="form-group">
                    <label for="packageName" class="form-label">Nazwa stawki</label>
                    <input type="text" id="packageName" name="name" class="form-input" placeholder="np. Pakiet Standard, Pakiet Premium" value="{{ old('name') }}" required>
                </div>

                <div class="form-group">
                    <label for="packagePrice" class="form-label">Cena (PLN)</label>
                    <input type="number" id="packagePrice" name="price" class="form-input" placeholder="np. 99.99" step="0.01" min="0" value="{{ old('price') }}" required>
                </div>

                <div class="form-actions">
                    <label for="toggle-package-form" class="btn btn-cancel toggle-btn">Anuluj</label>
                    <button type="submit" class="btn btn-submit">Zapisz stawkę</button>
                </div>
            </form>
        </div>

        @if($packages->count() > 0)
            <div class="settings-container">
                <h2>Lista istniejących stawek</h2>
            </div>

            @foreach($packages as $package)
                @php $unique_toggle_id = 'toggle-form-' . $package->id; @endphp
                <div class="settings-container">
                    <div class="settings-section">
                        <div class="package-header-row">
                            <h2>{{ $package->name }}</h2>
                            <div class="package-actions">
                                <form action="{{ route('settings.packages.destroy', $package) }}" method="post" class="delete-form" data-name="{{ $package->name }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-delete" type="submit">
                                        <i class="fas fa-trash-alt"></i>
                                        Usuń stawkę
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="current-amount">
                            <div class="amount-info">
                                <span class="amount-label">Aktualna kwota brutto wynosi:</span>
                                <span class="amount-value" id="amount-value-{{ $package->id }}">{{ number_format($package->price, 2) }}</span>
                                <span class="currency">PLN</span>
                            </div>
                            <label for="{{ $unique_toggle_id }}" class="btn btn-change">
                                <i class="fas fa-edit"></i>
                                Zmień kwotę
                            </label>
                        </div>

                        <input type="checkbox" id="{{ $unique_toggle_id }}">
                        <div class="edit-form">
                            <form action="{{ route('settings.packages.update', $package) }}" method="post" id="form-package">
                                @csrf
                                @method('PUT')

                                <div class="form-group">
                                    <label class="form-label" for="new-name-package-{{ $package->id }}">
                                        <i class="fas fa-tag"></i>
                                        Nowa nazwa stawki
                                    </label>
                                    <input
                                        type="text"
                                        id="new-name-package-{{ $package->id }}"
                                        class="form-input"
                                        name="name"
                                        value="{{ $package->name }}"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="new-amount-package-{{ $package->id }}">
                                        <i class="fas fa-money-bill-wave"></i>
                                        Nowa kwota brutto (PLN)
                                    </label>
                                    <input
                                        type="number"
                                        id="new-amount-package-{{ $package->id }}"
                                        class="form-input"
                                        name="price"
                                        value="{{ $package->price }}"
                                        step="0.01" min="0" required
                                    >
                                </div>

                                <div class="form-actions">
                                    <label for="{{ $unique_toggle_id }}" class="btn btn-cancel">
                                        <i class="fas fa-times"></i>
                                        Anuluj
                                    </label>
                                    <button type="submit" class="btn btn-submit" id="submit-package-edit">
                                        <i class="fas fa-check"></i>
                                        Zatwierdź
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            @endforeach
        @else
            <div class="settings-container">
                <div class="settings-section">
                    <h2>Brak istniejących stawek</h2>
                </div>
            </div>
        @endif

    </main>
</div>
@endsection

@push('scripts')
    @vite(['resources/js/settings.js'])
@endpush
