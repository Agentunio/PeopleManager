<?php
//if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
//    die("CSRF token mismatch");
//}
if(session_status() == PHP_SESSION_NONE){
    session_start();
}
if(!isset($_SESSION['user_session'])){
    header('Location: ../');
    exit();
}