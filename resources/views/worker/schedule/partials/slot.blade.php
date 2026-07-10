{{-- Pojedynczy slot zmiany (rano / popołudnie) w wierszu dnia grafiku.
     Wejście: $day (tablica z kontrolera), $type ('morning'|'afternoon').

     Stany:
     - PRZYPISANY przez admina  -> "twoja zmiana": czerwony box (is-mine) + "Ty" w rosterze, godziny.
     - dostępność (przyszłość)   -> zapis: wygaszony chip "Zapisany" + "Wypisz się".
     - dostępność (przeszłość)   -> chip "Zgłoszona dostępność" (nie przypisano, brak godzin). --}}
@php
    $assigned = $day['assigned_' . $type];              // przypisany przez admina (opublikowana zmiana)
    $availability = (bool) $day[$type];                 // zgłoszona dostępność
    $isPast = $day['is_past'];

    $availabilityOnly = $availability && !$assigned;   // zgłoszona dostępność, bez przydziału admina
    $signedUpFuture = $availabilityOnly && !$isPast;   // zapis na przyszłość
    $availabilityPast = $availabilityOnly && $isPast;  // dostępność bez przydziału (przeszłość/dziś)
    $canSignup = !$isPast && $day['in_schedule'];

    $startLabel = $day[$type . '_start_label'];
    $source = $day[$type . '_source'];
    $shiftStatus = $day[$type . '_status'];
    $from = $day[$type . '_from'];
    $to = $day[$type . '_to'];
    $minutes = $day[$type . '_minutes'];

    $roster = array_values(array_filter($day['assigned_workers'], fn ($w) => $w['shift_type'] === $type));
    usort($roster, fn ($a, $b) => ($b['is_me'] <=> $a['is_me']));   // "Ty" na początku

    $canViewHours = $day['can_view_hours'] && $assigned;
    $canInputHours = $day['can_input_hours'] && $assigned;

    $label = $type === 'morning' ? 'Rano' : 'Popołudnie';

    // Stan sekcji godzin (jak w oryginalnym modalu):
    // absent -> nieobecność | admin -> zatwierdzone | saved -> godziny pracownika | input -> puste pola
    $hoursState = null;
    if ($canViewHours) {
        if ($shiftStatus === 'absent') {
            $hoursState = 'absent';
        } elseif ($source === 'admin') {
            $hoursState = 'admin';
        } elseif ($source === 'worker' && $from && $to) {
            $hoursState = 'saved';
        } elseif ($canInputHours) {
            $hoursState = 'input';
        }
    }

    $adminHours = '';
    if ($hoursState === 'admin' && $minutes !== '' && $minutes !== null) {
        $mH = intdiv((int) $minutes, 60);
        $mM = (int) $minutes % 60;
        $adminHours = ($mH > 0 ? $mH . 'h' : '') . ($mM > 0 ? ($mH > 0 ? ' ' : '') . $mM . 'min' : '');
        $adminHours = $adminHours ?: '0h';
    }

    $fromH = $from ? \Illuminate\Support\Str::before($from, ':') : '';
    $fromM = $from ? \Illuminate\Support\Str::after($from, ':') : '';
    $toH = $to ? \Illuminate\Support\Str::before($to, ':') : '';
    $toM = $to ? \Illuminate\Support\Str::after($to, ':') : '';
@endphp

<div @class(['gr-slot', 'is-mine' => $assigned])
     data-type="{{ $type }}"
     data-start-minutes="{{ $day[$type . '_unlock_minutes'] ?? '' }}"
     data-start-label="{{ $day[$type . '_unlock_label'] ?? '' }}">
    <div class="gr-slot-top">
        <span class="gr-mono gr-slot-label">{{ $label }}</span>
        @if($startLabel)
            <span class="gr-slot-start">Start: {{ $startLabel }}</span>
        @endif
    </div>

    @if(count($roster))
        <div class="gr-roster">
            @foreach($roster as $w)
                <span @class(['gr-name', 'is-me' => $w['is_me']])
                      @if($w['is_me']) title="{{ $w['name'] }}" @endif>{{ $w['is_me'] ? 'Ty' : $w['name'] }}</span>
            @endforeach
        </div>
    @endif

    <div class="gr-slot-foot">
        <div class="gr-slot-status">
            @if($hoursState === 'absent')
                <span class="gr-hours-chip is-absent">Nieobecność</span>
            @elseif($hoursState === 'admin')
                <span class="gr-hours-chip is-admin">Zatwierdzone: <b>{{ $adminHours }}</b></span>
            @elseif($hoursState === 'saved')
                <span class="gr-hours-chip is-pending">Oczekuje na akceptację: <b>{{ $from }}–{{ $to }}</b></span>
            @elseif($signedUpFuture)
                <span class="gr-hours-chip is-avail">Zapisany</span>
            @elseif($availabilityPast)
                <span class="gr-hours-chip is-avail" title="Zgłosiłeś dostępność, ale nie zostałeś przypisany na tę zmianę">Zgłoszona dostępność</span>
            @endif
        </div>

        <div class="gr-slot-action">
            @if($hoursState === 'saved' && $canInputHours)
                <button type="button" class="gr-link gr-hours-toggle" data-label="Edytuj">Edytuj</button>
            @elseif($hoursState === 'input')
                <button type="button" class="gr-link gr-hours-toggle" data-label="Wpisz godziny">Wpisz godziny</button>
            @endif

            @if(!$availability && !$assigned && $canSignup)
                <button type="button" class="gr-btn gr-signup">Zapisz się</button>
            @elseif($availabilityOnly && $canSignup)
                <button type="button" class="gr-btn gr-unsign">Wypisz się</button>
            @endif
        </div>
    </div>

    @if($hoursState === 'input' || ($hoursState === 'saved' && $canInputHours))
        <div class="gr-hours-form" hidden>
            <div class="gr-hours-row">
                <label class="gr-hours-field">
                    <span class="gr-mono">start</span>
                    <span class="gr-time-pair">
                        <input type="number" class="gr-h-from-h" inputmode="numeric" placeholder="00" min="0" max="23" value="{{ $fromH }}">
                        <span class="gr-colon">:</span>
                        <input type="number" class="gr-h-from-m" inputmode="numeric" placeholder="00" min="0" max="59" value="{{ $fromM }}">
                    </span>
                </label>
                <span class="gr-hours-dash">—</span>
                <label class="gr-hours-field">
                    <span class="gr-mono">koniec</span>
                    <span class="gr-time-pair">
                        <input type="number" class="gr-h-to-h" inputmode="numeric" placeholder="00" min="0" max="23" value="{{ $toH }}">
                        <span class="gr-colon">:</span>
                        <input type="number" class="gr-h-to-m" inputmode="numeric" placeholder="00" min="0" max="59" value="{{ $toM }}">
                    </span>
                </label>
                <button type="button" class="gr-btn gr-hours-save">Zapisz</button>
                <div class="gr-hours-note" hidden></div>
            </div>
        </div>
    @endif
</div>
