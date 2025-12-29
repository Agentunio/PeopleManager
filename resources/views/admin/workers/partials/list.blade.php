@forelse($workers as $worker)
    @include('admin.workers.partials.card', ['worker' => $worker])
@empty
<div class="settings-container empty-state">
    <div class="settings-section">
        <h2>Brak pracowników</h2>
    </div>
</div>
@endforelse
