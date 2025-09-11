<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') exit(header('Location: /inventory_system/login.php'));

$err = $msg = '';
if ($_POST) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? 'user';

    // Validate username
    $stmt = $db->prepare("SELECT id FROM users WHERE username=?");
    $stmt->bind_param('s', $username);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) $err = "Username already exists!";
    elseif ($role !== 'admin' && $role !== 'user') $err = "Invalid role!";
    else {
        $passhash = password_hash($password, PASSWORD_DEFAULT);
        $stmt2 = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt2->bind_param('sss', $username, $passhash, $role);
        if ($stmt2->execute()) {
            $msg = "User registered!";
            // === Audit log ===
            $actor = $_SESSION['user_id'];
            $newid = $db->insert_id;
            $action = "create_user";
            $details = "Created user '$username' (role: $role, id: $newid)";
            $stmtLog = $db->prepare("INSERT INTO audit_logs (item_id, action, performed_by, log_details) VALUES (NULL, ?, ?, ?)");
            $stmtLog->bind_param('sis', $action, $actor, $details);
            $stmtLog->execute();
        } else $err = "Registration failed!";
    }
}
?>
<!doctype html>
<html>
<head>
<title>Register User</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container pt-5">
    <h3>Register New User</h3>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post" style="max-width:350px;">
        <label>Username:</label>
        <input class="form-control mb-2" name="username" required>
        <label>Password:</label>
        <input class="form-control mb-2" name="password" type="password" required>
        <label>Role:</label>
        <select name="role" class="form-control mb-2">
            <option value="user">User</option>
            <option value="admin">Admin</option>
        </select>
        <button class="btn btn-primary">Register</button>
        <a href="dashboard.php" class="btn btn-secondary ml-2">Back</a>
    </form>
</div>
</body>
</html>
