<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ustaw nowe hasło</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if($errors->any())
        <meta name="flash-error" content="{{ $errors->first() }}">
    @endif
    @vite(['resources/css/password-reset.css', 'resources/js/password-reset.js', 'resources/js/password-toggle.js'])
</head>
<body>
    <main class="login-page password-reset-page" aria-labelledby="password-reset-title">
        <section class="login-card password-reset-card">
            <header class="login-header">
                <h1 id="password-reset-title">Ustaw nowe hasło</h1>
                <p>Nowe hasło będzie obowiązywać przy kolejnym logowaniu na konto {{ $email }}.</p>
            </header>

            <form action="{{ route('password.update') }}" method="post">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                @include('guest.partials.password-fields')

                <button type="submit" class="login-btn">Zmień hasło</button>
            </form>

            <p class="login-note"><a class="auth-back-link" href="{{ route('login') }}">Wróć do logowania</a></p>
        </section>
    </main>
</body>
</html>
