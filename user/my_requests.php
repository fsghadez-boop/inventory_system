<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') exit(header("Location: /inventory_system/login.php"));
$uid = $_SESSION['user_id'];
$q = "SELECT r.*, i.product_name, i.property_number
      FROM requests r
      LEFT JOIN items i ON r.item_id=i.id
      WHERE r.user_id=$uid
      ORDER BY r.created_at DESC";
$requests = $db->query($q)->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
<title>My Requests</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"></head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
<div class="container"><a class="navbar-brand" href="dashboard.php">User Inventory</a></div>
</nav>
<div class="container pt-5">
<h4>My Requests</h4>
<table class="table table-bordered">
<thead><tr>
    <th>ID</th>
    <th>Type</th>
    <th>Property #</th>
    <th>Name</th>
    <th>Quantity</th>
    <th>Status</th>
    <th>Requested at</th>
</tr></thead>
<tbody>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
<div class="container">
    <a class="navbar-brand" href="dashboard.php">User Inventory</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a href="items.php" class="nav-link">All Items</a></li>
        <li class="nav-item"><a href="request_item.php" class="nav-link">Request Item</a></li>
        <li class="nav-item"><a href="return_item.php" class="nav-link">Return Item</a></li>
        <li class="nav-item"><a href="my_requests.php" class="nav-link active">My Requests</a></li>
		<li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
        <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
    </ul>
</div>
</nav>
<div class="container pt-1">
    <?php foreach($requests as $r): ?>
    <tr>
        <td><?= $r['id'] ?></td>
        <td>
        <?php
            $is_return = isset($r['is_return']) ? $r['is_return'] : 0;
            echo $is_return
              ? "<span class='badge badge-info'>Return</span>"
              : "<span class='badge badge-warning'>Withdraw</span>";
        ?>
        </td>
        <td><?= htmlspecialchars($r['property_number']) ?></td>
        <td><?= htmlspecialchars($r['product_name']) ?></td>
        <td><?= $r['requested_quantity'] ?></td>
        <td>
            <?php
            if ($r['status'] == 'pending') echo "<span class='badge badge-warning'>Pending</span>";
            elseif ($r['status'] == 'approved') echo "<span class='badge badge-success'>Approved</span>";
            else echo "<span class='badge badge-danger'>Rejected</span>";
            ?>
        </td>
        <td><?= $r['created_at'] ?></td>
    </tr>
    <?php endforeach; ?>
</tbody>
</table>
<a href="dashboard.php" class="btn btn-secondary">Back</a>
</div>
</body>
</html>
