<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odzyskaj hasło</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if($errors->any())
        <meta name="flash-error" content="{{ $errors->first() }}">
    @endif
    @if(session('status'))
        <meta name="flash-status" content="{{ session('status') }}">
    @endif
    @vite(['resources/css/password-reset.css', 'resources/js/password-reset.js'])
</head>
<body>
    <main class="login-page" aria-labelledby="password-request-title">
        <section class="login-card password-reset-card">
            <header class="login-header">
                <h1 id="password-request-title">Odzyskaj hasło</h1>
                <p>Podaj e-mail przypisany do aktywnego konta. Wyślemy link do ustawienia nowego hasła.</p>
            </header>

            <form action="{{ route('password.email') }}" method="post">
                @csrf
                <div class="input-group">
                    <label for="email">Adres e-mail</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="adres@firma.pl" autocomplete="email" required autofocus>
                </div>

                <button type="submit" class="login-btn">Wyślij link</button>
            </form>

            <p class="login-note"><a class="auth-back-link" href="{{ route('login') }}">Wróć do logowania</a></p>
        </section>
    </main>
</body>
</html>
