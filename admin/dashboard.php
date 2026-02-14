<?php
require '../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /inventory_system/login.php");
    exit();
}

// User's name for display
$admin_name = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';

// ======= FETCH DATA =======

// Total items
$total_items = $db->query("SELECT COUNT(*) AS cnt FROM items WHERE is_deleted=0")->fetch_assoc()['cnt'];

// Brand new
$total_brand_new = $db->query("SELECT COUNT(*) AS cnt FROM items WHERE status='brand_new' AND is_deleted=0")->fetch_assoc()['cnt'];

// Condemned
$total_condemned = $db->query("SELECT COUNT(*) AS cnt FROM items WHERE status='condemned' AND is_deleted=0")->fetch_assoc()['cnt'];

// Supplies
$total_supplies = $db->query("
    SELECT COUNT(*) AS cnt
    FROM items i
    JOIN categories c ON i.category_id = c.id
    WHERE c.category_type='supply' AND i.is_deleted=0
")->fetch_assoc()['cnt'];

// Assets
$total_assets = $db->query("
    SELECT COUNT(*) AS cnt
    FROM items i
    JOIN categories c ON i.category_id = c.id
    WHERE c.category_type='asset' AND i.is_deleted=0
")->fetch_assoc()['cnt'];

// Assets per office
$assets_per_office = ['labels' => [], 'data' => []];
$result = $db->query("
    SELECT o.name AS office_name, COUNT(i.id) AS total
    FROM items i
    JOIN categories c ON i.category_id = c.id
    JOIN offices o ON i.office_id = o.id
    WHERE c.category_type='asset' AND i.is_deleted=0
    GROUP BY o.name
    ORDER BY total DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $assets_per_office['labels'][] = $row['office_name'];
        $assets_per_office['data'][] = (int)$row['total'];
    }
}

// Assets per category
$assets_per_category = ['labels' => [], 'data' => []];
$result = $db->query("
    SELECT c.name AS category_name, COUNT(i.id) AS total
    FROM items i
    JOIN categories c ON i.category_id = c.id
    WHERE c.category_type='asset' AND i.is_deleted=0
    GROUP BY c.name
    ORDER BY total DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $assets_per_category['labels'][] = $row['category_name'];
        $assets_per_category['data'][] = (int)$row['total'];
    }
}

// Supplies requests trend
$supply_requests = ['dates' => [], 'approved' => [], 'rejected' => []];
$result = $db->query("
    SELECT DATE(r.created_at) AS req_date, r.status, COUNT(r.id) AS total
    FROM requests r
    JOIN items i ON r.item_id = i.id
    JOIN categories c ON i.category_id = c.id
    WHERE c.category_type='supply' AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY req_date, r.status
    ORDER BY req_date ASC
");

$dailyData = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $date = $row['req_date'];
        if (!isset($dailyData[$date])) {
            $dailyData[$date] = ['approved' => 0, 'rejected' => 0];
        }
        if ($row['status'] === 'approved') {
            $dailyData[$date]['approved'] = (int)$row['total'];
        } elseif ($row['status'] === 'rejected') {
            $dailyData[$date]['rejected'] = (int)$row['total'];
        }
    }
}

// Populate last 30 days
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $supply_requests['dates'][] = $date;
    $supply_requests['approved'][] = isset($dailyData[$date]) ? $dailyData[$date]['approved'] : 0;
    $supply_requests['rejected'][] = isset($dailyData[$date]) ? $dailyData[$date]['rejected'] : 0;
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Admin Dashboard | Inventory System</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        :root {
            --primary-color: #4A90E2;
            --sidebar-bg: #2C3E50;
            --sidebar-link: #ECF0F1;
            --sidebar-hover: #34495E;
            --sidebar-active: #4A90E2;
            --content-bg: #F4F6F9;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--content-bg); padding-top: 56px; }
        .navbar { background-color: white; box-shadow: 0 2px 4px rgba(0, 0, 0, .1); }
        .navbar-brand { color: var(--primary-color) !important; font-weight: 600; }
        .sidebar { height: calc(100vh - 56px); position: fixed; top: 56px; left: 0; width: 240px; background-color: var(--sidebar-bg); padding-top: 15px; overflow-y: auto; }
        .sidebar a { color: var(--sidebar-link); display: block; padding: 12px 20px; text-decoration: none; font-weight: 500; }
        .sidebar a:hover { background-color: var(--sidebar-hover); color: #fff; }
        .sidebar a.active { background-color: var(--sidebar-active); color: #fff; border-left: 4px solid var(--sidebar-link); }
        .sidebar a i.fa-solid { width: 25px; margin-right: 8px; }
        .sidebar .collapse a { padding-left: 40px; font-size: 14px; }
        .content { margin-left: 240px; padding: 25px; }
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, .08); }
        .chart-card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, .08); padding: 20px; }
        #overviewChart, #supplyRequestsChart { max-height: 350px !important; }
        #assetsOfficeChart, #assetsCategoryChart { max-height: 500px !important; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <a class="navbar-brand" href="dashboard.php"><i class="fa-solid fa-cubes-stacked"></i> Inventory System</a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                    <i class="fa-solid fa-user-circle mr-1"></i> <?= $admin_name ?>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="edit_profile.php"><i class="fa-solid fa-user-gear mr-2"></i> Profile</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/inventory_system/logout.php"><i class="fa-solid fa-right-from-bracket mr-2"></i> Logout</a>
                </div>
            </li>
        </ul>
    </div>
