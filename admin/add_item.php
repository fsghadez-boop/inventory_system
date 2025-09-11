<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /inventory_system/login.php");
    exit;
}

$cats = $db->query("SELECT * FROM categories where category_type = 'supply' ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $property_number = trim($_POST['property_number'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $unit = $_POST['unit'] ?? '';
    $status = $_POST['status'] ?? '';
    $created_by = $_SESSION['user_id'];

    // Validate status value
    $valid_statuses = ['brand_new', 'for_replacement', 'for_condemn'];
    if (!in_array($status, $valid_statuses)) {
        $err = "Invalid status selected.";
    } else {
        // Check unique property_number
        $check = $db->prepare("SELECT id FROM items WHERE property_number=?");
        $check->bind_param("s", $property_number);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $err = "Property number already exists!";
        } else {
            // Generate QR code
            require_once '../vendor/phpqrcode/qrlib.php';
            $qrDir = "../assets/qrcodes/";
            if (!file_exists($qrDir)) mkdir($qrDir, 0777, true);
            $qrFile = $qrDir . $property_number . ".png";
            QRcode::png($property_number, $qrFile);

            // Prepare SQL with correct parameter types!
            $stmt = $db->prepare(
                "INSERT INTO items 
                (property_number, product_name, category_id, qr_code_path, quantity, unit, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            // s = string, i = integer
            // s  s   i   s   i  s   s  i
            $stmt->bind_param(
                "ssissssi", 
                $property_number, 
                $product_name, 
                $category_id, 
                $qrFile, 
                $quantity, 
                $unit, 
                $status, 
                $created_by
            );

            if ($stmt->execute()) {
                $itemid = $db->insert_id;
                // Audit trail
                $action = "add";
                $details = "Added item $product_name (Property: $property_number), Qty: $quantity $unit, Status: $status";
                $stmt2 = $db->prepare("INSERT INTO audit_logs (item_id, action, performed_by, log_details) VALUES (?, ?, ?, ?)");
                $stmt2->bind_param('isis', $itemid, $action, $created_by, $details);
                $stmt2->execute();
                header("Location: items.php?added=1");
                exit;
            } else {
                $err = "Failed to add item! DB error: " . $db->error;
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
<title>Add Item</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Admin Inventory</a>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item"><a href="items.php" class="nav-link active">Items</a></li>
            <li class="nav-item"><a href="categories.php" class="nav-link">Categories</a></li>
            <li class="nav-item"><a href="requests.php" class="nav-link">Requests</a></li>
            <li class="nav-item"><a href="audit_logs.php" class="nav-link">Audit Logs</a></li>
            <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
        </ul>
    </div>
</nav>
<div class="container pt-5">
    <h4 class="mt-4">Add New Supply</h4>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Property Number</label>
            <input name="property_number" class="form-control" required value="<?= isset($_POST['property_number']) ? htmlspecialchars($_POST['property_number']) : '' ?>">
        </div>
        <div class="form-group">
            <label>Product Name</label>
            <input name="product_name" class="form-control" required value="<?= isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : '' ?>">
        </div>
        <div class="form-group">
            <label>Category</label>
            <select name="category_id" class="form-control" required>
                <option value="">Select Category</option>
                <?php foreach($cats as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id']==$c['id']) ? "selected" : "" ?>>
                    <?= htmlspecialchars($c['name']) ?>
                  </option>
                <?php endforeach; ?>
            </select>
            <small><a href="manage_supplies_category.php">Add new category</a></small>
        </div>
        <div class="form-group">
            <label>Quantity</label>
            <input name="quantity" type="number" min="1" class="form-control" required value="<?= isset($_POST['quantity']) ? intval($_POST['quantity']) : '' ?>">
        </div>
        <div class="form-group">
            <label>Unit</label>
            <select name="unit" class="form-control">
                <option value="pieces" <?= (isset($_POST['unit']) && $_POST['unit']=='pieces') ? "selected" : "" ?>>Pieces</option>
                <option value="boxes" <?= (isset($_POST['unit']) && $_POST['unit']=='boxes') ? "selected" : "" ?>>Boxes</option>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="brand_new" <?= (isset($_POST['status']) && $_POST['status']=='brand_new') ? "selected" : "" ?>>Brand New</option>
                <option value="for_replacement" <?= (isset($_POST['status']) && $_POST['status']=='for_replacement') ? "selected" : "" ?>>For Replacement</option>
                <option value="for_condemn" <?= (isset($_POST['status']) && $_POST['status']=='for_condemn') ? "selected" : "" ?>>Condemn</option>
            </select>
        </div>
        <button class="btn btn-success">Add Item</button>
        <a href="items.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
