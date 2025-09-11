<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') exit(header('Location: /inventory_system/login.php'));
$id = intval($_GET['id'] ?? 0);

// Prevent admin from deleting themselves
if ($id == $_SESSION['user_id']) {
    header("Location: users.php?error=cant_delete_self"); exit;
}

// Before delete: get user info for log
$stmt = $db->prepare("SELECT username, role FROM users WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($uname, $urole);
$stmt->fetch();

$stmtdel = $db->prepare("DELETE FROM users WHERE id=?");
$stmtdel->bind_param('i', $id);
$stmtdel->execute();

if ($stmtdel->affected_rows > 0) {
    // === Audit log ===
    $actor = $_SESSION['user_id'];
    $action = "delete_user";
    $details = "Deleted user '$uname' (role: $urole, id: $id)";
    $stmtLog = $db->prepare("INSERT INTO audit_logs (item_id, action, performed_by, log_details) VALUES (NULL, ?, ?, ?)");
    $stmtLog->bind_param('sis', $action, $actor, $details);
    $stmtLog->execute();
}

header("Location: users.php?deleted=1");
exit;
