<?php 


include "db.php"; 

if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];

    // Use order_id instead of id
    $sql = "DELETE FROM orders WHERE order_id='$order_id'";

    if ($conn->query($sql) === TRUE) {
        header("Location: view_orders.php");
        exit;
    } else {
        echo "Error deleting record: " . $conn->error;
    }
} else {
    echo "Invalid request";
}
?>

