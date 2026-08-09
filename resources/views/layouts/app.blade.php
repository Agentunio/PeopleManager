<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel Administratora')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    @vite(['resources/css/main.css', 'resources/css/admin-navigation.css'])

    @stack('styles')
</head>
<body class="@yield('body_class')">
    @yield('content')

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
