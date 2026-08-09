@foreach($workers as $worker)
    @php
        $availability = $worker->availabilities->first();
        $onMorning = $workers_on_shift->where('worker_id', $worker->id)->where('shift_type', 'morning')->isNotEmpty();
        $onAfternoon = $workers_on_shift->where('worker_id', $worker->id)->where('shift_type', 'afternoon')->isNotEmpty();

        $freeMorning = (bool) $availability?->morning_shift && !$onMorning;
        $freeAfternoon = (bool) $availability?->afternoon_shift && !$onAfternoon;
        $fullName = $worker->first_name . ' ' . $worker->last_name;
    @endphp

    @continue(!$freeMorning && !$freeAfternoon)

    <div class="worker-card"
         data-worker-id="{{ $worker->id }}"
         data-morning="{{ $freeMorning ? 'true' : 'false' }}"
         data-afternoon="{{ $freeAfternoon ? 'true' : 'false' }}">
        <span class="worker-name">{{ $fullName }}</span>
        <div class="worker-card__actions">
            <button type="button"
                    class="planner-day-add planner-day-add--morning"
                    data-shift="morning"
                    @disabled(!$freeMorning)
                    aria-label="Przypisz {{ $fullName }} do zmiany rannej">Rano</button>
            <button type="button"
                    class="planner-day-add planner-day-add--afternoon"
                    data-shift="afternoon"
                    @disabled(!$freeAfternoon)
                    aria-label="Przypisz {{ $fullName }} do zmiany popołudniowej">Popo.</button>
        </div>
    </div>
@endforeach
