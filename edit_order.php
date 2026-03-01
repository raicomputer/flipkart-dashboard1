<?php
include "db.php";

if (!isset($_GET['id'])) {
    die("Invalid request");
}
$sr_no = intval($_GET['id']); // fix: using sr_no as primary key

// Fetch existing order
$result = $conn->query("SELECT * FROM orders WHERE sr_no=$sr_no");
$order = $result->fetch_assoc();

if (!$order) {
    die("Order not found");
}

// Update on form submit
if (isset($_POST['update'])) {
    $order_id = $_POST['order_id'];
    $order_date = $_POST['order_date'];
    $order_type = $_POST['order_type'];
    $product_name = $_POST['product_name'];
    $item_picture = $_POST['item_picture'];
    $quantity = $_POST['quantity'];
    $gross_price = $_POST['gross_price'];
    $customer_name = $_POST['customer_name'];
    $party_location = $_POST['party_location'];

    $sql = "
        UPDATE orders 
        SET 
            order_id='$order_id', 
            order_date='$order_date', 
            order_type='$order_type', 
            product_name='$product_name',
            item_picture='$item_picture',
            quantity='$quantity', 
            gross_price='$gross_price',
            customer_name='$customer_name',
            party_location='$party_location'
        WHERE sr_no=$sr_no
    ";

    if ($conn->query($sql) === TRUE) {
        header("Location: view_orders.php");
        exit;
    } else {
        echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}
?>

<?php include "auth.php"; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Flipkart Seller Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-warning">
                <h4 class="mb-0">Edit Order</h4>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Order ID</label>
                        <input type="text" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Order Date</label>
                        <input type="date" name="order_date" value="<?= htmlspecialchars($order['order_date']) ?>" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Order Type</label>
                        <input type="text" name="order_type" value="<?= htmlspecialchars($order['order_type']) ?>" class="form-control">
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Product Name</label>
                            <select name="product_name" id="product_name" class="form-select" required>
                                <option value="">Select Product</option>

                                <option value="2pin" <?= ($order['product_name'] == '2pin') ? 'selected' : '' ?>>2pin</option>
                                <option value="i3fan" <?= ($order['product_name'] == 'i3fan') ? 'selected' : '' ?>>i3fan</option>
                                <option value="Hddcase 3.0 Black" <?= ($order['product_name'] == 'Hddcase 3.0 Black') ? 'selected' : '' ?>>Hddcase 3.0 Black</option>
                                <option value="Cmos Batt." <?= ($order['product_name'] == 'Cmos Batt.') ? 'selected' : '' ?>>Cmos Batt.</option>
                                <option value="DDr2 2 gb" <?= ($order['product_name'] == 'DDr2 2 gb') ? 'selected' : '' ?>>DDr2 2 gb</option>
                                <option value="Zeb SMPS" <?= ($order['product_name'] == 'Zeb SMPS') ? 'selected' : '' ?>>Zeb SMPS</option>
                                <option value="Mini audio" <?= ($order['product_name'] == 'Mini audio') ? 'selected' : '' ?>>Mini audio</option>
                                <option value="Consti. h61" <?= ($order['product_name'] == 'Consti. h61') ? 'selected' : '' ?>>Consti. h61</option>
                                <option value="USB-WIFI" <?= ($order['product_name'] == 'USB-WIFI') ? 'selected' : '' ?>>USB-WIFI</option>
                                <option value="DIGI.MTR." <?= ($order['product_name'] == 'DIGI.MTR.') ? 'selected' : '' ?>>DIGI.MTR.</option>
                                <option value="HDMIM2M 1.5M" <?= ($order['product_name'] == 'HDMIM2M 1.5M') ? 'selected' : '' ?>>HDMIM2M 1.5M</option>
                                <option value="PC34GB DT" <?= ($order['product_name'] == 'PC34GB DT') ? 'selected' : '' ?>>PC34GB DT</option>
                                <option value="VGAM2M 1.5M" <?= ($order['product_name'] == 'VGAM2M 1.5M') ? 'selected' : '' ?>>VGAM2M 1.5M</option>
                                <option value="8GBPC3LLT" <?= ($order['product_name'] == '8GBPC3LLT') ? 'selected' : '' ?>>8GBPC3LLT</option>
                                <option value="CONSTH81" <?= ($order['product_name'] == 'CONSTH81') ? 'selected' : '' ?>>CONSTH81</option>
                                <option value="3FAN12VCD" <?= ($order['product_name'] == '3FAN12VCD') ? 'selected' : '' ?>>3FAN12VCD</option>
                                <option value="LANC3M" <?= ($order['product_name'] == 'LANC3M') ? 'selected' : '' ?>>LANC3M</option>
                                <option value="PCCABLE" <?= ($order['product_name'] == 'PCCABLE') ? 'selected' : '' ?>>PCCABLE</option>
                                <option value="ZEBH61" <?= ($order['product_name'] == 'ZEBH61') ? 'selected' : '' ?>>ZEBH61</option>
                                <option value="3CFAN" <?= ($order['product_name'] == '3CFAN') ? 'selected' : '' ?>>3CFAN</option>
                                <option value="I33240" <?= ($order['product_name'] == 'I33240') ? 'selected' : '' ?>>I33240</option>
                                 <option value="MOUSE" <?= ($order['product_name'] == 'MOUSE') ? 'selected' : '' ?>>MOUSE</option>
                            </select>


                        </div>


                        <label for="item_picture" class="form-label">Item Picture URL</label>
                        <div class="input-group">
                            <input type="text"
                                id="item_picture"
                                name="item_picture"
                                class="form-control"
                                required
                                value="<?= htmlspecialchars($order['item_picture']) ?>">

                        </div>

                        <!-- Live Preview Section -->
                        <div id="imagePreview" class="mt-3 text-center" style="display:none;">
                            <img id="previewImage" src="" alt="Item Preview" style="max-width:50px; max-height:50px; border:1px solid #ccc; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.15);">
                            <div class="mt-2"></div>
                        </div>


                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" value="<?= htmlspecialchars($order['quantity']) ?>" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Gross Price</label>
                            <input type="number" step="0.01" name="gross_price" value="<?= htmlspecialchars($order['gross_price']) ?>" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Customer Name</label>
                            <input type="text" name="customer_name" value="<?= htmlspecialchars($order['customer_name']) ?>" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Party Location</label>
                            <input type="text" name="party_location" value="<?= htmlspecialchars($order['party_location']) ?>" class="form-control">
                        </div>

                        <button type="submit" name="update" class="btn btn-warning">Update Order</button>
                        <a href="view_orders.php" class="btn btn-secondary">Cancel</a>
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


</body>

</html>