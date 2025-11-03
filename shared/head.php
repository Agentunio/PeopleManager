<?php
    require_once 'user_checker.php';
    $title ??= 'Panel Administratora';
    $css ??= [];
    $js ??= [];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($title); ?></title>
    <?php
        if(!empty($css)){
            foreach ($css as $single_css) {
                echo "<link rel='stylesheet' href='" . $single_css . "'>";
            }
        }

        if(!empty($js)){
            foreach ($js as $single_js) {
                echo "<script src='" . $single_js . "'></script>";
            }
        }
    ?>
    <link rel="stylesheet" href="../styles/shared/main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
</head>
<body>