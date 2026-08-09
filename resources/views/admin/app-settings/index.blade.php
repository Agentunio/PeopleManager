@extends('layouts.app')

@section('title', 'Ustawienia - Panel administratora')
@section('body_class', 'app-settings-page')

@push('styles')
    @vite(['resources/css/app-settings.css'])
@endpush

@section('content')
<div class="admin-panel">
    @include('partials.menu')

    <main class="main-content app-settings-content">
        <header class="app-settings-heading">
            <h1>Ustawienia</h1>
        </header>

        <div class="app-settings-stack">
            @if($attemptedDisable)
                <section class="app-setting-card" aria-labelledby="pending-hours-title">
                    <div class="app-setting-warning-content">
                        <span class="app-setting-status app-setting-status-warning">
                            <span aria-hidden="true"></span>
                            Wymaga potwierdzenia
                        </span>

                        <h2 id="pending-hours-title">Pracownicy mają niezaakceptowane godziny</h2>
                        <p>
                            Po wyłączeniu samodzielnego wpisywania pracownicy nie będą mogli edytować poniższych wpisów.
                            Trzeba je będzie zatwierdzić ręcznie w panelu rozliczeń.
                        </p>

                        <ul class="pending-workers-list">
                            @foreach($pendingWorkers as $pendingWorker)
                                <li>
                                    <strong>{{ $pendingWorker['name'] }}</strong>
                                    <span>{{ $pendingWorker['date'] }} · {{ $pendingWorker['shift'] }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @if($pendingWorkers->hasPages())
                            <div class="pending-workers-pagination">
                                <p>
                                    Wpisy {{ $pendingWorkers->firstItem() }}–{{ $pendingWorkers->lastItem() }}
                                    z {{ $pendingWorkers->total() }}
                                </p>
                                {{ $pendingWorkers->onEachSide(1)->links() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('app-settings.update') }}">
                            @csrf
                            <input type="hidden" name="worker_self_hours_enabled" value="0">
                            <input type="hidden" name="force_disable_with_pending" value="1">
                            <div class="app-setting-actions">
                                <a href="{{ route('app-settings.index') }}" class="app-setting-button app-setting-button-secondary">Anuluj</a>
                                <button type="submit" class="app-setting-button app-setting-button-danger">Wyłącz mimo wszystko</button>
                            </div>
                        </form>
                    </div>

                    <div class="app-setting-footer app-setting-footer-warning">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                            <path d="M12 9v4M12 17h.01" />
                        </svg>
                        Wyłączenie wpłynie na wymienione wpisy czasu pracy.
                    </div>
                </section>
            @else
                <form method="POST" action="{{ route('app-settings.update') }}" class="app-setting-card app-setting-form" data-app-setting-form>
                    @csrf

                    <div class="app-setting-row">
                        <div class="app-setting-copy">
                            <div class="app-setting-title-row">
                                <h2>Samodzielne wpisywanie godzin pracy</h2>
                                <span class="app-setting-status {{ $workerSelfHoursEnabled ? 'app-setting-status-enabled' : 'app-setting-status-disabled' }}">
                                    <span aria-hidden="true"></span>
                                    {{ $workerSelfHoursEnabled ? 'Włączone' : 'Wyłączone' }}
                                </span>
                            </div>

                            <p id="worker-self-hours-description">
                                Gdy włączone, pracownicy wpisują godziny pracy bezpośrednio w swoim panelu.
                                Po wyłączeniu pole jest zablokowane i godziny uzupełnia administrator.
                            </p>
                        </div>

                        <label class="app-setting-toggle">
                            <span class="app-setting-visually-hidden">Samodzielne wpisywanie godzin pracy</span>
                            <input
                                type="checkbox"
                                name="worker_self_hours_enabled"
                                value="1"
                                class="settings-toggle-input"
                                aria-describedby="worker-self-hours-description"
                                data-app-setting-toggle
                                @checked($workerSelfHoursEnabled)
                            >
                            <span class="app-setting-toggle-ui" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="app-setting-footer {{ $workerSelfHoursEnabled ? '' : 'app-setting-footer-warning' }}">
                        @if($workerSelfHoursEnabled)
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M12 8v4M12 16h.01" />
                            </svg>
                            Aktywne dla wszystkich pracowników.
                        @else
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                <path d="M12 9v4M12 17h.01" />
                            </svg>
                            Pracownicy nie będą mogli sami wpisywać godzin pracy.
                        @endif
                    </div>
                </form>
            @endif
        </div>
    </main>
</div>
@endsection

@push('scripts')
    @vite(['resources/js/app-settings.js'])
@endpush
