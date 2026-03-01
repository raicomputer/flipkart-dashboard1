<?php
// view_orders.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "seller_db1";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

include 'score_board_logic.php';
//include 'save_return_reason.php';

// Start  filter session================================================= 


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Read filter from GET
if (isset($_GET['month_year'])) {
    $candidate = $_GET['month_year'];
    if (preg_match('/^\d{4}-\d{2}$/', $candidate)) {
        $_SESSION['filter_month'] = $candidate;
    }
}

// Clear filter
if (isset($_GET['clear_filter'])) {
    unset($_SESSION['filter_month']);
    header("Location: view_orders.php");
    exit;
}

// Final selected month
$filter_month = $_SESSION['filter_month'] ?? '';

// Single WHERE clause for ALL 8 tables
$whereFilter = "";
if (!empty($filter_month)) {
    $safeMonth = $conn->real_escape_string($filter_month);
    $whereFilter = "WHERE DATE_FORMAT(order_date, '%Y-%m') = '$safeMonth'";
}

// End of filter session================================================= 

// --- Queries for ALL 8 tables ---
$result_orders    = $conn->query("SELECT * FROM orders $whereFilter ORDER BY sr_no ASC");
$result_dispatch  = $conn->query("SELECT * FROM dispatch $whereFilter ORDER BY sr_no ASC");
$result_delivered = $conn->query("SELECT * FROM delivered_orders $whereFilter ORDER BY sr_no ASC");
$result_returned  = $conn->query("SELECT * FROM returned_orders $whereFilter ORDER BY sr_no ASC");

$result_payment    = $conn->query("SELECT * FROM payments $whereFilter ORDER BY sr_no ASC");
$result_custreturn = $conn->query("SELECT * FROM customer_returns $whereFilter ORDER BY sr_no ASC");
$result_spf        = $conn->query("SELECT * FROM spf_claims $whereFilter ORDER BY sr_no ASC");
$result_inward     = $conn->query("SELECT * FROM inward_to_shop $whereFilter ORDER BY sr_no ASC");

