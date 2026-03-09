<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Strefa Pracownika')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    @vite(['resources/css/worker-base.css', 'resources/css/worker-menu.css'])

    @stack('styles')
</head>
<body>
    <div class="worker-app">
        @yield('content')

        <nav class="bottom-bar">
            <a href="{{ route('worker.dashboard') }}" class="bar-item {{ request()->routeIs('worker.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-house"></i>
                <span>Strona główna</span>
            </a>

            <a href="{{ route('worker.schedule') }}" class="bar-item {{ request()->routeIs('worker.schedule') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Grafik</span>
            </a>

            <form method="POST" action="{{ route('logout') }}" class="bar-item-form">
                @csrf
                <button type="submit" class="bar-item">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Wyloguj</span>
                </button>
            </form>
        </nav>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

    @vite(['resources/js/app.js'])

    @if(session('success'))
        <script>document.addEventListener('DOMContentLoaded', function() { showToast.success(@json(session('success'))); });</script>
    @endif

    @if(session('error'))
        <script>document.addEventListener('DOMContentLoaded', function() { showToast.error(@json(session('error'))); });</script>
    @endif

    @stack('scripts')
</body>
</html>
