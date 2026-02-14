<?php
require '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /inventory_system/login.php");
    exit;
}

$admin_name = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';

function p($key, $default = null) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

$err = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic Information
    $property_number = p('property_number');
    $product_name = p('product_name');
    $category_id = p('category_id') !== '' ? intval(p('category_id')) : null;
    $brand = p('brand');
    $model = p('model');
    
    // Computer Specifications
    $processor = p('processor');
    $ram = p('ram');
    $graphics1 = p('graphics1');
    $graphics2 = p('graphics2');
    $storage_type = p('storage_type');
    $storage_capacity = p('storage_capacity');
    
    // Software Configuration
    $computer_name = p('computer_name');
    $workgroup = p('workgroup');
    $os = p('os');
    $office_app = p('office_app');
    $ms_account = p('ms_account');
    $endpoint_protection = p('endpoint_protection');
    $endpoint_other = p('endpoint_other');
    if ($endpoint_protection === 'others') $endpoint_protection = $endpoint_other;
    $endpoint_updated = isset($_POST['endpoint_updated']) ? intval($_POST['endpoint_updated']) : 0;
    $anydesk_id = p('anydesk_id');
    $belarc_installed = isset($_POST['belarc_installed']) ? 1 : 0;
    $accounts_updated = isset($_POST['accounts_updated']) ? 1 : 0;
    $ultravnc_installed = isset($_POST['ultravnc_installed']) ? 1 : 0;
    $snmp_installed = isset($_POST['snmp_installed']) ? 1 : 0;
    
    // Network Configuration
    $connection_type = p('connection_type');
    $dhcp_type = p('dhcp_type');
    $static_app = p('static_app');
    $ip_address1 = p('ip_address1');
    $ip_address2 = p('ip_address2');
    $lan_mac = p('lan_mac');
    $wlan_mac1 = p('wlan_mac1');
    $wlan_mac2 = p('wlan_mac2');
    $gateway = p('gateway');
    
    // Administrative Information
    $office_id = p('office_id') !== '' ? intval(p('office_id')) : null;
    $miaa_property = p('miaa_property');
    $memorandum_receipt = p('memorandum_receipt');
    $po_number = p('po_number');
    $serial_number = p('serial_number');
    
    // Category-specific fields
    $display_size = p('display_size');
    $printer_type = p('printer_type');
    $capacity_va = p('capacity_va');
    $other_details = p('other_details');
    
    // Network Equipment
    $network_equipment_type = p('network_equipment_type');
    $network_equipment_other = p('network_equipment_other');
    if ($network_equipment_type === 'others') $network_equipment_type = $network_equipment_other;
    $area_of_deployment = p('area_of_deployment');
    
    // System fields
    $quantity = 1;
    $unit = 'pieces';
    $status = 'brand_new';
    $created_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
    $is_deleted = 0;

    $USERids = p('USERids');
    $acquisition_date = p('acquisition_date');
    $supplier_name = p('supplier_name');
    $warranty = p('warranty');
	
	$supplier_contact = p('supplier_contact');
	$supplier_email = p('supplier_email');
	$supplier_address = p('supplier_address');
	$supplier_contact_name = p('supplier_contact_name');

    // Check if property number already exists
    $check_sql = "SELECT id FROM items WHERE property_number = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("s", $property_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $err = "Error: Property number '$property_number' already exists.";
    } else {
        require_once '../vendor/phpqrcode/qrlib.php';
        $qrDir = $_SERVER['DOCUMENT_ROOT'] . "/inventory_system/qrcodes/";
        if (!file_exists($qrDir)) mkdir($qrDir, 0777, true);
        $qrFile = $qrDir . "qr_" . $property_number . ".png";
        QRcode::png($property_number, $qrFile, QR_ECLEVEL_L, 10);
        $qr_code_path = "/inventory_system/qrcodes/qr_" . $property_number . ".png";

        // SQL Template - Total 59 Columns/Placeholders
        $sql = "INSERT INTO items (
            property_number, product_name, category_id, brand, model, 
            processor, ram, graphics1, graphics2, computer_name, 
            workgroup, os, office_app, ms_account, endpoint_protection, 
            endpoint_updated, anydesk_id, belarc_installed, accounts_updated, 
            ultravnc_installed, snmp_installed, connection_type, dhcp_type, static_app, 
            ip_address1, ip_address2, lan_mac, wlan_mac1, wlan_mac2, gateway, 
            office_id, USERids, miaa_property, memorandum_receipt, po_number, 
            serial_number, display_size, printer_type, capacity_va, other_details, 
            network_equipment_type, network_equipment_other, area_of_deployment, 
            storage_type, storage_capacity, qr_code_path, quantity, unit, 
            status, created_by, is_active, is_deleted, acquisition_date, 
            supplier_name, supplier_contact, supplier_email, supplier_address, supplier_contact_name, warranty
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $stmt = $db->prepare($sql);

        if ($stmt) {
            // Fix: ensure types string length matches number of placeholders.
            // Use 59 's' characters so bind_param alignment is correct and all fields (including connection_type, dhcp_type, miaa_property) are passed.
            $types = str_repeat('s', 59);
            
            $stmt->bind_param(
                $types,
                $property_number,      // s
                $product_name,         // s  
                $category_id,          // i (passed as string here to keep binding aligned)
                $brand,                // s
                $model,                // s
                $processor,            // s
                $ram,                  // s
                $graphics1,            // s
                $graphics2,            // s
                $computer_name,        // s
                $workgroup,            // s
                $os,                   // s
                $office_app,           // s
                $ms_account,           // s
                $endpoint_protection,  // s
                $endpoint_updated,     // i (passed as string)
                $anydesk_id,           // s
                $belarc_installed,     // i (passed as string)
                $accounts_updated,     // i (passed as string)
                $ultravnc_installed,   // i (passed as string)
                $snmp_installed,       // i (passed as string)
                $connection_type,      // s
                $dhcp_type,            // s
                $static_app,           // s
                $ip_address1,          // s
                $ip_address2,          // s
                $lan_mac,              // s
                $wlan_mac1,            // s
                $wlan_mac2,            // s
                $gateway,              // s
                $office_id,            // i (passed as string)
                $USERids,              // s
                $miaa_property,        // s
                $memorandum_receipt,   // s
                $po_number,            // s
                $serial_number,        // s
                $display_size,         // s
                $printer_type,         // s
                $capacity_va,          // s
                $other_details,        // s
                $network_equipment_type, // s
                $network_equipment_other, // s
                $area_of_deployment,   // s
                $storage_type,         // s
                $storage_capacity,     // s
                $qr_code_path,         // s
                $quantity,             // i (passed as string)
                $unit,                 // s
                $status,               // s
                $created_by,           // i (passed as string)
                $is_active,            // i (passed as string)
                $is_deleted,           // i (passed as string)
                $acquisition_date,     // s
                $supplier_name,        // s
                $supplier_contact,     // s
                $supplier_email,       // s
                $supplier_address,     // s
                $supplier_contact_name,// s
                $warranty              // s
            );

            if ($stmt->execute()) {
                $item_id = $stmt->insert_id;
                $log_sql = "INSERT INTO audit_logs (performed_by, item_id, action, log_details, created_at) VALUES (?, ?, 'add', ?, NOW())";
                $log_stmt = $db->prepare($log_sql);
                $log_details = "Added new asset: $property_number - $product_name";
                $log_stmt->bind_param("iis", $_SESSION['user_id'], $item_id, $log_details);
                $log_stmt->execute();
                $log_stmt->close();
                $success = "Asset added successfully.";
            } else {
                $err = "MySQL Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $err = "Failed to prepare statement: " . $db->error;
        }
    }
    $check_stmt->close();
}

