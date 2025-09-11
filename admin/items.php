<?php
require '../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') exit(header("Location: /inventory_system/login.php"));
$q = "SELECT i.*, c.name as category FROM items i LEFT JOIN categories c ON i.category_id=c.id ORDER BY i.id DESC";
$items = $db->query($q)->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
<title>Admin - Items</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
    body {
        padding-top: 70px;
    }
    .qrcode-img {
        max-width: 50px;
        height: auto;
    }
</style>
</head>
<body>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
<?php elseif (isset($_GET['deleted'])): ?>
<div class="alert alert-<?= $_GET['deleted'] == 1 ? 'success' : 'warning' ?>">
    <?= $_GET['deleted'] == 1 ? 'Item deleted successfully.' : 'Item not deleted (maybe already gone).' ?>
</div>
<?php endif; ?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Admin Inventory</a>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item"><a href="items.php" class="nav-link active">Items</a></li>
            <li class="nav-item"><a href="categories.php" class="nav-link">Manage Categories</a></li>
            <li class="nav-item"><a href="offices.php" class="nav-link">Manage Offices</a></li>
            <li class="nav-item"><a href="requests.php" class="nav-link">Requests</a></li>
            <li class="nav-item"><a href="audit_logs.php" class="nav-link">Audit Logs</a></li>
            <li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
            <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container pt-5">
    <div class="mt-4 mb-2">
        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addItemModal">
            Add New Item
        </button>
    </div>
    
    <!-- Add Item Type Selection Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-labelledby="addItemModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addItemModalLabel">Select Item Type</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <p>What type of item would you like to add?</p>
                    <div class="mt-4">
                        <a href="add_item_assets.php" class="btn btn-primary btn-lg mr-3">Add Asset</a>
                        <a href="add_item.php" class="btn btn-success btn-lg">Add Supply</a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <h4>Inventory Items</h4>
    <table class="table table-bordered table-hover">
        <thead class="thead-light">
        <tr>
            <th>#</th>
            <th>Property Number</th>
            <th>QR Code</th>
            <th>Name</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Status</th>
            <th>Date Created</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i): ?>
        <tr>
            <td><?= $i['id'] ?></td>
            <td><?= htmlspecialchars($i['property_number']) ?></td>
            <td>
                <?php if($i['qr_code_path']): ?>
                    <img class="qrcode-img" src="<?= $i['qr_code_path'] ?>" alt="QR Code">
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($i['product_name']) ?></td>
            <td><?= htmlspecialchars($i['category']) ?></td>
            <td><?= $i['quantity'] ?></td>
            <td><?= $i['unit'] ?></td>
            <td>
                <?php
                if ($i['status'] == 'brand_new') {
                    echo "<span class='badge badge-success'>Brand New</span>";
                } else if ($i['status'] == 'for_replacement') {
                    echo "<span class='badge badge-warning'>Replacement</span>";
                } else {
                    echo "<span class='badge badge-danger'>Condemn</span>";
                }
                ?>
            </td>
            <td><?= date('m/d/Y', strtotime($i['created_at'])) ?></td>
            <td>
                <a href="edit_item.php?id=<?= $i['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                <a href="delete_item.php?id=<?= $i['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete item?')">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
</body>
</html>