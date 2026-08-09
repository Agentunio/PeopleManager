@extends('layouts.app')

@section('title', 'Stawki - Panel administratora')
@section('body_class', 'rates-page')

@section('mobile_header_action')
    <label for="toggle-package-form" class="rates-mobile-add-button">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Dodaj
    </label>
@endsection

@push('styles')
    @vite(['resources/css/settings.css'])
@endpush

@section('content')
<div class="admin-panel rates-admin-panel">
    @include('partials.menu')

    <main class="main-content rates-content">
        <header class="rates-heading">
            <div>
                <h1>Stawki</h1>
                <p>
                    Stawki za paczkę używane przy rozliczeniach. Ustaw jedną jako domyślną — będzie wybierana automatycznie przy wprowadzaniu nowych pozycji.
                </p>
            </div>

            <label for="toggle-package-form" class="rates-primary-button">
                <span aria-hidden="true">+</span>
                Dodaj stawkę
            </label>
        </header>

        <input
            type="checkbox"
            id="toggle-package-form"
            class="rates-visibility-toggle"
            @checked($errors->any() && old('editing_package_id') === 'new')
        >

        <section class="rates-list" aria-label="Lista stawek">
            <div class="rates-table-header" aria-hidden="true">
                <span class="rates-column-default">Dom.</span>
                <span>Nazwa stawki</span>
                <span class="rates-column-price">Cena</span>
                <span></span>
            </div>

            <form
                id="packageForm"
                action="{{ route('settings.packages.store') }}"
                method="post"
                class="rate-row rate-row-new"
            >
                @csrf
                <input type="hidden" name="editing_package_id" value="new">

                <span class="rate-default-control rate-default-control-disabled" aria-hidden="true">
                    <span></span>
                </span>

                <div class="rate-field rate-name-field">
                    <label for="packageName">Nazwa stawki</label>
                    <input
                        type="text"
                        id="packageName"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="np. Stawka standardowa"
                        autocomplete="off"
                        required
                    >
                </div>

                <div class="rate-field rate-price-field">
                    <label for="packagePrice">Cena</label>
                    <input
                        type="number"
                        id="packagePrice"
                        name="price"
                        value="{{ old('price') }}"
                        placeholder="0"
                        step="0.01"
                        min="0"
                        inputmode="decimal"
                        required
                    >
                </div>

                <div class="rate-edit-actions rate-new-actions">
                    <button type="submit" class="rate-save-button">Zapisz</button>

                    <div class="rate-delete-form" data-new-delete>
                        <button
                            type="button"
                            class="rate-delete-trigger"
                            aria-label="Anuluj dodawanie stawki"
                            aria-expanded="false"
                            data-delete-trigger
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                <path d="M10 11v6M14 11v6" />
                                <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
                            </svg>
                        </button>

                        <div class="rate-delete-confirm" role="alertdialog" aria-modal="true" aria-labelledby="new-rate-delete-title" hidden data-delete-confirm>
                            <div class="rate-delete-panel">
                                <h2 id="new-rate-delete-title">Usunąć stawkę?</h2>
                                <p>Tej operacji nie można cofnąć.</p>
                                <div>
                                    <button type="button" class="rate-delete-cancel" data-delete-cancel>Anuluj</button>
                                    <button type="button" class="rate-delete-submit" data-new-delete-submit>Usuń</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            @forelse($packages as $package)
                <article @class(['rate-row', 'is-default' => $defaultPackageId === $package->id])>
                    <form
                        action="{{ route('settings.packages.default') }}"
                        method="post"
                        class="rate-default-form"
                        data-default-form
                    >
                        @csrf
                        <input
                            type="hidden"
                            name="package_id"
                            value="{{ $defaultPackageId === $package->id ? '' : $package->id }}"
                        >
                        <button
                            type="submit"
                            class="rate-default-control"
                            role="radio"
                            aria-checked="{{ $defaultPackageId === $package->id ? 'true' : 'false' }}"
                            aria-label="{{ $defaultPackageId === $package->id ? 'Usuń domyślną stawkę' : 'Ustaw jako domyślną stawkę' }}: {{ $package->name }}"
                        >
                            <span></span>
                        </button>
                    </form>

                    <input
                        type="checkbox"
                        id="toggle-form-{{ $package->id }}"
                        class="rate-edit-toggle"
                        @checked($errors->any() && (int) old('editing_package_id') === $package->id)
                    >

                    <div class="rate-name rate-display-value">
                        <span class="rate-mobile-label">Nazwa stawki</span>
                        <strong>{{ $package->name }}</strong>
                    </div>

                    <div class="rate-price rate-display-value">
                        <span class="rate-mobile-label">Cena</span>
                        <strong>{{ rtrim(rtrim(number_format($package->price, 2, ',', ' '), '0'), ',') }} zł</strong>
                    </div>

                    <div class="rate-actions rate-display-value">
                        <label for="toggle-form-{{ $package->id }}" class="rate-edit-button">Edytuj</label>

                        <form
                            action="{{ route('settings.packages.destroy', $package) }}"
                            method="post"
                            class="rate-delete-form"
                            data-delete-form
                        >
                            @csrf
                            @method('DELETE')

                            <button
                                type="button"
                                class="rate-delete-trigger"
                                aria-label="Usuń stawkę: {{ $package->name }}"
                                aria-expanded="false"
                                data-delete-trigger
                            >
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="3 6 5 6 21 6" />
                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                    <path d="M10 11v6M14 11v6" />
                                    <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
                                </svg>
                            </button>

                            <div class="rate-delete-confirm" role="alertdialog" aria-modal="true" aria-labelledby="delete-title-{{ $package->id }}" hidden data-delete-confirm>
                                <div class="rate-delete-panel">
                                    <h2 id="delete-title-{{ $package->id }}">Usunąć stawkę?</h2>
                                    <p>Tej operacji nie można cofnąć.</p>
                                    <div>
                                        <button type="button" class="rate-delete-cancel" data-delete-cancel>Anuluj</button>
                                        <button type="submit" class="rate-delete-submit">Usuń</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <form
                        action="{{ route('settings.packages.update', $package) }}"
                        method="post"
                        class="rate-edit-form"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="editing_package_id" value="{{ $package->id }}">

                        <div class="rate-field rate-name-field">
                            <label for="new-name-package-{{ $package->id }}">Nazwa stawki</label>
                            <input
                                type="text"
                                id="new-name-package-{{ $package->id }}"
                                name="name"
                                value="{{ (int) old('editing_package_id') === $package->id ? old('name', $package->name) : $package->name }}"
                                required
                            >
                        </div>

                        <div class="rate-field rate-price-field">
                            <label for="new-amount-package-{{ $package->id }}">Cena</label>
                            <input
                                type="number"
                                id="new-amount-package-{{ $package->id }}"
                                name="price"
                                value="{{ (int) old('editing_package_id') === $package->id ? old('price', $package->price) : $package->price }}"
                                step="0.01"
                                min="0"
                                inputmode="decimal"
                                required
                            >
                        </div>

                        <div class="rate-edit-actions">
                            <button type="submit" class="rate-save-button">Zapisz</button>
                        </div>
                    </form>
                </article>
            @empty
                <div class="rates-empty-state">
                    <span>[ brak stawek ]</span>
                    <p>Dodaj pierwszą stawkę, by zacząć rozliczać paczki.</p>
                </div>
            @endforelse
        </section>
    </main>
</div>
@endsection

@push('scripts')
    @vite(['resources/js/settings.js'])
@endpush
