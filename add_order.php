<?php include "auth.php"; ?>


<?php include "db.php"; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Order - Flipkart Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #preview {
            max-width: 120px;
            max-height: 120px;
            display: none;
            margin-top: 10px;
        }
    </style>
</head>

<body class="bg-light">

    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Flipkart Seller Dashboard</a>
            <a href="logout.php" class="btn btn-light btn-sm">Logout</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card shadow-sm">
            <!-- Centered Title -->
            <div class="card-header bg-success text-white text-center">
                <h4 class="mb-0 fw-bold">Add New Order</h4>
            </div>

            <div class="card-body">
                <form method="post">
                    <!-- 3x3 Grid Form -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label"><strong>Order ID</strong></label>
                            <input type="text" name="order_id" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><strong>Order Date</strong></label>
                            <input type="date" name="order_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><strong>Order Type</strong></label>
                            <select name="order_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Prepaid">Prepaid</option>
                                <option value="COD" selected>COD</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><strong>Product Name</strong></label>
                            <select name="product_name" id="product_name" class="form-select" required>
                                <option value="">Select Product</option>
                                <option value="2pin">2pin</option>
                                <option value="i3fan">i3fan</option>
                                <option value="Hddcase 3.0 Black">Hddcase 3.0 Black</option>
                                <option value="Cmos Batt.">Cmos Batt.</option>
                                <option value="DDr2 2 gb">DDr2 2 gb</option>
                                <option value="Zeb SMPS">Zeb SMPS</option>
                                <option value="Mini audio">Mini audio</option>
                                <option value="Consti. h61">Consti. h61</option>
                                <option value="USB-WIFI">USB-WIFI</option>
                                <option value="DIGI.MTR.">DIGI.MTR.</option>
                                <option value="HDMIM2M 1.5M">HDMIM2M 1.5M</option>
                                <option value="PC34GB DT">PC34GB DT</option>
                                <option value="VGAM2M 1.5M">VGAM2M 1.5M</option>
                                <option value="8GBPC3LLT">8GBPC3LLT</option>
                                <option value="CONSTH81">CONSTH81</option>
                                <option value="3FAN12VCD">3FAN12VCD</option>
                                <option value="LANC3M">LANC3M</option>
                                <option value="PCCABLE">PCCABLE</option>
                                <option value="ZEBH61">ZEBH61</option>
                                <option value="3CFAN">3CFAN</option>
                                <option value="I33240">I33240</option>
                                <option value="MOUSE">MOUSE</option>
                                "MOUSE":
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><strong>Quantity</strong></label>
                            <input type="number" name="quantity" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><strong>Gross Price</strong> (Product Cost + Packing Rs=10)</label>
                            <input type="number" step="0.01" name="gross_price" class="form-control" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 mb-3">
                            <label for="item_picture" class="form-label"><strong>Item Picture URL</strong></label>
                            <div class="input-group">
                                <input type="text" id="item_picture" name="item_picture" class="form-control" placeholder="uploads/*.png">
                            </div>

                            <!-- Live Preview Section -->
                            <div id="imagePreview" class="mt-3 text-center" style="display:none;">
                                <img id="previewImage" src="" alt="Item Preview" style="max-width:50px; max-height:50px; border:1px solid #ccc; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.15);">
                                <div class="mt-2"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><strong>Customer Name</strong></label>
                            <input type="text" name="customer_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><strong>Party Location</strong></label>
                            <input type="text" name="party_location" class="form-control" required>
                        </div>
                    </div>

                    <!-- Warning Message -->
                    <div class="alert alert-warning text-center mt-4" role="alert">
                        ⚠️<strong> Please verify all order details carefully before saving.</strong>
                    </div>

                    <!-- Buttons -->
                    <div class="text-end mt-3">
                        <button type="submit" name="save" class="btn btn-success">Save Order</button>
                        <a href="view_orders.php" class="btn btn-secondary dark bg-primary">View Orders</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS Preview Script -->
    <script>
        document.getElementById('item_picture').addEventListener('input', function() {
            const img = document.getElementById('preview');
            img.src = this.value || '';
        });
    </script>

    <script>
        const productImages = {
            "2pin": "uploads/2pin.png",
            "i3fan": "uploads/i3cpufan.png",
            "Hddcase 3.0 Black": "uploads/hddc.png",
            "Cmos Batt.": "uploads/cmos.png",
            "DDr2 2 gb": "uploads/ddr22gb.png",
            "Zeb SMPS": "uploads/zebsmps.png",
            "Mini audio": "uploads/miniaudio.png",
            "Consti. h61": "uploads/consh61.png",
            "USB-WIFI": "uploads/wifi.png",
            "DIGI.MTR.": "uploads/digimtr.png",
            "HDMIM2M 1.5M": "uploads/hdmim2m1.5.png",
            "PC34GB DT": "uploads/pc34gbdt.png",
            "VGAM2M 1.5M": "uploads/vgam2m1.5.png",
            "8GBPC3LLT": "uploads/8gbpc3l.png",
            "CONSTH81": "uploads/consth81.png",
            "3FAN12VCD": "uploads/3fan.png",
            "LANC3M": "uploads/lanc3m.png",
            "PCCABLE": "uploads/pccable.png",
            "ZEBH61": "uploads/zebh61.png",
            "3CFAN": "uploads/3cfan.png",
            "I33240": "uploads/i33240.png",
            "MOUSE": "uploads/mouse.png",
        };

        function showPreview(path) {
            const preview = document.getElementById('imagePreview');
            const img = document.getElementById('previewImage');

            if (!path) {
                preview.style.display = 'none';
                return;
            }

            if (!path.startsWith('http')) {
                path = window.location.origin + '/flipkart_dashboard/' + path.replace(/^\/+/, '');
            }

            img.src = path;
            img.onload = () => preview.style.display = 'block';
            img.onerror = () => {
                preview.style.display = 'none';
                alert('⚠️ Image not found at: ' + path);
            };
        }

        document.getElementById('item_picture').addEventListener('input', function() {
            showPreview(this.value.trim());
        });

        document.getElementById('product_name').addEventListener('change', function() {
            const product = this.value;
            const path = productImages[product] || '';
            document.getElementById('item_picture').value = path;
            showPreview(path);
        });
    </script>

