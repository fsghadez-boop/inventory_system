<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') exit(header("Location: /inventory_system/login.php"));
$uid = $_SESSION['user_id'];
$rows = $db->query("SELECT i.property_number, i.product_name, uh.quantity
    FROM user_holdings uh
    LEFT JOIN items i ON uh.item_id = i.id
    WHERE uh.user_id=$uid AND uh.quantity > 0
    ORDER BY i.product_name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
<title>My Items</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
  <div class="container">
    <a class="navbar-brand" href="dashboard.php">User Inventory</a>
  </div>
</nav>
<div class="container pt-5">
  <h4>My Items</h4>
  <table class="table table-bordered">
    <thead><tr>
      <th>Property #</th>
      <th>Item Name</th>
      <th>Quantity (in hand)</th>
    </tr></thead>
    <tbody>
      <?php foreach($rows as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['property_number']) ?></td>
        <td><?= htmlspecialchars($row['product_name']) ?></td>
        <td><?= $row['quantity'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <a href="dashboard.php" class="btn btn-secondary">Back</a>
</div>
</body>
</html>
