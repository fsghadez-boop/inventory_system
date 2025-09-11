<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') exit(header('Location: /inventory_system/login.php'));
$users = $db->query("SELECT * FROM users ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
<title>Manage Users</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container pt-5">
    <h3>User Accounts</h3>
    <a href="register_user.php" class="btn btn-sm btn-success mb-2">+ Register New User</a>
    <table class="table table-bordered">
        <thead>
            <tr><th>ID</th><th>Username</th><th>Role</th><th>Created</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['username']) ?><?= $_SESSION['user_id'] == $u['id'] ? ' <span class="badge badge-warning">You</span>' : "" ?></td>
                <td><?= $u['role'] ?></td>
                <td><?= $u['created_at'] ?></td>
                <td>
                    <a class="btn btn-sm btn-info" href="edit_user.php?id=<?= $u['id'] ?>">Edit</a>
                    <?php if($u['id'] != $_SESSION['user_id']): ?>
                    <a class="btn btn-sm btn-danger" href="delete_user.php?id=<?= $u['id'] ?>"
                        onclick="return confirm('Delete this user? This cannot be undone.');">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <a href="dashboard.php" class="btn btn-secondary">Back</a>
</div>
</body>
</html>
