<?php
require '../config/db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /inventory_system/login.php");
    exit();
}

// Fetch brand new assets for dropdown
$assets = $db->query("SELECT i.*, c.name as category_name, c.category_type 
                      FROM items i 
                      JOIN categories c ON i.category_id = c.id 
                      WHERE i.status = 'brand_new' AND c.category_type = 'asset'")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = $_POST['item_id'];
    $memorandum_receipt = $_POST['memorandum_receipt'];
    $reason = $_POST['reason'];
    $condemned_by = $_SESSION['user_id'];
    
    try {
        $db->begin_transaction();
        
        // Insert into condemnations table
        $stmt = $db->prepare("INSERT INTO condemnations (item_id, memorandum_receipt, reason, condemned_by) 
                              VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $item_id, $memorandum_receipt, $reason, $condemned_by);
        $stmt->execute();
        
        // Update item status to condemned
        $stmt = $db->prepare("UPDATE items SET status = 'condemned' WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        
        // Track the status change
        $notes = "Condemned: " . $reason;
        $stmt = $db->prepare("INSERT INTO inventory_item_tracker (item_id, from_status, to_status, moved_by, notes) 
                              VALUES (?, 'brand_new', 'condemned', ?, ?)");
        $stmt->bind_param("iis", $item_id, $condemned_by, $notes);
        $stmt->execute();
        
        // Log the action
        $item_result = $db->query("SELECT property_number FROM items WHERE id = $item_id");
        $item_data = $item_result->fetch_assoc();
        $log_detail = "Condemned item " . $item_data['property_number'] . ": " . $reason;
        
        $stmt = $db->prepare("INSERT INTO audit_logs (action, performed_by, item_id, log_details) VALUES (?, ?, ?, ?)");
        $action = "condemn";
        $stmt->bind_param("siis", $action, $condemned_by, $item_id, $log_detail);
        $stmt->execute();
        
        $db->commit();
        $success = "Item successfully condemned!";
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error condemning item: " . $db->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Condemn Item</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <script>
    function updateItemInfo() {
        var itemId = document.getElementById('item_id').value;
        if (itemId) {
            // AJAX request to get item details
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_item_details.php?id=' + itemId, true);
            xhr.onload = function() {
                if (this.status == 200) {
                    var item = JSON.parse(this.responseText);
                    document.getElementById('category').value = item.category_name || '';
                    document.getElementById('brand').value = item.brand || '';
                    document.getElementById('model').value = item.model || '';
                    document.getElementById('mr').value = item.memorandum_receipt || '';
                }
            };
            xhr.send();
        } else {
            document.getElementById('category').value = '';
            document.getElementById('brand').value = '';
            document.getElementById('model').value = '';
            document.getElementById('mr').value = '';
        }
    }
    </script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Admin Inventory</a>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item"><a href="items.php" class="nav-link">Items</a></li>
            <li class="nav-item"><a href="manage_assets_category.php" class="nav-link">Asset Categories</a></li>
            <li class="nav-item"><a href="manage_supplies_category.php" class="nav-link">Supply Categories</a></li>
            <li class="nav-item"><a href="offices.php" class="nav-link">Manage Offices</a></li>
            <li class="nav-item"><a href="requests.php" class="nav-link">Requests</a></li>
            <li class="nav-item"><a href="audit_logs.php" class="nav-link">Audit Logs</a></li>
            <li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
            <li class="nav-item"><a href="condemn.php" class="nav-link active">Condemn Items</a></li>
            <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container" style="margin-top: 80px;">
    <h2>Condemn Item</h2>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label for="item_id">Property Number (P/N)</label>
                    <select class="form-control" id="item_id" name="item_id" required onchange="updateItemInfo()">
                        <option value="">Select an asset</option>
                        <?php foreach ($assets as $asset): ?>
                        <option value="<?php echo $asset['id']; ?>">
                            <?php echo htmlspecialchars($asset['property_number'] . ' - ' . $asset['product_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" class="form-control" id="category" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="brand">Brand</label>
                            <input type="text" class="form-control" id="brand" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="model">Model</label>
                            <input type="text" class="form-control" id="model" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="mr">Memorandum Receipt (MR)</label>
                            <input type="text" class="form-control" id="mr" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="reason">Reason for Disposal</label>
                    <select class="form-control" id="reason" name="reason" required>
                        <option value="">Select a reason</option>
                        <option value="The above items are obsolete and no longer available at the market.">
                            The above items are obsolete and no longer available at the market.
                        </option>
                        <option value="The above items are found beyond economical repair.">
                            The above items are found beyond economical repair.
                        </option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-danger">Condemn Item</button>
                <a href="items.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>