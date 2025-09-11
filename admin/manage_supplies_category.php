<?php
require '../config/db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /inventory_system/login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = $_POST['name'];
        $stmt = $db->prepare("INSERT INTO categories (name, category_type) VALUES (?, 'supply')");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            $success = "Supply category added successfully!";
        } else {
            $error = "Error adding category: " . $db->error;
        }
    } elseif (isset($_POST['delete_category'])) {
        $id = $_POST['id'];
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "Supply category deleted successfully!";
        } else {
            $error = "Error deleting category: " . $db->error;
        }
    }
}

// Fetch all supply categories
$categories = $db->query("SELECT * FROM categories WHERE category_type = 'supply'")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Supply Categories</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Admin Inventory</a>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item"><a href="items.php" class="nav-link">Items</a></li>
            <li class="nav-item"><a href="manage_assets_category.php" class="nav-link">Asset Categories</a></li>
            <li class="nav-item"><a href="manage_supplies_category.php" class="nav-link active">Supply Categories</a></li>
            <li class="nav-item"><a href="offices.php" class="nav-link">Manage Offices</a></li>
            <li class="nav-item"><a href="requests.php" class="nav-link">Requests</a></li>
            <li class="nav-item"><a href="audit_logs.php" class="nav-link">Audit Logs</a></li>
            <li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
            <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container" style="margin-top: 80px;">
    <h2>Manage Supply Categories</h2>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Add New Supply Category</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="name">Category Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Existing Supply Categories</h5>
                </div>
                <div class="card-body">
                    <?php if (count($categories) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                            <button type="submit" name="delete_category" class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Are you sure you want to delete this category?');">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No supply categories found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>