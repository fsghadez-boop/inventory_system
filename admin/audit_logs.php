<?php
require '../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /inventory_system/login.php");
    exit();
}

// --- Filtering / Sorting ---
$where = [];
$params = [];
$types = "";

$search = trim($_GET['search'] ?? "");
$action = trim($_GET['action'] ?? "");
$date_from = trim($_GET['date_from'] ?? "");
$date_to = trim($_GET['date_to'] ?? "");
$sort = trim($_GET['sort'] ?? "created_at");
$dir = strtoupper(trim($_GET['dir'] ?? "DESC"));
if (!in_array($sort, ['created_at', 'username', 'action'])) $sort = 'created_at';
if ($dir !== "ASC") $dir = "DESC";

if ($search !== "") {
    $where[] = "(u.username LIKE CONCAT('%', ?, '%')
        OR a.action LIKE CONCAT('%', ?, '%')
        OR a.log_details LIKE CONCAT('%', ?, '%')
        OR i.property_number LIKE CONCAT('%', ?, '%'))";
    $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search;
    $types .= "ssss";
}
if ($action !== "") {
    $where[] = "a.action = ?";
    $params[] = $action;
    $types .= "s";
}
if ($date_from !== "") {
    $where[] = "DATE(a.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if ($date_to !== "") {
    $where[] = "DATE(a.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}
$where_sql = $where ? "WHERE ".implode(" AND ", $where) : "";

// Get list of action types for filter dropdown
$actions = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetch_all(MYSQLI_ASSOC);

// --- Data Query ---
$sql = "
SELECT a.*, u.username, i.property_number, i.product_name
FROM audit_logs a
LEFT JOIN users u ON a.performed_by=u.id
LEFT JOIN items i ON a.item_id=i.id
$where_sql
ORDER BY $sort $dir
LIMIT 200
";

$stmt = $db->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);

