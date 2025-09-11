<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') exit(header("Location: /inventory_system/login.php"));
?>
<!doctype html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="#">Admin Inventory</a>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item"><a href="items.php" class="nav-link">Items</a></li>
            <button id="manageCategoriesBtn">Manage Categories</button>
			<div id="subCategories" style="display: none;">
			<button onclick="window.location.href='manage_supplies_category.php'">Manage Supplies</button>
			<button onclick="window.location.href='manage_assets_category.php'">Manage Assets</button>
			<button onclick="window.location.href='offices.php'">Manage Office</button>
			</div>
<script>
    document.getElementById('manageCategoriesBtn').onclick = function() {
        var subCategories = document.getElementById('subCategories');
        subCategories.style.display = (subCategories.style.display === 'none') ? 'block' : 'none';
    };
</script>

            <li class="nav-item"><a href="requests.php" class="nav-link">Requests</a></li>
            <li class="nav-item"><a href="audit_logs.php" class="nav-link">Audit Logs</a></li>
			<li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
            <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
        </ul>
    </div>
</nav>
<div class="container pt-5">
    <div class="jumbotron mt-5">
        <h2>Welcome, Admin!</h2>
        <p>Manage your inventory system here.</p>
		<a href="register_user.php" class="btn btn-primary">+ Register New User</a>
<a href="users.php" class="btn btn-secondary ml-2">Manage Users</a>
<a href="edit_profile.php" class="btn btn-warning ml-2">Edit My Profile</a>

    </div>
</div>
</body>
</html>
