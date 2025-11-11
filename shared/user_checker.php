<?php
if(session_status() == PHP_SESSION_NONE){
    session_start();
}


if(!isset($_SESSION['user_session'])){
    header('Location: ../');
    exit();
}