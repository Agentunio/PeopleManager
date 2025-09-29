<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administratora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/system/style.css">
</head>
<body>
    <div class="admin-panel">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3>Panel Admina</h3>
            </div>
            <ul class="nav-links">
                <li><a href="#podsumowanie" class="active"><i class="fa-solid fa-chart-pie"></i> Podsumowanie</a></li>
                <li><a href="#pracownicy"><i class="fa-solid fa-users"></i> Pracownicy</a></li>
                <li><a href="#grafik"><i class="fa-solid fa-calendar-alt"></i> Grafik</a></li>
                <li><a href="#ustawienia"><i class="fa-solid fa-cog"></i> Ustawienia</a></li>
                <li><a href="#wyloguj"><i class="fa-solid fa-sign-out-alt"></i> Wyloguj</a></li>
            </ul>
        </nav>
        <main class="main-content">
            <div class="content-grid">
                <div class="card" id="podsumowanie">
                    <h2>Podsumowanie</h2>
                    <div class="card-content">
                        <div class="summary-item">
                            <p class="summary-label">Przepracowane godziny (mc):</p>
                            <p class="summary-value">1,240 h</p>
                        </div>
                        <div class="summary-item">
                            <p class="summary-label">Dostarczone paczki:</p>
                            <p class="summary-value">8,921</p>
                        </div>
                        <div class="summary-item">
                            <p class="summary-label">Przychód:</p>
                            <p class="summary-value">150,200 PLN</p>
                        </div>
                    </div>
                    <a href="#" class="card-link">Zobacz więcej &rarr;</a>
                </div>
                <div class="card" id="podsumowanie">
                    <h2>Podsumowanie dnia</h2>
                    <div class="card-content">
                        <div class="summary-item">
                            <p class="summary-label">Przepracowane godziny (mc):</p>
                            <p class="summary-value">1,240 h</p>
                        </div>
                        <div class="summary-item">
                            <p class="summary-label">Dostarczone paczki:</p>
                            <p class="summary-value">8,921</p>
                        </div>
                        <div class="summary-item">
                            <p class="summary-label">Data</p>
                            <p class="summary-value">29.09.2025</p>
                        </div>
                    </div>
                    <a href="#" class="card-link">Zobacz więcej &rarr;</a>
                </div>
                <div class="card" id="pracownicy">
                    <h2>Pracownicy</h2>
                    <div class="card-content">
                        <ul class="employee-list">
                            <li>
                                <span>Jan Kowalski</span>
                                <span>160h / 4500 PLN</span>
                            </li>
                            <li>
                                <span>Anna Nowak</span>
                                <span>152h / 4300 PLN</span>
                            </li>
                            <li>
                                <span>Piotr Wiśniewski</span>
                                <span>168h / 4800 PLN</span>
                            </li>
                        </ul>
                    </div>
                    <a href="#" class="card-link">Zarządzaj pracownikami &rarr;</a>
                </div>
                <div class="card" id="grafik">
                    <h2>Grafik</h2>
                    <div class="card-content">
                        <p>Podgląd najbliższych zmian i zaplanowanych zadań.</p>
                        <div class="schedule-item">
                            <strong>Dziś (26.09):</strong> 8:00 - 16:00 - Zmiana A
                        </div>
                        <div class="schedule-item">
                            <strong>Jutro (27.09):</strong> 10:00 - 18:00 - Zmiana B
                        </div>
                    </div>
                    <a href="#" class="card-link">Otwórz grafik &rarr;</a>
                </div>
                <div class="card" id="ustawienia">
                    <h2>Ustawienia</h2>
                    <div class="card-content">
                        <p>Zarządzaj ustawieniami konta, powiadomieniami i integracjami systemu.</p>
                    </div>
                    <a href="#" class="card-link">Przejdź do ustawień &rarr;</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>