@forelse($packages as $package)
    <option value="{{ $package['id'] }}">{{ $package['name'] }}</option>
@empty
    <option>Brak opcji</option>
@endforelse
