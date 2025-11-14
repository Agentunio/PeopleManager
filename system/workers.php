<?php
    $title = "Pracownicy - Panel administratora";
    $css = ['../styles/system/settings.css', '../styles/system/workers.css'];
    $js = ['../scripts/workers/scripts.js'];
    include_once '../shared/head.php';
?>

    <div class="admin-panel">
        <?php
        include_once('../shared/menu.php');
        ?>

        <main class="main-content">
            <div class="header">
                <h1>Pracownicy</h1>
                <p>Zarządzaj listą pracowników, ich danymi oraz sprawdź ich wynagrodzenie.</p>
                <label for="toggle-worker-form" class="toggle-btn btn btn-change">
                    <i class="fa-solid fa-plus"></i> Dodaj Nowego Pracownika
                </label>
            </div>

            <input type="checkbox" id="toggle-worker-form">

            <div class="edit-form">
                <h2>Dodaj Nowego Pracownika</h2>

                <form id="addWorkerForm" action="" method="post">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="workerFirstName" class="form-label">Imię</label>
                            <input type="text" id="workerFirstName" name="workerFirstName" class="form-input" placeholder="np. Jan" required>
                        </div>

                        <div class="form-group">
                            <label for="workerLastName" class="form-label">Nazwisko</label>
                            <input type="text" id="workerLastName" name="workerLastName" class="form-input" placeholder="np. Kowalski" required>
                        </div>

                        <div class="form-group">
                            <label for="workerPhone" class="form-label">Numer telefonu</label>
                            <input type="tel" id="workerPhone" name="workerPhone" class="form-input" placeholder="np. +48 123 456 789">
                        </div>

                        <div class="form-group">
                            <label for="workerAddress" class="form-label">Miejsce zamieszkania</label>
                            <input type="text" id="workerAddress" name="workerAddress" class="form-input" placeholder="np. Warszawa, ul. Prosta 1">
                        </div>

                        <div class="form-group">
                            <label for="workerDob" class="form-label">Data urodzenia</label>
                            <input type="date" id="workerDob" name="workerDob" class="form-input">
                        </div>

                        <div class="form-group">
                            <label for="workerStudentStatus" class="form-label">Status ucznia</label>
                            <select id="workerStudentStatus" name="workerStudentStatus" class="form-input">
                                <option value="0">Nie</option>
                                <option value="1">Tak</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="workerEmploymentStatus" class="form-label">Status zatrudnienia</label>
                            <select id="workerEmploymentStatus" name="workerEmploymentStatus" class="form-input">
                                <option value="1">Tak</option>
                                <option value="0">Nie</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="workerContractFrom" class="form-label">Umowa od daty</label>
                            <input type="date" id="workerContractFrom" name="workerContractFrom" class="form-input">
                        </div>

                        <div class="form-group">
                            <label for="workerContractTo" class="form-label">Umowa do daty</label>
                            <input type="date" id="workerContractTo" name="workerContractTo" class="form-input">
                        </div>
                    </div>

                    <input type="hidden" name="csrf_token" value="">
                    <div class="form-actions">
                        <label for="toggle-worker-form" class="btn btn-cancel toggle-btn">Anuluj</label>
                        <button type="submit" name="workerSubmit" class="btn btn-submit">Zapisz Pracownika</button>
                    </div>
                </form>
            </div>


            <div class="settings-container">
                <h2>Lista pracowników</h2>

                <form class="worker-search-bar" action="" method="get">
                    <div class="form-group">
                        <label for="searchWorker" class="form-label">Wyszukaj użytkownika</label>
                        <input type="text" id="searchWorker" name="searchWorker" class="form-input" placeholder="Wpisz imię lub nazwisko...">
                    </div>

                    <div class="form-group">
                        <label for="filterStatus" class="form-label">Status zatrudnienia</label>
                        <select id="filterStatus" name="filterStatus" class="form-input">
                            <option value="">Wszystkie</option>
                            <option value="1">Tak</option>
                            <option value="0">Nie</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="searchSubmit" class="btn btn-submit">
                            <i class="fas fa-search"></i>
                            Szukaj
                        </button>
                    </div>
                </form>
            </div>

            <div class="settings-container">
                <div class="settings-section">
                    <div class="package-header-row">
                        <h2>Jan Kowalski</h2>
                        <div class="package-actions">
                            <form action="" method="post" onsubmit="return confirm('Czy na pewno chcesz usunąć pracownika: Jan Kowalski?');">
                                <input type="hidden" name="worker_id_to_delete" value="1">
                                <input type="hidden" name="csrf_token" value="">
                                <button class="btn btn-delete" type="submit" name="delete_worker_submit">
                                    <i class="fas fa-trash-alt"></i>
                                    Usuń
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="worker-info-grid">
                        <div class="worker-info-item">
                            <span class="label">Telefon</span>
                            <span class="value">+48 123 456 789</span>
                        </div>
                        <div class="worker-info-item">
                            <span class="label">Adres</span>
                            <span class="value">Warszawa, ul. Prosta 1</span>
                        </div>
                        <div class="worker-info-item">
                            <span class="label">Status ucznia</span>
                            <span class="value">Tak</span>
                        </div>
                        <div class="worker-info-item">
                            <span class="label">Status zatrudnienia</span>
                            <span class="value">Tak</span>
                        </div>

                        <div class="worker-info-item">
                            <span class="label">Data urodzenia</span>
                            <span class="value">2002-05-10</span>
                        </div>
                        <div class="worker-info-item">
                            <span class="label">Umowa</span>
                            <span class="value">2024-01-01 do 2025-12-31</span>
                        </div>
                    </div>

                    <label for="toggle-edit-worker-1" class="btn btn-change">
                        <i class="fas fa-edit"></i>
                        Edytuj Dane
                    </label>

                    <hr class="section-separator">

                    <div class="current-amount">
                        <div class="financial-stats-stack">
                            <div class="amount-info">
                                <span class="amount-label">Godziny (w zakresie):</span>
                                <span class="amount-value" id="hours-value-1">42.5</span>
                            </div>
                            <div class="amount-info">
                                <span class="amount-label">Wynagrodzenie (w zakresie):</span>
                                <span class="amount-value" id="salary-value-1">850.00</span>
                                <span class="currency">PLN</span>
                            </div>
                        </div>
                        <div class="financial-controls">
                            <label for="toggle-range-1" class="btn btn-change">
                                <i class="fas fa-calendar-alt"></i>
                                Zmień zakres
                            </label>
                        </div>
                    </div>

                    <input type="checkbox" id="toggle-range-1">

                    <div class="edit-form date-range-form">
                        <form class="date-range-form-inner" id="date-range-form-1">
                            <input type="hidden" name="worker_id_range" value="1">
                            <div class="form-group flatpickr-form-group">
                                <label for="flatpickr-range-1" class="form-label">Wybierz zakres dat</label>
                                <input type="text" id="flatpickr-range-1" class="form-input flatpickr-range-input"
                                       placeholder="Kliknij, aby wybrać zakres..." readonly="readonly">
                            </div>
                            <input type="date" id="date-from-1" name="dateFrom" class="form-input hidden-date-input" required>
                            <input type="date" id="date-to-1" name="dateTo" class="form-input hidden-date-input" required>
                            <div class="form-actions">
                                <button type="button" class="btn btn-submit" onclick="alert('Filtrowanie...')">
                                    <i class="fas fa-filter"></i>
                                    Filtruj
                                </button>
                            </div>
                        </form>
                    </div>


                    <input type="checkbox" id="toggle-edit-worker-1">

                    <div class="edit-form">
                        <form action="" method="post">
                            <input type="hidden" name="worker_id_to_edit" value="1">
                            <input type="hidden" name="csrf_token" value="">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="edit-workerFirstName-1" class="form-label">Imię</label>
                                    <input type="text" id="edit-workerFirstName-1" name="workerFirstName" class="form-input" value="Jan" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit-workerLastName-1" class="form-label">Nazwisko</label>
                                    <input type="text" id="edit-workerLastName-1" name="workerLastName" class="form-input" value="Kowalski" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit-workerPhone-1" class="form-label">Numer telefonu</label>
                                    <input type="tel" id="edit-workerPhone-1" name="workerPhone" class="form-input" value="+48 123 456 789">
                                </div>
                                <div class="form-group">
                                    <label for="edit-workerAddress-1" class="form-label">Miejsce zamieszkania</label>
                                    <input type="text" id="edit-workerAddress-1" name="workerAddress" class="form-input" value="Warszawa, ul. Prosta 1">
                                </div>
                                <div class="form-group">
                                    <label for="edit-workerDob-1" class="form-label">Data urodzenia</label>
                                    <input type="date" id="edit-workerDob-1" name="workerDob" class="form-input" value="2002-05-10">
                                </div>
                                <div class="form-group">
                                    <label for="edit-workerStudentStatus-1" class="form-label">Status ucznia</label>
                                    <select id="edit-workerStudentStatus-1" name="workerStudentStatus" class="form-input">
                                        <option value="0">Nie</option>
                                        <option value="1" selected>Tak</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="edit-workerEmploymentStatus-1" class="form-label">Status zatrudnienia</label>
                                    <select id="edit-workerEmploymentStatus-1" name="workerEmploymentStatus" class="form-input">
                                        <option value="1" selected>Tak</option>
                                        <option value="0">Nie</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="edit-workerContractFrom-1" class="form-label">Umowa od daty</label>
                                    <input type="date" id="edit-workerContractFrom-1" name="workerContractFrom" class="form-input" value="2024-01-01">
                                </div>
                                <div class="form-group">
                                    <label for="edit-workerContractTo-1" class="form-label">Umowa do daty</label>
                                    <input type="date" id="edit-workerContractTo-1" name="workerContractTo" class="form-input" value="2025-12-31">
                                </div>
                            </div>

                            <div class="form-actions">
                                <label for="toggle-edit-worker-1" class="btn btn-cancel">Anuluj</label>
                                <button type="submit" name="edit_worker_submit" class="btn btn-submit">Zatwierdź Zmiany</button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </main>
    </div>

<?php include_once('../shared/footer.php'); ?>