<?php
    require_once 'config.php';

    $pdo = new PDO("mysql:host=".DB_SERVER.";dbname=".DB_NAME, DB_USERNAME, DB_PASSWORD);

    $sql = "INSERT INTO peoplemanager.users (username, password, role) VALUES (:username, :password, :role)";
    $stmt = $pdo->prepare($sql);
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt->execute([':username' => 'admin', ':password' => $password, ':role' => 'admin']);
    if($stmt){
        echo "New record created successfully";
    }
?>