// Function to format log details for better readability
function formatLogDetails($log) {
    $details = $log['log_details'];
    
    // Check if it's an edit action with detailed information
    if ($log['action'] === 'edit') {
        // Pattern for detailed edit logs
        if (preg_match('/Edited item (.+?) (.+?): from (.+?) -> (.+)/', $details, $matches)) {
            $date = $matches[1];
            $field = $matches[2];
            $oldValue = $matches[3];
            $newValue = $matches[4];
            
            return "Edited item $date $field: <span class='text-danger'>$oldValue</span> → <span class='text-success'>$newValue</span>";
        }
        // Pattern for status changes (already working)
        elseif (preg_match('/Edited item (.+?) status: (.+?) -> (.+)/', $details, $matches)) {
            $date = $matches[1];
            $oldValue = $matches[2];
            $newValue = $matches[3];
            
            return "Edited item $date status: <span class='badge badge-danger'>$oldValue</span> → <span class='badge badge-success'>$newValue</span>";
        }
        // Pattern for quantity changes
        elseif (preg_match('/Edited item (.+?) Qty: (.+?) -> (.+)/', $details, $matches)) {
            $date = $matches[1];
            $oldValue = $matches[2];
            $newValue = $matches[3];
            
            return "Edited item $date Qty: <span class='text-danger'>$oldValue</span> → <span class='text-success'>$newValue</span>";
        }
        // Pattern for product name changes
        elseif (preg_match('/Edited item (.+?) Product Name from (.+?) to (.+)/', $details, $matches)) {
            $date = $matches[1];
            $oldValue = $matches[2];
            $newValue = $matches[3];
            
            return "Edited item $date Product Name: <span class='text-danger'>$oldValue</span> → <span class='text-success'>$newValue</span>";
        }
        // Pattern for category changes
        elseif (preg_match('/Edited item (.+?) category from (.+?) -> (.+)/', $details, $matches)) {
            $date = $matches[1];
            $oldValue = $matches[2];
            $newValue = $matches[3];
            
            return "Edited item $date category: <span class='text-danger'>$oldValue</span> → <span class='text-success'>$newValue</span>";
        }
    }
    
    // For other actions, just return the details as is
    return htmlspecialchars($details);
}
?>
<!doctype html>
<html>
<head>
<title>Audit Logs - Search & Filter</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
    body {
        padding-top: 70px;
        background-color: #f8f9fa;
    }
    .navbar-brand {
        font-weight: bold;
    }
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        margin-bottom: 20px;
    }
    .table th {
        border-top: none;
        font-weight: 600;
    }
    th.sortable:hover { 
        text-decoration: underline; 
        cursor: pointer; 
        color: #007bff; 
    }
    .log-details {
        max-width: 400px;
        word-break: break-word;
    }
    .badge-success {
        background-color: #28a745;
    }
    .badge-danger {
        background-color: #dc3545;
    }
    .text-success {
        color: #28a745 !important;
    }
    .text-danger {
        color: #dc3545 !important;
    }
    .filter-form {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Admin Inventory</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item"><a href="items.php" class="nav-link">Items</a></li>
                <li class="nav-item"><a href="manage_assets_category.php" class="nav-link">Asset Categories</a></li>
                <li class="nav-item"><a href="manage_supplies_category.php" class="nav-link">Supply Categories</a></li>
                <li class="nav-item"><a href="offices.php" class="nav-link">Manage Offices</a></li>
                <li class="nav-item"><a href="requests.php" class="nav-link">Requests</a></li>
                <li class="nav-item"><a href="audit_logs.php" class="nav-link active">Audit Logs</a></li>
                <li class="nav-item"><a href="tracker.php" class="nav-link">Inventory Tracker</a></li>
                <li class="nav-item"><a href="condemn.php" class="nav-link">Condemn Items</a></li>
                <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Audit Logs</h5>
                    <p class="mb-0">Track all system activities and changes</p>
                </div>
                <div class="card-body">
                    <div class="filter-form">
                        <form class="form-inline" method="GET" style="flex-wrap:wrap">
                            <input name="search" class="form-control mb-2 mr-2" placeholder="Keyword" value="<?= htmlspecialchars($search) ?>">
                            <select name="action" class="form-control mb-2 mr-2">
                                <option value="">--All Actions--</option>
                                <?php foreach($actions as $a): ?>
                                    <option value="<?= htmlspecialchars($a['action']) ?>" <?= $action===$a['action'] ? "selected":"" ?>>
                                        <?= htmlspecialchars($a['action']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="date_from" class="form-control mb-2 mr-2" value="<?= htmlspecialchars($date_from) ?>">
                            <span class="mb-2 mr-2">to</span>
                            <input type="date" name="date_to" class="form-control mb-2 mr-2" value="<?= htmlspecialchars($date_to) ?>">
                            <button class="btn btn-primary mb-2 mr-2">Filter</button>
                            <a href="audit_logs.php" class="btn btn-secondary mb-2">Reset</a>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th class="sortable" onclick="sortLogs('created_at')">
                                        Date/Time
                                        <?= $sort=="created_at" ? ($dir=="DESC" ? "🔽":"🔼") : "" ?>
                                    </th>
                                    <th class="sortable" onclick="sortLogs('action')">
                                        Action <?= $sort=="action" ? ($dir=="DESC" ? "🔽":"🔼") : "" ?>
                                    </th>
                                    <th class="sortable" onclick="sortLogs('username')">
                                        User <?= $sort=="username" ? ($dir=="DESC" ? "🔽":"🔼") : "" ?>
                                    </th>
                                    <th>Item</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($logs as $l): ?>
                                <tr>
                                    <td><?= date('m/d/Y h:i A', strtotime($l['created_at'])) ?></td>
                                    <td>
                                        <span class="badge 
                                            <?= $l['action'] == 'add' ? 'badge-success' : '' ?>
                                            <?= $l['action'] == 'edit' ? 'badge-warning' : '' ?>
                                            <?= $l['action'] == 'delete' ? 'badge-danger' : '' ?>
                                            <?= $l['action'] == 'condemn' ? 'badge-dark' : '' ?>
                                        ">
                                            <?= htmlspecialchars($l['action']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($l['username']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($l['property_number']) ?>
                                        <?php if (!empty($l['product_name'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($l['product_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="log-details"><?= formatLogDetails($l) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i>No logs found for current filter/keyword.</i>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (!empty($logs)): ?>
                    <div class="mt-3 text-center">
                        <small class="text-muted">Showing <?= count($logs) ?> most recent records</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function sortLogs(col) {
    var params = new URLSearchParams(window.location.search);
    var current = params.get("sort") || "created_at";
    var dir = params.get("dir") || "DESC";
    if (current === col) {
        dir = (dir === "DESC") ? "ASC" : "DESC";
    } else {
        dir = "DESC";
    }
    params.set("sort", col);
    params.set("dir", dir);
    window.location = window.location.pathname + "?" + params.toString();
}
</script>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
</body>
</html>