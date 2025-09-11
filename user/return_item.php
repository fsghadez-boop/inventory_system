<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') exit(header("Location: /inventory_system/login.php"));

$err = $msg = "";
$uid = $_SESSION['user_id'];
$items = $db->query(
    "SELECT i.id, i.product_name, i.property_number, uh.quantity as holding_qty
     FROM user_holdings uh
     LEFT JOIN items i ON uh.item_id = i.id
     WHERE uh.user_id=$uid AND uh.quantity > 0"
)->fetch_all(MYSQLI_ASSOC);

if ($_POST) {
    $item_id = intval($_POST['item_id']);
    $qty = intval($_POST['requested_quantity']);
    // Find holding qty
    $holdq = 0;
    foreach ($items as $it) {
        if ($it['id'] == $item_id) $holdq = $it['holding_qty'];
    }
    if ($qty > 0 && $qty <= $holdq) {
        $user_id = $_SESSION['user_id'];
        $req = $db->prepare("INSERT INTO requests (user_id, item_id, requested_quantity, is_return) VALUES (?, ?, ?, 1)");
        $req->bind_param('iii', $user_id, $item_id, $qty);
        if ($req->execute()) {
            $msg = "Return request submitted for approval.";
        } else {
            $err = "Failed to submit return request.";
        }
    } else {
        $err = "You can't return more than you have ($holdq).";
    }
}
?>
<!doctype html>
<html>
<head>
<title>Return Item</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container"><a class="navbar-brand" href="dashboard.php">User Inventory</a></div>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
<div class="container">
    <a class="navbar-brand" href="dashboard.php">User Inventory</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a href="items.php" class="nav-link">All Items</a></li>
        <li class="nav-item"><a href="request_item.php" class="nav-link">Request Item</a></li>
        <li class="nav-item"><a href="return_item.php" class="nav-link active">Return Item</a></li>
        <li class="nav-item"><a href="my_requests.php" class="nav-link">My Requests</a></li>
		<li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
        <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
    </ul>
</div>
</nav>
<div class="container pt-3">
    <h4>Return Item</h4>
    <?php if($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Item</label>
            <select name="item_id" class="form-control" required>
                <option value="">Select item...</option>
                <?php foreach($items as $i): ?>
                    <option value="<?= $i['id'] ?>">
                        <?= htmlspecialchars($i['product_name']) ?> (<?= htmlspecialchars($i['property_number']) ?>, Have: <?= $i['holding_qty'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Quantity to return</label>
            <input type="number" name="requested_quantity" class="form-control" min="1" required>
        </div>
        <button class="btn btn-primary">Submit Return Request</button>
        <a href="dashboard.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
