@extends('layouts.app')

@section('title', 'Ustawienia - Panel administratora')

@push('styles')
    @vite(['resources/css/settings.css'])
@endpush

@section('content')
<div class="admin-panel">
    @include('partials.menu')

    <main class="main-content">
        <div class="header">
            <h1>Ustawienia</h1>
            <p>Zarzadzaj ustawieniami aplikacji</p>
        </div>

        <div class="settings-container">
            <div class="settings-section">
                @if($attemptedDisable)
                    <h2>Uwaga: Pracownicy maja niezaakceptowane godziny</h2>
                    <p class="settings-hint">Po wylaczeniu samodzielnego wpisywania pracownicy nie beda mogli edytowac ponizszych wpisow. Trzeba je bedzie zatwierdzic recznie w panelu rozliczen.</p>

                    <ul class="pending-workers-list">
                        @foreach($pendingWorkers as $pendingWorker)
                            <li>
                                {{ $pendingWorker['name'] }}
                                - {{ $pendingWorker['date'] }}
                                ({{ $pendingWorker['shift'] }})
                            </li>
                        @endforeach
                    </ul>

                    <form method="POST" action="{{ route('app-settings.update') }}" class="force-disable-form">
                        @csrf
                        <input type="hidden" name="worker_self_hours_enabled" value="0">
                        <input type="hidden" name="force_disable_with_pending" value="1">
                        <div class="form-actions">
                            <a href="{{ route('app-settings.index') }}" class="btn btn-cancel">
                                <i class="fa-solid fa-times"></i> Anuluj
                            </a>
                            <button type="submit" class="btn btn-delete">
                                <i class="fa-solid fa-triangle-exclamation"></i> Wylacz mimo wszystko
                            </button>
                        </div>
                    </form>
                @else
                    <h2>Samodzielne wpisywanie czasu pracy</h2>
                    <p class="settings-hint">Gdy wylaczone, pracownicy nie moga wpisywac godzin za swoje zmiany. Wpisuje je administrator w panelu rozliczen.</p>

                    <form method="POST" action="{{ route('app-settings.update') }}">
                        @csrf
                        <div class="form-group form-group-toggle">
                            <label class="toggle-label">
                                <input type="checkbox" name="worker_self_hours_enabled" value="1" class="settings-toggle-input" @checked($workerSelfHoursEnabled)>
                                <span>Wlacz mozliwosc samodzielnego wpisywania czasu przez pracownikow</span>
                            </label>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-submit">
                                <i class="fa-solid fa-check"></i> Zapisz
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </main>
</div>
@endsection
