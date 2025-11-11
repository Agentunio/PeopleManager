<?php

require_once '../shared/user_checker.php';

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $error = "Sesja wygasła. Odśwież stronę i spróbuj ponownie.";
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

unset($_SESSION['user_session']);
session_destroy();
header('Location: ../');
exit();