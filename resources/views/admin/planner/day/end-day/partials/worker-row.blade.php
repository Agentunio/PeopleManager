<div
    class="settlement-worker-card settlement-grid settlement-grid--worker {{ $worker['status'] === 'absent' ? 'worker-absent' : '' }} {{ $worker['isSubstitute'] ? 'substitute-card' : '' }}"
    data-shift-id="{{ $worker['shiftId'] }}"
    data-worker-id="{{ $worker['workerId'] }}"
    data-substitute-for-shift="{{ $worker['substitutedForShiftId'] }}"
    data-initial-minutes="{{ $worker['displayMinutes'] ?? '' }}"
    data-worker-entry="{{ $worker['hasWorkerEntry'] ? 'true' : 'false' }}"
>
    <input type="hidden" name="workers[{{ $worker['workerId'] }}_{{ $shiftType }}][id]" value="{{ $worker['workerId'] }}">
    <input type="hidden" name="workers[{{ $worker['workerId'] }}_{{ $shiftType }}][shift_type]" value="{{ $shiftType }}">
    <input
        type="hidden"
        name="workers[{{ $worker['workerId'] }}_{{ $shiftType }}][status]"
        value="{{ $worker['status'] === 'absent' ? 'absent' : 'worked' }}"
        class="worker-status-input"
    >
    @if($worker['isSubstitute'])
        <input type="hidden" name="workers[{{ $worker['workerId'] }}_{{ $shiftType }}][is_substitute]" value="1">
        <input
            type="hidden"
            name="workers[{{ $worker['workerId'] }}_{{ $shiftType }}][substituted_for_shift_id]"
            value="{{ $worker['substitutedForShiftId'] }}"
        >
    @endif

    <div class="worker-identity">
        <span class="worker-avatar" aria-hidden="true">{{ $worker['initials'] }}</span>
        <span class="worker-name">{{ $worker['name'] }}</span>

        @if($worker['isSubstitute'])
            <span class="worker-badge worker-badge--substitute">
                zast&#281;pstwo za {{ $worker['substituteForName'] ?: 'nieobecnego' }}
            </span>
        @elseif($worker['status'] === 'absent')
            <span class="worker-badge worker-badge--absent">nieobecny</span>
        @endif

        @if($worker['hasWorkerEntry'])
            <span class="worker-entry-note {{ $worker['isWorkerEntryOverridden'] ? 'is-overridden' : 'is-worker-entered' }}">
                <span class="worker-entry-dot" aria-hidden="true"></span>
                <span class="worker-entry-text">
                    @if($worker['isWorkerEntryOverridden'])
                        nadpisano samodzielny wpis
                    @else
                        wpis pracownika
                    @endif
                </span>
            </span>
        @elseif($worker['isLegacyApproved'])
            <span class="worker-entry-note">
                <span class="worker-entry-dot" aria-hidden="true"></span>
                <span class="worker-entry-text">zapis historyczny</span>
            </span>
        @endif

        <span class="worker-actions">
            @if($worker['isSubstitute'])
                <button type="button" class="worker-action btn-remove-substitute" title="Usu&#324; zast&#281;pstwo" aria-label="Usu&#324; zast&#281;pstwo {{ $worker['name'] }}">
                    <svg aria-hidden="true" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            @else
                <button
                    type="button"
                    class="worker-action btn-absent {{ $worker['status'] === 'absent' ? 'active' : '' }}"
                    title="Oznacz jako nieobecnego"
                    aria-label="Oznacz {{ $worker['name'] }} jako nieobecnego"
                    aria-pressed="{{ $worker['status'] === 'absent' ? 'true' : 'false' }}"
                >
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <line x1="17" y1="8" x2="22" y2="13"></line>
                        <line x1="22" y1="8" x2="17" y2="13"></line>
                    </svg>
                </button>
                <button
                    type="button"
                    class="worker-action btn-add-substitute"
                    title="Dodaj zast&#281;pstwo"
                    aria-label="Dodaj zast&#281;pstwo za {{ $worker['name'] }}"
                    data-shift="{{ $shiftType }}"
                    data-shift-id="{{ $worker['shiftId'] }}"
                >
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <line x1="19" y1="8" x2="19" y2="14"></line>
                        <line x1="22" y1="11" x2="16" y2="11"></line>
                    </svg>
                </button>
            @endif
        </span>
    </div>

    <label class="visually-hidden" for="worker-{{ $worker['shiftId'] }}-from">Godzina rozpocz&#281;cia</label>
    <input
        type="time"
        id="worker-{{ $worker['shiftId'] }}-from"
        name="workers[{{ $worker['workerId'] }}_{{ $shiftType }}][from_hour]"
        class="settlement-input settlement-time-input worker-from-time"
        value="{{ $worker['displayFrom'] }}"
    >

    <label class="visually-hidden" for="worker-{{ $worker['shiftId'] }}-to">Godzina zako&#324;czenia</label>
    <input
        type="time"
        id="worker-{{ $worker['shiftId'] }}-to"
        name="workers[{{ $worker['workerId'] }}_{{ $shiftType }}][to_hour]"
        class="settlement-input settlement-time-input worker-to-time"
        value="{{ $worker['displayTo'] }}"
    >

    <span class="calculated-hours" data-minutes="{{ $worker['displayMinutes'] ?? '' }}">
        {{ $worker['displayHours'] }}
    </span>

    <label class="visually-hidden" for="worker-{{ $worker['shiftId'] }}-rate">Stawka pracownika</label>
    <select
        id="worker-{{ $worker['shiftId'] }}-rate"
        name="workers[{{ $worker['workerId'] }}_{{ $shiftType }}][package]"
        class="settlement-input settlement-select worker-rate"
    >
        <option value="">&mdash; wybierz stawk&#281; &mdash;</option>
        @foreach($packages as $package)
            <option value="{{ $package['id'] }}" @selected($worker['packageId'] == $package['id'])>
                {{ $package['name'] }} &middot; {{ number_format($package['price'], 2, ',', ' ') }} z&#322;
            </option>
        @endforeach
    </select>
</div>
