<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktywacja konta</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    @vite(['resources/css/activate.css', 'resources/js/activate.js'])
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="login-container">
    <div class="circle circle1"></div>
    <div class="circle circle2"></div>
    <div class="circle circle3"></div>

    <h1>Aktywacja konta</h1>

    @if($step === 'verify')
        <p class="step-info">Aby potwierdzić swoją tożsamość, podaj datę urodzenia</p>

        <form action="{{ route('account.verify', $token) }}" method="post">
            @csrf
            <div class="input-group">
                <label for="date_of_birth">Data urodzenia</label>
                <input type="text" id="date_of_birth_display" placeholder="Wybierz datę urodzenia" readonly>
                <input type="hidden" id="date_of_birth" name="date_of_birth">
            </div>
            <button type="submit" class="login-btn">Potwierdź</button>
        </form>
    @else
        <p class="step-info">Tożsamość potwierdzona. Ustaw swoje hasło</p>

        <form action="{{ route('account.activate.store', $token) }}" method="post">
            @csrf
            <div class="input-group">
                <label for="password">Nowe hasło</label>
                <input type="password" id="password" name="password" placeholder="Wprowadź hasło" required>
                <ul class="password-requirements">
                    <li id="req-length" class="invalid">Minimum 8 znaków</li>
                    <li id="req-uppercase" class="invalid">Wielka litera</li>
                    <li id="req-lowercase" class="invalid">Mała litera</li>
                    <li id="req-number" class="invalid">Cyfra</li>
                    <li id="req-special" class="invalid">Znak specjalny</li>
                </ul>
            </div>
            <div class="input-group">
                <label for="password_confirmation">Powtórz hasło</label>
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Powtórz hasło" required>
                <p id="password-match" class="password-match hidden"></p>
            </div>
            <button type="submit" class="login-btn">Aktywuj konto</button>
        </form>
    @endif
</div>

@if($errors->any())
    <script>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: @json($errors->first()),
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            background: '#1f1f1f',
            color: '#f0f0f0'
        });
    </script>
@endif
</body>
</html>
