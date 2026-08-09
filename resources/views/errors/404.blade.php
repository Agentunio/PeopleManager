@if(request()->routeIs('account.activate'))
    @include('errors.guest', [
        'status' => 404,
        'title' => 'Link aktywacyjny jest nieprawidłowy',
        'description' => 'Sprawdź, czy adres został skopiowany w całości. Jeśli problem się powtarza, poproś administratora o nowy link.',
    ])
@else
    @include('errors.guest', [
        'status' => 404,
        'title' => 'Nie znaleziono strony',
        'description' => 'Podany adres nie istnieje lub strona została przeniesiona.',
    ])
@endif
