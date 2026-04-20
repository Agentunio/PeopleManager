<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel Administratora')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;700&display=swap" rel="stylesheet">
    @vite(['resources/css/fontawesome.css', 'resources/css/main.css'])

    @stack('styles')
</head>
<body>
    @yield('content')

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

    @vite(['resources/js/app.js'])

    @if(session('success'))
        <script>document.addEventListener('DOMContentLoaded', function() { showToast.success(@json(session('success'))); });</script>
    @endif

    @if(session('error'))
        <script>document.addEventListener('DOMContentLoaded', function() { showToast.error(@json(session('error'))); });</script>
    @endif

    @if($errors->any() && !request()->routeIs('login'))
        <script>document.addEventListener('DOMContentLoaded', function() { showToast.error(@json($errors->first())); });</script>
    @endif

    @stack('scripts')
</body>
</html>
