@forelse($workers as $worker)
    @php $availability = $worker->availabilities->first(); @endphp
    @continue(!$availability)

    @php
        $onMorning = $workers_on_shift->where('worker_id', $worker->id)->where('shift_type', 'morning')->isNotEmpty();
        $onAfternoon = $workers_on_shift->where('worker_id', $worker->id)->where('shift_type', 'afternoon')->isNotEmpty();

        $freeMorning = $availability->morning_shift && !$onMorning;
        $freeAfternoon = $availability->afternoon_shift && !$onAfternoon;
    @endphp

    @if(!$freeMorning && !$freeAfternoon)
        @continue
    @endif

    <div class="worker-card draggable"
         data-worker-id="{{ $worker->id }}"
         data-morning="{{ $freeMorning ? 'true' : 'false' }}"
         data-afternoon="{{ $freeAfternoon ? 'true' : 'false' }}">
        <span class="worker-name">{{ $worker->first_name }} {{ $worker->last_name }}</span>
        <div class="worker-availability-badges">
            @if($freeMorning)
                <span class="badge badge-morning">R</span>
            @endif
            @if($freeAfternoon)
                <span class="badge badge-afternoon">P</span>
            @endif
        </div>
    </div>

@empty
    <p>Brak pracowników</p>
@endforelse
