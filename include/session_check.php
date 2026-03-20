<?php 

session_start();

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_type']) ||
    !in_array($_SESSION['user_type'], ['admin', 'user'])
) {
    header("Location: login.php");
    exit();
}


?>
