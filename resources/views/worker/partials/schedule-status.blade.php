@if($scheduleStatus['is_active'])
    <div class="schedule-status is-active">
        <i class="fa-solid fa-circle"></i>
        <span>{!! $scheduleStatus['text'] !!}</span>
    </div>
@else
    <div class="schedule-status">
        <i class="fa-solid fa-circle"></i>
        <span>Grafik: <strong>Nieaktywny</strong></span>
    </div>
@endif
