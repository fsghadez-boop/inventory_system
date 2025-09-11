<?php
// add_item_assets.php
require '../config/db.php';

// Helper: safe POST fetch
function p($key, $default = null) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// Initialize variables (so form doesn't throw undefined index notices)
$err = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize/collect inputs
    $property_number         = p('property_number');
    $product_name            = p('product_name');
    $category_id             = p('category_id') !== '' ? intval(p('category_id')) : null;
    $brand                   = p('brand');
    $model                   = p('model');
    $processor               = p('processor');
    $ram                     = p('ram');
    $graphics1               = p('graphics1');
    $graphics2               = p('graphics2');
    $computer_name           = p('computer_name');
    $workgroup               = p('workgroup');
    $os                      = p('os');
    $office_app              = p('office_app');
    $ms_account              = p('ms_account');
    $endpoint_protection     = p('endpoint_protection');
    $endpoint_updated        = isset($_POST['endpoint_updated']) ? 1 : 0;
    $anydesk_id              = p('anydesk_id');
    $belarc_installed        = isset($_POST['belarc_installed']) ? 1 : 0;
    $accounts_updated        = isset($_POST['accounts_updated']) ? 1 : 0;
    $ultravnc_installed      = isset($_POST['ultravnc_installed']) ? 1 : 0;
    $snmp_installed          = isset($_POST['snmp_installed']) ? 1 : 0;
    $connection_type         = p('connection_type');
    $dhcp_type               = p('dhcp_type');
    $static_app              = p('static_app');
    $ip_address1             = p('ip_address1');
    $ip_address2             = p('ip_address2');
    $lan_mac                 = p('lan_mac');
    $wlan_mac1               = p('wlan_mac1');
    $wlan_mac2               = p('wlan_mac2');
    $gateway                 = p('gateway');
    $office_id               = p('office_id') !== '' ? intval(p('office_id')) : null;
    $miaa_property           = p('miaa_property');
    $memorandum_receipt      = p('memorandum_receipt');
    $po_number               = p('po_number');
    $serial_number           = p('serial_number');
    $display_size            = p('display_size');
    $printer_type            = p('printer_type');
    $capacity_va             = p('capacity_va');
    $other_details           = p('other_details');
    $network_equipment_type  = p('network_equipment_type');
    $network_equipment_other = p('network_equipment_other');
    $area_of_deployment      = p('area_of_deployment');
    $storage_type            = p('storage_type');
    $storage_capacity        = p('storage_capacity');
    $qr_code_path            = p('qr_code_path');
    $quantity                = p('quantity') !== '' ? intval(p('quantity')) : 1;
    $unit                    = p('unit', 'pieces');
    $status                  = p('status', 'brand_new');
    $created_by              = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $is_active               = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

	// Generate QR code
    require_once '../vendor/phpqrcode/qrlib.php';
    $qrDir = "../assets/qrcodes/";
    if (!file_exists($qrDir)) mkdir($qrDir, 0777, true);
    $qrFile = $qrDir . $property_number . ".png";
    QRcode::png($property_number, $qrFile);
    $qr_code_path = $qrFile;

    // Determine whether update (id passed) or insert
    $is_update = (isset($_POST['id']) && $_POST['id'] !== '');

    if ($is_update) {
        $id = intval($_POST['id']);

        $sql = "UPDATE items SET
            property_number=?, product_name=?, category_id=?, brand=?, model=?, processor=?, ram=?, graphics1=?, graphics2=?,
            computer_name=?, workgroup=?, os=?, office_app=?, ms_account=?, endpoint_protection=?, endpoint_updated=?,
            anydesk_id=?, belarc_installed=?, accounts_updated=?, ultravnc_installed=?, snmp_installed=?,
            connection_type=?, dhcp_type=?, static_app=?, ip_address1=?, ip_address2=?, lan_mac=?, wlan_mac1=?, wlan_mac2=?,
            gateway=?, office_id=?, miaa_property=?, memorandum_receipt=?, po_number=?, serial_number=?, display_size=?,
            printer_type=?, capacity_va=?, other_details=?, network_equipment_type=?, network_equipment_other=?,
            area_of_deployment=?, storage_type=?, storage_capacity=?, qr_code_path=?, quantity=?, unit=?, status=?,
            created_by=?, is_active=?
            WHERE id=?";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            $err = "Prepare failed: " . $db->error;
        } else {
            // Types string (50 fields + id)
            $types = 'ssissssssssssssisiiiisssssssssissssssssssssssissii' . 'i';
            // bind params in the exact column order above
            $bind = $stmt->bind_param(
                $types,
                $property_number, $product_name, $category_id, $brand, $model, $processor, $ram, $graphics1, $graphics2,
                $computer_name, $workgroup, $os, $office_app, $ms_account, $endpoint_protection, $endpoint_updated,
                $anydesk_id, $belarc_installed, $accounts_updated, $ultravnc_installed, $snmp_installed,
                $connection_type, $dhcp_type, $static_app, $ip_address1, $ip_address2, $lan_mac, $wlan_mac1, $wlan_mac2,
                $gateway, $office_id, $miaa_property, $memorandum_receipt, $po_number, $serial_number, $display_size,
                $printer_type, $capacity_va, $other_details, $network_equipment_type, $network_equipment_other,
                $area_of_deployment, $storage_type, $storage_capacity, $qr_code_path, $quantity, $unit, $status,
                $created_by, $is_active, $id
            );

            if ($bind === false) {
                $err = "bind_param failed: " . $stmt->error;
            } else {
                if ($stmt->execute()) {
                    $success = "Item updated successfully.";
                } else {
                    $err = "Error updating item: " . $stmt->error;
                }
            }
            $stmt->close();
        }
    } else {
        // INSERT path (50 columns)
        $sql = "INSERT INTO items (
            property_number, product_name, category_id, brand, model, processor, ram, graphics1, graphics2,
            computer_name, workgroup, os, office_app, ms_account, endpoint_protection, endpoint_updated,
            anydesk_id, belarc_installed, accounts_updated, ultravnc_installed, snmp_installed,
            connection_type, dhcp_type, static_app, ip_address1, ip_address2, lan_mac, wlan_mac1, wlan_mac2,
            gateway, office_id, miaa_property, memorandum_receipt, po_number, serial_number, display_size,
            printer_type, capacity_va, other_details, network_equipment_type, network_equipment_other,
            area_of_deployment, storage_type, storage_capacity, qr_code_path, quantity, unit, status,
            created_by, is_active
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            $err = "Prepare failed: " . $db->error;
        } else {
            // Types string (50 fields)
            $types = 'ssissssssssssssisiiiisssssssssissssssssssssssissii';
            $bind = $stmt->bind_param(
                $types,
                $property_number, $product_name, $category_id, $brand, $model, $processor, $ram, $graphics1, $graphics2,
                $computer_name, $workgroup, $os, $office_app, $ms_account, $endpoint_protection, $endpoint_updated,
                $anydesk_id, $belarc_installed, $accounts_updated, $ultravnc_installed, $snmp_installed,
                $connection_type, $dhcp_type, $static_app, $ip_address1, $ip_address2, $lan_mac, $wlan_mac1, $wlan_mac2,
                $gateway, $office_id, $miaa_property, $memorandum_receipt, $po_number, $serial_number, $display_size,
                $printer_type, $capacity_va, $other_details, $network_equipment_type, $network_equipment_other,
                $area_of_deployment, $storage_type, $storage_capacity, $qr_code_path, $quantity, $unit, $status,
                $created_by, $is_active
            );

            if ($bind === false) {
                $err = "bind_param failed: " . $stmt->error;
            } else {
                if ($stmt->execute()) {
                    $success = "Item added successfully.";
                } else {
                    // show nicer error (e.g., duplicate property_number)
                    $err = "Error adding item: " . $stmt->error;
                }
            }
            $stmt->close();
        }
    }
}

