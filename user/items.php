<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') exit(header("Location: /inventory_system/login.php"));

$q = "SELECT i.*, c.name as category FROM items i LEFT JOIN categories c ON i.category_id=c.id ORDER BY i.id DESC";
$items = $db->query($q)->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
<title>Items (User)</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"></head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
<div class="container">
    <a class="navbar-brand" href="dashboard.php">User Inventory</a>
    <!-- navbar -->
</div>
</nav>
<div class="container pt-5">
<h4>Inventory Items</h4>
<table class="table table-bordered table-hover"><thead>
<tr>
    <th>#</th><th>Property Number</th><th>QR</th><th>Name</th>
    <th>Cat</th><th>Qty</th><th>Unit</th><th>Status</th><th>Created</th>
</tr></thead>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
<div class="container">
    <a class="navbar-brand" href="dashboard.php">User Inventory</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a href="items.php" class="nav-link active">All Items</a></li>
        <li class="nav-item"><a href="request_item.php" class="nav-link">Request Item</a></li>
        <li class="nav-item"><a href="return_item.php" class="nav-link">Return Item</a></li>
        <li class="nav-item"><a href="my_requests.php" class="nav-link">My Requests</a></li>
		<li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
        <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
    </ul>
</div>
</nav>
<?php foreach ($items as $i): ?>
<tr>
    <td><?= $i['id'] ?></td>
    <td><?= htmlspecialchars($i['property_number']) ?></td>
    <td><?php if($i['qr_code_path']): ?><img class="qrcode-img" src="<?= $i['qr_code_path'] ?>"><?php endif; ?></td>
    <td><?= htmlspecialchars($i['product_name']) ?></td>
    <td><?= htmlspecialchars($i['category']) ?></td>
    <td><?= $i['quantity'] ?></td>
    <td><?= $i['unit'] ?></td>
    <td>
        <?php
        if ($i['status'] == 'brand_new') echo "<span class='badge badge-success'>Brand New</span>";
        else if ($i['status'] == 'for_replacement') echo "<span class='badge badge-warning'>Replacement</span>";
        else echo "<span class='badge badge-danger'>Condemn</span>";
        ?>
    </td>
    <td><?= $i['created_at'] ?></td>
</tr>
<?php endforeach; ?>
</body></table>
</div>
</body>
</html>
