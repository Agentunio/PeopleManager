<?php
    $title = "Ustawienia - Panel administratora";
    $css = ['../styles/system/settings.css'];

    include_once '../shared/head.php';
    require_once '../shared/config.php';

    $error = '';
    $success = '';
    if(isset($_POST['packageSubmit'])){
        $packageName = $_POST['packageName'];
        $packagePrice = $_POST['packagePrice'];

        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $error = "Sesja wygasła. Odśwież stronę i spróbuj ponownie.";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        if(empty($packageName) || empty($packagePrice)){
            $error = "Wszystkie pola są wymagane";
        }
        elseif(!is_numeric($packagePrice)){
            $error = "Musisz podać poprawną liczbę";
        }
        else {
            $sql = "SELECT * FROM settings WHERE name = ? ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$packageName]);

            if($stmt->fetch()){
                $error = "Taki pakiet już istnieje";
            }
            else {
                $sql = "INSERT INTO settings (name, price) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$packageName, $packagePrice]);
                $success = 'Poprawnie dodano pakiet';
            }
        }
    }
?>

    <div class="admin-panel">
        <?php
        include_once('../shared/menu.php');
        ?>

        <main class="main-content">
            <div class="header">
                <h1>Ustawienia Systemu</h1>
                <p>Zarządzaj ustawieniami stawek, parametrami systemu oraz konfiguracją</p>

                <label for="toggle-package-form" class="toggle-btn btn btn-change">
                    <i class="fa-solid fa-plus"></i> Nowy Pakiet
                </label>
            </div>

            <input type="checkbox" id="toggle-package-form">

            <div class="edit-form">
                <h2>Dodaj Nowy Pakiet</h2>

                <?php if(!empty($error)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if(!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form id="packageForm" action="" method="post">
                    <div class="form-group">
                        <label for="packageName" class="form-label">Nazwa Pakietu</label>
                        <input type="text" id="packageName" name="packageName" class="form-input" placeholder="np. Pakiet Standard, Pakiet Premium" required>
                    </div>

                    <div class="form-group">
                        <label for="packagePrice" class="form-label">Cena (PLN)</label>
                        <input type="number" id="packagePrice" name="packagePrice" class="form-input" placeholder="np. 99.99" step="0.01" min="0" required>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="form-actions">
                        <label for="toggle-package-form" class="btn btn-cancel toggle-btn">Anuluj</label>

                        <button type="submit" name="packageSubmit" class="btn btn-submit">Zapisz Pakiet</button>
                    </div>
                </form>
            </div>

            <?php
                $sql = "SELECT name, price FROM settings";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $row = $stmt->fetch();
                if($row):
            ?>
                <div class="settings-container">
                    <h2>Lista istniejących pakietów</h2>
                </div>
                
                <div class="settings-container">
                        <div class="settings-section">
                            <h2><?= htmlspecialchars($row['name']) ?></h2>

                            <div class="current-amount">
                                <div class="amount-info">
                                    <span class="amount-label">Aktualna kwota brutto wynosi:</span>
                                    <span class="amount-value" id="amount-value-hours"><?= htmlspecialchars($row['price']) ?></span>
                                    <span class="currency">PLN</span>
                                </div>
                                <label for="toggle-form-hours" class="btn btn-change">
                                    <i class="fas fa-edit"></i>
                                    Zmień kwotę
                                </label>
                            </div>

                            <input type="checkbox" id="toggle-form-hours">
                            <div class="edit-form">
                                <form action="" method="post" id="form-hours">
                                    <div class="form-group">
                                        <label class="form-label" for="new-amount-hours">
                                            <i class="fas fa-money-bill-wave"></i>
                                            Nowa kwota brutto (PLN)
                                        </label>
                                        <input
                                                type="text"
                                                id="new-amount-hours"
                                                class="form-input"
                                                name="new-amount-hours"
                                                value="<?= htmlspecialchars($row['price']) ?>"
                                        >
                                    </div>

                                    <div class="form-actions">
                                        <label for="toggle-form-hours" class="btn btn-cancel cancel-hours">
                                            <i class="fas fa-times"></i>
                                            Anuluj
                                        </label>
                                        <button type="submit" name="new-amount-hours-submit" class="btn btn-submit" id="submit-hours">
                                            <i class="fas fa-check"></i>
                                            Zatwierdź
                                        </button>
                                    </div>
                                </form>
                            </div>

                        </div>
                </div>
                
            <?php else: ?>
                    <div class="settings-container">
                        <div class="settings-section">
                            <h2>Brak istniejących pakietów</h2>
                        </div>
                    </div>
            <?php endif; ?>

        </main>
    </div>
<?php include_once('../shared/footer.php'); ?>