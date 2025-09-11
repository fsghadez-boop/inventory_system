<?php
require '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') exit(header("Location: /inventory_system/login.php"));

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
if (!in_array($sort, ['created_at','username','action'])) $sort = 'created_at';
if ($dir !== "ASC") $dir = "DESC";

if ($search !== "") {
    $where[] = "(u.username LIKE CONCAT('%', ?, '%')
        OR a.action LIKE CONCAT('%', ?, '%')
        OR a.log_details LIKE CONCAT('%', ?, '%'))";
    $params[] = $search; $params[] = $search; $params[] = $search;
    $types .= "sss";
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
SELECT a.*, u.username, i.property_number 
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
?>
<!doctype html>
<html>
<head>
<title>Inventory Tracker - Search & Filter</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
th.sortable:hover { text-decoration: underline; cursor: pointer; color: #007bff; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top"><div class="container">
    <a class="navbar-brand" href="dashboard.php">User Inventory</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a href="items.php" class="nav-link">All Items</a></li>
        <li class="nav-item"><a href="request_item.php" class="nav-link">Request Item</a></li>
        <li class="nav-item"><a href="return_item.php" class="nav-link">Return Item</a></li>
        <li class="nav-item"><a href="my_requests.php" class="nav-link">My Requests</a></li>
		<li class="nav-item"><a href="tracker.php" class="nav-link active">Inventory Tracker</a></li>
        <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
    </ul>
</div></nav>

<div class="container pt-5">
  <h4 class="mt-4">Inventory Tracker (search/filter/sort)</h4>
  <form class="form-inline mb-3" method="GET" style="flex-wrap:wrap">
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
    <input type="date" name="date_to" class="form-control mb-2 mr-2" value="<?= htmlspecialchars($date_to) ?>">
    <button class="btn btn-primary mb-2 mr-2">Filter</button>
    <a href="audit_logs.php" class="btn btn-secondary mb-2">Reset</a>
  </form>

  <div class="table-responsive">
  <table class="table table-bordered table-hover table-sm">
    <thead>
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
        <th>Detail</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($logs as $l): ?>
      <tr>
        <td><?= $l['created_at'] ?></td>
        <td><?= htmlspecialchars($l['action']) ?></td>
        <td><?= htmlspecialchars($l['username']) ?></td>
        <td><?= htmlspecialchars($l['property_number']) ?></td>
        <td style="white-space:pre-line"><?= htmlspecialchars($l['log_details']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?>
        <tr><td colspan="5" class="text-center text-muted">
          No logs found for current filter/keyword.
        </td></tr>
      <?php endif; ?>
    </tbody>
  </table>
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
</body>
</html>
