<?php
require '../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') exit(header("Location: /inventory_system/login.php"));

// User's name for display, default to 'Admin'
$admin_name = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';


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
<html lang="en">
<head>
    <title>User Requests (Admin) | Inventory System</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Replicating dashboard.php styles */
        :root {
            --primary-color: #4A90E2;
            --sidebar-bg: #2C3E50;
            --sidebar-link: #ECF0F1;
            --sidebar-hover: #34495E;
            --sidebar-active: #4A90E2;
            --content-bg: #F4F6F9;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--content-bg);
            padding-top: 56px;
        }

        .navbar {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
        }

        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: 600;
        }

        .sidebar {
            height: calc(100vh - 56px);
            position: fixed;
            top: 56px;
            left: 0;
            width: 240px;
            background-color: var(--sidebar-bg);
            padding-top: 15px;
            transition: all 0.3s;
        }

        .sidebar a {
            color: var(--sidebar-link);
            display: block;
            padding: 12px 20px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
        }

        .sidebar a:hover {
            background-color: var(--sidebar-hover);
            color: #fff;
        }

        .sidebar a.active {
            background-color: var(--sidebar-active);
            color: #fff;
            border-left: 4px solid var(--sidebar-link);
        }
        
        .sidebar a i.fa-solid {
            width: 25px;
            margin-right: 8px;
        }
        
        .sidebar .collapse .pl-4 a {
            padding-left: calc(20px + 25px + 8px) !important;
            font-size: 0.9em;
            color: #bdc3c7;
        }
        
        .sidebar .collapse .pl-4 a:hover {
            color: #fff;
        }

        .content {
            margin-left: 240px;
            padding: 25px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
            padding: 10px;
        }

        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <a class="navbar-brand" href="dashboard.php"><i class="fa-solid fa-cubes-stacked"></i> Inventory System</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavDropdown">
        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fa-solid fa-user-circle mr-1"></i> <?= $admin_name ?>
                </a>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdownMenuLink">
                    <a class="dropdown-item" href="edit_profile.php"><i class="fa-solid fa-user-gear mr-2"></i> Profile</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/inventory_system/logout.php"><i class="fa-solid fa-right-from-bracket mr-2"></i> Logout</a>
                </div>
            </li>
        </ul>
    </div>
</nav>

<div class="sidebar">
    <a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
    <a href="items.php"><i class="fa-solid fa-box"></i> Items</a>
	<a href="item_reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
    <a href="requests.php" class="active"><i class="fa-solid fa-file-circle-check"></i> Requests</a>
    <a href="audit_logs.php"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a>
    <a href="tracker.php"><i class="fa-solid fa-location-dot"></i> Inventory Tracker</a>
    <a href="condemn.php"><i class="fa-solid fa-ban"></i> Condemn</a>
    <a href="#usersSub" data-toggle="collapse" aria-expanded="false"><i class="fa-solid fa-users-cog"></i> Manage Users</a>
    <div class="collapse" id="usersSub">
        <a href="edit_user.php" class="pl-4">Edit User</a>
        <a href="register_user.php" class="pl-4">Register New User</a>
    </div>
    <a href="#categoriesSub" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle"><i class="fa-solid fa-tags"></i> Manage Categories</a>
    <div class="collapse" id="categoriesSub">
        <a href="manage_supplies_category.php" class="pl-4">Supplies</a>
        <a href="manage_assets_category.php" class="pl-4">Assets</a>
        <a href="offices.php" class="pl-4">Offices</a>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">✉️ User Requests</h1>
            <p class="text-muted mb-0">Review and manage item requests from users.</p>
        </div>

        <div class="card p-3 mb-4">
            <h5 class="font-weight-bold text-secondary mb-3">Pending and Past Requests</h5>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Property #</th>
                            <th>Name</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No requests found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($requests as $r): ?>
                            <tr>
                                <td><?= $r['id'] ?></td>
                                <td><?= htmlspecialchars($r['username']) ?></td>
                                <td>
                                    <?php
                                    $is_return = isset($r['is_return']) && $r['is_return'];
                                    echo $is_return
                                        ? "<span class='badge badge-info'><i class='fas fa-undo-alt mr-1'></i> Return</span>"
                                        : "<span class='badge badge-warning'><i class='fas fa-sign-out-alt mr-1'></i> Withdraw</span>";
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($r['property_number']) ?></td>
                                <td><?= htmlspecialchars($r['product_name']) ?></td>
                                <td><?= $r['requested_quantity'] ?></td>
                                <td>
                                    <?php
                                    if ($r['status'] == 'pending') echo "<span class='badge badge-warning'><i class='fas fa-clock mr-1'></i> Pending</span>";
                                    elseif ($r['status'] == 'approved') echo "<span class='badge badge-success'><i class='fas fa-check-circle mr-1'></i> Approved</span>";
                                    else echo "<span class='badge badge-danger'><i class='fas fa-times-circle mr-1'></i> Rejected</span>";
                                    ?>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <?php if($r['status']=='pending'): ?>
                                        <a href="?approve=<?= $r['id'] ?>" class="btn btn-sm btn-success mr-1" 
                                            onclick="return confirm('Are you sure you want to approve this request?');" 
                                            data-toggle="tooltip" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="?reject=<?= $r['id'] ?>" class="btn btn-sm btn-danger" 
                                            onclick="return confirm('Are you sure you want to reject this request?');" 
                                            data-toggle="tooltip" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>