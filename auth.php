<?php

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);

if ($current_page != "login.php" && $current_page != "logout.php") {
    
    if (!isset($_SESSION['loggedin'])) {
        header("Location: login.php");
        exit();
    }
}

?>