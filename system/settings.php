<?php
    if(isset($_POST['submit'])){
        $new_amount_hours = $_POST['new-amount-hours'];
    }

    $title = "Ustawienia - Panel administratora";
    $css = ['../styles/system/settings.css'];

    include_once '../shared/head.php';
?>

<div class="admin-panel">
    <?php
    include_once('../shared/menu.php');
    ?>

    <main class="main-content">
        <div class="header">
            <h1>Ustawienia Systemu</h1>
            <p>Zarządzaj ustawieniami stawek, parametrami systemu oraz konfiguracją</p>
        </div>

        <div class="settings-container">
            <div class="settings-section">
                <h2>Stawka za Godzinę</h2>

                <div class="current-amount">
                    <div class="amount-info">
                        <span class="amount-label">Aktualna kwota brutto wynosi:</span>
                        <span class="amount-value-hours">32,50</span>
                        <span class="currency">PLN</span>
                    </div>
                    <label for="toggle-form-hours" class="btn btn-change">
                        <i class="fas fa-edit"></i>
                        Zmień kwotę
                    </label>
                </div>

                <input type="checkbox" id="toggle-form-hours">
                <div class="edit-form">
                    <form action="" method="post">
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
                                value="30,50"
                            >
                        </div>

                        <div class="form-actions">
                            <label for="toggle-form-hours" class="btn btn-cancel">
                                <i class="fas fa-times"></i>
                                Anuluj
                            </label>
                            <button type="submit" class="btn btn-submit-hours">
                                <i class="fas fa-check"></i>
                                Zatwierdź
                            </button>
                        </div>
                    </form>
                </div>

                <div class="info-box">
                    <p>
                        <i class="fas fa-info-circle"></i>
                        Stawka godzinowa jest używana do automatycznego obliczania wynagrodzeń pracowników.
                    </p>
                </div>
            </div>
        </div>
        <div style="margin-top: 20px" class="settings-container">
            <div class="settings-section">
                <h2>Stawka za paczkę</h2>

                <div class="current-amount">
                    <div class="amount-info">
                        <span class="amount-label">Aktualna kwota brutto wynosi:</span>
                        <span class="amount-value">0,50</span>
                        <span class="currency">PLN</span>
                    </div>
                    <label for="toggle-form" class="btn btn-change">
                        <i class="fas fa-edit"></i>
                        Zmień kwotę
                    </label>
                </div>

                <input type="checkbox" id="toggle-form">
                <div class="edit-form">
                    <form action="" method="post">
                        <div class="form-group">
                            <label class="form-label" for="new-amount">
                                <i class="fas fa-money-bill-wave"></i>
                                Nowa kwota brutto (PLN)
                            </label>
                            <input
                                    type="text"
                                    id="new-amount"
                                    class="form-input"
                                    name="new-amount"
                                    value="0,50"
                            >
                        </div>

                        <div class="form-actions">
                            <label for="toggle-form" class="btn btn-cancel">
                                <i class="fas fa-times"></i>
                                Anuluj
                            </label>
                            <button type="submit" class="btn btn-submit">
                                <i class="fas fa-check"></i>
                                Zatwierdź
                            </button>
                        </div>
                    </form>
                </div>

                <div class="info-box">
                    <p>
                        <i class="fas fa-info-circle"></i>
                        Stawka godzinowa jest używana do automatycznego obliczania budżetu miesięcznego.
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
    jQuery(function($) {
        $('#new-amount-hours').val($('.amount-value-hours').text());
    });
</script>
<script>
    document.querySelector('.btn-submit-hours').addEventListener('click', function(e) {
        e.preventDefault();
        const newValue = document.getElementById('new-amount-hours').value;
        if (newValue) {
            document.querySelector('.amount-value-hours').textContent = newValue;
            document.getElementById('toggle-form-hours').checked = false;
        }
    });
</script>
<?php include_once('../shared/footer.php'); ?>