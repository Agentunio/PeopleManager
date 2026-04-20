<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktywacja konta</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if($errors->any())
        <meta name="flash-error" content="{{ $errors->first() }}">
    @endif
    @vite(['resources/css/activate.css', 'resources/js/activate.js', 'resources/js/password-toggle.js'])
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
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Wprowadź hasło" class="has-toggle" required>
                    <button type="button" class="password-toggle" aria-label="Pokaż hasło" aria-pressed="false">
                        <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-off-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
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
                <div class="password-wrapper">
                    <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Powtórz hasło" class="has-toggle" required>
                    <button type="button" class="password-toggle" aria-label="Pokaż hasło" aria-pressed="false">
                        <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-off-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
                <p id="password-match" class="password-match hidden"></p>
            </div>
            <button type="submit" class="login-btn">Aktywuj konto</button>
        </form>
    @endif
</div>

</body>
</html>
