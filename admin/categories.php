<?php
require '../config/db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') exit(header("Location: /inventory_system/login.php"));
$err = "";
$msg = "";

// Handle REMOVE CATEGORY
if (isset($_GET['delcat'])) {
    $cat_id = intval($_GET['delcat']);
    // Check if category is in use
    $item_check = $db->prepare("SELECT COUNT(*) FROM items WHERE category_id=?");
    $item_check->bind_param("i", $cat_id);
    $item_check->execute();
    $item_check->bind_result($item_count);
    $item_check->fetch();
    $item_check->close();

    if ($item_count > 0) {
        $err = "Cannot delete: There are $item_count item(s) using this category.";
    } else {
        $del = $db->prepare("DELETE FROM categories WHERE id=?");
        $del->bind_param("i", $cat_id);
        if ($del->execute() && $del->affected_rows > 0) {
            $msg = "Category deleted successfully.";
        } else {
            $err = "Failed to delete category, or category not found!";
        }
    }
}

// Handle NEW CATEGORY
if ($_POST && isset($_POST['new_category'])) {
    $catname = trim($_POST['new_category']);
    if ($catname != '') {
        $stmt = $db->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
        $stmt->bind_param('s', $catname);
        if (!$stmt->execute()) $err = "Failed to add category!";
        else $msg = "New category added!";
    }
}

$cats = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
<title>Manage Item Categories</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Admin Inventory</a>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item"><a href="items.php" class="nav-link">Items</a></li>
            <li class="nav-item"><a href="categories.php" class="nav-link active">Manage Categories</a></li>
            <li class="nav-item"><a href="offices.php" class="nav-link">Manage Offices</a></li>
            <li class="nav-item"><a href="requests.php" class="nav-link">Requests</a></li>
            <li class="nav-item"><a href="audit_logs.php" class="nav-link">Audit Logs</a></li>
			<li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
            <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
        </ul>
    </div>
</nav>
<div class="container pt-5">
    <h4 class="mt-4">Item Categories</h4>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <form class="form-inline mb-3" method="POST">
        <input name="new_category" class="form-control mr-2" placeholder="New Category" required>
        <button class="btn btn-primary">Add Category</button>
    </form>
    <ul class="list-group">
        <?php foreach($cats as $c): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= htmlspecialchars($c['name']) ?>
            <a href="?delcat=<?= $c['id'] ?>" class="btn btn-danger btn-sm"
               onclick="return confirm('Are you sure you want to remove this category?');">
               Delete
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
</body>
</html>
