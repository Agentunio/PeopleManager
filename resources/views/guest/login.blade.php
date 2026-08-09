<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Logowania</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if($errors->any())
        <meta name="flash-error" content="{{ $errors->first() }}">
    @endif
    @vite(['resources/css/login.css', 'resources/js/password-toggle.js', 'resources/js/login.js'])
</head>
<body>
    <main class="login-page" aria-labelledby="login-title">
        <section class="login-card">
            <header class="login-header">
                <h1 id="login-title">Zaloguj się</h1>
                <p>Wpisz login i hasło, aby przejść do swojego panelu.</p>
            </header>

            <form action="{{ route('login') }}" method="post">
                @csrf

                <div class="input-group">
                    <label for="login">Login</label>
                    <input
                        type="text"
                        id="login"
                        name="login"
                        placeholder="login lub e-mail"
                        value="{{ old('login') }}"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="input-group">
                    <label for="password">Hasło</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            class="has-toggle"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="password-toggle" aria-label="Pokaż hasło" aria-pressed="false">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-off-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>

                <div class="login-options">
                    <label class="remember-option" for="remember">
                        <input
                            type="checkbox"
                            id="remember"
                            name="remember"
                            value="1"
                            @checked(old('remember'))
                        >
                        <span class="remember-box" aria-hidden="true">
                            <svg class="remember-check" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        <span>Zapamiętaj mnie</span>
                    </label>
                    <a class="forgot-password" href="{{ route('password.request') }}">Zapomniałeś hasła?</a>
                </div>

                <button type="submit" class="login-btn">Zaloguj się</button>
            </form>

            <p class="login-note">Nie masz konta? Skontaktuj się z administratorem.</p>
        </section>
    </main>
</body>
</html>
