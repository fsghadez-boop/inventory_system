<?php
require '../config/db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /inventory_system/login.php");
    exit();
}

// Get item ID from URL parameter
$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch item details
$item_result = $db->query("SELECT * FROM items WHERE id = $item_id");
$item = $item_result->fetch_assoc();

// Fetch all categories for dropdown
$categories = $db->query("SELECT * FROM categories")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = $_POST['product_name'];
    $category_id = $_POST['category_id'];
    $quantity = $_POST['quantity'];
    $unit = $_POST['unit'];
    $new_status = $_POST['status'];
    $updated_by = $_SESSION['user_id'];
    
    try {
        $db->begin_transaction();
        
        // Get current status before update
        $old_status = $item['status'];
        
        // Update item
        $stmt = $db->prepare("UPDATE items SET product_name = ?, category_id = ?, quantity = ?, unit = ?, status = ? WHERE id = ?");
        $stmt->bind_param("siissi", $product_name, $category_id, $quantity, $unit, $new_status, $item_id);
        $stmt->execute();
        
        // If status changed, track the change
        if ($old_status != $new_status) {
            $stmt = $db->prepare("INSERT INTO inventory_item_tracker (item_id, from_status, to_status, moved_by, notes) 
                                  VALUES (?, ?, ?, ?, ?)");
            $notes = "Status changed via edit";
            $stmt->bind_param("isssi", $item_id, $old_status, $new_status, $updated_by, $notes);
            $stmt->execute();
            
            // Log the status change in the audit log
            $log_detail = "Edited item " . $item['property_number'] . " status: " . $old_status . " -> " . $new_status;
        } else {
            // Log standard edit without status change
            $log_detail = "Edited item " . $item['property_number'];
        }
        
        // Log the action
        $stmt = $db->prepare("INSERT INTO audit_logs (action, performed_by, item_id, log_details) VALUES (?, ?, ?, ?)");
        $action = "edit";
        $stmt->bind_param("siis", $action, $updated_by, $item_id, $log_detail);
        $stmt->execute();
        
        $db->commit();
        $success = "Item updated successfully!";
        
        // Refresh item data
        $item_result = $db->query("SELECT * FROM items WHERE id = $item_id");
        $item = $item_result->fetch_assoc();
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error updating item: " . $db->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
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
            <li class="nav-item"><a href="condemn.php" class="nav-link">Condemn Items</a></li>
            <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container" style="margin-top: 80px;">
    <h2>Edit Item</h2>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($item): ?>
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label for="property_number">Property Number</label>
                    <input type="text" class="form-control" id="property_number" 
                           value="<?php echo htmlspecialchars($item['property_number']); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label for="product_name">Product Name</label>
                    <input type="text" class="form-control" id="product_name" name="product_name" 
                           value="<?php echo htmlspecialchars($item['product_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select class="form-control" id="category_id" name="category_id" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" 
                                <?php echo $category['id'] == $item['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" class="form-control" id="quantity" name="quantity" 
                           value="<?php echo htmlspecialchars($item['quantity']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="unit">Unit</label>
                    <select class="form-control" id="unit" name="unit" required>
                        <option value="pieces" <?php echo $item['unit'] == 'pieces' ? 'selected' : ''; ?>>Pieces</option>
                        <option value="boxes" <?php echo $item['unit'] == 'boxes' ? 'selected' : ''; ?>>Boxes</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status" required>
                        <option value="brand_new" <?php echo $item['status'] == 'brand_new' ? 'selected' : ''; ?>>Brand New</option>
                        <option value="for_replacement" <?php echo $item['status'] == 'for_replacement' ? 'selected' : ''; ?>>For Replacement</option>
                        <option value="for_condemn" <?php echo $item['status'] == 'for_condemn' ? 'selected' : ''; ?>>For Condemn</option>
                        <option value="condemned" <?php echo $item['status'] == 'condemned' ? 'selected' : ''; ?>>Condemned</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Update Item</button>
                <a href="items.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-danger">Item not found!</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>