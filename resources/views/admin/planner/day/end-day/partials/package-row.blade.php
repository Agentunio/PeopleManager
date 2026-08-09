<div class="package-entry-row settlement-grid settlement-grid--package" data-entry-index="{{ $index }}">
    <label class="visually-hidden" for="package-{{ $shiftType }}-{{ $index }}-count">Liczba paczek</label>
    <input
        type="number"
        inputmode="numeric"
        min="0"
        id="package-{{ $shiftType }}-{{ $index }}-count"
        name="{{ $shiftType }}_package_entries[{{ $index }}][packages_count]"
        class="settlement-input package-count-input"
        value="{{ $entry['count'] }}"
        placeholder="0"
    >

    <label class="visually-hidden" for="package-{{ $shiftType }}-{{ $index }}-rate">Stawka za paczk&#281;</label>
    <select
        id="package-{{ $shiftType }}-{{ $index }}-rate"
        name="{{ $shiftType }}_package_entries[{{ $index }}][package_id]"
        class="settlement-input settlement-select package-rate"
    >
        <option value="">&mdash; wybierz stawk&#281; &mdash;</option>
        @foreach($packages as $package)
            <option value="{{ $package['id'] }}" @selected($entry['packageId'] == $package['id'])>
                {{ $package['name'] }} &middot; {{ number_format($package['price'], 2, ',', ' ') }} z&#322;/szt.
            </option>
        @endforeach
    </select>

    <span class="package-value" aria-live="polite">&mdash;</span>

    <button type="button" class="worker-action btn-remove-entry" title="Usu&#324; pozycj&#281;" aria-label="Usu&#324; pozycj&#281;">
        <svg aria-hidden="true" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>
</div>
