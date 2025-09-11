<?php
require '../config/db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') exit(header("Location: /inventory_system/login.php"));

$err = $msg = "";

// Load all items and their available stock
$items = $db->query("SELECT id, product_name, property_number, quantity FROM items ORDER BY product_name ASC")->fetch_all(MYSQLI_ASSOC);

// Load offices
$offices = $db->query("SELECT id, name FROM offices ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

if ($_POST) {
    $item_id   = intval($_POST['item_id'] ?? 0);
    $qty       = intval($_POST['requested_quantity'] ?? 0);
    $office_id = intval($_POST['office_id'] ?? 0);
    $user_id   = $_SESSION['user_id'];

    // Find selected item (for stock check/display)
    $item_info = null;
    foreach ($items as $i) if ($i['id'] == $item_id) $item_info = $i;

    if ($item_info && $qty > 0 && $office_id > 0) {
        if ($qty > $item_info['quantity']) {
            $err = "You cannot request more than the available stock (" . $item_info['quantity'] . ").";
        } else {
            // Insert into requests (make sure requests table has office_id column)
            $req = $db->prepare("INSERT INTO requests (user_id, item_id, requested_quantity, office_id, is_return) VALUES (?, ?, ?, ?, 0)");
            $req->bind_param('iiii', $user_id, $item_id, $qty, $office_id);
            if ($req->execute()) {
                $msg = "Request submitted for admin approval.";
            } else {
                $err = "Failed to submit request!";
            }
        }
    } else {
        $err = "Please select an item, office, and enter a valid quantity.";
    }
}
?>
<!doctype html>
<html>
<head>
<title>Request Item</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
<div class="container">
    <a class="navbar-brand" href="dashboard.php">User Inventory</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a href="items.php" class="nav-link">All Items</a></li>
        <li class="nav-item"><a href="request_item.php" class="nav-link active">Request Item</a></li>
        <li class="nav-item"><a href="return_item.php" class="nav-link">Return Item</a></li>
        <li class="nav-item"><a href="my_requests.php" class="nav-link">My Requests</a></li>
		<li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
        <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
    </ul>
</div>
</nav>
<div class="container pt-5">
<h4>Request Item</h4>
<?php if($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
<form method="post">
    <div class="form-group">
        <label>Item</label>
        <select name="item_id" class="form-control" required>
            <option value="">Select item...</option>
            <?php foreach($items as $i): ?>
                <option value="<?= $i['id'] ?>"
                    <?= (isset($_POST['item_id']) && $_POST['item_id']==$i['id']) ? "selected" : "" ?>>
                    <?= htmlspecialchars($i['product_name']) ?> (<?= htmlspecialchars($i['property_number']) ?>, Stock: <?= $i['quantity'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Quantity to request</label>
        <input type="number" name="requested_quantity" class="form-control" min="1" required
            value="<?= isset($_POST['requested_quantity']) ? intval($_POST['requested_quantity']) : '' ?>">
    </div>
    <div class="form-group">
        <label>Office designation</label>
        <select name="office_id" class="form-control" required>
            <option value="">Select Office...</option>
            <?php foreach($offices as $o): ?>
                <option value="<?= $o['id'] ?>"
                    <?= (isset($_POST['office_id']) && $_POST['office_id']==$o['id']) ? "selected" : "" ?>>
                    <?= htmlspecialchars($o['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
	
    <button class="btn btn-primary">Submit Request</button>
    <a href="dashboard.php" class="btn btn-secondary">Back</a>
</form>
</div>
</body>
</html>
