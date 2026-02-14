<?php
require '../config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /inventory_system/login.php");
    exit;
}

// User's name for display
$admin_name = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';

function p($key, $default = null) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

$err = '';
$success = '';

// Get item ID from GET and fetch item
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: items.php?error=missing_id");
    exit;
}

$stmt = $db->prepare("SELECT * FROM items WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$item = $res->fetch_assoc();
$stmt->close();

if (!$item) {
    header("Location: items.php?error=not_found");
    exit;
}

// Fetch categories and offices for dropdowns
$cats = $db->query("SELECT * FROM categories WHERE category_type='asset' ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$offices = $db->query("SELECT * FROM offices ORDER by name ASC")->fetch_all(MYSQLI_ASSOC);

// Map id → name for categories and offices (for readable logs)
$catMap = [];
foreach ($cats as $c) $catMap[$c['id']] = $c['name'];
$officeMap = [];
foreach ($offices as $o) $officeMap[$o['id']] = $o['name'];

// On POST: update the item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'property_number','product_name','category_id','brand','model','processor','ram',
        'graphics1','graphics2','computer_name','workgroup','os','office_app','ms_account',
        'endpoint_protection','endpoint_updated','anydesk_id','belarc_installed','accounts_updated',
        'ultravnc_installed','snmp_installed','connection_type','dhcp_type','static_app',
        'ip_address1','ip_address2','lan_mac','wlan_mac1','wlan_mac2','gateway','office_id',
        'miaa_property','memorandum_receipt','po_number','serial_number','display_size',
        'printer_type','capacity_va','other_details','network_equipment_type',
        'area_of_deployment','storage_type','storage_capacity','quantity','unit','status','is_active',
        'acquisition_date', 'warranty', 'supplier_name', 'supplier_contact_name', 
        'supplier_contact', 'supplier_email', 'supplier_address'
    ];

    $updates = [];
    $types = ""; // Initialize types string
    $params = [];

    // Process "others" logic for specific fields
    $endpoint_protection = p('endpoint_protection');
    if ($endpoint_protection === 'others') $endpoint_protection = p('endpoint_other');
    
    $network_equipment_type = p('network_equipment_type');
    if ($network_equipment_type === 'others') $network_equipment_type = p('network_equipment_other');

    foreach ($fields as $f) {
        $val = p($f);
        
        // Specific field overrides
        if ($f == 'endpoint_protection') $val = $endpoint_protection;
        if ($f == 'network_equipment_type') $val = $network_equipment_type;

        // Type Casting for Integers
        if (in_array($f, ['category_id','office_id','quantity','is_active', 'endpoint_updated'])) {
            $val = ($val !== '' && $val !== null) ? intval($val) : 0;
        }
        
        // Handle Checkboxes (force 0 if not present in POST)
        if (in_array($f, ['belarc_installed','accounts_updated','ultravnc_installed','snmp_installed'])) {
            $val = isset($_POST[$f]) ? 1 : 0;
        }

        $updates[$f] = $val;
    }
    
    // Check for duplicate property number
    $check_sql = "SELECT id FROM items WHERE property_number = ? AND id != ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("si", $updates['property_number'], $id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $err = "Property number '{$updates['property_number']}' already exists for another item.";
    } else {
        $setParts = [];
        foreach ($updates as $f => $val) {
            $setParts[] = "`$f`=?";
            if (is_int($val)) $types .= 'i';
            else $types .= 's';
            $params[] = $val;
        }

        $sql = "UPDATE items SET ".implode(", ", $setParts)." WHERE id=?";
        $stmt = $db->prepare($sql);
        
        $types .= 'i';
        $params[] = $id;
        
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $success = "Asset updated successfully!";
            
            // Audit Log Logic
            $changes = [];
            foreach ($updates as $f => $newVal) {
                if (!isset($item[$f])) continue;
                $oldVal = $item[$f];
                
                if ($f == 'category_id') {
                    $oldVal = $oldVal ? ($catMap[$oldVal] ?? $oldVal) : '';
                    $newVal = $newVal ? ($catMap[$newVal] ?? $newVal) : '';
                }
                if ($f == 'office_id') {
                    $oldVal = $oldVal ? ($officeMap[$oldVal] ?? $oldVal) : '';
                    $newVal = $newVal ? ($officeMap[$newVal] ?? $newVal) : '';
                }
                
                if ((string)$oldVal !== (string)$newVal) {
                    $changes[] = "$f: '$oldVal' → '$newVal'";
                }
            }

            $log_details = !empty($changes) 
                ? "Edited item {$updates['property_number']} | Changes: ".implode("; ", $changes)
                : "Edited item {$updates['property_number']} (no value changes)";

            $log_sql = "INSERT INTO audit_logs (performed_by, item_id, action, log_details) VALUES (?, ?, 'edit', ?)";
            $log_stmt = $db->prepare($log_sql);
            $log_stmt->bind_param("iis", $_SESSION['user_id'], $id, $log_details);
            $log_stmt->execute();
            $log_stmt->close();
            
            // Refresh $item with new data for the form
            $item = array_merge($item, $updates);
        } else {
            $err = "Error updating item: " . $stmt->error;
        }
        $stmt->close();
    }
    $check_stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Edit Asset | Inventory System</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        .sidebar { height: calc(100vh - 56px); position: fixed; top: 56px; left: 0; width: 240px; background-color: var(--sidebar-bg); padding-top: 15px; }
        .sidebar a { color: var(--sidebar-link); display: block; padding: 12px 20px; text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .sidebar a:hover { background-color: var(--sidebar-hover); color: #fff; }
        .sidebar a.active { background-color: var(--sidebar-active); color: #fff; border-left: 4px solid var(--sidebar-link); }
        .sidebar a i.fa-solid { width: 25px; margin-right: 8px; }
        .sidebar .collapse .pl-4 a { padding-left: calc(20px + 25px + 8px) !important; font-size: 0.9em; color: #bdc3c7; }
        .sidebar .collapse .pl-4 a:hover { color: #fff; }
        .content { margin-left: 240px; padding: 25px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, .08); }
        .card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; font-weight: 600; color: var(--primary-color); }
        .form-control:focus { box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25); border-color: #8ab7ed; }
    </style>
     <script>
    function showSection(id, show) {
        var el = document.getElementById(id);
        if (el) el.style.display = show ? 'block' : 'none';
    }
    function updateFormSections() {
        var catSelect = document.getElementById('category_id');
        if(!catSelect) return;
        var selected = catSelect.options[catSelect.selectedIndex].text.toLowerCase();
        
        showSection('monitor_section', false);
        showSection('printer_section', false);
        showSection('ups_section', false);
        showSection('scanner_section', false);
        showSection('speaker_section', false);
        showSection('portable_section', false);
        showSection('network_section', false);
        showSection('server_section', false);
        showSection('smarttv_section', false);
        showSection('computer_section', false);
        showSection('software_section', false);
        showSection('network_config_section', false);

        if (selected.includes('monitor')) showSection('monitor_section', true);
        else if (selected.includes('printer')) {
            showSection('printer_section', true);
            showSection('network_config_section', true);
        }
        else if (selected.includes('ups')) {
            showSection('ups_section', true);
            showSection('network_config_section', true);
        }
        else if (selected.includes('scanner')) showSection('scanner_section', true);
        else if (selected.includes('speaker')) showSection('speaker_section', true);
        else if (selected.includes('portable')) {
            showSection('portable_section', true);
            showSection('network_config_section', true);
        }
        else if (selected.includes('network')) {
            showSection('network_section', true);
            showSection('network_config_section', true);
        }
        else if (selected.includes('server')) {
            showSection('server_section', true);
            showSection('network_config_section', true);
        }
        else if (selected.includes('smart') || selected.includes('tv')) {
            showSection('smarttv_section', true);
            showSection('network_config_section', true);
        }
        else if (selected.includes('cpu') || selected.includes('laptop')) {
            showSection('computer_section', true);
            showSection('software_section', true);
            showSection('network_config_section', true);
        } else {
            if(selected !== "select category") {
                 showSection('network_config_section', true);
            }
        }
    }
    function toggleStaticApp() {
        var dhcpType = document.getElementById('dhcp_type').value;
        var staticAppDiv = document.getElementById('static_app_div');
        staticAppDiv.style.display = (dhcpType === 'static') ? 'block' : 'none';
    }
    function toggleEndpointOther() {
        var endpointType = document.getElementById('endpoint_protection').value;
        var endpointOtherDiv = document.getElementById('endpoint_other_div');
        endpointOtherDiv.style.display = (endpointType === 'others') ? 'block' : 'none';
    }
    function toggleNetworkEquipmentOther() {
        var equipmentType = document.getElementById('network_equipment_type').value;
        var equipmentOtherDiv = document.getElementById('network_equipment_other_div');
        equipmentOtherDiv.style.display = (equipmentType === 'others') ? 'block' : 'none';
    }
    document.addEventListener("DOMContentLoaded", function() {
        updateFormSections();
        toggleStaticApp();
        toggleEndpointOther();
        toggleNetworkEquipmentOther();
    });
    </script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <a class="navbar-brand" href="dashboard.php"><i class="fa-solid fa-cubes-stacked"></i> Inventory System</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavDropdown"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="navbarNavDropdown">
        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-toggle="dropdown">
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
    <a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
    <a href="items.php" class="active"><i class="fa-solid fa-box"></i> Items</a>
    <a href="requests.php"><i class="fa-solid fa-file-circle-check"></i> Requests</a>
    <a href="audit_logs.php"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a>
    <a href="tracker.php"><i class="fa-solid fa-location-dot"></i> Inventory Tracker</a>
    <a href="condemn.php"><i class="fa-solid fa-ban"></i> Condemn</a>
    <a href="#usersSub" data-toggle="collapse"><i class="fa-solid fa-users-cog"></i> Manage Users</a>
    <div class="collapse" id="usersSub">
        <a href="edit_user.php" class="pl-4">Edit User</a>
        <a href="register_user.php" class="pl-4">Register New User</a>
    </div>
    <a href="#categoriesSub" data-toggle="collapse"><i class="fa-solid fa-tags"></i> Manage Categories</a>
    <div class="collapse" id="categoriesSub">
        <a href="manage_supplies_category.php" class="pl-4">Supplies</a>
        <a href="manage_assets_category.php" class="pl-4">Assets</a>
        <a href="offices.php" class="pl-4">Offices</a>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">📝 Edit Asset</h1>
            <p class="text-muted mb-0">Updating: <strong><?= htmlspecialchars($item['property_number']) ?></strong></p>
        </div>

        <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="card mb-4">
                <div class="card-header">Basic Information</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Property Number *</label>
                            <input name="property_number" class="form-control" required value="<?= htmlspecialchars($item['property_number']) ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Product Name *</label>
                            <input name="product_name" class="form-control" required value="<?= htmlspecialchars($item['product_name']) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Category *</label>
                            <select name="category_id" id="category_id" class="form-control" required onchange="updateFormSections()">
                                <option value="">Select Category</option>
                                <?php foreach($cats as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($item['category_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Status</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="is_active" id="active_yes" value="1" <?= ($item['is_active'] == 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="active_yes">Active</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="is_active" id="active_no" value="0" <?= ($item['is_active'] == 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="active_no">Not Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Brand</label>
                            <input name="brand" class="form-control" value="<?= htmlspecialchars($item['brand']) ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Model</label>
                            <input name="model" class="form-control" value="<?= htmlspecialchars($item['model']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div id="monitor_section" style="display:none;"><div class="card mb-4"><div class="card-header">Monitor Details</div><div class="card-body"><div class="form-group"><label>Display Size</label><input name="display_size" class="form-control" value="<?= htmlspecialchars($item['display_size']) ?>"></div></div></div></div>
            <div id="printer_section" style="display:none;"><div class="card mb-4"><div class="card-header">Printer Details</div><div class="card-body"><div class="form-group"><label>Type of Printer</label><input name="printer_type" class="form-control" value="<?= htmlspecialchars($item['printer_type']) ?>"></div></div></div></div>
            <div id="ups_section" style="display:none;"><div class="card mb-4"><div class="card-header">UPS Details</div><div class="card-body"><div class="form-group"><label>Capacity (VA)</label><input name="capacity_va" class="form-control" value="<?= htmlspecialchars($item['capacity_va']) ?>"></div></div></div></div>
            <div id="scanner_section" style="display:none;"><div class="card mb-4"><div class="card-header">Scanner Details</div><div class="card-body"><div class="form-group"><label>Other Details</label><textarea name="other_details" class="form-control"><?= htmlspecialchars($item['other_details']) ?></textarea></div></div></div></div>
            <div id="speaker_section" style="display:none;"><div class="card mb-4"><div class="card-header">Speaker Details</div><div class="card-body"><div class="form-group"><label>Other Details</label><textarea name="other_details" class="form-control"><?= htmlspecialchars($item['other_details']) ?></textarea></div></div></div></div>
            <div id="portable_section" style="display:none;"><div class="card mb-4"><div class="card-header">Portable Device Details</div><div class="card-body"><div class="form-group"><label>Other Details</label><textarea name="other_details" class="form-control"><?= htmlspecialchars($item['other_details']) ?></textarea></div></div></div></div>
            <div id="network_section" style="display:none;"><div class="card mb-4"><div class="card-header">Network Equipment Details</div><div class="card-body">
                <div class="form-group"><label>Network Equipment Type</label><select name="network_equipment_type" id="network_equipment_type" class="form-control" onchange="toggleNetworkEquipmentOther()"><option value="">Select</option><option value="Switch" <?= $item['network_equipment_type'] == 'Switch' ? 'selected' : '' ?>>Switch</option><option value="Access Point" <?= $item['network_equipment_type'] == 'Access Point' ? 'selected' : '' ?>>Access Point</option><option value="Router" <?= $item['network_equipment_type'] == 'Router' ? 'selected' : '' ?>>Router</option><option value="Controller" <?= $item['network_equipment_type'] == 'Controller' ? 'selected' : '' ?>>Controller</option><option value="others" <?= !in_array($item['network_equipment_type'], ['','Switch','Access Point','Router','Controller']) ? 'selected' : '' ?>>Others</option></select></div>
                <div id="network_equipment_other_div" style="display:none;"><div class="form-group"><label>Specify Other</label><input name="network_equipment_other" class="form-control" value="<?= !in_array($item['network_equipment_type'], ['','Switch','Access Point','Router','Controller']) ? htmlspecialchars($item['network_equipment_type']) : '' ?>"></div></div>
                <div class="form-group"><label>Area of Deployment</label><input name="area_of_deployment" class="form-control" value="<?= htmlspecialchars($item['area_of_deployment']) ?>"></div>
                <div class="form-group"><label>Other Details</label><textarea name="other_details" class="form-control"><?= htmlspecialchars($item['other_details']) ?></textarea></div>
            </div></div></div>
            <div id="server_section" style="display:none;"><div class="card mb-4"><div class="card-header">Server Details</div><div class="card-body"><div class="form-group"><label>Other Details</label><textarea name="other_details" class="form-control"><?= htmlspecialchars($item['other_details']) ?></textarea></div></div></div></div>
            <div id="smarttv_section" style="display:none;"><div class="card mb-4"><div class="card-header">SmartTV Details</div><div class="card-body"><div class="form-group"><label>Other Details</label><textarea name="other_details" class="form-control"><?= htmlspecialchars($item['other_details']) ?></textarea></div></div></div></div>
            
            <div id="computer_section" style="display:none;"><div class="card mb-4"><div class="card-header">Technical Specifications</div><div class="card-body">
                <div class="form-row">
                    <div class="col-md-4 form-group"><label>Processor</label><input name="processor" class="form-control" value="<?= htmlspecialchars($item['processor']) ?>"></div>
                    <div class="col-md-4 form-group"><label>Storage Type</label><select name="storage_type" class="form-control"><option value="">Select</option><option value="SSD" <?= $item['storage_type'] == 'SSD' ? 'selected' : '' ?>>SSD</option><option value="HDD" <?= $item['storage_type'] == 'HDD' ? 'selected' : '' ?>>HDD</option><option value="BOTH" <?= $item['storage_type'] == 'BOTH' ? 'selected' : '' ?>>BOTH</option></select></div>
                    <div class="col-md-4 form-group"><label>Storage Capacity</label><input name="storage_capacity" class="form-control" value="<?= htmlspecialchars($item['storage_capacity']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="col-md-4 form-group"><label>RAM</label><input name="ram" class="form-control" value="<?= htmlspecialchars($item['ram']) ?>"></div>
                    <div class="col-md-4 form-group"><label>Graphics 1</label><input name="graphics1" class="form-control" value="<?= htmlspecialchars($item['graphics1']) ?>"></div>
                    <div class="col-md-4 form-group"><label>Graphics 2</label><input name="graphics2" class="form-control" value="<?= htmlspecialchars($item['graphics2']) ?>"></div>
                </div>
            </div></div></div>
            
            <div id="software_section" style="display:none;"><div class="card mb-4"><div class="card-header">Software Configuration</div><div class="card-body">
                <div class="form-row">
                    <div class="col-md-6 form-group"><label>Computer Name</label><input name="computer_name" class="form-control" value="<?= htmlspecialchars($item['computer_name']) ?>"></div>
                    <div class="col-md-6 form-group"><label>Workgroup</label><input name="workgroup" class="form-control" value="<?= htmlspecialchars($item['workgroup']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="col-md-6 form-group"><label>Operating System</label><input name="os" class="form-control" value="<?= htmlspecialchars($item['os']) ?>"></div>
                    <div class="col-md-6 form-group"><label>Office Application</label><select name="office_app" class="form-control"><option value="">Select</option><option value="365" <?= $item['office_app'] == '365' ? 'selected' : '' ?>>365</option><option value="Libre Office" <?= $item['office_app'] == 'Libre Office' ? 'selected' : '' ?>>Libre Office</option><option value="WPS" <?= $item['office_app'] == 'WPS' ? 'selected' : '' ?>>WPS</option></select></div>
                </div>
                <div class="form-row">
                    <div class="col-md-6 form-group"><label>MS 365 Account</label><input name="ms_account" class="form-control" value="<?= htmlspecialchars($item['ms_account']) ?>"></div>
                    <div class="col-md-6 form-group"><label>End Point Protection</label><select name="endpoint_protection" id="endpoint_protection" class="form-control" onchange="toggleEndpointOther()"><option value="">Select</option><option value="Seqrite" <?= $item['endpoint_protection'] == 'Seqrite' ? 'selected' : '' ?>>Seqrite</option><option value="Cynet" <?= $item['endpoint_protection'] == 'Cynet' ? 'selected' : '' ?>>Cynet</option><option value="Windows Defender" <?= $item['endpoint_protection'] == 'Windows Defender' ? 'selected' : '' ?>>Windows Defender</option><option value="others" <?= !in_array($item['endpoint_protection'], ['','Seqrite','Cynet','Windows Defender']) ? 'selected' : '' ?>>Others</option></select></div>
                </div>
                <div class="form-row" id="endpoint_other_div" style="display:none;"><div class="col form-group"><label>Specify Other</label><input name="endpoint_other" class="form-control" value="<?= !in_array($item['endpoint_protection'], ['','Seqrite','Cynet','Windows Defender']) ? htmlspecialchars($item['endpoint_protection']) : '' ?>"></div></div>
                <div class="form-row">
                    <div class="col-md-6 form-group"><label>Updated EPP</label><div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="endpoint_updated" value="1" <?= $item['endpoint_updated'] ? 'checked' : '' ?>><label class="form-check-label">Yes</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="endpoint_updated" value="0" <?= !$item['endpoint_updated'] ? 'checked' : '' ?>><label class="form-check-label">No</label></div></div></div>
                    <div class="col-md-6 form-group"><label>Any Desk ID</label><input name="anydesk_id" class="form-control" value="<?= htmlspecialchars($item['anydesk_id']) ?>"></div>
                </div>
                <hr>
                <div class="form-row">
                    <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="belarc_installed" id="belarc" value="1" <?= $item['belarc_installed'] ? 'checked' : '' ?>><label class="form-check-label" for="belarc">Belarc Installed</label></div></div>
                    <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="accounts_updated" id="accounts" value="1" <?= $item['accounts_updated'] ? 'checked' : '' ?>><label class="form-check-label" for="accounts">Accounts Updated</label></div></div>
                    <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="ultravnc_installed" id="ultravnc" value="1" <?= $item['ultravnc_installed'] ? 'checked' : '' ?>><label class="form-check-label" for="ultravnc">UltraVNC Installed</label></div></div>
                    <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="snmp_installed" id="snmp" value="1" <?= $item['snmp_installed'] ? 'checked' : '' ?>><label class="form-check-label" for="snmp">SNMP Installed</label></div></div>
                </div>
            </div></div></div>
            
            <div id="network_config_section" style="display:none;"><div class="card mb-4"><div class="card-header">Network Configuration</div><div class="card-body">
                <div class="form-row">
                    <div class="col-md-4 form-group"><label>Connection</label><select name="connection_type" class="form-control"><option value="">Select</option><option value="LAN" <?= $item['connection_type'] == 'LAN' ? 'selected' : '' ?>>LAN</option><option value="WLAN" <?= $item['connection_type'] == 'WLAN' ? 'selected' : '' ?>>WLAN</option><option value="NONE" <?= $item['connection_type'] == 'NONE' ? 'selected' : '' ?>>NONE</option></select></div>
                    <div class="col-md-4 form-group"><label>DHCP Type</label><select name="dhcp_type" id="dhcp_type" class="form-control" onchange="toggleStaticApp()"><option value="">Select</option><option value="DHCP" <?= $item['dhcp_type'] == 'DHCP' ? 'selected' : '' ?>>DHCP</option><option value="static" <?= $item['dhcp_type'] == 'static' ? 'selected' : '' ?>>Static</option></select></div>
                    <div class="col-md-4 form-group" id="static_app_div"><label>Static IP App</label><input name="static_app" class="form-control" value="<?= htmlspecialchars($item['static_app']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="col-md-6 form-group"><label>IP Address 1</label><input name="ip_address1" class="form-control" value="<?= htmlspecialchars($item['ip_address1']) ?>"></div>
                    <div class="col-md-6 form-group"><label>IP Address 2</label><input name="ip_address2" class="form-control" value="<?= htmlspecialchars($item['ip_address2']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="col-md-4 form-group"><label>LAN MAC</label><input name="lan_mac" class="form-control" value="<?= htmlspecialchars($item['lan_mac']) ?>"></div>
                    <div class="col-md-4 form-group"><label>WLAN MAC 1</label><input name="wlan_mac1" class="form-control" value="<?= htmlspecialchars($item['wlan_mac1']) ?>"></div>
                    <div class="col-md-4 form-group"><label>WLAN MAC 2</label><input name="wlan_mac2" class="form-control" value="<?= htmlspecialchars($item['wlan_mac2']) ?>"></div>
                </div>
                <div class="form-row"><div class="col-md-12 form-group"><label>Gateway</label><input name="gateway" class="form-control" value="<?= htmlspecialchars($item['gateway']) ?>"></div></div>
            </div></div></div>

            <div class="card mb-4">
                <div class="card-header">Administrative & Supplier Information</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Office</label>
                            <select name="office_id" class="form-control">
                                <option value="">Select Office</option>
                                <?php foreach($offices as $o): ?>
                                <option value="<?= $o['id'] ?>" <?= $item['office_id'] == $o['id'] ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Serial Number</label>
                            <input name="serial_number" class="form-control" value="<?= htmlspecialchars($item['serial_number']) ?>">
                        </div>
                    </div>
					<div class="form-row">
                        <div class="col-md-4 form-group">
                            <label>MIAA Property</label>
                            <select name="miaa_property" class="form-control">
                                <option value="">Select Type</option>
                                <option value="MIAA Property" <?= $item['miaa_property'] == 'MIAA Property' ? 'selected' : '' ?>>MIAA Property</option>
                                <option value="Donated" <?= $item['miaa_property'] == 'Donated' ? 'selected' : '' ?>>Donated</option>
                                <option value="Personal" <?= $item['miaa_property'] == 'Personal' ? 'selected' : '' ?>>Personal</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Memorandum Receipt (MR)</label>
                            <input name="memorandum_receipt" class="form-control" value="<?= htmlspecialchars($item['memorandum_receipt']) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>P.O. Number</label>
                            <input name="po_number" class="form-control" value="<?= htmlspecialchars($item['po_number']) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Acquisition Date</label>
                            <input type="date" name="acquisition_date" class="form-control" value="<?= htmlspecialchars($item['acquisition_date']) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Warranty</label>
                            <input name="warranty" class="form-control" value="<?= htmlspecialchars($item['warranty']) ?>" placeholder="e.g. 1 Year">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Supplier Name</label>
                            <input name="supplier_name" class="form-control" value="<?= htmlspecialchars($item['supplier_name']) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Supplier Contact Person</label>
                            <input name="supplier_contact_name" class="form-control" value="<?= htmlspecialchars($item['supplier_contact_name']) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Supplier Phone</label>
                            <input name="supplier_contact" class="form-control" value="<?= htmlspecialchars($item['supplier_contact']) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Supplier Email</label>
                            <input type="email" name="supplier_email" class="form-control" value="<?= htmlspecialchars($item['supplier_email']) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Supplier Address</label>
                        <textarea name="supplier_address" class="form-control" rows="2"><?= htmlspecialchars($item['supplier_address']) ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">Inventory Info</div>
                 <div class="card-body">
                     <div class="row">
                        <div class="col-md-4 form-group"><label>Quantity</label><input type="number" name="quantity" class="form-control" value="<?= htmlspecialchars($item['quantity']) ?>" required min="0"></div>
                        <div class="col-md-4 form-group"><label>Unit</label><select name="unit" class="form-control"><option value="pieces" <?= $item['unit'] == 'pieces' ? 'selected' : '' ?>>Pieces</option><option value="boxes" <?= $item['unit'] == 'boxes' ? 'selected' : '' ?>>Boxes</option><option value="others" <?= $item['unit'] == 'others' ? 'selected' : '' ?>>Others</option></select></div>
                        <div class="col-md-4 form-group"><label>Item Condition</label><select name="status" class="form-control"><option value="brand_new" <?= $item['status'] == 'brand_new' ? 'selected' : '' ?>>Brand New</option><option value="for_replacement" <?= $item['status'] == 'for_replacement' ? 'selected' : '' ?>>For Replacement</option><option value="for_condemn" <?= $item['status'] == 'for_condemn' ? 'selected' : '' ?>>For Condemn</option><option value="condemned" <?= $item['status'] == 'condemned' ? 'selected' : '' ?>>Condemned</option></select></div>
                    </div>
                </div>
            </div>

            <div class="mb-5">
                <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fa-solid fa-save mr-2"></i>Update Asset</button>
                <a href="items.php" class="btn btn-secondary btn-lg px-4"><i class="fa-solid fa-arrow-left mr-2"></i>Back</a>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
