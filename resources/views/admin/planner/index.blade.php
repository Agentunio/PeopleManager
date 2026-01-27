@extends('layouts.app')

@section('title', 'Grafik - Panel administratora')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}">
    <link rel="stylesheet" href="{{ asset('css/planner.css') }}">
@endpush

@section('content')
<div class="admin-panel">
    @include('partials.menu')

    <main class="main-content">
        <div class="header">
            <h1>Grafik pracy</h1>
            <p>Zarządzaj harmonogramem pracy. Wybierz datę, aby przypisać pracowników do zmian.</p>
            <a href="{{ route('planner.schedule.index') }}" class="btn btn-change">
                <i class="fas fa-calendar-check"></i> Włącz grafik
            </a>
        </div>

        <div id="calendar-view" class="planner-view">
            <div class="settings-container">
                <div class="calendar-header">
                    <h2><i class="fas fa-calendar-alt"></i> Wybierz datę</h2>
                </div>
                <div class="calendar-wrapper">
                    <div id="calendar-inline"></div>
                </div>
                <div class="calendar-legend">
                    <div class="legend-section">
                        <span class="legend-section-title">Rozliczenie:</span>
                        <div class="legend-item">
                            <span class="legend-icon legend-settled"><i class="fas fa-check"></i></span>
                            <span>Rozliczony</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-icon legend-unsettled"><i class="fas fa-clock"></i></span>
                            <span>Nierozliczony</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/planner.js') }}"></script>
@endpush
