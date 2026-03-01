<?php
session_start();

if (!isset($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit();
}
?>

<?php include "auth.php"; ?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flipkart Seller Dashboard (Local)</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">Flipkart Seller Dashboard</span>
            <a href="logout.php" class="btn btn-light btn-sm">Logout</a>
        </div>
    </nav>

    <div class="container mt-4">

        <!-- Month-Year Dropdown -->
        <form method="get" action="view_orders.php" class="row mb-4">
            <div class="col-md-4">
                <label for="monthYear" class="form-label fw-bold">Select Month-Year</label>
                <select class="form-select" id="monthYear" name="month_year" required>
                    <?php
                    // Generate last 12 months dynamically
                    for ($i = 0; $i < 12; $i++) {
                        $monthYear = date("Y-m", strtotime("-$i months"));
                        $label = date("F Y", strtotime("-$i months"));
                        echo "<option value='$monthYear'>$label</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary w-100">Go</button>
            </div>
        </form>

        <div class="row">
            <!-- Card 1 -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">Add New Order</h5>
                        <p class="card-text">Insert a new Flipkart order into the system.</p>
                        <a href="add_order.php" class="btn btn-success">Add Order</a>
                    </div>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">View Orders</h5>
                        <p class="card-text">See all orders you have saved.</p>
                        <a href="view_orders.php" class="btn btn-primary">View Orders</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
