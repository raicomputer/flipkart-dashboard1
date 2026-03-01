<?php
require 'db.php';

$order_id = $_POST['order_id'] ?? '';

if (!$order_id) {
    exit('Invalid');
}

$stmt = $conn->prepare("DELETE FROM return_reasons WHERE order_id = ?");
$stmt->bind_param("s", $order_id);
$stmt->execute();

echo "OK";
?>
