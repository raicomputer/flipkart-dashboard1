<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['month_year'])) {
    $candidate = $_GET['month_year'];
    if (preg_match('/^\d{4}-\d{2}$/', $candidate)) {
        $_SESSION['filter_month'] = $candidate;
    }
}

if (isset($_GET['clear_filter'])) {
    unset($_SESSION['filter_month']);
    header("Location: view_orders.php");
    exit;
}

$filter_month = $_SESSION['filter_month'] ?? '';

$whereFilter = "";
if (!empty($filter_month)) {
    $safeMonth = $conn->real_escape_string($filter_month);
    $whereFilter = "WHERE DATE_FORMAT(order_date, '%Y-%m') = '$safeMonth'";
}