// Fetch categories and offices for select lists
$cats = $db->query("SELECT * FROM categories WHERE category_type = 'asset' ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$offices = $db->query("SELECT * FROM offices ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
<title>Add Asset</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<script>

// automatically set quantity to 1
function setDefaultQuantity() {
    document.getElementById('quantity').value = 1;
    document.getElementById('quantity').readOnly = true;
}

// Call function on page load
window.onload = function() {
    setDefaultQuantity();
    updateFormSections(); // Keep your existing function call
};

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

function updateFormSections() {
    var categorySelect = document.getElementById('category_id');
    var categoryId = categorySelect.value;
    var categoryName = categorySelect.options[categorySelect.selectedIndex].text.toLowerCase();
    
    // Hide all sections first
    document.getElementById('monitor_section').style.display = 'none';
    document.getElementById('printer_section').style.display = 'none';
    document.getElementById('ups_section').style.display = 'none';
    document.getElementById('scanner_section').style.display = 'none';
    document.getElementById('speaker_section').style.display = 'none';
    document.getElementById('portable_section').style.display = 'none';
    document.getElementById('network_section').style.display = 'none';
    document.getElementById('server_section').style.display = 'none';
    document.getElementById('smarttv_section').style.display = 'none';
    document.getElementById('computer_section').style.display = 'none';
    document.getElementById('software_section').style.display = 'none';
    document.getElementById('network_config_section').style.display = 'none';
    
    // Show sections based on category
    if (categoryName.includes('monitor')) {
        document.getElementById('monitor_section').style.display = 'block';
    } else if (categoryName.includes('printer')) {
        document.getElementById('printer_section').style.display = 'block';
        document.getElementById('network_config_section').style.display = 'block';
    } else if (categoryName.includes('ups')) {
        document.getElementById('ups_section').style.display = 'block';
        document.getElementById('network_config_section').style.display = 'block';
    } else if (categoryName.includes('scanner')) {
        document.getElementById('scanner_section').style.display = 'block';
    } else if (categoryName.includes('speaker')) {
        document.getElementById('speaker_section').style.display = 'block';
    } else if (categoryName.includes('portable')) {
        document.getElementById('portable_section').style.display = 'block';
        document.getElementById('network_config_section').style.display = 'block';
    } else if (categoryName.includes('network')) {
        document.getElementById('network_section').style.display = 'block';
        document.getElementById('network_config_section').style.display = 'block';
    } else if (categoryName.includes('server')) {
        document.getElementById('server_section').style.display = 'block';
        document.getElementById('network_config_section').style.display = 'block';
    } else if (categoryName.includes('smart') || categoryName.includes('tv')) {
        document.getElementById('smarttv_section').style.display = 'block';
        document.getElementById('network_config_section').style.display = 'block';
    } else if (categoryName.includes('system unit') || categoryName.includes('laptop')) {
        document.getElementById('computer_section').style.display = 'block';
        document.getElementById('software_section').style.display = 'block';
        document.getElementById('network_config_section').style.display = 'block';
    } else {
        // Default section for other categories
        document.getElementById('network_config_section').style.display = 'block';
    }
}
</script>
</head>
<body onload="updateFormSections()">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Admin Inventory</a>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item"><a href="items.php" class="nav-link">Items</a></li>
            <li class="nav-item"><a href="categories.php" class="nav-link">Categories</a></li>
            <li class="nav-item"><a href="requests.php" class="nav-link">Requests</a></li>
            <li class="nav-item"><a href="audit_logs.php" class="nav-link">Audit Logs</a></li>
            <li class="nav-item"><a href="/inventory_system/logout.php" class="nav-link">Logout</a></li>
        </ul>
    </div>
</nav>
<div class="container pt-5">
    <h4 class="mt-4">Add New Asset</h4>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post">
        <div class="card mb-4">
            <div class="card-header">Basic Information</div>
            <div class="card-body">
			<div class="form-group">
			<input type="hidden" name="quantity" value="1">
			</div>
			
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Property Number *</label>
                            <input name="property_number" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Product Name *</label>
                            <input name="product_name" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category_id" id="category_id" class="form-control" required onchange="updateFormSections()">
                                <option value="">Select Category</option>
                                <?php foreach($cats as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
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
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Brand</label>
                            <input name="brand" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Model</label>
                            <input name="model" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Monitor Section -->
        <div class="card mb-4" id="monitor_section" style="display: none;">
            <div class="card-header">Monitor Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Display Size</label>
                            <input name="display_size" class="form-control" placeholder="e.g., 24 inches">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Printer Section -->
        <div class="card mb-4" id="printer_section" style="display: none;">
            <div class="card-header">Printer Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Type of Printer</label>
                            <input name="printer_type" class="form-control" placeholder="e.g., Laser, Inkjet, etc.">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- UPS Section -->
        <div class="card mb-4" id="ups_section" style="display: none;">
            <div class="card-header">UPS Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Capacity (VA)</label>
                            <input name="capacity_va" class="form-control" placeholder="e.g., 1000VA">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scanner Section -->
        <div class="card mb-4" id="scanner_section" style="display: none;">
            <div class="card-header">Scanner Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Other Details</label>
                            <textarea name="other_details" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Speaker Section -->
        <div class="card mb-4" id="speaker_section" style="display: none;">
            <div class="card-header">Speaker Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Other Details</label>
                            <textarea name="other_details" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Portable Devices Section -->
        <div class="card mb-4" id="portable_section" style="display: none;">
            <div class="card-header">Portable Device Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Other Details</label>
                            <textarea name="other_details" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Network Equipment Section -->
        <div class="card mb-4" id="network_section" style="display: none;">
            <div class="card-header">Network Equipment Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
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
                        <div id="network_equipment_other_div" style="display: none;">
                            <div class="form-group">
                                <label>Specify Other Network Equipment</label>
                                <input name="network_equipment_other" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Area of Deployment</label>
                            <input name="area_of_deployment" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Other Details</label>
                            <textarea name="other_details" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Server Section -->
        <div class="card mb-4" id="server_section" style="display: none;">
            <div class="card-header">Server Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Other Details</label>
                            <textarea name="other_details" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SmartTV Section -->
        <div class="card mb-4" id="smarttv_section" style="display: none;">
            <div class="card-header">SmartTV Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Other Details</label>
                            <textarea name="other_details" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Computer Section (for System Unit and Laptop) -->
        <div class="card mb-4" id="computer_section" style="display: none;">
            <div class="card-header">Technical Specifications</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Processor</label>
                            <input name="processor" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Storage Type</label>
                            <select name="storage_type" class="form-control">
                                <option value="">Select Type</option>
                                <option value="SSD">SSD</option>
                                <option value="HDD">HDD</option>
                                <option value="BOTH">BOTH</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Storage Capacity</label>
                            <input name="storage_capacity" class="form-control" placeholder="e.g., 256 SSD / 1TB HDD">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>RAM</label>
                            <input name="ram" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Graphics 1</label>
                            <input name="graphics1" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Graphics 2</label>
                            <input name="graphics2" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Software Section (for System Unit and Laptop) -->
        <div class="card mb-4" id="software_section" style="display: none;">
            <div class="card-header">Software Configuration</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Computer Name</label>
                            <input name="computer_name" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Workgroup</label>
                            <input name="workgroup" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Operating System</label>
                            <input name="os" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Office Application</label>
                            <select name="office_app" class="form-control">
                                <option value="">Select Application</option>
                                <option value="365">365</option>
                                <option value="Libre Office">Libre Office</option>
                                <option value="WPS">WPS</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Microsoft 365 Account (if applicable)</label>
                            <input name="ms_account" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>End Point Protection</label>
                            <select name="endpoint_protection" class="form-control" onchange="toggleEndpointOther()">
                                <option value="">Select Protection</option>
                                <option value="Seqrite">Seqrite</option>
                                <option value="Cynet">Cynet</option>
                                <option value="Windows Defender">Windows Defender</option>
                                <option value="others">Others</option>
                            </select>
                        </div>
                        <div id="endpoint_other_div" style="display: none;">
                            <div class="form-group">
                                <label>Specify Other End Point Protection</label>
                                <input name="endpoint_other" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Updated End-Point-Protection</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="endpoint_updated" id="ep_yes" value="1">
                                    <label class="form-check-label" for="ep_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="endpoint_updated" id="ep_no" value="0" checked>
                                    <label class="form-check-label" for="ep_no">No</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Any Desk ID</label>
                            <input name="anydesk_id" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="belarc_installed" id="belarc">
                            <label class="form-check-label" for="belarc">Belarc Installed & Saved</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="accounts_updated" id="accounts">
                            <label class="form-check-label" for="accounts">Auditmisd Accounts Checked & Updated</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="ultravnc_installed" id="ultravnc">
                            <label class="form-check-label" for="ultravnc">Ultra VNC Installed & Configured</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="snmp_installed" id="snmp">
                            <label class="form-check-label" for="snmp">SNMP Installed</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Network Configuration Section -->
        <div class="card mb-4" id="network_config_section" style="display: none;">
            <div class="card-header">Network Configuration</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Connection Type</label>
                            <select name="connection_type" class="form-control">
                                <option value="">Select Type</option>
                                <option value="LAN">LAN</option>
                                <option value="WLAN">WLAN</option>
                                <option value="NONE">NONE</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>DHCP Type</label>
                            <select name="dhcp_type" id="dhcp_type" class="form-control" onchange="toggleStaticApp()">
                                <option value="">Select Type</option>
                                <option value="DHCP">DHCP</option>
                                <option value="static">Static</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div id="static_app_div" style="display: none;">
                            <div class="form-group">
                                <label>Static IP Application</label>
                                <input name="static_app" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>IP Address 1</label>
                            <input name="ip_address1" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>IP Address 2</label>
                            <input name="ip_address2" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>LAN MAC Address</label>
                            <input name="lan_mac" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>WLAN MAC Address 1</label>
                            <input name="wlan_mac1" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>WLAN MAC Address 2</label>
                            <input name="wlan_mac2" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Gateway</label>
                            <input name="gateway" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Administrative Information Section -->
        <div class="card mb-4">
            <div class="card-header">Administrative Information</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Office</label>
                            <select name="office_id" class="form-control">
                                <option value="">Select Office</option>
                                <?php foreach($offices as $office): ?>
                                <option value="<?= $office['id'] ?>"><?= htmlspecialchars($office['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>User *</label>
                            <input name="user" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>MIAA Property</label>
                            <select name="miaa_property" class="form-control">
                                <option value="">Select Type</option>
                                <option value="MIAA Property">MIAA Property</option>
                                <option value="Donated">Donated</option>
                                <option value="Personal">Personal</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Memorandum Receipt (MR)</label>
                            <input name="memorandum_receipt" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>P.O. Number</label>
                            <input name="po_number" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Serial Number</label>
                            <input name="serial_number" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <button class="btn btn-success">Add Asset</button>
        <a href="add_item.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
</body>
</html>