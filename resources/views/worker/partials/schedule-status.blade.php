@if($schedule && $schedule->isActive())
    <div class="schedule-status is-active">
        <i class="fa-solid fa-circle"></i>
        <span>Grafik: <strong>Aktywny{{ $schedule->end_date ? ' do ' . $schedule->end_date->format('d.m.Y') : '' }}</strong></span>
    </div>
@else
    <div class="schedule-status">
        <i class="fa-solid fa-circle"></i>
        <span>Grafik: <strong>Nieaktywny</strong></span>
    </div>
@endif
