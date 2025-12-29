@extends('layouts.app')

@section('title', 'Grafik dnia - Panel administratora')

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
        </div>

        <div id="day-view" class="planner-view">
            <div class="day-view-header">
                <a href="{{ route('planner.index') }}" class="btn btn-cancel">
                    <i class="fas fa-arrow-left"></i> Powrót do kalendarza
                </a>
                <h2 id="selected-date-title">Grafik na dzień: <span>{{ $formattedDate ?? '--' }}</span></h2>
            </div>

            <div class="day-view-content">
                <!-- LISTA PRACOWNIKÓW -->
                <div class="workers-panel">
                    <div class="workers-panel-header">
                        <h3><i class="fas fa-users"></i> Pracownicy</h3>
                        <button id="change-availability-btn" class="btn btn-change">
                            <i class="fas fa-user-clock"></i> Zmień dostępność
                        </button>
                    </div>

                    <div class="workers-list" id="workers-list">
                        <!-- Pracownicy do przeciągania -->
                        <div class="worker-card draggable" data-worker-id="1" data-morning="true" data-afternoon="true">
                            <span class="worker-name">Jan Kowalski</span>
                            <div class="worker-availability-badges">
                                <span class="badge badge-morning">R</span>
                                <span class="badge badge-afternoon">P</span>
                            </div>
                        </div>

                        <div class="worker-card draggable" data-worker-id="2" data-morning="true" data-afternoon="true">
                            <span class="worker-name">Anna Nowak</span>
                            <div class="worker-availability-badges">
                                <span class="badge badge-morning">R</span>
                                <span class="badge badge-afternoon">P</span>
                            </div>
                        </div>

                        <div class="worker-card draggable" data-worker-id="3" data-morning="true" data-afternoon="true">
                            <span class="worker-name">Piotr Wiśniewski</span>
                            <div class="worker-availability-badges">
                                <span class="badge badge-morning">R</span>
                                <span class="badge badge-afternoon">P</span>
                            </div>
                        </div>

                        <div class="worker-card draggable" data-worker-id="4" data-morning="false" data-afternoon="false">
                            <span class="worker-name">Maria Zielińska</span>
                            <div class="worker-availability-badges">
                            </div>
                        </div>

                        <div class="worker-card draggable" data-worker-id="5" data-morning="true" data-afternoon="true">
                            <span class="worker-name">Tomasz Dąbrowski</span>
                            <div class="worker-availability-badges">
                                <span class="badge badge-morning">R</span>
                                <span class="badge badge-afternoon">P</span>
                            </div>
                        </div>

                        <div class="worker-card draggable" data-worker-id="6" data-morning="true" data-afternoon="true">
                            <span class="worker-name">Katarzyna Lewandowska</span>
                            <div class="worker-availability-badges">
                                <span class="badge badge-morning">R</span>
                                <span class="badge badge-afternoon">P</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ZMIANY -->
                <div class="shifts-panel">
                    <!-- ZMIANA RANNA -->
                    <div class="shift-box">
                        <div class="shift-header shift-morning">
                            <div class="shift-icon">
                                <i class="fas fa-sun"></i>
                            </div>
                            <div class="shift-title">
                                <h3>Zmiana ranna</h3>
                            </div>
                            <div class="shift-count">
                                <span id="morning-count">0</span> / 3
                            </div>
                        </div>
                        <div class="shift-dropzone" id="morning-shift" data-shift="morning">
                            <div class="dropzone-placeholder">
                                <i class="fas fa-user-plus"></i>
                                <span>Przeciągnij pracownika tutaj</span>
                            </div>
                            <div class="assigned-workers"></div>
                        </div>
                    </div>

                    <!-- ZMIANA POPOŁUDNIOWA -->
                    <div class="shift-box">
                        <div class="shift-header shift-afternoon">
                            <div class="shift-icon">
                                <i class="fas fa-cloud-sun"></i>
                            </div>
                            <div class="shift-title">
                                <h3>Zmiana popołudniowa</h3>
                            </div>
                            <div class="shift-count">
                                <span id="afternoon-count">0</span> / 3
                            </div>
                        </div>
                        <div class="shift-dropzone" id="afternoon-shift" data-shift="afternoon">
                            <div class="dropzone-placeholder">
                                <i class="fas fa-user-plus"></i>
                                <span>Przeciągnij pracownika tutaj</span>
                            </div>
                            <div class="assigned-workers"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PODSUMOWANIE -->
            <div class="day-summary">
                <div class="summary-item">
                    <i class="fas fa-users"></i>
                    <span>Łącznie przypisanych: <strong id="total-assigned">0</strong></span>
                </div>
                <div class="summary-item">
                    <i class="fas fa-user-check"></i>
                    <span>Dostępnych pracowników: <strong id="available-workers">5</strong></span>
                </div>
                <button id="save-schedule" class="btn btn-submit">
                    <i class="fas fa-save"></i> Zapisz grafik
                </button>
            </div>
        </div>

        <!-- MODAL DOSTĘPNOŚCI -->
        <div id="availability-modal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-user-clock"></i> Zmień dostępność pracowników</h3>
                    <button class="modal-close" id="close-modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="availability-list">
                        <div class="availability-item" data-worker-id="1">
                            <span class="worker-name">Jan Kowalski</span>
                            <div class="availability-toggles">
                                <div class="toggle-group">
                                    <span class="toggle-label">Ranna</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" data-shift="morning" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-group">
                                    <span class="toggle-label">Popołudniowa</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" data-shift="afternoon" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="availability-item" data-worker-id="2">
                            <span class="worker-name">Anna Nowak</span>
                            <div class="availability-toggles">
                                <div class="toggle-group">
                                    <span class="toggle-label">Ranna</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" data-shift="morning" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-group">
                                    <span class="toggle-label">Popołudniowa</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" data-shift="afternoon" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="availability-item" data-worker-id="3">
                            <span class="worker-name">Piotr Wiśniewski</span>
                            <div class="availability-toggles">
                                <div class="toggle-group">
                                    <span class="toggle-label">Ranna</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" data-shift="morning" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-group">
                                    <span class="toggle-label">Popołudniowa</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" data-shift="afternoon" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="availability-item" data-worker-id="4">
                            <span class="worker-name">Maria Zielińska</span>
                            <div class="availability-toggles">
                                <div class="toggle-group">
                                    <span class="toggle-label">Ranna</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" data-shift="morning">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-group">
                                    <span class="toggle-label">Popołudniowa</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" data-shift="afternoon">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="availability-item" data-worker-id="5">
                            <span class="worker-name">Tomasz Dąbrowski</span>
                            <div class="availability-toggles">
                                <div class="toggle-group">
                                    <span class="toggle-label">Ranna</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" data-shift="morning" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-group">
                                    <span class="toggle-label">Popołudniowa</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" data-shift="afternoon" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="availability-item" data-worker-id="6">
                            <span class="worker-name">Katarzyna Lewandowska</span>
                            <div class="availability-toggles">
                                <div class="toggle-group">
                                    <span class="toggle-label">Ranna</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" data-shift="morning" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-group">
                                    <span class="toggle-label">Popołudniowa</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" data-shift="afternoon" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-cancel" id="cancel-availability">Anuluj</button>
                    <button class="btn btn-submit" id="save-availability">Zapisz zmiany</button>
                </div>
            </div>
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js"></script>
<script src="{{ asset('js/planner-day.js') }}"></script>
@endpush
