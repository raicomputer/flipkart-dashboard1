<?php
require_once 'db.php';

/* =========================================================
   PAYMENT HISTORY (RESPECTS MONTH FILTER)
   ========================================================= */

$payments = [];

$paymentWhere = "";

if (!empty($filter_month)) {
    // 2026-01 → 202601
    $srPrefix = str_replace('-', '', $filter_month);

    $paymentWhere = "WHERE received_payments.sr_no LIKE '{$srPrefix}%'";
}


$sql = "SELECT id, received_date, received_amount, remarks
        FROM received_payments
        $paymentWhere
        ORDER BY received_date DESC, id DESC";

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
    }
}

/* =========================================================
   DELETE PAYMENT
   ========================================================= */
if (isset($_GET['delete_payment'])) {

    $delete_id = (int) $_GET['delete_payment'];

    if ($delete_id > 0) {
        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM received_payments WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?history=open");
    exit;
}


/* =========================================================
   PAYMENT INSERT AND FILTER BY SR.NO. 3 NEW CHANGE 1-$paymentWhere 2-JUST BELOW 3 -UI 
   ========================================================= */


$selectedSrNo = $_POST['sr_no'] ?? '';

$orderData = [];

if ($selectedSrNo) {
    $stmt = $conn->prepare("
        SELECT sr_no, product_name
        FROM orders
        WHERE sr_no = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $selectedSrNo);
    $stmt->execute();
    $orderData = $stmt->get_result()->fetch_assoc();
}


$srPrefix = '';
if (!empty($filter_month)) {
    $srPrefix = str_replace('-', '', $filter_month); // 202601
}



$orderSrList = [];

if ($srPrefix) {
    $stmt = $conn->prepare("
        SELECT sr_no, product_name
        FROM orders
        WHERE sr_no LIKE CONCAT(?, '%')
        ORDER BY sr_no DESC
    ");
    $stmt->bind_param("s", $srPrefix);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = mysqli_query(
        $conn,
        "SELECT sr_no, product_name FROM orders ORDER BY sr_no DESC"
    );
}

while ($row = mysqli_fetch_assoc($res)) {
    $orderSrList[] = $row;
}






/* =========================================================
   SCORE BOARD CALCULATIONS (REUSE SAME FILTER)
   ========================================================= */

// Helpers (LOCAL – no redeclare risk)
function sc_count($conn, $table, $where)
{
    $sql = "SELECT COUNT(*) FROM $table $where";
    $res = mysqli_query($conn, $sql);
    return ($res && $row = mysqli_fetch_row($res)) ? (int)$row[0] : 0;
}

function sc_sum($conn, $table, $column, $where)
{
    $sql = "SELECT SUM($column) FROM $table $where";
    $res = mysqli_query($conn, $sql);
    return ($res && $row = mysqli_fetch_row($res)) ? (float)($row[0] ?? 0) : 0;
}

/* ----- Volume ----- */
$total_orders      = sc_count($conn, 'orders', $whereFilter);
$dispatched        = sc_count($conn, 'dispatch', $whereFilter);
$delivered_orders  = sc_count($conn, 'delivered_orders', $whereFilter);
$customer_returns  = sc_count($conn, 'customer_returns', $whereFilter);
$returned_orders   = sc_count($conn, 'returned_orders', $whereFilter);
$total_returns     = $customer_returns + $returned_orders;
$inward_to_shop    = sc_count($conn, 'inward_to_shop', $whereFilter);
$spf_claims        = sc_count($conn, 'spf_claims', $whereFilter);

/* ----- Financial ----- */
$gross_dispatched_value = sc_sum($conn, 'orders', 'gross_price', $whereFilter);
$created_amount         = sc_sum($conn, 'inward_to_shop', 'gross_price', $whereFilter);
$received_amount        = sc_sum($conn, 'received_payments', 'received_amount', $paymentWhere);

/* ----- Profit / Loss ----- */
$net_loss   = max(($gross_dispatched_value - $received_amount), 0);
$net_profit = $received_amount - $gross_dispatched_value;

/* ----- Discrepancy ----- */
$disc_amount = $created_amount - $received_amount;
$disc_display_value = -1 * $disc_amount;
$disc_display = ($disc_display_value > 0 ? '+' : '') . number_format($disc_display_value, 2);
$disc_class = $disc_display_value >= 0 ? 'text-success' : 'text-danger';

/* ----- Performance Score (simple & safe) ----- */
$delivery_ratio = ($dispatched > 0) ? ($delivered_orders / $dispatched) : 0;
$delivery_score = round($delivery_ratio * 30);

$return_ratio = ($dispatched > 0) ? ($total_returns / $dispatched) : 0;
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

?>

<?php include "auth.php"; ?>


<div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-25">
        <strong>💰 Received Amount Entry (Bank Credit)</strong>
    </div>

    <div class="card-body">
        <form method="post">

            <!-- ================= INPUT ROW ================= -->
            <div class="row g-2 align-items-end mb-2">


                <?php
                // Default received date logic (smart)
                if (!empty($filter_month)) {

                    $currentMonth = date('Y-m');

                    if ($filter_month === $currentMonth) {
                        // Selected month is current month → today
                        $default_received_date = date('Y-m-d');
                    } else {
                        // Past or future month → first day of that month
                        $default_received_date = $filter_month . '-28';
                    }
                } else {
                    // No filter → today
                    $default_received_date = date('Y-m-d');
                }
                ?>

                <!--SR. No. & Product Name (NEW PART) -->

                <div class="col-md-3">
                    <label class="form-label">SR. No. & Product Name</label>
                    <select name="sr_no" class="form-select" required onchange="this.form.submit()">
                        <option value="">-- Select Order Sr No --</option>

                        <?php foreach ($orderSrList as $order): ?>
                            <option value="<?= htmlspecialchars($order['sr_no']) ?>"
                                <?= ($order['sr_no'] == $selectedSrNo) ? 'selected' : '' ?>>

                                <?= htmlspecialchars($order['sr_no']) ?> ==
                                <?= htmlspecialchars($order['product_name']) ?>

                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>


                <!-- Date -->
                <div class="col-md-2">
                    <label class="form-label">Received Date</label>
                    <input type="date"
                        name="received_date"
                        class="form-control"
                        value="<?= htmlspecialchars($default_received_date) ?>"
                        required>
                </div>


                <!-- Amount -->
                <div class="col-md-2">
                    <label class="form-label">Received Amount (₹)</label>
                    <input type="number" step="0.01" name="received_amount"
                        class="form-control"
                        placeholder="Bank credited amount" required>
                </div>

                <!-- Remarks -->
                <div class="col-md-3">
                    <label class="form-label">Remarks (optional)</label>
                    <input type="text" name="remarks"
                        class="form-control"
                        placeholder="UTR / Reference / Payment ID">
                </div>

                <!-- Save Button -->
                <div class="col-md-2">
                    <button type="submit" name="save_received"
                        class="btn btn-success w-100"
                        onclick="return confirm('Are you sure you want to add this amount?');">
                        Add Amount
                    </button>
                </div>


            </div>

            <!-- ================= ACTION ROW ================= -->
            <div class="row g-2 align-items-end mt-1">

                <!-- Spacer (matches label height) -->
                <div class="col-md-5">
                    <label class="form-label invisible">Action</label>
                </div>

                <!-- Payment History Button -->
                <div class="col-md-2 text-end">
                    <button class="btn btn-outline-secondary btn-sm w-100"
                        type="button"
                        id="togglePaymentHistoryBtn">
                        🔐 Open Payment History
                    </button>
                </div>

            </div>

            <!-- ================= PAYMENT HISTORY (LOCKER MODE) ================= -->
            <div class="collapse mt-3" id="paymentHistoryLocker">
                <div class="card border-secondary">
                    <div class="card-header bg-secondary bg-opacity-10">
                        <strong>📜 Payment History (Confidential)</strong>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0 text-center align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount (₹)</th>
                                        <th>Remarks</th>
                                        <th style="width:120px;">Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (!empty($payments)): ?>
                                        <?php foreach ($payments as $p): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($p['received_date']) ?></td>

                                                <td class="text-success fw-bold">
                                                    ₹<?= number_format($p['received_amount'], 2) ?>
                                                </td>

                                                <td><?= htmlspecialchars($p['remarks']) ?></td>

                                                <td>
                                                    <!-- DELETE (Job 2) -->
                                                    <a href="?delete_payment=<?= $p['id'] ?>"
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Delete this entry?');">
                                                        Delete
                                                    </a>

                                                    <!-- EDIT (Job 3 – next step) -->
                                                    <button
                                                        class="btn btn-sm btn-warning edit-btn"
                                                        data-id="<?= $p['id'] ?>"
                                                        data-date="<?= $p['received_date'] ?>"
                                                        data-amount="<?= $p['received_amount'] ?>"
                                                        data-remarks="<?= htmlspecialchars($p['remarks'], ENT_QUOTES) ?>">
                                                        Edit
                                                    </button>

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-muted">No records found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>

                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!-- ================= END PAYMENT HISTORY ================= -->


        </form>
    </div>
</div>


<!-- score code is begin here  -->

<style>
    /* 7-column score grid */
    .score-grid-7 {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
    }

    @media (max-width: 992px) {
        .score-grid-7 {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 576px) {
        .score-grid-7 {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>



<!-- ================= MONTHLY SCORE BOARD ================= -->

<?php

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

// ✅ Monthly date range (MUST be defined in every file)
$start = "$year-$month-01";
$end   = date('Y-m-t', strtotime($start)); // Last day of current month

function tableCount1($conn, $tableName)
{
    global $start, $end;

    $sql = "SELECT COUNT(*) AS total
            FROM `$tableName`
            WHERE order_date >= '$start'
            AND order_date <= '$end'";

    $result = mysqli_query($conn, $sql);
    if (!$result) return 0;

    $row = mysqli_fetch_assoc($result);
    return (int)$row['total'];
}

$totalmthOrders =
    tableCount1($conn, 'dispatch') +
    tableCount1($conn, 'returned_orders') +
    tableCount1($conn, 'delivered_orders') +
    tableCount1($conn, 'customer_returns') +
    tableCount1($conn, 'spf_claims') +
    tableCount1($conn, 'inward_to_shop');


// 3️⃣ Count function (order_date based)

?>

<div class="container-fluid mb-5">
    <div class="card shadow border-0">
        <div class="card-header bg-info bg-opacity-25 text-dark">
            <h4 class="mb-0">
                📊 Monthly Operational Score Board (<?= $totalmthOrders ?>)
            </h4>
        </div>


        <div class="card-body">

            <?php include 'filter_logic.php'; ?>

            <!-- ================= ROW 1 (7 BOXES) ================= -->
            <div class="score-grid-7 text-center mb-3">

                <div class="card border-primary">
                    <div class="card-body p-2">
                        <small>Total Orders</small>
                        <h5 class="text-primary mb-0"><?= $total_orders ?></h5>

                    </div>
                </div>

                <div class="card border-info">
                    <div class="card-body p-2">
                        <small>In Transit</small>
                        <h5 class="text-info mb-0"><?= $dispatched ?></h5>
                    </div>
                </div>

                <div class="card border-danger">
                    <div class="card-body p-2">
                        <small>Courier Returns</small>
                        <h5 class="text-danger mb-0"><?= $returned_orders ?></h5>
                    </div>
                </div>

                <div class="card border-success">
                    <div class="card-body p-2">
                        <small>Delivered</small>
                        <h5 class="text-success mb-0"><?= $delivered_orders ?></h5>
                    </div>
                </div>

                <div class="card border-danger">
                    <div class="card-body p-2">
                        <small>Customer Returns</small>
                        <h5 class="text-danger mb-0"><?= $customer_returns ?></h5>
                    </div>
                </div>

                <div class="card border-primary">
                    <div class="card-body p-2">
                        <small>SPF Claims</small>
                        <h5 class="text-primary mb-0"><?= $spf_claims ?></h5>
                    </div>
                </div>

                <div class="card border-dark">
                    <div class="card-body p-2">
                        <small>Closed Successfully</small>
                        <h5 class="text-dark mb-0"><?= $inward_to_shop ?></h5>
                    </div>
                </div>

            </div>

            <!-- ================= ROW 2 (7 BOXES) ================= -->
            <div class="score-grid-7 text-center">

                <div class="card border-danger">
                    <div class="card-body p-2">
                        <small>Net Loss</small>
                        <h5 class="text-danger mb-0">
                            ₹<?= number_format($net_loss, 2) ?>
                        </h5>
                    </div>
                </div>



                <div class="card border-success">
                    <div class="card-body p-2">
                        <small>Gross Dispatched Value</small>
                        <h5 class="text-success mb-0">
                            ₹<?= number_format($gross_dispatched_value, 2) ?>
                        </h5>
                    </div>
                </div>




                <div class="card border-danger">
                    <div class="card-body p-2">
                        <small>Total Cou.+Cus. Returns</small>
                        <h5 class="text-danger mb-0">(<?= $total_returns ?>)</h5>
                    </div>
                </div>

                <div class="card border-info">
                    <div class="card-body p-2">
                        <small>Penalty By Cus. Return</small>
                        <h5 class="<?= $disc_class ?>">
                            ₹<?= $disc_display ?>
                        </h5>
                    </div>
                </div>

                <div class="card border-info">
                    <div class="card-body p-2">
                        <small>Created Amount</small>
                        <h5 class="text-info mb-0">
                            ₹<?= number_format($created_amount, 2) ?>
                        </h5>
                    </div>
                </div>

                <div class="card border-info">
                    <div class="card-body p-2">
                        <small>Received Amount</small>
                        <h5 class="text-info mb-0">
                            ₹<?= number_format($received_amount, 2) ?>
                        </h5>
                    </div>
                </div>

                <div class="card border-success">
                    <div class="card-body p-2">
                        <small>Total Net Profit</small>
                        <h5 class="text-success mb-0">
                            ₹<?= number_format($net_profit, 2) ?>
                        </h5>
                    </div>
                </div>

            </div>

        </div>

        <div class="card-footer text-center bg-light">
            <h6 class="mb-0">
                🎯 Monthly Performance Score :
                <span class="badge bg-<?= $badge ?>">
                    <?= $performance_score ?> / 100
                </span>
                <span class="ms-2 text-muted">(<?= $status ?>)</span>

            </h6>
        </div>



        <!-- ================= EDIT PAYMENT MODAL ================= -->
        <div class="modal fade" id="editPaymentModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">✏️ Edit Payment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <form id="editPaymentForm">

                            <input type="hidden" name="id" id="edit_id">

                            <div class="mb-2">
                                <label class="form-label">Date</label>
                                <input type="date" name="received_date" id="edit_date" class="form-control" required>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" name="received_amount" id="edit_amount" class="form-control" required>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Remarks</label>
                                <input type="text" name="remarks" id="edit_remarks" class="form-control">
                            </div>

                        </form>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-success" id="saveEditBtn">Save Changes</button>
                    </div>

                </div>
            </div>
        </div>


    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const btn = document.getElementById('togglePaymentHistoryBtn');
            const locker = document.getElementById('paymentHistoryLocker');

            if (!btn || !locker) return;

            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(locker, {
                toggle: false
            });

            btn.addEventListener('click', function() {

                const isOpen = locker.classList.contains('show');

                // 🔐 ASK PASSWORD ONLY WHEN OPENING
                if (!isOpen) {
                    const password = prompt("Enter password to access Payment History:");
                    const correctPassword = ""; // change later

                    if (password !== correctPassword) {
                        alert("❌ Wrong password");
                        return;
                    }

                    bsCollapse.show();
                    btn.innerHTML = "🔓 Close Payment History";

                } else {
                    // 🔓 CLOSING — NO PASSWORD
                    bsCollapse.hide();
                    btn.innerHTML = "🔐 Open Payment History";

                    // remove ?history=open from URL
                    const url = new URL(window.location);
                    url.searchParams.delete('history');
                    window.history.replaceState({}, '', url);
                }

            });

        });
    </script>


    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const locker = document.getElementById('paymentHistoryLocker');
            const btn = document.getElementById('togglePaymentHistoryBtn');

            if (!locker || !btn) return;

            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(locker, {
                toggle: false
            });

            const params = new URLSearchParams(window.location.search);

            // 🔓 AUTO OPEN IF history=open
            if (params.get('history') === 'open') {
                bsCollapse.show();
                btn.innerHTML = "🔓 Close Payment History";
            }

        });
    </script>


    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const modal = new bootstrap.Modal(document.getElementById('editPaymentModal'));

            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {

                    document.getElementById('edit_id').value = this.dataset.id;
                    document.getElementById('edit_date').value = this.dataset.date;
                    document.getElementById('edit_amount').value = this.dataset.amount;
                    document.getElementById('edit_remarks').value = this.dataset.remarks;

                    modal.show();
                });
            });

        });
    </script>

    <script>
        document.getElementById('saveEditBtn').addEventListener('click', function() {

            if (!confirm("Save changes?")) return;

            const form = document.getElementById('editPaymentForm');
            const formData = new FormData(form);

            fetch('update_payment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload(); // simple & safe (keeps history=open)
                    } else {
                        alert("Update failed");
                    }
                });

        });
    </script>


    <script>
        (function() {
            const params = new URLSearchParams(window.location.search);
            const month = params.get('month_year');

            // If month exists, lock it in localStorage
            if (month) {
                localStorage.setItem('filter_month', month);
            } else {
                // If lost during action reload, restore it
                const saved = localStorage.getItem('filter_month');
                if (saved) {
                    params.set('month_year', saved);
                    window.location.replace(window.location.pathname + '?' + params.toString());
                }
            }
        })();
    </script>



</div>
<!-- ================= END MONTHLY SCORE BOARD ================= -->



<!-- score code is end here  -->