<nav class="sidebar">
    <div class="sidebar-header">
        <h3>Panel Admina</h3>
    </div>
    <ul class="nav-links">
        <li>
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-pie"></i> Podsumowanie
            </a>
        </li>
        <li>
            <a href="{{ route('workers.index') }}" class="{{ request()->routeIs('workers.*') ? 'active' : '' }}">
                <i class="fa-solid fa-users"></i> Pracownicy
            </a>
        </li>
        <li>
            <a href="{{ route('planner.index') }}"  class="{{ request()->routeIs('planner.*') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-alt"></i> Grafik
            </a>
        </li>
        <li>
            <a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <i class="fa-solid fa-cog"></i> Ustawienia
            </a>
        </li>
        <li>
            <i class="fa-solid fa-sign-out-alt"></i>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Wyloguj</button>
            </form>
        </li>
    </ul>
</nav>
