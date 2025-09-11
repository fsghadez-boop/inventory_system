<?php
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /inventory_system/login.php");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: items.php?error=missing_or_bad_id");
    exit;
}

$stmt = $db->prepare("DELETE FROM items WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($db->error) {
    $msg = "Delete failed: " . htmlspecialchars($db->error);
    header("Location: items.php?error=" . urlencode($msg));
    exit;
}
header("Location: items.php?deleted=1");
exit;