</nav>

<div class="sidebar">
    <a href="dashboard.php" class="active"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
    <a href="items.php"><i class="fa-solid fa-box"></i> Items</a>
	<a href="item_reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
    <a href="requests.php"><i class="fa-solid fa-file-circle-check"></i> Requests</a>
    <a href="audit_logs.php"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a>
    <a href="tracker.php"><i class="fa-solid fa-location-dot"></i> Inventory Tracker</a>
    <a href="condemn.php"><i class="fa-solid fa-ban"></i> Condemn</a>

    <!-- Manage Users -->
    <a href="#usersSub" data-toggle="collapse" aria-expanded="false" role="button" aria-controls="usersSub">
        <i class="fa-solid fa-users-cog"></i> Manage Users
    </a>
    <div class="collapse" id="usersSub">
        <a href="edit_user.php">Edit User</a>
        <a href="register_user.php">Register New User</a>
    </div>

    <!-- Manage Categories -->
    <a href="#categoriesSub" data-toggle="collapse" aria-expanded="false" role="button" aria-controls="categoriesSub">
        <i class="fa-solid fa-tags"></i> Manage Categories
    </a>
    <div class="collapse" id="categoriesSub">
        <a href="manage_supplies_category.php">Supplies</a>
        <a href="manage_assets_category.php">Assets</a>
        <a href="offices.php">Offices</a>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <h1 class="h3 mb-4 text-gray-800">👋 Welcome, <?= $admin_name ?>!</h1>

        <!-- Stat Cards -->
        <div class="row">
            <div class="col-md-3 mb-4"><div class="card stat-card"><div class="card-body"><div>Total Items</div><h5><?= $total_items ?></h5></div></div></div>
            <div class="col-md-3 mb-4"><div class="card stat-card"><div class="card-body"><div>Brand New</div><h5><?= $total_brand_new ?></h5></div></div></div>
            <div class="col-md-3 mb-4"><div class="card stat-card"><div class="card-body"><div>Condemned</div><h5><?= $total_condemned ?></h5></div></div></div>
            <div class="col-md-3 mb-4"><div class="card stat-card"><div class="card-body"><div>Supplies</div><h5><?= $total_supplies ?></h5></div></div></div>
            <div class="col-md-3 mb-4"><div class="card stat-card"><div class="card-body"><div>Assets</div><h5><?= $total_assets ?></h5></div></div></div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-lg-6 mb-4"><div class="card chart-card"><h5 class="text-center">Assets per Office</h5><canvas id="assetsOfficeChart"></canvas></div></div>
            <div class="col-lg-6 mb-4"><div class="card chart-card"><h5 class="text-center">Assets per Category</h5><canvas id="assetsCategoryChart"></canvas></div></div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4"><div class="card chart-card"><h5 class="text-center">Inventory Overview</h5><canvas id="overviewChart"></canvas></div></div>
            <div class="col-lg-6 mb-4"><div class="card chart-card"><h5 class="text-center">Supply Requests Trend</h5><canvas id="supplyRequestsChart"></canvas></div></div>
        </div>
    </div>
</div>

<!-- jQuery + Bootstrap Bundle -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    Chart.defaults.font.family = "'Poppins', sans-serif";

    // Overview Chart
    new Chart(document.getElementById('overviewChart'), {
        type: 'doughnut',
        data: {
            labels: ['Brand New', 'Condemned', 'Supplies', 'Assets'],
            datasets: [{ data: [<?= $total_brand_new ?>, <?= $total_condemned ?>, <?= $total_supplies ?>, <?= $total_assets ?>],
                backgroundColor: ['#1cc88a', '#e74a3b', '#f6c23e', '#36b9cc'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    // Assets per Office - Pie Chart
new Chart(document.getElementById('assetsOfficeChart').getContext('2d'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($assets_per_office['labels']) ?>,
        datasets: [
            {
                label: 'Assets per Office',
                data: <?= json_encode($assets_per_office['data']) ?>,
                backgroundColor: [
                    '#4A90E2', '#50E3C2', '#F5A623', '#D0021B',
                    '#7ED321', '#9013FE', '#B8E986', '#417505',
                    '#F8E71C', '#BD10E0', '#FF7F50', '#2ECC71'
                ],
                borderWidth: 0 // removes the circle outline
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let office = context.label || '';
                        let value = context.parsed || 0;
                        return office + ': ' + value;
                    }
                }
            }
        }
    }
});



    // Assets per Category
    new Chart(document.getElementById('assetsCategoryChart').getContext('2d'), {
        type: 'bar',
        data: { labels: <?= json_encode($assets_per_category['labels']) ?>, datasets: [{ data: <?= json_encode($assets_per_category['data']) ?>, backgroundColor: '#f6c23e' }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    // Supply Requests Trend
    new Chart(document.getElementById('supplyRequestsChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($supply_requests['dates']) ?>,
            datasets: [
                { label: 'Approved', data: <?= json_encode($supply_requests['approved']) ?>, borderColor: '#1cc88a', fill: true },
                { label: 'Rejected', data: <?= json_encode($supply_requests['rejected']) ?>, borderColor: '#e74a3b', fill: true }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
});
</script>
</body>
</html>
