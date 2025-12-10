<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Logowania</title>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
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

@if($errors->any())
<script>
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title: '{{ $errors->first() }}',
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
