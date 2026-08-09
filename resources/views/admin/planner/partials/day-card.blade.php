@php
    $statusMeta = match ($day['status']) {
        'draft' => ['label' => 'Szkic', 'class' => 'draft'],
        'settled' => ['label' => 'Rozliczony', 'class' => 'settled'],
        default => ['label' => 'Aktywny', 'class' => 'active'],
    };
@endphp

<article
    id="planner-day-{{ $day['date'] }}"
    class="planner-day-card calendar-day-{{ $statusMeta['class'] }} {{ $day['is_today'] ? 'is-today' : '' }}"
    data-planner-day-card
    data-day-url="{{ route('planner.day.index', $day['date']) }}"
    data-day="{{ $day['date'] }}"
    data-status="{{ $day['status'] }}"
>
    <header class="planner-day-card__header">
        <a
            class="planner-day-card__date-link"
            href="{{ route('planner.day.index', $day['date']) }}"
            aria-label="Otwórz obsadę dnia: {{ $day['weekday_long'] }}, {{ $day['day_number'] }} {{ $day['month_name'] }}"
        >
            <span class="planner-date-tile" aria-hidden="true">
                <span class="planner-date-tile__weekday">{{ $day['weekday_short'] }}</span>
                <span class="planner-date-tile__number">{{ str_pad((string) $day['day_number'], 2, '0', STR_PAD_LEFT) }}</span>
            </span>
            <span class="planner-day-card__date-name">
                {{ $day['weekday_long'] }}, {{ $day['day_number'] }} {{ $day['month_name'] }}
            </span>

        </a>

        <div class="planner-day-card__actions">
            <span class="planner-status-badge planner-status-badge--{{ $statusMeta['class'] }}">
                {{ $statusMeta['label'] }}
            </span>

            @if($day['status'] === 'active')
                <a class="planner-small-button" href="{{ route('planner.day.end-day', $day['date']) }}">
                    Rozlicz dzień
                </a>
            @elseif($day['locked'])
                <span class="planner-locked-label">
                    <svg aria-hidden="true" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    Zablokowane
                </span>
            @endif
        </div>
    </header>

    <div class="planner-day-card__shifts">
        @foreach(['morning' => 'Rano', 'afternoon' => 'Popołudnie'] as $shiftType => $shiftLabel)
            @php($shift = $day['shifts'][$shiftType])
            <section class="planner-shift-column" aria-label="{{ $shiftLabel }}">
                <div class="planner-shift-column__header">
                    <h3>{{ $shiftLabel }}</h3>
                    <span>{{ $shift['start_time'] ?? ($shiftType === 'morning' ? '09:00' : '21:00') }}</span>
                </div>

                <div class="planner-shift-column__people">
                    @forelse($shift['people'] as $person)
                        @if($day['locked'])
                            <span class="planner-person-pill planner-person-pill--{{ $person['state'] }}">
                                {{ $person['name'] }}
                                @if($person['state'] === 'substitute')
                                    <span class="sr-only">, zastępstwo za {{ $person['replaces'] }}</span>
                                @elseif($person['state'] === 'unavailable')
                                    <span class="sr-only">, niedostępny</span>
                                @endif
                            </span>
                        @else
                            <button
                                type="button"
                                class="planner-person-pill planner-person-pill--{{ $person['state'] }}"
                                data-person-menu-trigger
                                data-shift-id="{{ $person['shift_id'] }}"
                                data-person-state="{{ $person['state'] }}"
                                data-has-substitute="{{ $person['has_substitute'] ? 'true' : 'false' }}"
                                data-person-name="{{ $person['name'] }}"
                                aria-expanded="false"
                                aria-controls="planner-person-menu"
                            >
                                {{ $person['name'] }}
                                @if($person['state'] === 'substitute')
                                    <span class="sr-only">, zastępstwo za {{ $person['replaces'] }}</span>
                                @elseif($person['state'] === 'unavailable')
                                    <span class="sr-only">, niedostępny</span>
                                @endif
                            </button>
                        @endif
                    @empty
                        <span class="planner-shift-empty">[ brak zapisanych ]</span>
                    @endforelse

                    @unless($day['locked'])
                        <a
                            class="planner-add-worker"
                            href="{{ route('planner.day.index', $day['date']) }}"
                            aria-label="Dodaj pracownika do zmiany {{ mb_strtolower($shiftLabel) }}, {{ $day['weekday_long'] }} {{ $day['day_number'] }} {{ $day['month_name'] }}"
                        >
                            + Dodaj
                        </a>
                    @endunless
                </div>
            </section>

        @endforeach
    </div>
</article>
