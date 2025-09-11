<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') exit(header('Location: /inventory_system/login.php'));

$id = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) exit("User not found!");

$err = $msg = '';
if ($_POST) {
    $username = trim($_POST['username']);
    $role = $_POST['role'] ?? 'user';
    $password = $_POST['password'] ?? '';

    // Check for duplicate username (exclude self)
    $dup = $db->prepare("SELECT id FROM users WHERE username=? AND id!=?");
    $dup->bind_param('si', $username, $id);
    $dup->execute(); $dup->store_result();
    if ($dup->num_rows > 0) $err = "Username exists!";
    elseif ($role !== 'admin' && $role !== 'user') $err = "Invalid role!";
    else {
        $pwchanged = false;
        if ($password) {
            $passhash = password_hash($password, PASSWORD_DEFAULT);
            $stmt2 = $db->prepare("UPDATE users SET username=?, role=?, password=? WHERE id=?");
            $stmt2->bind_param('sssi', $username, $role, $passhash, $id);
            $pwchanged = true;
        } else {
            $stmt2 = $db->prepare("UPDATE users SET username=?, role=? WHERE id=?");
            $stmt2->bind_param('ssi', $username, $role, $id);
        }
        if ($stmt2->execute()) {
            $msg = "User updated.";
            // === Audit log ===
            $actor = $_SESSION['user_id'];
            $action = "edit_user";
            $details = "Edited user '$username' (id: $id" . ($pwchanged ? ", password changed" : "") . ", role: $role)";
            $stmtLog = $db->prepare("INSERT INTO audit_logs (item_id, action, performed_by, log_details) VALUES (NULL, ?, ?, ?)");
            $stmtLog->bind_param('sis', $action, $actor, $details);
            $stmtLog->execute();
        } else $err = "Failed to update.";
    }
}
?>
<!doctype html>
<html>
<head>
<title>Edit User</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container pt-5" style="max-width:440px;">
    <h3>Edit User</h3>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post">
        <label>Username:</label>
        <input class="form-control mb-2" name="username" required value="<?= htmlspecialchars($user['username']) ?>">
        <label>Password (leave blank to keep current):</label>
        <input class="form-control mb-2" name="password" type="password">
        <label>Role:</label>
        <select name="role" class="form-control mb-2">
            <option value="user" <?= ($user['role'] == 'user') ? "selected" : "" ?>>User</option>
            <option value="admin" <?= ($user['role'] == 'admin') ? "selected" : "" ?>>Admin</option>
        </select>
        <button class="btn btn-primary">Update</button>
        <a href="users.php" class="btn btn-secondary ml-2">Back</a>
    </form>
</div>
</body>
</html>
