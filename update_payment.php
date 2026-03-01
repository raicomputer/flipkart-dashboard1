<?php
require_once 'db.php';
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id      = (int)($_POST['id'] ?? 0);
    $date    = $_POST['received_date'] ?? '';
    $amount  = (float)($_POST['received_amount'] ?? 0);
    $remarks = $_POST['remarks'] ?? '';

    if ($id > 0 && $date !== '' && $amount > 0) {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE received_payments
             SET received_date = ?, received_amount = ?, remarks = ?
             WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "sdsi",
            $date,
            $amount,
            $remarks,
            $id
        );

        if (mysqli_stmt_execute($stmt)) {
            $response['success'] = true;
        }

        mysqli_stmt_close($stmt);
    }
}

echo json_encode($response);

