@forelse($packages as $package)
    <option
        value="{{ $package['id'] }}" @selected(isset($selected_id) && $selected_id == $package['id'])>
        {{ $package['name'] }}
    </option>
@empty
    <option>Brak opcji</option>
@endforelse
