<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} - PeopleManager</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    @vite('resources/css/account-activation.css')
</head>
<body>
<main class="login-page activation-page" aria-labelledby="error-title">
    <section class="login-card activation-card error-card">
        <p class="error-status">Błąd {{ $status }}</p>
        <header class="login-header">
            <h1 id="error-title">{{ $title }}</h1>
            <p>{{ $description }}</p>
        </header>
        <a href="{{ route('login') }}" class="login-btn">Wróć do logowania</a>
    </section>
</main>
</body>
</html>
