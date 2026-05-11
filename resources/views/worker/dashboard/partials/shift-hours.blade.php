@if($shift['status'] === 'absent')
    <div class="dash-absent-info">
        <i class="fa-solid fa-user-slash"></i>
        <span>Nieobecność</span>
    </div>
@elseif($shift['hours_source'] === 'admin')
    <div class="dash-admin-info">
        <i class="fa-solid fa-check-circle"></i>
        <span>Zatwierdzone: <strong>{{ $shift['minutes'] ? floor($shift['minutes'] / 60) . 'h' . ($shift['minutes'] % 60 > 0 ? ' ' . ($shift['minutes'] % 60) . 'min' : '') : '-' }}</strong></span>
    </div>
@elseif($shift['hours_source'] === 'worker' && $shift['from'] && $shift['to'])
    <div class="dash-saved-info">
        <div class="dash-saved-text">
            <i class="fa-solid fa-clock"></i>
            Twoje godziny zostały zapisane.
        </div>
        <div class="dash-saved-times">{{ $shift['from'] }} - {{ $shift['to'] }}</div>
        <div class="dash-saved-status">
            Oczekuje na akceptację administratora
        </div>
        @if($workerSelfHoursEnabled)
            <button type="button" class="dash-edit-btn">
                Edytuj
            </button>
        @endif
    </div>
    @if($workerSelfHoursEnabled)
        <div class="dash-hours-inputs" data-shift-type="{{ $type }}" style="display: none;">
            <div class="dash-hours-field">
                <label>Od</label>
                <div class="dash-time-pair">
                    <input type="number" class="dash-from-hour" placeholder="00" min="0" max="23" value="{{ substr($shift['from'], 0, 2) }}">
                    <span class="time-colon">:</span>
                    <input type="number" class="dash-from-minute" placeholder="00" min="0" max="59" value="{{ substr($shift['from'], 3, 2) }}">
                </div>
            </div>
            <span class="dash-hours-separator">-</span>
            <div class="dash-hours-field">
                <label>Do</label>
                <div class="dash-time-pair">
                    <input type="number" class="dash-to-hour" placeholder="00" min="0" max="23" value="{{ substr($shift['to'], 0, 2) }}">
                    <span class="time-colon">:</span>
                    <input type="number" class="dash-to-minute" placeholder="00" min="0" max="59" value="{{ substr($shift['to'], 3, 2) }}">
                </div>
            </div>
        </div>
        <button type="button" class="dash-cancel-btn" style="display: none;">Anuluj</button>
    @endif
@elseif($workerSelfHoursEnabled)
    @if($shift['blocked'])
        <div class="dash-time-note">
            Godziny można wpisać po {{ $shift['block_label'] }}
        </div>
    @else
        <div class="dash-hours-inputs" data-shift-type="{{ $type }}">
            <div class="dash-hours-field">
                <label>Od</label>
                <div class="dash-time-pair">
                    <input type="number" class="dash-from-hour" placeholder="00" min="0" max="23" value="{{ $shift['from'] ? substr($shift['from'], 0, 2) : '' }}">
                    <span class="time-colon">:</span>
                    <input type="number" class="dash-from-minute" placeholder="00" min="0" max="59" value="{{ $shift['from'] ? substr($shift['from'], 3, 2) : '' }}">
                </div>
            </div>
            <span class="dash-hours-separator">-</span>
            <div class="dash-hours-field">
                <label>Do</label>
                <div class="dash-time-pair">
                    <input type="number" class="dash-to-hour" placeholder="00" min="0" max="23" value="{{ $shift['to'] ? substr($shift['to'], 0, 2) : '' }}">
                    <span class="time-colon">:</span>
                    <input type="number" class="dash-to-minute" placeholder="00" min="0" max="59" value="{{ $shift['to'] ? substr($shift['to'], 3, 2) : '' }}">
                </div>
            </div>
        </div>
    @endif
@endif