<?php
if (isset($_POST['save'])) {
    $order_id = $_POST['order_id'];
    $order_date = $_POST['order_date'];
    $order_type = $_POST['order_type'];
    $product_name = $_POST['product_name'];
    $quantity = $_POST['quantity'] ?? 1;
    $gross_price = $_POST['gross_price'];
    $item_picture = $_POST['item_picture'] ?? '';
    $customer_name = $_POST['customer_name'];
    $party_location = $_POST['party_location'] ?? '';

    // Prevent duplicate order_id
    $check = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ?");
    $check->bind_param("s", $order_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<div id='autoAlert' class='container mt-3'><div class='alert alert-danger'>❌ Order ID already exists.❌ Please use a unique ID. ❌</div></div>";
    } else {

// ==========================
// GENERATE MONTH-WISE SR NO
// ==========================

// Use the order date; if not set, use today
$order_date = $_POST['order_date'] ?? date("Y-m-d");

// Extract year + month
$year  = date("Y", strtotime($order_date));
$month = date("m", strtotime($order_date));

// Prefix for search (example: 202501)
$prefix = $year . $month;

// Find last SR NO for this month
$stmt = $conn->prepare("
    SELECT sr_no 
    FROM orders 
    WHERE sr_no LIKE CONCAT(?, '%')
    ORDER BY sr_no DESC 
    LIMIT 1
");
$stmt->bind_param("s", $prefix);
$stmt->execute();
$res = $stmt->get_result();

// Generate next serial number (3 digits)
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();

    // FIX: Convert string to number before +1
    $last_serial = intval(substr($row['sr_no'], 6));

    $new_serial = str_pad($last_serial + 1, 3, "0", STR_PAD_LEFT);
} else {
    $new_serial = "001";
}


// Final SR NO (example: 202501001)
$sr_no = $prefix . $new_serial;


        /* ---------------------------------------------------
           STEP 2 — INSERT with manual sr_no
        ----------------------------------------------------*/
        $stmt = $conn->prepare("
            INSERT INTO orders 
            (sr_no, order_id, order_date, order_type, product_name, quantity, gross_price, item_picture, customer_name, party_location)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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

        if ($stmt->execute()) {
            echo "<div id='autoAlert' class='container mt-3'><div class='alert alert-success'>✅ Order saved successfully!</div></div>";
        } else {
            echo "<div id='autoAlert' class='container mt-3'><div class='alert alert-danger'>❌ Error: ".$stmt->error."</div></div>";
        }

        $stmt->close();
    }

    $check->close();
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    setTimeout(() => {
        const alertBox = document.getElementById('autoAlert');
        if (alertBox) {
            alertBox.style.transition = "opacity 0.5s ease";
            alertBox.style.opacity = 0;
            setTimeout(() => alertBox.remove(), 500);
        }
    }, 3000);
</script>

</body>
</html>
