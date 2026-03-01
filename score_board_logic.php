<?php
// ================= SAFETY CHECK =================
if (!isset($conn)) {
    die("DB connection missing");
}

// ================= MONTH SETUP =================
$month = $_GET['month'] ?? date('m');
$year  = $_GET['year']  ?? date('Y');

$startDate = "$year-$month-01";
$endDate   = date("Y-m-t", strtotime($startDate));

// ================= HELPERS =================
function getCount($conn, $sql)
{
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_row($result);
    return $row[0] ?? 0;
}

function getSum($conn, $sql)
{
    $res = mysqli_query($conn, $sql);
    if (!$res) return 0;
    $row = mysqli_fetch_row($res);
    return $row[0] ?? 0;
}

// ================= METRICS (SAFE MODE) =================

// 1️⃣ Total Orders
$total_orders = getCount(
    $conn,
    "SELECT COUNT(*) FROM orders
     WHERE order_date BETWEEN '$startDate' AND '$endDate'"
);

// 2️⃣ Dispatched
$dispatched = getCount(
    $conn,
    "SELECT COUNT(*) FROM dispatch
     WHERE order_date BETWEEN '$startDate' AND '$endDate'"
);

// 3️⃣ Deliverd
$delivered_orders = getCount(
    $conn,
    "SELECT COUNT(*) FROM delivered_orders
     WHERE order_date BETWEEN '$startDate' AND '$endDate'"
);

// 3️⃣ Gross Order Value---may deleted
$gross_order_value = getSum(
    $conn,
    "SELECT SUM(gross_price) FROM orders
     WHERE order_date BETWEEN '$startDate' AND '$endDate'"
);

// 4️⃣ Customer Returns (order_type based)
$customer_returns = getCount(
    $conn,
    "SELECT COUNT(*) FROM customer_returns
     WHERE order_date BETWEEN '$startDate' AND '$endDate'"
);

// 5️⃣ Courier Returns
$returned_orders = getCount(
    $conn,
    "SELECT COUNT(*) FROM returned_orders
    WHERE order_date BETWEEN '$startDate' AND '$endDate'"
);

// 6️⃣ Total Returns
$total_returns = $customer_returns + $returned_orders;

// 6️⃣=7 Closed Successfully
$inward_to_shop = getCount(
    $conn,
    "SELECT COUNT(*) FROM inward_to_shop
    WHERE order_date BETWEEN '$startDate' AND '$endDate'"
);

// 6️⃣=8 Net Loss
// code missing

// 6️⃣=9 Gross Dispatched Value
$gross_dispatched_value = getSum(
    $conn,
    "SELECT SUM(gross_price) FROM orders
     WHERE order_date BETWEEN '$startDate' AND '$endDate'"
);

// 6️⃣= 10 SPF Claims
$spf_claims = getCount(
    $conn,
    "SELECT COUNT(*) FROM spf_claims 
    WHERE order_date BETWEEN '$startDate' AND '$endDate'"
);

// 6️⃣= 11 Recovered via SPF
// code missing

// 6️⃣= 12 Created Amount
$created_amount = getSum(
    $conn,
    "SELECT SUM(gross_price) FROM inward_to_shop
     WHERE order_date BETWEEN '$startDate' AND '$endDate'"
);

// 6️⃣= 13 Received Amount
$received_amount = getSum(
    $conn,
    "SELECT SUM(received_amount)
     FROM received_payments
     WHERE received_date BETWEEN '$startDate' AND '$endDate'"
);


// 6️⃣= 14 Total Net Profit
// code missing

$net_loss = max(($gross_dispatched_value - $received_amount), 0);
$net_profit = $received_amount - $gross_dispatched_value;
$disc_amount = $created_amount - $received_amount;
$disc_display_value = -1 * $disc_amount;
$disc_display = ($disc_display_value > 0 ? '+' : '') . number_format($disc_display_value, 2);
$disc_class = $disc_display_value >= 0 ? 'text-success' : 'text-danger';


//Monthely Perfomance score start

$delivery_ratio = ($dispatched > 0)
    ? ($delivered_orders / $dispatched)
    : 0;

$delivery_score = round($delivery_ratio * 30);


$return_ratio = ($dispatched > 0)
    ? ($total_returns / $dispatched)
    : 0;

$return_score = round((1 - $return_ratio) * 20);
$return_score = max(0, $return_score);


$recovery_ratio = ($gross_dispatched_value > 0)
    ? ($received_amount / $gross_dispatched_value)
    : 0;

$recovery_score = round($recovery_ratio * 25);


$payment_ratio = ($created_amount > 0)
    ? ($received_amount / $created_amount)
    : 0;

$payment_score = round($payment_ratio * 15);




$closure_ratio = ($total_orders > 0)
    ? ($inward_to_shop / $total_orders)
    : 0;

$closure_score = round($closure_ratio * 10);



$performance_score =
    $delivery_score +
    $return_score +
    $recovery_score +
    $payment_score +
    $closure_score;

// Safety clamp
$performance_score = min(100, max(0, $performance_score));



if ($performance_score >= 85) {
    $status = "Excellent";
    $badge = "success";
} elseif ($performance_score >= 70) {
    $status = "Good";
    $badge = "primary";
} elseif ($performance_score >= 50) {
    $status = "Average";
    $badge = "warning";
} else {
    $status = "Critical";
    $badge = "danger";
}

//End of Monthely Perfomance score 


// ================= PLACEHOLDERS =================
// (future tables will feed these)
//$net_loss = 0;
//$net_profit = 0;
//$received_amount = 0;
//$created_amount = 0;
//$Recovered_Via_SPF = 0;
