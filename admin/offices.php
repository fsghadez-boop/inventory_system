<?php
require '../config/db.php';

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    exit(header("Location: /inventory_system/login.php"));
}

$err = "";
$msg = "";

// Handle REMOVE OFFICE
if (isset($_GET['deloffice'])) {
    $office_id = intval($_GET['deloffice']);
    $del = $db->prepare("DELETE FROM offices WHERE id=?");
    $del->bind_param("i", $office_id);
    if ($del->execute() && $del->affected_rows > 0) {
        $msg = "Office deleted successfully.";
    } else {
        $err = "Failed to delete office, or office not found!";
    }
}

// Handle NEW OFFICE
if ($_POST && isset($_POST['new_office'])) {
    $officename = trim($_POST['new_office']);
    if ($officename != '') {
        $stmt = $db->prepare("INSERT IGNORE INTO offices (name) VALUES (?)");
        $stmt->bind_param('s', $officename);
        if (!$stmt->execute()) {
            $err = "Failed to add office!";
        } else {
            $msg = "New office added!";
        }
    }
}

// Handle UPDATE OFFICE
if ($_POST && isset($_POST['edit_office_id'])) {
    $office_id = intval($_POST['edit_office_id']);
    $new_officename = trim($_POST['edit_officename']);
    if ($new_officename != '') {
        $stmt = $db->prepare("UPDATE offices SET name=? WHERE id=?");
        $stmt->bind_param('si', $new_officename, $office_id);
        if ($stmt->execute()) {
            // Log the edit in audit_logs
            $user_id = $_SESSION['user_id'];
            $log_sql = "INSERT INTO audit_logs (performed_by, action, log_details, created_at) 
                        VALUES (?, 'edit', ?, NOW())";
            $log_stmt = $db->prepare($log_sql);
            $log_details = "Edited office: '$new_officename'";
            $log_stmt->bind_param('is', $user_id, $log_details);
            $log_stmt->execute();

            $msg = "Office updated successfully!";
        } else {
            $err = "Failed to update office!";
        }
    }
}

// Fetch all offices
$offices = $db->query("SELECT * FROM offices ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
    <title>Manage Office Categories</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Admin Inventory</a>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item"><a href="items.php" class="nav-link">Items</a></li>
            <li class="nav-item"><a href="categories.php" class="nav-link">Manage Categories</a></li>
            <li class="nav-item"><a href="offices.php" class="nav-link active">Manage Offices</a></li>
            <li class="nav-item"><a href="requests.php" class="nav-link">Requests</a></li>
            <li class="nav-item"><a href="audit_logs.php" class="nav-link">Audit Logs</a></li>
            <li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
            <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container pt-5">
    <h4 class="mt-4">Office Categories</h4>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <!-- Add New Office -->
    <form class="form-inline mb-3" method="POST">
        <input name="new_office" class="form-control mr-2" placeholder="New Office" required>
        <button class="btn btn-primary">Add Office</button>
    </form>

    <!-- List Offices -->
    <ul class="list-group">
        <?php foreach($offices as $o): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= htmlspecialchars($o['name']) ?>
            <div>
                <!-- Edit -->
                <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editModal<?= $o['id'] ?>">Edit</button>
                <!-- Delete -->
                <a href="?deloffice=<?= $o['id'] ?>" class="btn btn-danger btn-sm"
                   onclick="return confirm('Are you sure you want to remove this office?');">
                   Delete
                </a>
            </div>
        </li>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?= $o['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="editModalLabel<?= $o['id'] ?>" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel<?= $o['id'] ?>">Edit Office: <?= htmlspecialchars($o['name']) ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="edit_office_id" value="<?= $o['id'] ?>">
                            <input type="text" name="edit_officename" class="form-control" value="<?= htmlspecialchars($o['name']) ?>" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </ul>
</div>

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
</body>
</html>
