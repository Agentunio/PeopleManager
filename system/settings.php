<?php
    $title = "Ustawienia - Panel administratora";
    $css = ['../styles/system/settings.css'];

    include_once '../shared/head.php';
    require_once '../shared/config.php';

    $error = '';
    $success = '';
    if(isset($_POST['packageSubmit'])){
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $error = "Sesja wygasła. Odśwież stronę i spróbuj ponownie.";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $packageName = $_POST['packageName'];
        $packagePrice = $_POST['packagePrice'];

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

    if(isset($_POST['delete_package_submit'])){
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $error = "Sesja wygasła. Odśwież stronę i spróbuj ponownie.";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $package_id_to_delete = $_POST['package_id_to_delete'];

        if(empty($package_id_to_delete) || !is_numeric($package_id_to_delete)){
            $error = 'Coś poszło nie tak spróbuj ponownie.';
        }else{
            $sql = "DELETE FROM settings WHERE id = ? ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$package_id_to_delete]);
            $success = 'Poprawnie usunięto pakiet';
        }

    }

    if(isset($_POST['edit-package-submit'])){
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $error = "Sesja wygasła. Odśwież stronę i spróbuj ponownie.";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $newPackageName = $_POST['new-name-package'];
        $newPackageId = $_POST['package_id_to_edit'];
        $newPackagePrice = $_POST['new-amount-package'];

        if (empty($newPackageName) || empty($newPackageId) || empty($newPackagePrice)) {
            $error = 'Wszystkie pola są wymagane.';
        }
        else if(!is_numeric($newPackagePrice)){
            $error = 'Coś poszło nie tak. Spróbuj ponownie.';
        }
        else{
            $sql = "UPDATE settings SET name = ?, price = ? WHERE id = ? ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$newPackageName, $newPackagePrice, $newPackageId]);
            if($stmt){
                $success = 'Poprawnie zaktualizowano pakiet';
            }else{
                $error = 'Nie zaktualizowano pakietu. Spróbuj ponownie.';
            }
        }
    }
?>

    <div class="admin-panel">
        <?php
        include_once('../shared/menu.php');
        ?>

        <main class="main-content">
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
            <div class="header">
                <h1>Ustawienia Systemu</h1>
                <p>Zarządzaj ustawieniami stawek, wprowadź własne stawki</p>

                <label for="toggle-package-form" class="toggle-btn btn btn-change">
                    <i class="fa-solid fa-plus"></i> Nowy Pakiet
                </label>
            </div>

            <input type="checkbox" <?php if (!empty($error) || !empty($success)): ?>checked<?php endif; ?> id="toggle-package-form">

            <div class="edit-form">
                <h2>Dodaj Nowy Pakiet</h2>


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
                $sql = "SELECT id,name, price FROM settings";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $row = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if($row):
            ?>
                <div class="settings-container">
                    <h2>Lista istniejących pakietów</h2>
                </div>
                    <?php foreach($row as $package): ?>
                    <?php
                    $unique_toggle_id = 'toggle-form-' . htmlspecialchars($package['id']);
                    ?>
                    <div class="settings-container">
                        <div class="settings-section">
                            <div class="package-header-row">
                                <h2><?= htmlspecialchars($package['name']) ?></h2>
                                <div class="package-actions">
                                    <form action="" method="post" onsubmit="return confirm('Czy na pewno chcesz usunąć pakiet: <?= htmlspecialchars($package['name']) ?>?');">
                                        <input type="hidden" name="package_id_to_delete" value="<?= htmlspecialchars($package['id']) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <button class="btn btn-delete" type="submit" name="delete_package_submit">
                                            <i class="fas fa-trash-alt"></i>
                                            Usuń Pakiet
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="current-amount">
                                <div class="amount-info">
                                    <span class="amount-label">Aktualna kwota brutto wynosi:</span>
                                    <span class="amount-value" id="amount-value-<?= htmlspecialchars($package['id']) ?>"><?= htmlspecialchars($package['price']) ?></span>
                                    <span class="currency">PLN</span>
                                </div>
                                <label for="<?= $unique_toggle_id ?>" class="btn btn-change">
                                    <i class="fas fa-edit"></i>
                                    Zmień kwotę
                                </label>
                            </div>

                            <input type="checkbox" id="<?= $unique_toggle_id ?>">
                            <div class="edit-form">
                                <form action="" method="post" id="form-package">
                                    <input type="hidden" name="package_id_to_edit" value="<?= htmlspecialchars($package['id']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                                    <div class="form-group">
                                        <label class="form-label" for="new-name-package">
                                            <i class="fas fa-tag"></i>
                                            Nowa nazwa pakietu
                                        </label>
                                        <input
                                                type="text"
                                                id="new-name-package"
                                                class="form-input"
                                                name="new-name-package"
                                                value="<?= htmlspecialchars($package['name']) ?>"
                                                required
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="new-amount-package">
                                            <i class="fas fa-money-bill-wave"></i>
                                            Nowa kwota brutto (PLN)
                                        </label>
                                        <input
                                                type="number"
                                                id="new-amount-package"
                                                class="form-input"
                                                name="new-amount-package"
                                                value="<?= htmlspecialchars($package['price']) ?>"
                                                step="0.01" min="0" required
                                        >
                                    </div>

                                    <div class="form-actions">
                                        <label for="<?= $unique_toggle_id ?>" class="btn btn-cancel">
                                            <i class="fas fa-times"></i>
                                            Anuluj
                                        </label>
                                        <button type="submit" name="edit-package-submit" class="btn btn-submit" id="submit-package-edit">
                                            <i class="fas fa-check"></i>
                                            Zatwierdź
                                        </button>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
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