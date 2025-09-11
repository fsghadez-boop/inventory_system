<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') exit(header("Location: /inventory_system/login.php"));

// Approve
if (isset($_GET['approve'])) {
    $rid = intval($_GET['approve']);
    $req = $db->query("SELECT * FROM requests WHERE id=$rid")->fetch_assoc();
    if ($req && $req['status'] == 'pending') {
        $itemid = $req['item_id'];
        $qty = intval($req['requested_quantity']);
        $who = $_SESSION['user_id'];

        // Withdraw approve
        if (!$req['is_return']) {
            $item = $db->query("SELECT quantity FROM items WHERE id=$itemid")->fetch_assoc();
            if ($item && $item['quantity'] >= $qty) {
                // 1. Deduct stock
                $db->query("UPDATE items SET quantity=quantity-$qty WHERE id=$itemid");
                // 2. Upsert user holdings
                $user_id = $req['user_id'];
                $up = $db->prepare(
                  "INSERT INTO user_holdings (user_id, item_id, quantity)
                  VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)"
                );
                $up->bind_param('iii', $user_id, $itemid, $qty);
                $up->execute();
                // 3. Approve & log
                $db->query("UPDATE requests SET status='approved', reviewed_by=$who, reviewed_at=NOW() WHERE id=$rid");
                $db->query("INSERT INTO audit_logs (item_id, action, performed_by, log_details)
                    VALUES ($itemid, 'approve', $who, 'Approved withdraw request ID $rid, stock deducted by $qty, issued to user $user_id')");
            }
        }
        // Return approve
        else {
            $user_id = $req['user_id'];
            // Get user's holding for this item
            $uh = $db->prepare("SELECT quantity FROM user_holdings WHERE user_id=? AND item_id=?");
            $uh->bind_param('ii', $user_id, $itemid);
            $uh->execute();
            $uh->bind_result($holding_qty);
            $uh->fetch();
            $uh->close();
            if ($qty > 0 && $holding_qty >= $qty) {
                // 1. Remove from holdings
                $ud = $db->prepare("UPDATE user_holdings SET quantity=quantity-? WHERE user_id=? AND item_id=?");
                $ud->bind_param('iii', $qty, $user_id, $itemid);
                $ud->execute();
                // 2. Add to stock
                $db->query("UPDATE items SET quantity=quantity+$qty WHERE id=$itemid");
                // 3. Approve & log
                $db->query("UPDATE requests SET status='approved', reviewed_by=$who, reviewed_at=NOW() WHERE id=$rid");
                $db->query("INSERT INTO audit_logs (item_id, action, performed_by, log_details)
                    VALUES ($itemid, 'approve_return', $who, 'Approved return request ID $rid, stock incremented by $qty, returned by user $user_id')");
            }
            // If user holding insufficient, reject
            else {
                $db->query("UPDATE requests SET status='rejected', reviewed_by=$who, reviewed_at=NOW() WHERE id=$rid");
                $db->query("INSERT INTO audit_logs (item_id, action, performed_by, log_details)
                    VALUES ($itemid, 'reject_return', $who, 'Auto-rejected: user return qty ($qty) > they hold ($holding_qty) [req $rid]')");
            }
        }
    }
    header("Location: requests.php");
    exit;
}

// Reject
if (isset($_GET['reject'])) {
    $rid = intval($_GET['reject']);
    $who = $_SESSION['user_id'];
    $request = $db->query("SELECT * FROM requests WHERE id=$rid")->fetch_assoc();
    $itemid = $request['item_id'];
    $return_text = $request['is_return'] ? "return" : "withdraw";
    $db->query("UPDATE requests SET status='rejected', reviewed_by=$who, reviewed_at=NOW() WHERE id=$rid");
    $db->query("INSERT INTO audit_logs (item_id, action, performed_by, log_details)
        VALUES ($itemid, 'reject', $who, 'Rejected $return_text request ID $rid')");
    header("Location: requests.php");
    exit;
}

$q = "SELECT r.*, i.property_number, i.product_name, u.username
      FROM requests r
      LEFT JOIN items i ON r.item_id = i.id
      LEFT JOIN users u ON r.user_id = u.id
      ORDER BY r.created_at DESC";
$requests = $db->query($q)->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
<title>User Requests (Admin)</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"></head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top"><div class="container">
    <a class="navbar-brand" href="dashboard.php">Admin Inventory</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a href="items.php" class="nav-link">Items</a></li>
        <li class="nav-item"><a href="categories.php" class="nav-link">Manage Categories</a></li>
		<li class="nav-item"><a href="offices.php" class="nav-link">Manage Offices</a></li>
        <li class="nav-item"><a href="requests.php" class="nav-link active">Requests</a></li>
        <li class="nav-item"><a href="audit_logs.php" class="nav-link">Audit Logs</a></li>
		<li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
        <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
    </ul>
</div></nav>
<div class="container pt-5">
<h4 class="mt-4">User Requests</h4>
<table class="table table-bordered mt-2">
    <thead><tr>
        <th>ID</th>
        <th>User</th>
        <th>Type</th>
        <th>Property #</th>
        <th>Name</th>
        <th>Quantity</th>
        <th>Status</th>
        <th>Date</th>
        <th>Action</th>
    </tr></thead>
    <tbody>
        <?php foreach($requests as $r): ?>
        <tr>
            <td><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['username']) ?></td>
            <td>
                <?= (isset($r['is_return']) && $r['is_return'])
                    ? "<span class='badge badge-info'>Return</span>"
                    : "<span class='badge badge-warning'>Withdraw</span>" ?>
            </td>
            <td><?= htmlspecialchars($r['property_number']) ?></td>
            <td><?= htmlspecialchars($r['product_name']) ?></td>
            <td><?= $r['requested_quantity'] ?></td>
            <td>
                <?php
                if ($r['status'] == 'pending') echo "<span class='badge badge-warning'>Pending</span>";
                elseif ($r['status'] == 'approved') echo "<span class='badge badge-success'>Approved</span>";
                else echo "<span class='badge badge-danger'>Rejected</span>";
                ?>
            </td>
            <td><?= $r['created_at'] ?></td>
            <td>
                <?php if($r['status']=='pending'): ?>
                    <a href="?approve=<?= $r['id'] ?>" class="btn btn-sm btn-success">Approve</a>
                    <a href="?reject=<?= $r['id'] ?>" class="btn btn-sm btn-danger">Reject</a>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
</body>
</html>
