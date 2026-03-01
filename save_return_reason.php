<?php

require 'db.php'; // your existing connection handeler for return reason

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $order_id    = $_POST['order_id'] ?? '';
    $return_type = $_POST['return_type'] ?? '';
    $reason      = $_POST['reason'] ?? '';
    $remark      = $_POST['remark'] ?? '';

    if (!$order_id || !$return_type || !$reason) {
        http_response_code(400);
        exit('Invalid data');
    }

    $stmt = $conn->prepare("
        INSERT INTO return_reasons (order_id, return_type, reason_code, remark)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            reason_code = VALUES(reason_code),
            remark = VALUES(remark)
    ");

    $stmt->bind_param("ssss", $order_id, $return_type, $reason, $remark);
    $stmt->execute();
    $stmt->close();

    echo "OK";
}
// end handeler for return reason

?>
