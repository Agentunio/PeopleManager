@forelse($workers as $worker)
    @php $availability = $worker->availabilities->first(); @endphp
    @continue(!$availability)

    <div class="worker-card draggable"
         data-worker-id="{{ $worker->id }}"
         data-morning="{{ $availability->morning_shift ? 'true' : 'false' }}"
         data-afternoon="{{ $availability->afternoon_shift ? 'true' : 'false' }}">
        <span class="worker-name">{{ $worker->first_name }} {{ $worker->last_name }}</span>
        <div class="worker-availability-badges">
            @if($availability->morning_shift)
                <span class="badge badge-morning">R</span>
            @endif
            @if($availability->afternoon_shift)
                <span class="badge badge-afternoon">P</span>
            @endif
        </div>
    </div>

@empty
    <p>Brak pracowników</p>
@endforelse
