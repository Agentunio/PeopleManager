<?php
    if(session_status() == PHP_SESSION_NONE){
        session_start();
    }

    define('DB_SERVER', 'localhost');
    define('DB_USERNAME', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'peoplemanager');
    define('DB_CHARSET', 'utf8');

?>