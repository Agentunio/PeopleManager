@php
    $mobileTitle = match (true) {
        request()->routeIs('dashboard') => 'Pulpit',
        request()->routeIs('workers.*') => 'Pracownicy',
        request()->routeIs('settings.*') => 'Stawki',
        request()->routeIs('app-settings.*') => 'Ustawienia',
        request()->routeIs('planner.day.end-day') => 'Rozliczenie dnia',
        default => 'Grafik',
    };
@endphp

<header class="admin-mobile-header">
    <button
        type="button"
        class="mobile-menu-toggle"
        id="mobileMenuToggle"
        aria-label="Otwórz menu"
        aria-controls="sidebar"
        aria-expanded="false"
    >
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="4" y1="7" x2="20" y2="7" />
            <line x1="4" y1="12" x2="20" y2="12" />
            <line x1="4" y1="17" x2="14" y2="17" />
        </svg>
    </button>
    <span class="admin-mobile-title">{{ $mobileTitle }}</span>
    @hasSection('mobile_header_action')
        <div class="admin-mobile-action">
            @yield('mobile_header_action')
        </div>
    @endif
</header>

<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<nav class="sidebar" id="sidebar" aria-label="Menu administratora">
    <button type="button" class="mobile-menu-close" id="mobileMenuClose" aria-label="Zamknij menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="6" y1="6" x2="18" y2="18" />
            <line x1="6" y1="18" x2="18" y2="6" />
        </svg>
    </button>

    <ul class="nav-links">
        <li>
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Pulpit</a>
        </li>
        <li>
            <a href="{{ route('workers.index') }}" class="{{ request()->routeIs('workers.*') ? 'active' : '' }}">Pracownicy</a>
        </li>
        <li>
            <a href="{{ route('planner.index') }}" class="{{ request()->routeIs('planner.*') ? 'active' : '' }}">Grafik</a>
        </li>
        <li>
            <a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">Stawki</a>
        </li>
        <li>
            <a href="{{ route('app-settings.index') }}" class="{{ request()->routeIs('app-settings.*') ? 'active' : '' }}">Ustawienia</a>
        </li>
        <li class="nav-logout">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Wyloguj</button>
            </form>
        </li>
    </ul>
</nav>
