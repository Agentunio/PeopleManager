<nav class="sidebar">
    <div class="sidebar-header">
        <h3>Panel Admina</h3>
    </div>
    <ul class="nav-links">
        <li><a href="../system" class="active"><i class="fa-solid fa-chart-pie"></i> Podsumowanie</a></li>
        <li><a href="workers.php"><i class="fa-solid fa-users"></i> Pracownicy</a></li>
        <li><a href="#grafik"><i class="fa-solid fa-calendar-alt"></i> Grafik</a></li>
        <li><a href="settings.php"><i class="fa-solid fa-cog"></i> Ustawienia</a></li>
        <li><i class="fa-solid fa-sign-out-alt"></i><form method="POST" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit">Wyloguj</button>
        </form></li>
    </ul>
</nav>