$result_custreturn = $conn->query("
    SELECT cr.*,
           rr.return_type, 
           rr.reason_code,
           rr.remark
    FROM customer_returns cr
    LEFT JOIN return_reasons rr
      ON rr.order_id = cr.order_id
     AND rr.return_type = 'customer'
    $whereFilter
    ORDER BY cr.sr_no ASC
");


$result_returned = $conn->query("
    SELECT ro.*,
           rr.reason_code,
           rr.remark
    FROM returned_orders ro
    LEFT JOIN return_reasons rr
      ON rr.order_id = ro.order_id
     AND rr.return_type = 'courier'
    $whereFilter
    ORDER BY ro.sr_no ASC
");



$result_spf = $conn->query("
    SELECT s.*,

           COALESCE(
               rr_courier.return_type,
               rr_customer.return_type
           ) AS return_type,

           COALESCE(
               rr_courier.reason_code,
               rr_customer.reason_code
           ) AS reason_code,

           COALESCE(
               rr_courier.remark,
               rr_customer.remark
           ) AS remark

    FROM spf_claims s

    LEFT JOIN return_reasons rr_courier
           ON rr_courier.order_id = s.order_id
          AND rr_courier.return_type = 'courier'

    LEFT JOIN return_reasons rr_customer
           ON rr_customer.order_id = s.order_id
          AND rr_customer.return_type = 'customer'

    $whereFilter
    ORDER BY s.sr_no ASC
");




$result_inward = $conn->query("
    SELECT i.*,

           COALESCE(rr_courier.reason_code, rr_customer.reason_code) AS reason_code,
           COALESCE(rr_courier.remark, rr_customer.remark) AS remark,

           CASE
               WHEN rr_courier.order_id IS NOT NULL THEN 'courier'
               WHEN rr_customer.order_id IS NOT NULL THEN 'customer'
               ELSE NULL
           END AS return_type

    FROM inward_to_shop i

    LEFT JOIN return_reasons rr_courier
           ON rr_courier.order_id = i.order_id
          AND rr_courier.return_type = 'courier'

    LEFT JOIN return_reasons rr_customer
           ON rr_customer.order_id = i.order_id
          AND rr_customer.return_type = 'customer'

    $whereFilter
    ORDER BY i.sr_no ASC
");


// debug (optional)
if ($result_spf === false) {
    error_log("SPF query failed: " . $conn->error);
}

//HAndeler for bank credit

if (isset($_POST['save_received'])) {

    $sr_no            = (int) $_POST['sr_no'];   // ✅ ADD THIS
    $received_date    = $_POST['received_date'];
    $received_amount  = (float) $_POST['received_amount'];
    $remarks          = $_POST['remarks'] ?? null;

    $stmt = $conn->prepare(
        "INSERT INTO received_payments
         (sr_no, received_date, received_amount, remarks)
         VALUES (?, ?, ?, ?)"
    );

    // i = INT (sr_no)
    // s = STRING (date)
    // d = DOUBLE (amount)
    // s = STRING (remarks)
    $stmt->bind_param(
        "isds",
        $sr_no,
        $received_date,
        $received_amount,
        $remarks
    );

    $stmt->execute();
    $stmt->close();

    // 🔴 VERY IMPORTANT
    header("Location: " . $_SERVER['PHP_SELF'] . "?history=open");
    exit;
}
//End HAndeler for bank credit


// --- Table 1: Pending Orders Actions OK ----------------------------------------------------------------
// --- 1. Dispatch Order (Move from orders ➝ dispatch) ---
if (isset($_GET['dispatch_id'])) {
    $dispatch_id = intval($_GET['dispatch_id']);

    // Fetch order data from 'orders'
    $orderRes = $conn->prepare("SELECT * FROM orders WHERE sr_no = ?");
    $orderRes->bind_param("i", $dispatch_id);
    $orderRes->execute();
    $result = $orderRes->get_result();

    if ($result && $result->num_rows > 0) {
        $order = $result->fetch_assoc();

        // ✅ Insert into dispatch (same column structure)
        $stmt = $conn->prepare("
            INSERT INTO dispatch 
            (sr_no, order_id, order_date, order_type, product_name, quantity, gross_price, item_picture, customer_name, party_location, dispatch_date )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "isssssdsss",
            $order['sr_no'],
            $order['order_id'],
            $order['order_date'],
            $order['order_type'],
            $order['product_name'],
            $order['quantity'],
            $order['gross_price'],
            $order['item_picture'],
            $order['customer_name'],
            $order['party_location']
        );
        $stmt->execute();
        $stmt->close();

        // ✅ Delete from 'orders'
        //$delete = $conn->prepare("DELETE FROM orders WHERE sr_no = ?");
        // $delete->bind_param("i", $dispatch_id);
        //$delete->execute();
        // $delete->close();


    }

    header("Location: view_orders.php");
    exit;
}

// --- End of Table 1: Pending Orders Actions ---======================================================



// --- Table 2: Dispatched Orders Actions -----------------------------------------------------------

// 1️⃣ Dispatch → Delivered Orders
if (isset($_GET['deliver_dispatch_id'])) {
    $dispatch_sr_no = (int) $_GET['deliver_dispatch_id'];

    // Fetch order from dispatch
    $res = $conn->prepare("SELECT * FROM dispatch WHERE sr_no = ?");
    $res->bind_param("i", $dispatch_sr_no);
    $res->execute();
    $result = $res->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Insert into delivered_orders
        $stmt = $conn->prepare("
            INSERT INTO delivered_orders
            (
                sr_no,
                order_id,
                order_date,
                order_type,
                product_name,
                quantity,
                gross_price,
                item_picture,
                customer_name,
                party_location,
                delivered_date
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "issssidsss",
            $row['sr_no'],
            $row['order_id'],
            $row['order_date'],
            $row['order_type'],
            $row['product_name'],
            $row['quantity'],
            $row['gross_price'],
            $row['item_picture'],
            $row['customer_name'],
            $row['party_location']
        );

        $stmt->execute();
        $stmt->close();

        // Remove from dispatch
        $del = $conn->prepare("DELETE FROM dispatch WHERE sr_no = ?");
        $del->bind_param("i", $dispatch_sr_no);
        $del->execute();
        $del->close();
    }

    header("Location: view_orders.php");
    exit;
}


// 2️⃣ Reverse Dispatch → Pending Orders
if (isset($_GET['reverse_dispatch_id'])) {
    $dispatch_id = intval($_GET['reverse_dispatch_id']);

    // Delete from dispatch (T2 table)
    $del = $conn->prepare("DELETE FROM dispatch WHERE sr_no = ?");
    $del->bind_param("i", $dispatch_id);
    $del->execute();
    $del->close();

    // Nothing else needed – T1 already has original data
    header("Location: view_orders.php?rev=1");

    exit;
}


// 3️⃣ Dispatch → Courier Return OK

if (isset($_GET['returned_id'])) {
    $dispatch_id = intval($_GET['returned_id']);

    // Fetch full record from dispatch table
    $res = $conn->prepare("SELECT * FROM dispatch WHERE sr_no = ?");
    $res->bind_param("i", $dispatch_id);
    $res->execute();
    $result = $res->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Insert into returned_orders (including original sr_no + returned_date)
        $stmt = $conn->prepare("
            INSERT INTO returned_orders 
            (sr_no, order_id, order_date, order_type, product_name, quantity, gross_price, item_picture, customer_name, party_location, returned_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        // ✅ Note correct types: i = int, s = string, d = decimal
        $stmt->bind_param(
            "isssssdsss",
            $row['sr_no'],
            $row['order_id'],
            $row['order_date'],
            $row['order_type'],
            $row['product_name'],
            $row['quantity'],
            $row['gross_price'],
            $row['item_picture'],
            $row['customer_name'],
            $row['party_location']
        );

        $stmt->execute();
        $stmt->close();

        // Delete from dispatch
        $del = $conn->prepare("DELETE FROM dispatch WHERE sr_no = ?");
        $del->bind_param("i", $dispatch_id);
        $del->execute();
        $del->close();
    }

    header("Location: view_orders.php");
    exit;
}

// --- End of Table 2: Dispatched Orders Actions ---==================================================

// --- Table 3: Returned Orders / Courier Return Actions ----------------------------------------------

// --- one = Move Delivered Order to Payments (Mark as Paid) ---
// ============================
// Move Delivered → Payments OK
// ============================
if (isset($_GET['payment_id'])) {
    $sr_no = intval($_GET['payment_id']);

    $res = $conn->prepare("SELECT * FROM delivered_orders WHERE sr_no = ?");
    $res->bind_param("i", $sr_no);
    $res->execute();
    $result = $res->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Insert into payments
        $stmt = $conn->prepare("
            INSERT INTO payments 
            (order_id, order_date, order_type, product_name, quantity, gross_price, item_picture, customer_name, party_location, payments_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "sssssdsss",
            $row['order_id'],
            $row['order_date'],
            $row['order_type'],
            $row['product_name'],
            $row['quantity'],
            $row['gross_price'],
            $row['item_picture'],
            $row['customer_name'],
            $row['party_location']
        );
        $stmt->execute();
        $stmt->close();

        // Delete from delivered_orders
        $del = $conn->prepare("DELETE FROM delivered_orders WHERE sr_no = ?");
        $del->bind_param("i", $sr_no);
        $del->execute();
        $del->close();
    }

    header("Location: view_orders.php");
    exit;
}

// ============================
// Mark Payment (Manual Button) OK
// ============================
if (isset($_POST['mark_payment'])) {
    $delivered_id = intval($_POST['delivered_id']);
    $amount_received = floatval($_POST['amount_received']); // Entered payment amount

    // Fetch row from delivered_orders
    $res = $conn->prepare("SELECT * FROM delivered_orders WHERE sr_no = ?");
    $res->bind_param("i", $delivered_id);
    $res->execute();
    $result = $res->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Insert into payments with original sr_no but updated amount
        $stmt = $conn->prepare("
            INSERT INTO payments 
            (sr_no, order_id, order_date, order_type, product_name, quantity, gross_price, item_picture, customer_name, party_location, payments_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "issssidsss",
            $row['sr_no'],          // Preserve original sr_no
            $row['order_id'],
            $row['order_date'],
            $row['order_type'],
            $row['product_name'],
            $row['quantity'],
            $amount_received,       // <-- Use the entered amount here
            $row['item_picture'],
            $row['customer_name'],
            $row['party_location']
        );
        $stmt->execute();
        $stmt->close();

        // Delete from delivered_orders
        $del = $conn->prepare("DELETE FROM delivered_orders WHERE sr_no = ?");
        $del->bind_param("i", $delivered_id);
        $del->execute();
        $del->close();
    }

    header("Location: view_orders.php");
    exit;
}

// --- two = Move Delivered Order to Customer Returns (Mark as Returned) OK ---
if (isset($_GET['cust_return_id'])) {
    $sr_no = intval($_GET['cust_return_id']);

    // Fetch the delivered order
    $res = $conn->prepare("SELECT * FROM delivered_orders WHERE sr_no = ?");
    $res->bind_param("i", $sr_no);
    $res->execute();
    $result = $res->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        $item_picture = $row['item_picture'] ?? '';

        // ✅ Correct INSERT query with proper parentheses and preserved SR No
        $stmt = $conn->prepare("
            INSERT INTO customer_returns (
                sr_no, order_id, order_date, order_type,
                product_name, quantity, gross_price, item_picture,
                customer_name, party_location
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            die('Prepare failed: ' . $conn->error);
        }

        // ✅ Bind types: i = int, s = string, d = double
        $stmt->bind_param(
            "isssssdsss",
            $row['sr_no'],          // Preserve same SR number
            $row['order_id'],
            $row['order_date'],
            $row['order_type'],
            $row['product_name'],
            $row['quantity'],
            $row['gross_price'],
            $item_picture,
            $row['customer_name'],
            $row['party_location']
        );

        if (!$stmt->execute()) {
            die('Execute failed: ' . $stmt->error);
        }

        $stmt->close();

        // Delete the original record
        $del = $conn->prepare("DELETE FROM delivered_orders WHERE sr_no = ?");
        $del->bind_param("i", $sr_no);
        $del->execute();
        $del->close();

        session_start();
        $_SESSION['success_msg'] = "✅ Order moved to Customer Returns successfully (SR No preserved).";
    }

    header("Location: view_orders.php");
    exit;
}


// --- Reverse Delivered Order back to Dispatch ---

// --- Reverse Delivered Order back to Dispatch ---
// Reverse Delivered → Dispatch OK
if (isset($_GET['reverse_delivered_id'])) {
    $reverse_id = intval($_GET['reverse_delivered_id']);

    // ✅ Use sr_no instead of id
    $res = $conn->query("SELECT * FROM delivered_orders WHERE sr_no=$reverse_id");

    if ($res && $res->num_rows > 0) {
        $order = $res->fetch_assoc();

        // ✅ Insert back into dispatch table (matching existing structure)
        $stmt = $conn->prepare("
            INSERT INTO dispatch (
                sr_no, order_id, order_date, order_type,
                product_name, quantity, gross_price, item_picture,
                customer_name, party_location
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isssssdsss",
            $order['sr_no'],
            $order['order_id'],
            $order['order_date'],
            $order['order_type'],
            $order['product_name'],
            $order['quantity'],
            $order['gross_price'],
            $order['item_picture'],
            $order['customer_name'],
            $order['party_location']
        );

        $stmt->execute();
        $stmt->close();

        // ✅ Delete from delivered_orders
        $conn->query("DELETE FROM delivered_orders WHERE sr_no=$reverse_id");
    }

    header("Location: view_orders.php");
    exit;
}


// --- End of Table 3: Returned Orders / Courier Return Actions ---================================

// --- Table 4: Manual Payment & Return Received Actions ------------------------------------------

// --- two= Edit Payment Amount OK ---

if (isset($_POST['edit_payment'])) {
    $sr_no = intval($_POST['sr_no']); // ✅ use sr_no not id
    $new_amount = floatval($_POST['new_amount']);

    $stmt = $conn->prepare("UPDATE payments SET gross_price=? WHERE sr_no=?");
    $stmt->bind_param("di", $new_amount, $sr_no);
    $stmt->execute();

    $stmt->close();

    echo "<script>alert('Payment updated successfully!');window.location='view_orders.php';</script>";
}

// --- one = Move Dispatch Order to Returned Order NOT REQ---
if (isset($_GET['returned_id'])) {
    $id = intval($_GET['returned_id']);
    $res = $conn->query("SELECT * FROM dispatch WHERE id=$id");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();

        $stmt = $conn->prepare("
            INSERT INTO returned_orders (order_id, customer_name, price, order_date, returned_date)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("ssds", $row['order_id'], $row['customer_name'], $row['price'], $row['order_date']);
        $stmt->execute();
        $stmt->close();

        $conn->query("DELETE FROM dispatch WHERE id=$id");
    }

    header("Location: view_orders.php");
    exit;
}

// --- two = Move Returned Orders to Inward To Shop OK---
// --- Move Payment Record to Inward To Shop ---
if (isset($_GET['inward_payment_id'])) {
    $src_sr_no = intval($_GET['inward_payment_id']);

    // Step 1: Fetch record from payments table
    $res = $conn->prepare("SELECT * FROM payments WHERE sr_no = ?");
    $res->bind_param("i", $src_sr_no);
    $res->execute();
    $result = $res->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Step 2: Extract data safely
        $sr_no          = $row['sr_no'] ?? 0;
        $order_id       = $row['order_id'] ?? '';
        $order_date     = $row['order_date'] ?? '';
        $order_type     = $row['order_type'] ?? '';
        $product_name   = $row['product_name'] ?? '';
        $quantity       = isset($row['quantity']) ? intval($row['quantity']) : 0;
        $gross_price    = isset($row['gross_price']) ? floatval($row['gross_price']) : 0.00;
        $item_picture   = $row['item_picture'] ?? '';
        $customer_name  = $row['customer_name'] ?? '';
        $party_location = $row['party_location'] ?? '';

        // Step 3: Insert into inward_to_shop (explicitly including sr_no)
        $stmt = $conn->prepare("
            INSERT INTO inward_to_shop (
                sr_no,
                order_id,
                order_date,
                order_type,
                product_name,
                quantity,
                gross_price,
                item_picture,
                customer_name,
                party_location,
                inward_date
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "isssssdsss",
            $sr_no,
            $order_id,
            $order_date,
            $order_type,
            $product_name,
            $quantity,
            $gross_price,
            $item_picture,
            $customer_name,
            $party_location
        );

        // Step 4: Execute insert + delete from source
        if ($stmt->execute()) {
            $delete_stmt = $conn->prepare("DELETE FROM payments WHERE sr_no = ?");
            $delete_stmt->bind_param("i", $src_sr_no);
            $delete_stmt->execute();
            $delete_stmt->close();
        }

        $stmt->close();
    }

    $res->close();

    // Step 5: Redirect
    header("Location: view_orders.php");
    exit;
}


// --- Reverse Payment: Move from payments back to delivered_orders OK---
// ============================
// Reverse Payment to Delivered Orders
// ============================
if (isset($_GET['reverse_payment_id'])) {
    $sr_no = intval($_GET['reverse_payment_id']);

    // 1️⃣ Fetch from payments
    $res = $conn->prepare("SELECT * FROM payments WHERE sr_no = ?");
    $res->bind_param("i", $sr_no);
    $res->execute();
    $result = $res->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $res->close();

        // 2️⃣ Prepare values
        $order_id       = $row['order_id'] ?? '';
        $order_date     = $row['order_date'] ?? '';
        $order_type     = $row['order_type'] ?? '';
        $product_name   = $row['product_name'] ?? '';
        $quantity       = isset($row['quantity']) ? intval($row['quantity']) : 0;
        $gross_price    = isset($row['gross_price']) ? floatval($row['gross_price']) : 0.00;
        $item_picture   = !empty($row['item_picture']) ? $row['item_picture'] : '';
        $customer_name  = $row['customer_name'] ?? '';
        $party_location = $row['party_location'] ?? '';

        // 3️⃣ Insert back into delivered_orders
        $stmt = $conn->prepare("
            INSERT INTO delivered_orders (
                sr_no,
                order_id,
                order_date,
                order_type,
                product_name,
                quantity,
                gross_price,
                item_picture,
                customer_name,
                party_location
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "isssssdsss",
            $row['sr_no'],        // Keep original SR No
            $order_id,
            $order_date,
            $order_type,
            $product_name,
            $quantity,
            $gross_price,
            $item_picture,
            $customer_name,
            $party_location
        );
        $stmt->execute();
        $stmt->close();

        // 4️⃣ Delete from payments
        $del = $conn->prepare("DELETE FROM payments WHERE sr_no = ?");
        $del->bind_param("i", $sr_no);
        $del->execute();
        $del->close();
    }

    // 5️⃣ Redirect after success
    header("Location: view_orders.php");
    exit;
}

// --- End of Table 4: Manual Payment & Return Received Actions ----======================================

// --- Table 5: Customer Returns Actions ---------------------------------------------------

// --- one = Edit Return Amount ---
// ---------- 1. Edit Return Amount NOT REQ----------
if (isset($_POST['edit_return'])) {
    $id = $_POST['return_id'];
    $new_amount = $_POST['new_return_amount'];

    $update = $conn->prepare("UPDATE customer_returns SET gross_price=? WHERE sr_no=?");
    $update->bind_param("di", $new_amount, $id);

    if ($update->execute()) {
        // Use session message to show alert after redirect
        session_start();
        $_SESSION['success_msg'] = "✅ Return amount updated successfully!";
        header("Location: view_orders.php");
        exit;
    } else {
        session_start();
        $_SESSION['error_msg'] = "❌ Failed to update return amount.";
        header("Location: view_orders.php");
        exit;
    }
}


// --- two = Move Customer Return to Inward To Shop OK---
//---------- 2. Inward Return (Move from customer_returns → inward_to_shop) ----------
if (isset($_GET['inward_return_id'])) {
    $id = intval($_GET['inward_return_id']);

    // Step 1: Fetch the record from customer_returns
    $result = $conn->query("SELECT * FROM customer_returns WHERE sr_no = $id");
    if ($row = $result->fetch_assoc()) {

        // Step 2: Insert into inward_to_shop with same sr_no (no AUTO_INCREMENT)
        $stmt = $conn->prepare("
            INSERT INTO inward_to_shop (
                sr_no, order_id, order_date, order_type, product_name,
                quantity, gross_price, item_picture, customer_name,
                party_location, inward_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "isssssdsss",
            $row['sr_no'],
            $row['order_id'],
            $row['order_date'],
            $row['order_type'],
            $row['product_name'],
            $row['quantity'],
            $row['gross_price'],
            $row['item_picture'],
            $row['customer_name'],
            $row['party_location']
        );
        $stmt->execute();
        $stmt->close();

        // Step 3: Delete the record from customer_returns
        $conn->query("DELETE FROM customer_returns WHERE sr_no = $id");

        // Step 4: Redirect with success message
        session_start();
        $_SESSION['success_msg'] = "✅ Return moved to Inward To Shop successfully (SR No preserved).";
        header("Location: view_orders.php");
        exit;
    } else {
        echo "<script>alert('❌ Record not found in Customer Returns!');</script>";
    }
}


// --- three = Move Customer Return to SPF Claims OK ---
// ---------- 3. SPF Claim (Move from customer_returns → spf_claims) ----------
if (isset($_GET['spf_claim_id'])) {
    $id = intval($_GET['spf_claim_id']);

    // Step 1: Fetch the record from customer_returns
    $result = $conn->query("SELECT * FROM customer_returns WHERE sr_no = $id");
    if ($row = $result->fetch_assoc()) {

        // Step 2: Insert into spf_claims (use 'claim_date' column)
        $stmt = $conn->prepare("
            INSERT INTO spf_claims (
                sr_no, order_id, order_date, order_type, product_name,
                quantity, gross_price, item_picture, customer_name,
                party_location, claim_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "isssssdsss",
            $row['sr_no'],
            $row['order_id'],
            $row['order_date'],
            $row['order_type'],
            $row['product_name'],
            $row['quantity'],
            $row['gross_price'],
            $row['item_picture'],
            $row['customer_name'],
            $row['party_location']
        );
        $stmt->execute();
        $stmt->close();

        // Step 3: Delete from customer_returns after transfer
        $conn->query("DELETE FROM customer_returns WHERE sr_no = $id");

        // Step 4: Redirect with message
        session_start();
        $_SESSION['success_msg'] = "✅ Record moved to SPF Claims successfully (SR No preserved).";
        header("Location: view_orders.php");
        exit;
    } else {
        echo "<script>alert('❌ Record not found in Customer Returns!');</script>";
    }
}


// --- FOUR = Reverse FROM T5(Customer Return) TO T3(Delivered Orders)  OK---
// --- Reverse Customer Return back to Delivered Orders ---
if (isset($_GET['reverse1_returned_id'])) {
    $sr_no = intval($_GET['reverse1_returned_id']);

    // Fetch from customer_returns using sr_no
    $res = $conn->prepare("SELECT * FROM customer_returns WHERE sr_no = ?");
    $res->bind_param("i", $sr_no);
    $res->execute();
    $result = $res->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Use order_date if exists, else fallback to today
        $order_date = !empty($row['order_date']) ? $row['order_date'] : date('Y-m-d');

        // ✅ Insert back into delivered_orders
        $stmt = $conn->prepare("
            INSERT INTO delivered_orders (
                sr_no, order_id, order_date, order_type,
                product_name, quantity, gross_price, item_picture,
                customer_name, party_location
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param(
            "isssssdsss",
            $row['sr_no'],
            $row['order_id'],
            $order_date,
            $row['order_type'],
            $row['product_name'],
            $row['quantity'],
            $row['gross_price'],
            $row['item_picture'],
            $row['customer_name'],
            $row['party_location']
        );

        if (!$stmt->execute()) {
            die("Execute failed: " . $stmt->error);
        }

        $stmt->close();

        // ✅ Delete the record from customer_returns
        $del = $conn->prepare("DELETE FROM customer_returns WHERE sr_no = ?");
        $del->bind_param("i", $sr_no);
        $del->execute();
        $del->close();

        session_start();
        $_SESSION['success_msg'] = "✅ Record moved back to Delivered Orders successfully!";
    }

    header("Location: view_orders.php");
    exit;
}

// --- End of Table 5: Customer Returns Actions ---=====================================================

// --- Table 6: Payments Actions ------------------------------------------------------------

// --- one = Manual Payment Entry NOT REQ ---
if (isset($_POST['mark_payment'])) {
    $delivered_id   = intval($_POST['delivered_id']);
    $order_id       = $_POST['order_id'];
    $customer_name  = $_POST['customer_name'];
    $amount         = $_POST['amount_received'];
    $delivered_date = $_POST['delivered_date'];
    $payment_date   = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("
        INSERT INTO payments (order_id, customer_name, price, delivered_date, payment_date)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssdss", $order_id, $customer_name, $amount, $delivered_date, $payment_date);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM delivered_orders WHERE id=?");
    $stmt->bind_param("i", $delivered_id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Payment recorded successfully!');window.location='view_orders.php';</script>";
}


// --- two = Reverse Payment (Back to Delivered Orders) NOT REQ ---
if (isset($_GET['reverse_id'])) {
    $id = intval($_GET['reverse_id']);
    $res = $conn->query("SELECT * FROM payments WHERE id=$id");

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();

        $stmt = $conn->prepare("
            INSERT INTO delivered_orders (order_id, customer_name, price, order_date)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssds", $row['order_id'], $row['customer_name'], $row['price'], $row['delivered_date']);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM payments WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: view_orders.php");
    exit;
}

// --- three = Move Payment to Inward To Shop ---


// --- MOVE INWARD RETURN HANDLER AT TOP OF FILE OK ---

if (isset($_GET['inward_returned_id'])) {
    $src_sr_no = intval($_GET['inward_returned_id']);

    // Step 1: Fetch record from payments table
    $res = $conn->prepare("SELECT * FROM returned_orders WHERE sr_no = ?");
    $res->bind_param("i", $src_sr_no);
    $res->execute();
    $result = $res->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Step 2: Extract data safely
        $sr_no          = $row['sr_no'] ?? 0;
        $order_id       = $row['order_id'] ?? '';
        $order_date     = $row['order_date'] ?? '';
        $order_type     = $row['order_type'] ?? '';
        $product_name   = $row['product_name'] ?? '';
        $quantity       = isset($row['quantity']) ? intval($row['quantity']) : 0;
        $gross_price    = isset($row['gross_price']) ? floatval($row['gross_price']) : 0.00;
        $item_picture   = $row['item_picture'] ?? '';
        $customer_name  = $row['customer_name'] ?? '';
        $party_location = $row['party_location'] ?? '';

        // Step 3: Insert into inward_to_shop (explicitly including sr_no)
        $stmt = $conn->prepare("
            INSERT INTO inward_to_shop (
                sr_no,
                order_id,
                order_date,
                order_type,
                product_name,
                quantity,
                gross_price,
                item_picture,
                customer_name,
                party_location,
                inward_date
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "isssssdsss",
            $sr_no,
            $order_id,
            $order_date,
            $order_type,
            $product_name,
            $quantity,
            $gross_price,
            $item_picture,
            $customer_name,
            $party_location
        );

        // Step 4: Execute insert + delete from source
        if ($stmt->execute()) {
            $delete_stmt = $conn->prepare("DELETE FROM returned_orders WHERE sr_no = ?");
            $delete_stmt->bind_param("i", $src_sr_no);
            $delete_stmt->execute();
            $delete_stmt->close();
        }

        $stmt->close();
    }

    $res->close();

    // Step 5: Redirect
    header("Location: view_orders.php");
    exit;
}

// --- three = Move Returned Orders to SPF Claims  OK---
if (isset($_GET['spf_returned_id'])) {
    $id = intval($_GET['spf_returned_id']);

    // Step 1: Fetch the record from customer_returns
    $result = $conn->query("SELECT * FROM returned_orders WHERE sr_no = $id");
    if ($row = $result->fetch_assoc()) {

        // Step 2: Insert into spf_claims (use 'claim_date' column)
        $stmt = $conn->prepare("
            INSERT INTO spf_claims (
                sr_no, order_id, order_date, order_type, product_name,
                quantity, gross_price, item_picture, customer_name,
                party_location, claim_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "isssssdsss",
            $row['sr_no'],
            $row['order_id'],
            $row['order_date'],
            $row['order_type'],
            $row['product_name'],
            $row['quantity'],
            $row['gross_price'],
            $row['item_picture'],
            $row['customer_name'],
            $row['party_location']
        );
        $stmt->execute();
        $stmt->close();

        // Step 3: Delete from returned_orders after transfer
        $conn->query("DELETE FROM returned_orders WHERE sr_no = $id");

        // Step 4: Redirect with message
        session_start();
        $_SESSION['success_msg'] = "✅ Record moved to SPF Claims successfully (SR No preserved).";
        header("Location: view_orders.php");
        exit;
    } else {
        echo "<script>alert('❌ Record not found in Customer Returns!');</script>";
    }
}


// --- four = Reverse Returned Orders (back to Dispatch) OK ---
if (isset($_GET['reverse_returned_id'])) {
    $reverse_id = intval($_GET['reverse_returned_id']);

    // ✅ Use sr_no instead of id
    $res = $conn->query("SELECT * FROM returned_orders WHERE sr_no=$reverse_id");

    if ($res && $res->num_rows > 0) {
        $order = $res->fetch_assoc();

        // ✅ Insert back into dispatch table (matching existing structure)
        $stmt = $conn->prepare("
            INSERT INTO dispatch (
                sr_no, order_id, order_date, order_type,
                product_name, quantity, gross_price, item_picture,
                customer_name, party_location
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isssssdsss",
            $order['sr_no'],
            $order['order_id'],
            $order['order_date'],
            $order['order_type'],
            $order['product_name'],
            $order['quantity'],
            $order['gross_price'],
            $order['item_picture'],
            $order['customer_name'],
            $order['party_location']
        );

        $stmt->execute();
        $stmt->close();

        // ✅ Delete from delivered_orders
        $conn->query("DELETE FROM returned_orders WHERE sr_no=$reverse_id");
    }

    header("Location: view_orders.php");
    exit;
}

// --- four= Handle Edit Price in Returned Orders (Table 6) OK ---
if (isset($_POST['edit_returned_id']) && isset($_POST['new_price'])) {
    $edit_sr_no = intval($_POST['edit_returned_id']);
    $new_price = floatval($_POST['new_price']);

    // ✅ Use sr_no instead of id
    $stmt = $conn->prepare("UPDATE returned_orders SET gross_price = ? WHERE sr_no = ?");
    if ($stmt) {
        $stmt->bind_param("di", $new_price, $edit_sr_no);
        $stmt->execute();
        $stmt->close();
    }

    // Redirect to refresh page and avoid duplicate submission
    header("Location: view_orders.php?month_year=" . urlencode($filter_month));
    exit;
}


// --- End of Table 6: Payments Actions ----======================================================

// --- Table 7: SPF Claims / Inward To Shop Actions ---------------------------------------------

// --- one = Edit SPF Claim Amount OK---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_spf'])) {
    // Match input names correctly
    $sr_no = intval($_POST['edit_spf_sr_no']);
    $new_amount = floatval($_POST['new_claim_amount']);

    // Prepare statement
    $stmt = $conn->prepare("UPDATE spf_claims SET gross_price = ? WHERE sr_no = ?");
    $stmt->bind_param("di", $new_amount, $sr_no);
    $stmt->execute();
    $stmt->close();

    echo "<script>
        alert('Claim amount updated successfully!');
        window.location='view_orders.php';
    </script>";
    exit;
}

if (isset($_GET['delete_spf_id'])) {
    $id = intval($_GET['delete_spf_id']);
    $stmt = $conn->prepare("DELETE FROM spf_claims WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Claim deleted successfully!');window.location='view_orders.php';</script>";
}

// --- three = Approve SPF Claim (Move to Inward To Shop) OK---
if (isset($_GET['approve_spf'])) {
    $src_sr_no = intval($_GET['approve_spf']);

    // Step 1: Fetch record from spf_claims table
    $res = $conn->prepare("SELECT * FROM spf_claims WHERE sr_no = ?");
    $res->bind_param("i", $src_sr_no);
    $res->execute();
    $result = $res->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Step 2: Extract data safely
        $sr_no          = $row['sr_no'] ?? 0;
        $order_id       = $row['order_id'] ?? '';
        $order_date     = $row['order_date'] ?? '';
        $order_type     = $row['order_type'] ?? '';
        $product_name   = $row['product_name'] ?? '';
        $quantity       = isset($row['quantity']) ? intval($row['quantity']) : 0;
        $gross_price    = isset($row['gross_price']) ? floatval($row['gross_price']) : 0.00;
        $item_picture   = $row['item_picture'] ?? '';
        $customer_name  = $row['customer_name'] ?? '';
        $party_location = $row['party_location'] ?? '';

        // Step 3: Insert into inward_to_shop (explicitly including sr_no)
        $stmt = $conn->prepare("
            INSERT INTO inward_to_shop (
                sr_no,
                order_id,
                order_date,
                order_type,
                product_name,
                quantity,
                gross_price,
                item_picture,
                customer_name,
                party_location,
                inward_date
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "isssssdsss",
            $sr_no,
            $order_id,
            $order_date,
            $order_type,
            $product_name,
            $quantity,
            $gross_price,
            $item_picture,
            $customer_name,
            $party_location
        );

        // Step 4: Execute insert + delete from source
        if ($stmt->execute()) {
            $delete_stmt = $conn->prepare("DELETE FROM spf_claims WHERE sr_no = ?");
            $delete_stmt->bind_param("i", $src_sr_no);
            $delete_stmt->execute();
            $delete_stmt->close();
        }

        $stmt->close();
    }

    $res->close();

    // Step 5: Redirect
    header("Location: view_orders.php");
    exit;
}


// --- End of Table 7: SPF Claims / Inward To Shop Actions ---==========================================


// --- Table 8: Return Received (Inward To Shop) Actions ------------------------------------------

// --- one = Edit Inward Entry NOT REQ ---
if (isset($_POST['edit_inward_sr_no'])) {
    $sr_no = intval($_POST['edit_inward_sr_no']);
    $new_amount = floatval($_POST['new_inward_amount']);

    // ✅ Update the correct column name (gross_price)
    $stmt = $conn->prepare("UPDATE inward_to_shop SET gross_price = ? WHERE sr_no = ?");
    $stmt->bind_param("di", $new_amount, $sr_no);
    $stmt->execute();
    $stmt->close();

    echo "<script>
        alert('✅ Inward amount updated successfully!');
        window.location='view_orders.php';
    </script>";
}

// --- two = Delete Inward Entry  NOT REQ ---
if (isset($_GET['delete_inward_id'])) {
    $id = intval($_GET['delete_inward_id']);

    $stmt = $conn->prepare("DELETE FROM inward_to_shop WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Inward entry deleted successfully!');window.location='view_orders.php';</script>";
}

// --- three = Reverse Inward Entry (Back to Payments) OK ---
if (isset($_GET['reverse_inward_sr_no'])) {
    $sr_no = intval($_GET['reverse_inward_sr_no']);

    // Fetch from inward_to_shop using sr_no
    $res = $conn->prepare("SELECT * FROM inward_to_shop WHERE sr_no = ?");
    $res->bind_param("i", $sr_no);
    $res->execute();
    $result = $res->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Use order_date if exists, else fallback to today
        $order_date = !empty($row['order_date']) ? $row['order_date'] : date('Y-m-d');

        // ✅ Insert back into payments
        $stmt = $conn->prepare("
            INSERT INTO payments (
                sr_no, order_id, order_date, order_type,
                product_name, quantity, gross_price, item_picture,
                customer_name, party_location
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param(
            "isssssdsss",
            $row['sr_no'],
            $row['order_id'],
            $order_date,
            $row['order_type'],
            $row['product_name'],
            $row['quantity'],
            $row['gross_price'],
            $row['item_picture'],
            $row['customer_name'],
            $row['party_location']
        );

        if (!$stmt->execute()) {
            die("Execute failed: " . $stmt->error);
        }

        $stmt->close();

        // ✅ Delete the record from inward_to_shop
        $del = $conn->prepare("DELETE FROM inward_to_shop WHERE sr_no = ?");
        $del->bind_param("i", $sr_no);
        $del->execute();
        $del->close();

        session_start();
        $_SESSION['success_msg'] = "✅ Record moved back to Delivered Orders successfully!";
    }

    header("Location: view_orders.php");
    exit;
}


// --- End of Table 8: Return Received (Inward To Shop) Actions ---==================================


?>

<?php include "auth.php"; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>View Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">⬅ Back</a>
            <span class="navbar-brand mb-0 h1">ORDERS  DASHBOARD</span>
            <a href="logout.php" class="btn btn-light btn-sm">Logout</a>
        </div>
    </nav>

    <div class="container mt-4">

        <!-- Month-Year Filter -->
        <form method="get" class="row g-3 mb-4">
            <div class="col-auto">
                <input type="month" name="month_year" class="form-control" value="<?= htmlspecialchars($filter_month) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="view_orders.php" class="btn btn-secondary">Clear</a>
            </div>
        </form>

        <?php if ($filter_month): ?>
            <div class="alert alert-info">
                Showing data for <strong><?php echo date("F Y", strtotime($filter_month . "-01")); ?></strong>
            </div>

        <?php endif; ?>

        <?php include 'score_board.php'; ?>

        <?php

        // start score board filter

        // 1️⃣ Get the selected month-year from input

        $filter_month = $_GET['month_year'] ?? ''; // format: "YYYY-MM"

        if ($filter_month && preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
            $year  = substr($filter_month, 0, 4);
            $month = substr($filter_month, 5, 2);
        } else {
            // fallback to current month
            $year  = date('Y');
            $month = date('m');
        }

        // 2️⃣ Compute date range for SQL
        $start = "$year-$month-01";
        $end   = date("Y-m-t", strtotime($start));

        // 3️⃣ Count function (order_date based)
        function tableCount($conn, $tableName)
        {
            global $start, $end;

            $sql = "SELECT COUNT(*) AS total
            FROM $tableName
            WHERE order_date >= '$start'
            AND order_date <= '$end'";

            $result = mysqli_query($conn, $sql);
            if (!$result) return 0;

            $row = mysqli_fetch_assoc($result);
            return (int)$row['total'];
        }

        // 4️⃣ Get counts for all tables
        $totalOrders            = tableCount($conn, 'orders');
        $totaldispatch          = tableCount($conn, 'dispatch');
        $totaldelivered_orders  = tableCount($conn, 'delivered_orders');
        $totalpayments          = tableCount($conn, 'payments');
        $totalcustomer_returns  = tableCount($conn, 'customer_returns');
        $totalreturned_orders   = tableCount($conn, 'returned_orders');
        $totalspf_claims        = tableCount($conn, 'spf_claims');
        $totalinward_to_shop    = tableCount($conn, 'inward_to_shop');

        // end score board filter

        ?>

        <!-- Pending Orders  -->
        <h3 class="mb-3">📦 Table-1: Total Orders (<?= $totalOrders ?>)</h3>
        <div class="table-responsive mb-5">
            <table class="table table-bordered table-striped table-hover align-middle">
                <thead class="table-primary text-center">
                    <tr>
                        <th>Sr No</th>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Order Type</th>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Gross Price</th>
                        <th>Item Picture</th>
                        <th>Customer Name</th>
                        <th>Party Location</th>
                        <th>Actions (Edit & Dispatch)</th>
                    </tr>
                </thead>
                <tbody class="text-center">
                    <?php if ($result_orders && $result_orders->num_rows > 0): ?>
                        <?php while ($row = $result_orders->fetch_assoc()):

                            // Multi-table check using order_id
                            $check = $conn->prepare("
                                   SELECT order_id FROM (
                                       SELECT order_id FROM dispatch
                                       UNION ALL
                                       SELECT order_id FROM delivered_orders
                                       UNION ALL
                                       SELECT order_id FROM returned_orders
                                       UNION ALL                              
                                       SELECT order_id FROM customer_returns
                                       UNION ALL
                                       SELECT order_id FROM payments
                                       UNION ALL
                                       SELECT order_id FROM inward_to_shop
                                       UNION ALL
                                       SELECT order_id FROM spf_claims
                                   ) AS all_tables
                                   WHERE order_id = ?
                               ");

                            $check->bind_param("s", $row['order_id']);
                            $check->execute();
                            $isDispatched = $check->get_result()->num_rows > 0;

                            // Re-enable only if this exact order was reversed
                            if (
                                isset($_GET['rev']) &&
                                $_GET['rev'] == 1 &&
                                isset($_GET['reverse_dispatch_id']) &&
                                $_GET['reverse_dispatch_id'] == $row['sr_no']
                            ) {
                                $isDispatched = false;
                            }

                        ?>
                            <tr>
                                <td><?= $row['sr_no'] ?></td>
                                <td><?= htmlspecialchars($row['order_id']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['order_date'])) ?></td>
                                <td><?= htmlspecialchars($row['order_type']) ?></td>
                                <td><?= htmlspecialchars($row['product_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td>₹<?= number_format($row['gross_price'], 2) ?></td>
                                <td>
                                    <?php if (!empty($row['item_picture'])): ?>
                                        <img src="<?= htmlspecialchars($row['item_picture']) ?>" alt="Item" width="60" height="60" class="rounded">
                                    <?php else: ?>
                                        <span class="text-muted">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['party_location']) ?></td>
                                <td>
                                    <a href="edit_order.php?id=<?= $row['sr_no'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <!--    <a href="delete_order.php?order_id=<?= urlencode($row['order_id']) ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this order?');">
                                        Delete
                                    </a>-->


                                    <?php if ($isDispatched): ?>
                                        <button class="btn btn-secondary btn-sm" disabled>Dispatched</button>
                                    <?php else: ?>
                                        <a href="view_orders.php?dispatch_id=<?= $row['sr_no'] ?>"
                                            class="btn btn-success btn-sm"
                                            onclick="return confirm('Dispatch this order?');">
                                            Dispatch
                                        </a>
                                    <?php endif; ?>




                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center">No pending orders</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>


        <!-- Dispatched Orders -->
        <h3 class="mb-3">🚚 Table-2: In Transit (<?= $totaldispatch ?>)</h3>
        <div class="table-responsive mb-5">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-secondary">
                    <tr>
                        <th>Sr No</th>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Order Type</th>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Gross Price</th>
                        <th>Item Picture</th>
                        <th>Customer Name</th>
                        <th>Party Location</th>
                        <th>Dispatch Date</th>
                        <th>Actions (Delivered, Courier Return & Back To T1)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_dispatch && $result_dispatch->num_rows > 0): ?>
                        <?php while ($row = $result_dispatch->fetch_assoc()): ?>
                            <?php
                            // Fix undefined index — choose sr_no if exists, otherwise id
                            $sr_no = $row['sr_no'] ?? $row['id'];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($sr_no) ?></td>
                                <td><?= htmlspecialchars($row['order_id']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['order_date'])) ?></td>
                                <td><?= htmlspecialchars($row['order_type']) ?></td>
                                <td><?= htmlspecialchars($row['product_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td>₹<?= number_format($row['gross_price'], 2) ?></td>
                                <td>
                                    <?php if (!empty($row['item_picture'])): ?>
                                        <img src="<?= htmlspecialchars($row['item_picture']) ?>" alt="Item" width="60" height="60" class="rounded">
                                    <?php else: ?>
                                        <span class="text-muted">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['party_location']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['dispatch_date'])) ?></td>
                                <td>
                                    <a href="view_orders.php?deliver_dispatch_id=<?= $row['sr_no'] ?>"
                                        class="btn btn-sm btn-success"
                                        onclick="return confirm('Mark as Delivered?');">
                                        Delivered
                                    </a>

                                    <a href="view_orders.php?returned_id=<?= $row['sr_no'] ?>"
                                        class="btn btn-sm btn-warning"
                                        onclick="return confirm('Mark as Courier Return?');">
                                        Courier Return
                                    </a>

                                    <a href="view_orders.php?reverse_dispatch_id=<?= $row['sr_no'] ?>&rev=1"
                                        class="btn btn-sm bg-danger-subtle text-danger"
                                        onclick="return confirm('Reverse this order back to T1?');">
                                        ⬅ Back To T1
                                    </a>
                                </td>

                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center">No dispatched orders</td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            </table>
        </div>



        <!-- Delivered Orders -->
        <h3 class="mb-3">✅ Table-3: Delivered Orders (<?= $totaldelivered_orders ?>)</h3>
        <div class="table-responsive mb-5">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-success">
                    <tr>
                        <th>Sr No</th>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Order Type</th>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Gross Price</th>
                        <th>Item Picture</th>
                        <th>Customer Name</th>
                        <th>Party Location</th>
                        <th>Deliverd Date</th>
                        <th>Actions (Paid, Customer Return & Back To T2)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_delivered && $result_delivered->num_rows > 0): ?>
                        <?php while ($row = $result_delivered->fetch_assoc()): ?>
                            <?php
                            // Fix undefined index — choose sr_no if exists
                            $sr_no = $row['sr_no'] ?? 'N/A';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($sr_no) ?></td>
                                <td><?= htmlspecialchars($row['order_id']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['order_date'])) ?></td>
                                <td><?= htmlspecialchars($row['order_type']) ?></td>
                                <td><?= htmlspecialchars($row['product_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td>₹<?= number_format($row['gross_price'], 2) ?></td>
                                <td>
                                    <?php if (!empty($row['item_picture'])): ?>
                                        <img src="<?= htmlspecialchars($row['item_picture']) ?>" alt="Item" width="60" height="60" class="rounded">
                                    <?php else: ?>
                                        <span class="text-muted">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['party_location']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['delivered_date'])) ?></td>

                                <td class="d-flex flex-wrap gap-2">
                                    <!-- Mark Payment Form -->
                                    <form method="post" action="view_orders.php" class="d-flex">
                                        <input type="hidden" name="delivered_id" value="<?= $sr_no ?>">
                                        <input type="number" step="0.01" name="amount_received" class="form-control form-control-sm me-2" style="width:90px;" placeholder="Enter ₹" required>
                                        <button type="submit" name="mark_payment" class="btn btn-sm btn-primary" onclick="return confirm('Are you sure you want to mark this payment as PAID?');">
                                            ✔PAID
                                        </button>
                                    </form>

                                    <!-- Courier Return -->
                                    <a href="view_orders.php?cust_return_id=<?= $sr_no ?>"
                                        class="btn btn-sm btn-warning"
                                        onclick="return confirm('Mark as Customer Return?');">
                                        Customer Return
                                    </a>

                                    <!-- Reverse To Dispatch -->
                                    <a href="view_orders.php?reverse_delivered_id=<?= $sr_no ?>"
                                        class="btn btn-sm bg-danger-subtle text-danger"
                                        onclick="return confirm('Reverse this order back to Dispatch?');">
                                        ⬅ Back To T2
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center">No delivered orders</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>



        <!-- 💰 Table-4 = Payment Received Orders -->
        <h3 class="mb-3">💰 Table-4: Payment Received Orders (<?= $totalpayments ?>)</h3>
        <div class="table-responsive mb-5">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-success">
                    <tr>
                        <th>Sr No</th>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Order Type</th>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Gross Price</th>
                        <th>Item Picture</th>
                        <th>Customer Name</th>
                        <th>Party Location</th>
                        <th>Payment Date</th>
                        <th>Actions (Edit, Inward & Back To T3)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_payment && $result_payment->num_rows > 0): ?>
                        <?php while ($row = $result_payment->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['sr_no']) ?></td>
                                <td><?= htmlspecialchars($row['order_id']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['order_date'])) ?></td>
                                <td><?= htmlspecialchars($row['order_type']) ?></td>
                                <td><?= htmlspecialchars($row['product_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td>₹<?= number_format($row['gross_price'], 2) ?></td>
                                <td>
                                    <?php if (!empty($row['item_picture'])): ?>
                                        <img src="<?= htmlspecialchars($row['item_picture']) ?>" alt="Item" width="60" height="60" class="rounded">
                                    <?php else: ?>
                                        <span class="text-muted">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['party_location']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['payments_date'])) ?></td>

                                <td class="d-flex gap-2">
                                    <!-- Edit Button -->
                                    <form method="post" action="view_orders.php" class="d-flex">
                                        <input type="hidden" name="sr_no" value="<?= $row['sr_no'] ?>">
                                        <input type="number" step="0.01" name="new_amount"
                                            value="<?= $row['gross_price'] ?>"
                                            class="form-control form-control-sm me-2" required
                                            style="width:90px;">
                                        <button type="submit" name="edit_payment"
                                            class="btn btn-sm btn-warning">Edit</button>
                                    </form>

                                    <!-- Inward To Shop -->
                                    <a href="view_orders.php?inward_payment_id=<?= $row['sr_no'] ?>"
                                        class="btn btn-sm btn-success"
                                        onclick="return confirm('Move this payment to Inward To Shop?');">
                                        🏬 Inward
                                    </a>

                                    <!-- Reverse Button -->
                                    <a href="view_orders.php?reverse_payment_id=<?= $row['sr_no'] ?>"
                                        class="btn btn-sm bg-danger-subtle text-danger"
                                        onclick="return confirm('Are you sure to reverse this payment?');">
                                        ⬅ Back To T3
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center">No payment received orders</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>




        <!-- Customer Return Orders -->
        <h3 class="mb-3">🙍 Table-5: Customer Return (<?= $totalcustomer_returns ?>)</h3>
        <div class="table-responsive mb-5">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-warning">
                    <tr>
                        <th>Sr No</th>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Order Type</th>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Gross Price</th>
                        <th>Item Picture</th>
                        <th>Customer Name</th>
                        <th>Party Location</th>
                        <th>Returned Date</th>
                        <th>Actions (Edit, Inward, SPF Claim, Back To T3 & Reason)</th>

                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_custreturn && $result_custreturn->num_rows > 0): ?>
                        <?php while ($row = $result_custreturn->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['sr_no']) ?></td>
                                <td><?= htmlspecialchars($row['order_id']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['order_date'])) ?></td>
                                <td><?= htmlspecialchars($row['order_type']) ?></td>
                                <td><?= htmlspecialchars($row['product_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td>₹<?= number_format($row['gross_price'], 2) ?></td>
                                <td>
                                    <?php if (!empty($row['item_picture'])): ?>
                                        <img src="<?= htmlspecialchars($row['item_picture']) ?>" alt="Item" width="60" height="60" class="rounded">
                                    <?php else: ?>
                                        <span class="text-muted">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['party_location']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['returned_date'])) ?></td>



                                <td>
                                    <!-- Edit Button (Modal remains unchanged) -->
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editReturnModal<?= $row['sr_no'] ?>">Edit</button>

                                    <!-- Edit Return Modal (Do NOT change) -->
                                    <div class="modal fade" id="editReturnModal<?= $row['sr_no'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="post" action="view_orders.php">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Return Amount</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="return_id" value="<?= $row['sr_no'] ?>">

                                                        <div class="mb-3">
                                                            <label>Final Amount (₹) = (Return amount - Penalty Amount)</label>
                                                            <input type="number" step="0.01"
                                                                class="form-control"
                                                                name="new_return_amount"
                                                                value="<?= $row['gross_price'] ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" name="edit_return" class="btn btn-primary">Save</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Inward Button -->
                                    <a href="view_orders.php?inward_return_id=<?= $row['sr_no'] ?>"
                                        class="btn btn-sm btn-success"
                                        onclick="return confirm('Mark this return as Inward to Shop?');">
                                        Inward
                                    </a>

                                    <!-- SPF Claims Button -->
                                    <a href="view_orders.php?spf_claim_id=<?= $row['sr_no'] ?>"
                                        class="btn btn-sm btn-info"
                                        onclick="return confirm('Move this return to SPF Claims?');">
                                        SPF Claims
                                    </a>

                                    <!-- Reverse Button -->
                                    <a href="view_orders.php?reverse1_returned_id=<?= $row['sr_no'] ?>"
                                        class="btn btn-sm bg-danger-subtle text-danger"
                                        onclick="return confirm('Reverse this return To T3?');">
                                        Back To T3
                                    </a>


                                     <button
                                        class="btn btn-sm <?= !empty($row['reason_code']) ? 'btn btn-secondary btn-sm' : 'btn-secondary' ?> add-reason-btn"
                                        data-order-id="<?= htmlspecialchars($row['order_id']) ?>"
                                        data-return-type="<?= htmlspecialchars($row['return_type'] ?? 'customer') ?>"
                                        data-reason="<?= htmlspecialchars($row['reason_code'] ?? '') ?>"
                                        data-remark="<?= htmlspecialchars($row['remark'] ?? '') ?>">
                                        <?= !empty($row['reason_code'])                                                                                             
                                            ? htmlspecialchars(
                                                ($row['return_type'] == 'courier' ? 'RTO' : 'RVP') . ' — ' .
                                                    $row['reason_code'] .
                                                    (!empty($row['remark']) ? ', ' . $row['remark'] : '')
                                            )
                                            : '📝 Reason'
                                        ?>

                                    </button>

                                </td>

                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center">No customer return orders</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Returned Orders -->
        <h3 class="mb-3">↩️ Table-6: Courier Return (<?= $totalreturned_orders ?>)</h3>
        <div class="table-responsive mb-5">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-danger">
                    <tr>
                        <th>Sr No</th>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Order Type</th>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Gross Price</th>
                        <th>Item Picture</th>
                        <th>Customer Name</th>
                        <th>Party Location</th>
                        <th>Returned Date</th>
                        <th>Actions (Edit, Inward, SPF Claim, Back To T2 & Reason )</th>

                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_returned && $result_returned->num_rows > 0): ?>
                        <?php while ($row = $result_returned->fetch_assoc()): ?>
                            <?php
                            // Fix undefined index — choose sr_no if exists, otherwise id
                            $sr_no = $row['sr_no'] ?? $row['id'];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($sr_no) ?></td>
                                <td><?= htmlspecialchars($row['order_id']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['order_date'])) ?></td>
                                <td><?= htmlspecialchars($row['order_type']) ?></td>
                                <td><?= htmlspecialchars($row['product_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td>₹<?= number_format($row['gross_price'], 2) ?></td>
                                <td>
                                    <?php if (!empty($row['item_picture'])): ?>
                                        <img src="<?= htmlspecialchars($row['item_picture']) ?>" alt="Item" width="60" height="60" class="rounded">
                                    <?php else: ?>
                                        <span class="text-muted">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['party_location']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['returned_date'])) ?></td>

                                <td class="d-flex flex-wrap gap-2">
                                    <!-- Edit Price -->
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="edit_returned_id" value="<?= $sr_no ?>">
                                        <input type="number" step="0.01" name="new_price"
                                            value="<?= $row['gross_price'] ?>"
                                            class="form-control form-control-sm d-inline-block"
                                            style="width:80px;">
                                        <button type="submit" class="btn btn-sm btn-primary"
                                            onclick="return confirm('Amount updated successfully!')">Edit</button>
                                    </form>

                                    <!-- Inward -->
                                    <a href="?inward_returned_id=<?= $sr_no ?>"
                                        class="btn btn-sm btn-success"
                                        onclick="return confirm('Mark this return as Inward to Shop?')">Inward</a>

                                    <!-- SPF Claim -->
                                    <a href="?spf_returned_id=<?= $sr_no ?>"
                                        class="btn btn-sm btn-warning"
                                        onclick="return confirm('Move this return to SPF Claims?')">SPF Claim</a>

                                    <!-- Reverse To T2 -->
                                    <a href="?reverse_returned_id=<?= $sr_no ?>"
                                        class="btn btn-sm bg-danger-subtle text-danger"
                                        onclick="return confirm('Reverse this return?')">Back To T2</a>

                                    <button
                                        class="btn btn-sm <?= !empty($row['reason_code']) ? 'btn btn-secondary btn-sm' : 'btn-secondary' ?> add-reason-btn"
                                        data-order-id="<?= htmlspecialchars($row['order_id']) ?>"
                                        data-return-type="courier"
                                        data-reason="<?= htmlspecialchars($row['reason_code'] ?? '') ?>"
                                        data-remark="<?= htmlspecialchars($row['remark'] ?? '') ?>">

                                        <?= !empty($row['reason_code'])
                                            ? htmlspecialchars(
                                                'RTO — ' .
                                                    $row['reason_code'] .
                                                    (!empty($row['remark']) ? ', ' . $row['remark'] : '')
                                            )
                                            : '📝 Reason'
                                        ?>

                                    </button>



                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center">No returned orders</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>



        <!-- SPF Claims -->
        <h3 class="mb-3">🛡️ Table-7: SPF Claims (<?= $totalspf_claims ?>)</h3>
        <div class="table-responsive mb-5">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>Sr No</th>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Order Type</th>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Gross Price</th>
                        <th>Item Picture</th>
                        <th>Customer Name</th>
                        <th>Party Location</th>
                        <th>Claim Date</th>
                        <th>Actions (Edit & Inward)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_spf && $result_spf->num_rows > 0): ?>
                        <?php while ($row = $result_spf->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['sr_no']) ?></td>
                                <td><?= htmlspecialchars($row['order_id']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['order_date'])) ?></td>
                                <td><?= htmlspecialchars($row['order_type']) ?></td>
                                <td><?= htmlspecialchars($row['product_name']) ?></td>
                                <td><?= (int)$row['quantity'] ?></td>
                                <td>₹<?= number_format($row['gross_price'], 2) ?></td>
                                <td>
                                    <?php if (!empty($row['item_picture'])): ?>
                                        <img src="<?= htmlspecialchars($row['item_picture']) ?>" alt="Item" width="60" height="60" class="rounded">
                                    <?php else: ?>
                                        <span class="text-muted">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['party_location']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['claim_date'])) ?></td>
                                <td>
                                    <!-- Edit claim amount (inline small form) -->
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="edit_spf_sr_no" value="<?= htmlspecialchars($row['sr_no']) ?>">
                                        <input type="number" step="0.01" name="new_claim_amount"
                                            value="<?= htmlspecialchars($row['gross_price']) ?>"
                                            class="form-control form-control-sm d-inline-block"
                                            style="width:110px;" required>
                                        <button type="submit" name="edit_spf" class="btn btn-sm btn-primary">Edit</button>
                                    </form>

                                    <!-- Inward -->
                                    <a href="?approve_spf=<?= htmlspecialchars($row['sr_no']) ?>"
                                        class="btn btn-sm btn-success"
                                        onclick="return confirm('Move this SPF claim to Inward?');">
                                        Inward
                                    </a>

                                    <button
                                        class="btn btn-sm <?= !empty($row['reason_code']) ? 'btn btn-secondary btn-sm' : 'btn-secondary' ?> add-reason-btn"
                                        data-order-id="<?= htmlspecialchars($row['order_id']) ?>"
                                        data-return-type="<?= htmlspecialchars($row['return_type'] ?? 'customer') ?>"
                                        data-reason="<?= htmlspecialchars($row['reason_code'] ?? '') ?>"
                                        data-remark="<?= htmlspecialchars($row['remark'] ?? '') ?>">

                                        <?= !empty($row['reason_code'])
                                            ? htmlspecialchars(
                                                ($row['return_type'] == 'courier' ? 'RTO' : 'RVP') . ' — ' .
                                                    $row['reason_code'] .
                                                    (!empty($row['remark']) ? ', ' . $row['remark'] : '')
                                            )
                                            : '📝 Reason'
                                        ?>

                                    </button>



                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center">No SPF claims found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>



        <!-- Return Received -->
        <h3 class="mb-3">📦 Table-8: Inward To Shop (<?= $totalinward_to_shop ?>)</h3>
        <div class="table-responsive mb-5">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-success">
                    <tr>
                        <th>Sr No</th>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Order Type</th>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Gross Price</th>
                        <th>Item Picture</th>
                        <th>Customer Name</th>
                        <th>Party Location</th>
                        <th>Inward Date</th>
                        <th>Actions (Edit & Back To T4)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_inward && $result_inward->num_rows > 0): ?>
                        <?php while ($row = $result_inward->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['sr_no']) ?></td>
                                <td><?= htmlspecialchars($row['order_id']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['order_date'])) ?></td>
                                <td><?= htmlspecialchars($row['order_type']) ?></td>
                                <td><?= htmlspecialchars($row['product_name']) ?></td>
                                <td><?= (int)$row['quantity'] ?></td>
                                <td>₹<?= number_format($row['gross_price'], 2) ?></td>
                                <td>
                                    <?php if (!empty($row['item_picture'])): ?>
                                        <img src="<?= htmlspecialchars($row['item_picture']) ?>" alt="Item" width="60" height="60" class="rounded">
                                    <?php else: ?>
                                        <span class="text-muted">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['party_location']) ?></td>
                                <td><?= date("d-M-Y", strtotime($row['inward_date'])) ?></td>
                                <td>
                                    <!-- Edit Form -->
                                    <form method="post" style="display:inline-block;" class="me-1">
                                        <input type="hidden" name="edit_inward_sr_no" value="<?= htmlspecialchars($row['sr_no']) ?>">
                                        <input type="number" step="0.01" name="new_inward_amount"
                                            value="<?= htmlspecialchars($row['gross_price']) ?>"
                                            class="form-control form-control-sm d-inline-block"
                                            style="width:110px;" required>
                                        <button type="submit" name="edit_inward" class="btn btn-sm btn-warning ms-1">✏️ Edit</button>
                                    </form>

                                    <!-- Reverse To Source -->
                                    <a href="?reverse_inward_sr_no=<?= htmlspecialchars($row['sr_no']) ?>"
                                        class="btn btn-sm bg-danger-subtle text-danger"
                                        onclick="return confirm('Reverse this entry back to source table?')">
                                        ↩ Back To T4
                                    </a>

                                    <?php if (!empty($row['reason_code'])): ?>
                                        <?php
                                        // Determine button color by return type
                                        $btnClass = strtolower($row['order_type']) === 'customer return' ? 'btn-warning' : 'btn-secondary';
                                        ?>
                                        <button
                                            class="btn btn-sm <?= $btnClass ?> add-reason-btn"
                                            data-order-id="<?= htmlspecialchars($row['order_id']) ?>"
                                            data-return-type="<?= htmlspecialchars($row['return_type'] ?? 'customer') ?>"
                                            data-reason="<?= htmlspecialchars($row['reason_code'] ?? '') ?>"
                                            data-remark="<?= htmlspecialchars($row['remark'] ?? '') ?>">

                                            <?php if (!empty($row['reason_code'])): ?>
                                                <?= htmlspecialchars(
                                                    ($row['return_type'] == 'courier' ? 'RTO' : 'RVP') . ' — ' .
                                                        $row['reason_code'] .
                                                        (!empty($row['remark']) ? ', ' . $row['remark'] : '')
                                                ) ?>
                                            <?php else: ?>
                                                📝 Reason
                                            <?php endif; ?>

                                        </button>
                                    <?php endif; ?>
                                </td>

                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center">No inward entries</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>


        <!-- End of Product Journey -->
        <div class="product-journey-footer">
            <div class="journey-line"></div>

            <div class="journey-content">
                <span class="journey-dot"></span>
                <span class="journey-text">End of Product Journey</span>
            </div>

            <div class="journey-subtext">
                All operational stages for this item conclude here.
            </div>
        </div>


        <style>
            .product-journey-footer {
                margin: 26px auto 10px;
                padding: 18px 10px 6px;
                text-align: center;
                max-width: 520px;
                position: relative;
                margin-top: 0px;
            }

            .product-journey-footer .journey-line {
                height: 1px;
                background: linear-gradient(to right, transparent, #bbb, transparent);
                margin-bottom: 14px;
            }

            .journey-content {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                font-size: 15px;
                font-weight: 600;
                color: #333;
                letter-spacing: 0.3px;
            }

            .journey-dot {
                width: 9px;
                height: 9px;
                background: #6f42c1;
                border-radius: 50%;
                box-shadow: 0 0 0 6px rgba(111, 66, 193, 0.15);
                animation: softGlow 2.5s infinite;
            }

            .journey-text {
                background: linear-gradient(90deg, #6f42c1, #0d6efd);
                background-clip: text;
                -webkit-background-clip: text;

                color: transparent;
                -webkit-text-fill-color: transparent;

                text-decoration: none !important;
                caret-color: transparent;
                user-select: none;
                margin-top: 0px;
                /* reduce this value as needed */
            }



            .journey-subtext {
                margin-top: 6px;
                font-size: 12.5px;
                color: #666;
                font-style: italic;
            }

            /* Soft glow animation */
            @keyframes softGlow {
                0% {
                    box-shadow: 0 0 0 4px rgba(111, 66, 193, 0.18);
                }

                50% {
                    box-shadow: 0 0 0 9px rgba(111, 66, 193, 0.05);
                }

                100% {
                    box-shadow: 0 0 0 4px rgba(111, 66, 193, 0.18);
                }
            }
        </style>

        <!-- Return Reason Modal -->
        <div class="modal fade" id="returnReasonModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Return Reason</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <form id="returnReasonForm">

                            <input type="hidden" id="rr_order_id">
                            <input type="hidden" id="rr_type">




                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <select class="form-select" id="rr_reason" required>
                                    <option value="">-- Select Reason --</option>
                                    <option value="MISSHIPMENT.">MISSHIPMENT.</option>
                                    <option value="MISSING_ITEM">MISSING_ITEM</option>
                                    <option value="ORC_val_cust.">ORC_val_cust.</option>
                                    <option value="Mind Changed">Mind Changed</option>
                                    <option value="Expensive Now">Expensive_Now</option>
                                    <option value="Attem_Exhaus.">Attem_Exhaus.</option>
                                    <option value="Damg.SHIP.OBD">Damg.SHIP.OBD</option>
                                    <option value="ACCE._DEFEC.">ACCE._DEFEC.</option>
                                    <option value="DEFEC._PROD.">DEFEC._PROD.</option>
                                    <option value="CHANG.MO.NO.">CHANG.MO.NO.</option>
                                    <option value="SOFTW._ISSUE.">SOFTW._ISSUE</option>
                                    <option value="Order_Cancel.">Order_Cancel.</option>
                                    <option value="Any_Other_IS.">Any_Other_IS.</option>

                                </select>
                            </div>


                            <div class="mb-3">
                                <label class="form-label">Remark (optional)</label>
                                <textarea class="form-control" id="rr_remark" rows="2"></textarea>
                            </div>

                        </form>
                    </div>

<div class="modal-footer">
    <button type="button" class="btn btn-danger" id="clearReasonBtn">
        Clear Reason
    </button>

    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        Cancel
    </button>

    <button type="button" class="btn btn-primary" id="saveReasonBtn">
        Save Reason
    </button>
</div>

                </div>
            </div>
        </div>


    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .negative-text {
            color: red !important;
            font-weight: normal;
            /* remove bold */
        }

        .negative-input {
            color: red !important;
            font-weight: normal;
        }
    </style>

    <style>
        .reason-scroll {
            max-width: 140px;
            overflow: hidden;
            white-space: nowrap;
            position: relative;
        }

        .reason-scroll span {
            display: inline-block;
            padding-left: 100%;
            animation: slide-left 6s linear infinite;
        }

        @keyframes slide-left {
            from {
                transform: translateX(0);
            }

            to {
                transform: translateX(-100%);
            }
        }
    </style>

    <script>
        function highlightNegative() {
            // Only target numeric cells (not headers/dates)
            document.querySelectorAll("td").forEach(el => {
                const text = el.textContent.trim();

                // Match only currency/number values, not dates
                if (/^-?\d+(\.\d+)?$/.test(text.replace(/[₹$,]/g, ""))) {
                    if (parseFloat(text.replace(/[₹$,]/g, "")) < 0) {
                        el.classList.add("negative-text");
                    }
                }
            });

            // Handle number inputs
            document.querySelectorAll("input[type='number']").forEach(input => {
                const checkValue = () => {
                    if (parseFloat(input.value) < 0) {
                        input.classList.add("negative-input");
                    } else {
                        input.classList.remove("negative-input");
                    }
                };
                input.addEventListener("input", checkValue);
                checkValue(); // run on load
            });
        }

        document.addEventListener("DOMContentLoaded", highlightNegative);
    </script>


    <script>
        window.addEventListener("beforeunload", () => {
            localStorage.setItem("scrollPos", window.scrollY);
        });

        window.addEventListener("load", () => {
            let scrollPos = localStorage.getItem("scrollPos");
            if (scrollPos) window.scrollTo(0, scrollPos);
        });
    </script>

    <script>
        /* OPEN MODAL */
        document.querySelectorAll('.add-reason-btn').forEach(btn => {
            btn.addEventListener('click', function() {

                const orderId = this.dataset.orderId;
                const returnType = this.dataset.returnType;
                const reason = this.dataset.reason || '';
                const remark = this.dataset.remark || '';

                document.getElementById('rr_order_id').value = orderId;
                document.getElementById('rr_type').value = returnType;
                document.getElementById('rr_reason').value = reason;
                document.getElementById('rr_remark').value = remark;

                new bootstrap.Modal(
                    document.getElementById('returnReasonModal')
                ).show();
            });
        });

        /* SAVE REASON (UI + DB) */
        document.getElementById('saveReasonBtn').addEventListener('click', () => {

            const orderId = document.getElementById('rr_order_id').value;
            const returnType = document.getElementById('rr_type').value;
            const reason = document.getElementById('rr_reason').value;
            const remark = document.getElementById('rr_remark').value;

            if (!reason) {
                alert('Please select a reason');
                return;
            }

            fetch('save_return_reason.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        order_id: orderId,
                        return_type: returnType,
                        reason: reason,
                        remark: remark
                    })
                })
                .then(res => res.text())
                .then(() => {
                    alert('✅ Reason saved successfully');

                    /* ✅ SUCCESS TOGGLE — ADDED SAFELY */
                    const btn = document.querySelector(
                        `.add-reason-btn[data-order-id="${orderId}"][data-return-type="${returnType}"]`
                    );

                    if (btn) {
                        btn.classList.remove('btn-secondary');
                        btn.classList.add('btn-success');

                        btn.dataset.reason = reason;
                        btn.dataset.remark = remark;

                        btn.innerHTML = `
    <div class="reason-scroll">
      <span>${reason}${remark ? ', ' + remark : ''}</span>
    </div>
  `;
                    }
                    /* ✅ END SUCCESS TOGGLE */

                    bootstrap.Modal.getInstance(
                        document.getElementById('returnReasonModal')
                    ).hide();
                })
                .catch(() => {
                    alert('❌ Failed to save reason');
                });

        });


        document.getElementById('clearReasonBtn').addEventListener('click', () => {

    if (!confirm("Do you want to clear reason?")) {
        return;
    }

    const orderId = document.getElementById('rr_order_id').value;

    fetch('clear_return_reason.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'order_id=' + orderId
    })
    .then(() => location.reload());

});

        
    </script>

</body>

</html>