$cats = $db->query("SELECT * FROM categories WHERE category_type='asset' ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$offices = $db->query("SELECT * FROM offices ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <title>Add New Asset | Inventory System</title>
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
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
            color: var(--primary-color);
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
            border-color: #8ab7ed;
        }
    </style>
    <script>
    function showSection(id, show) {
        var el = document.getElementById(id);
        if (el) el.style.display = show ? 'block' : 'none';
    }
    function updateFormSections() {
        var catSelect = document.getElementById('category_id');
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
    });
    </script>
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
    <a href="items.php" class="active"><i class="fa-solid fa-box"></i> Items</a>
    <a href="requests.php"><i class="fa-solid fa-file-circle-check"></i> Requests</a>
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
            <h1 class="h3 mb-0 text-gray-800">➕ Add New Asset</h1>
            <p class="text-muted mb-0">Fill in the details to add a new asset to the inventory.</p>
        </div>

        <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        
        <form method="post" autocomplete="off">
            <input type="hidden" name="quantity" value="1">
            
            <div class="card mb-4">
                <div class="card-header">Basic Information</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Property Number *</label>
                            <input name="property_number" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Product Name *</label>
                            <input name="product_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Category *</label>
                            <select name="category_id" id="category_id" class="form-control" required onchange="updateFormSections()">
                                <option value="">Select Category</option>
                                <?php foreach($cats as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Status</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="is_active" id="active_yes" value="1" checked>
                                    <label class="form-check-label" for="active_yes">Active</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="is_active" id="active_no" value="0">
                                    <label class="form-check-label" for="active_no">Not Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Brand</label>
                            <input name="brand" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Model</label>
                            <input name="model" class="form-control">
                        </div>
                    </div>
                </div>
            </div>

            <div id="monitor_section" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header">Monitor Details</div>
                    <div class="card-body"><div class="form-group"><label>Display Size</label><input name="display_size" class="form-control" placeholder="e.g., 24 inches"></div></div>
                </div>
            </div>
            <div id="printer_section" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header">Printer Details</div>
                    <div class="card-body"><div class="form-group"><label>Type of Printer</label><input name="printer_type" class="form-control"></div></div>
                </div>
            </div>
            <div id="ups_section" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header">UPS Details</div>
                    <div class="card-body"><div class="form-group"><label>Capacity (VA)</label><input name="capacity_va" class="form-control" placeholder="e.g., 1000VA"></div></div>
                </div>
            </div>
            <div id="scanner_section" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header">Scanner Details</div>
                    <div class="card-body"><div class="form-group"><label>Other Details</label><textarea name="other_details" class="form-control" rows="3"></textarea></div></div>
                </div>
            </div>
            <div id="speaker_section" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header">Speaker Details</div>
                    <div class="card-body"><div class="form-group"><label>Other Details</label><textarea name="other_details" class="form-control" rows="3"></textarea></div></div>
                </div>
            </div>
            <div id="portable_section" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header">Portable Device Details</div>
                    <div class="card-body"><div class="form-group"><label>Other Details</label><textarea name="other_details" class="form-control" rows="3"></textarea></div></div>
                </div>
            </div>
            <div id="network_section" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header">Network Equipment Details</div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Network Equipment Type</label>
                            <select name="network_equipment_type" id="network_equipment_type" class="form-control" onchange="toggleNetworkEquipmentOther()">
                                <option value="">Select Type</option>
                                <option value="Switch">Switch</option>
                                <option value="Access Point">Access Point</option>
                                <option value="Router">Router</option>
                                <option value="Controller">Controller</option>
                                <option value="others">Others</option>
                            </select>
                        </div>
                        <div id="network_equipment_other_div" style="display:none">
                            <div class="form-group">
                                <label>Specify Other Network Equipment</label>
                                <input name="network_equipment_other" class="form-control">
                            </div>
                        </div>
                        <div class="form-group"><label>Area of Deployment</label><input name="area_of_deployment" class="form-control"></div>
                        <div class="form-group"><label>Other Details</label><textarea name="other_details" class="form-control" rows="3"></textarea></div>
                    </div>
                </div>
            </div>
            <div id="server_section" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header">Server Details</div>
                    <div class="card-body"><div class="form-group"><label>Other Details</label><textarea name="other_details" class="form-control" rows="3"></textarea></div></div>
                </div>
            </div>
            <div id="smarttv_section" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header">SmartTV Details</div>
                    <div class="card-body"><div class="form-group"><label>Other Details</label><textarea name="other_details" class="form-control" rows="3"></textarea></div></div>
                </div>
            </div>

            <div id="computer_section" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header">Technical Specifications</div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="col-md-4 form-group"><label>Processor</label><input name="processor" class="form-control"></div>
                            <div class="col-md-4 form-group"><label>Storage Type</label><select name="storage_type" class="form-control"><option value="">Select Type</option><option value="SSD">SSD</option><option value="HDD">HDD</option><option value="BOTH">BOTH</option></select></div>
                            <div class="col-md-4 form-group"><label>Storage Capacity</label><input name="storage_capacity" class="form-control" placeholder="e.g., 256 SSD / 1TB HDD"></div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-4 form-group"><label>RAM</label><input name="ram" class="form-control"></div>
                            <div class="col-md-4 form-group"><label>Graphics 1</label><input name="graphics1" class="form-control"></div>
                            <div class="col-md-4 form-group"><label>Graphics 2</label><input name="graphics2" class="form-control"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="software_section" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header">Software Configuration</div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="col-md-6 form-group"><label>Computer Name</label><input name="computer_name" class="form-control"></div>
                            <div class="col-md-6 form-group"><label>Workgroup</label><input name="workgroup" class="form-control"></div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-6 form-group"><label>Operating System</label><input name="os" class="form-control"></div>
                            <div class="col-md-6 form-group"><label>Office Application</label><select name="office_app" class="form-control"><option value="">Select Application</option><option value="365">365</option><option value="Libre Office">Libre Office</option><option value="WPS">WPS</option></select></div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-6 form-group"><label>Microsoft 365 Account (if applicable)</label><input name="ms_account" class="form-control"></div>
                            <div class="col-md-6 form-group"><label>End Point Protection</label><select name="endpoint_protection" id="endpoint_protection" class="form-control" onchange="toggleEndpointOther()"><option value="">Select Protection</option><option value="Seqrite">Seqrite</option><option value="Cynet">Cynet</option><option value="Windows Defender">Windows Defender</option><option value="others">Others</option></select></div>
                        </div>
                        <div class="form-row" id="endpoint_other_div" style="display:none;"><div class="col form-group"><label>Specify Other End Point Protection</label><input name="endpoint_other" class="form-control"></div></div>
                        <div class="form-row">
                            <div class="col-md-6 form-group"><label>Updated End-Point-Protection</label><div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="endpoint_updated" id="ep_yes" value="1"><label class="form-check-label" for="ep_yes">Yes</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="endpoint_updated" id="ep_no" value="0" checked><label class="form-check-label" for="ep_no">No</label></div></div></div>
                            <div class="col-md-6 form-group"><label>Any Desk ID</label><input name="anydesk_id" class="form-control"></div>
                        </div>
                        <hr>
                        <div class="form-row">
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="belarc_installed" id="belarc"><label class="form-check-label" for="belarc">Belarc Installed & Saved</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="accounts_updated" id="accounts"><label class="form-check-label" for="accounts">Auditmisd Accounts Checked & Updated</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="ultravnc_installed" id="ultravnc"><label class="form-check-label" for="ultravnc">Ultra VNC Installed & Configured</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="snmp_installed" id="snmp"><label class="form-check-label" for="snmp">SNMP Installed</label></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="network_config_section" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header">Network Configuration</div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="col-md-4 form-group"><label>Connection Type</label><select name="connection_type" class="form-control"><option value="">Select Type</option><option value="LAN">LAN</option><option value="WLAN">WLAN</option><option value="NONE">NONE</option></select></div>
                            <div class="col-md-4 form-group"><label>DHCP Type</label><select name="dhcp_type" id="dhcp_type" class="form-control" onchange="toggleStaticApp()"><option value="">Select Type</option><option value="DHCP">DHCP</option><option value="static">Static</option></select></div>
                            <div class="col-md-4 form-group" id="static_app_div" style="display:none;"><label>Static IP Application</label><input name="static_app" class="form-control"></div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-6 form-group"><label>IP Address 1</label><input name="ip_address1" class="form-control"></div>
                            <div class="col-md-6 form-group"><label>IP Address 2</label><input name="ip_address2" class="form-control"></div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-4 form-group"><label>LAN MAC Address</label><input name="lan_mac" class="form-control"></div>
                            <div class="col-md-4 form-group"><label>WLAN MAC Address 1</label><input name="wlan_mac1" class="form-control"></div>
                            <div class="col-md-4 form-group"><label>WLAN MAC Address 2</label><input name='wlan_mac2' class="form-control"></div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-12 form-group"><label>Gateway</label><input name="gateway" class="form-control"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Administrative Information</div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Office</label>
                        <select name="office_id" class="form-control">
                            <option value="">Select Office</option>
                            <?php foreach($offices as $office): ?>
                            <option value="<?= $office['id'] ?>"><?= htmlspecialchars($office['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label>MIAA Property</label>
                            <select name="miaa_property" class="form-control">
                                <option value="">Select Type</option>
                                <option value="MIAA Property">MIAA Property</option>
                                <option value="Donated">Donated</option>
                                <option value="Personal">Personal</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Memorandum Receipt (MR)</label>
                            <input name="memorandum_receipt" class="form-control">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>P.O. Number</label>
                            <input name="po_number" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input name="serial_number" class="form-control">
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label>Name of User</label>
                            <input name="USERids" class="form-control" placeholder="Enter user assigned">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Acquisition Date</label>
                            <input type="date" name="acquisition_date" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label>Name of Supplier</label>
                            <input name="supplier_name" class="form-control" placeholder="Enter supplier name">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Warranty</label>
                            <input name="warranty" class="form-control" placeholder="e.g., 1 year, 2 years, none">
                        </div>
						<div class="col-md-6 form-group">
                <label>Supplier Contact #</label>
                <input name="supplier_contact" class="form-control" placeholder="Enter supplier contact #">
            </div>
            <div class="col-md-6 form-group">
                <label>Supplier Email</label>
                <input type='email' name="supplier_email" class="form-control" placeholder="example: sharp@miaa.gov.ph">
            </div>
            <div class="col-md-6 form-group">
                <label>Supplier Address</label>
                <input name="supplier_address" class="form-control" placeholder="example: 123-A Pasay City">
            </div>
            <div class="col-md-6 form-group">
                <label>Supplier Contact Name</label>
                <input name="supplier_contact_name" class="form-control" placeholder="example: Nico Briones">
            </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus-circle mr-2"></i>Add Asset</button>
            <a href="items.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left mr-2"></i>Back to Items</a>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
