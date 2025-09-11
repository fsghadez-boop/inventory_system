<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') exit(header("Location: /inventory_system/login.php"));
?>
<!doctype html>
<html>
<head>
<title>User Dashboard</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
<div class="container">
    <a class="navbar-brand" href="dashboard.php">User Inventory</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a href="items.php" class="nav-link">All Items</a></li>
        <li class="nav-item"><a href="request_item.php" class="nav-link">Request Item</a></li>
        <li class="nav-item"><a href="return_item.php" class="nav-link">Return Item</a></li>
        <li class="nav-item"><a href="my_requests.php" class="nav-link">My Requests</a></li>
		<li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
        <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
    </ul>
</div>
</nav>
<div class="container pt-5">
    <div class="jumbotron mt-5">
        <h2>Welcome, <?= htmlspecialchars($_SESSION['user_id']) ?>!</h2>
        <p>You can view items, request withdrawals and returns, and track your inventory actions here.</p>
        <ul>
            <li><strong>All Items:</strong> See the full item inventory and current available stock.</li>
            <li><strong>Request Item:</strong> Request to withdraw items (admin approval needed).</li>
            <li><strong>Return Item:</strong> Submit a return for items (admin will increase stock on approval).</li>
            <li><strong>My Requests:</strong> View the statuses of your withdrawal or return requests.</li>
        </ul>
    </div>
</div>
</body>
</html>
