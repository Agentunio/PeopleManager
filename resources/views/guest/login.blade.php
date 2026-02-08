<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Logowania</title>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="login-container">
    <div class="circle circle1"></div>
    <div class="circle circle2"></div>
    <div class="circle circle3"></div>

    <h1>Zaloguj się</h1>

    <form action="{{ route('login') }}" method="post">
        @csrf
        <div class="input-group">
            <label for="login">Login</label>
            <input type="text" id="login" name="login" placeholder="Wprowadź login" value="{{ old('login') }}" required>
        </div>
        <div class="input-group">
            <label for="password">Hasło</label>
            <input type="password" id="password" name="password" placeholder="Wprowadź hasło" required>
        </div>

        <button type="submit" class="login-btn">Zaloguj</button>
    </form>
</div>

<script src="{{ asset('js/login.js') }}"></script>
@if($errors->any())
<script>
    showLoginError('{{ $errors->first() }}');
</script>
@endif

</body>
</html>
