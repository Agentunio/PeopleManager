<?php
    require_once 'config.php';
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $error = '';
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $sql = "SELECT COUNT(*) from login_attempts WHERE data > DATE_SUB(NOW(), INTERVAL 30 MINUTE) AND success = 0 AND ip_user = :user_ip";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_ip' => $user_ip]);
    $row = $stmt->fetchColumn();

    if($row < 5) {
        if (isset($_POST['submit'])) {

            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                $error = "Sesja wygasła. Odśwież stronę i spróbuj ponownie.";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

            $login = $_POST['login'];
            $password = $_POST['password'];

            $sql = "SELECT username, password FROM users WHERE username = :username";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['username' => $login]);
            $row = $stmt->fetch();

            if ($login == $row['username'] && password_verify($password, $row['password'])) {
                session_regenerate_id(true);
                $sql = "INSERT INTO login_attempts (ip_user, success) VALUES (:user_ip, :success)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['user_ip' => $user_ip, 'success' => 1]);
                $_SESSION['user_session'] = $row['username'];
                header('Location: system/');
                exit();
            } else {
                $sql = "INSERT INTO login_attempts (ip_user, success) VALUES (:user_ip, :success)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['user_ip' => $user_ip, 'success' => 0]);
                $error = "Invalid username or password";
            }
        }
    }else{
        $error = "Spróbuj ponownie później";
    }
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Logowania</title>
    <link rel="stylesheet" href="styles/registration/login.css">
</head>
<body>

<div class="login-container">
    <div class="circle circle1"></div>
    <div class="circle circle2"></div>
    <div class="circle circle3"></div>

    <h1>Zaloguj się</h1>

    <form action="" method="post">
        <?php if($error != ''): ?>
            <span style="color: #e50914"><?= $error ?></span>
        <?php endif; ?>
        <div class="input-group">
            <label for="login">Login</label>
            <input type="text" id="login" name="login" placeholder="Wprowadź login" required>
        </div>
        <input type="hidden" value="<?= $_SESSION['csrf_token']; ?>" name="csrf_token">
        <div class="input-group">
            <label for="password">Hasło</label>
            <input type="password" id="password" name="password" placeholder="Wprowadź hasło" required>
        </div>

        <button type="submit" name="submit" class="login-btn">Zaloguj</button>
    </form>

</div>
</body>
</html>