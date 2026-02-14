<?php
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /inventory_system/login.php");
    exit();
}

// User's name for display, default to 'Admin'
$admin_name = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';

// Fetch brand new assets for dropdown
$assets_query = $db->query("SELECT 
                                i.id, 
                                i.property_number, 
                                i.product_name, 
                                IFNULL(i.brand, '') AS brand, 
                                IFNULL(i.model, '') AS model, 
                                IFNULL(i.memorandum_receipt, '') AS memorandum_receipt, 
                                IFNULL(i.serial_number, '') AS serial_number,
                                c.name AS category_name, 
                                c.category_type 
                            FROM items i 
                            JOIN categories c ON i.category_id = c.id 
                            WHERE i.status = 'brand_new' AND c.category_type = 'asset' AND i.is_deleted = 0");
if ($assets_query) {
    $assets = $assets_query->fetch_all(MYSQLI_ASSOC);
} else {
    $assets = [];
}

foreach ($assets as &$a) { $a['id'] = (string)$a['id']; }
unset($a);

// Handle search and filter
$search = '';
$search_field = 'property_number'; // default search field
$sort_field = 'property_number';
$sort_order = 'ASC';

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $db->real_escape_string($_GET['search']);
}

if (isset($_GET['search_field']) && !empty($_GET['search_field'])) {
    $search_field = $db->real_escape_string($_GET['search_field']);
}

if (isset($_GET['sort']) && !empty($_GET['sort'])) {
    $sort_field = $db->real_escape_string($_GET['sort']);
}

if (isset($_GET['order']) && !empty($_GET['order'])) {
    $sort_order = $db->real_escape_string($_GET['order']);
}

// Apply search filter
$filtered_assets = $assets;
if (!empty($search)) {
    $filtered_assets = array_filter($assets, function($asset) use ($search, $search_field) {
        // Ensure the search field exists in the array keys
        if (!isset($asset[$search_field])) return false;
        return stripos($asset[$search_field], $search) !== false;
    });
}

// Apply sorting
usort($filtered_assets, function($a, $b) use ($sort_field, $sort_order) {
    $valueA = $a[$sort_field];
    $valueB = $b[$sort_field];
    
    if ($sort_order === 'ASC') {
        return strnatcasecmp($valueA, $valueB);
    } else {
        return strnatcasecmp($valueB, $valueA);
    }
});

// Calculate pagination
$total_items = count($filtered_assets);
$total_pages = $total_items > 0 ? ceil($total_items / $items_per_page) : 1;
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $items_per_page;

// Get items for current page
$paginated_assets = array_slice($filtered_assets, $offset, $items_per_page);

// Restore preselected items from GET - include ALL selected items, not just current page
$preselected = [];
if (isset($_GET['selected']) && !empty($_GET['selected'])) {
    $preselected = explode(',', $_GET['selected']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get selected items from hidden field instead of checkboxes
    $selected_items_input = $_POST['selected_items'] ?? '';
    
    if (empty($selected_items_input)) {
        $error = "Please select at least one item to condemn.";
    } else {
        $item_ids = explode(',', $selected_items_input);
        $item_ids = array_filter($item_ids); // Remove empty values
        
        if (empty($item_ids)) {
            $error = "Please select at least one item to condemn.";
        } else {
            $reason = $_POST['reason'];
            $condemned_by = $_SESSION['user_id'];
            $success_count = 0;
            $error_count = 0;
            $condemned_item_ids = [];

            try {
                $db->begin_transaction();

                foreach ($item_ids as $item_id) {
                    $item_id = intval($item_id);
                    
                    // Verify the item exists and is brand_new
                    $verify_stmt = $db->prepare("SELECT id, memorandum_receipt, property_number FROM items WHERE id = ? AND status = 'brand_new'");
                    $verify_stmt->bind_param("i", $item_id);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->get_result();
                    
                    if ($verify_result->num_rows === 0) {
                        $error_count++;
                        continue; // Skip if item doesn't exist or not brand_new
                    }
                    
                    $item_data = $verify_result->fetch_assoc();
                    $memorandum_receipt = $item_data['memorandum_receipt'] ?? '';

                    // Insert condemnation record
                    $stmt = $db->prepare("INSERT INTO condemnations (item_id, memorandum_receipt, reason, condemned_by) 
                                            VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("issi", $item_id, $memorandum_receipt, $reason, $condemned_by);
                    
                    if ($stmt->execute()) {
                        // Update item status
                        $update_stmt = $db->prepare("UPDATE items SET status = 'condemned' WHERE id = ?");
                        $update_stmt->bind_param("i", $item_id);
                        $update_stmt->execute();

                        // Add to tracker
                        $notes = "Condemned: " . $reason;
                        $tracker_stmt = $db->prepare("INSERT INTO inventory_item_tracker (item_id, from_status, to_status, moved_by, notes) 
                                                        VALUES (?, 'brand_new', 'condemned', ?, ?)");
                        $tracker_stmt->bind_param("iis", $item_id, $condemned_by, $notes);
                        $tracker_stmt->execute();

                        // Audit log
                        $log_detail = "Condemned item " . $item_data['property_number'] . ": " . $reason;

                        $audit_stmt = $db->prepare("INSERT INTO audit_logs (action, performed_by, item_id, log_details) VALUES (?, ?, ?, ?)");
                        $action = "condemn";
                        $audit_stmt->bind_param("siis", $action, $condemned_by, $item_id, $log_detail);
                        $audit_stmt->execute();

                        $success_count++;
                        $condemned_item_ids[] = $item_id;
                    } else {
                        $error_count++;
                    }
                }

                $db->commit();
                
                if ($success_count > 0) {
                    $success = "Successfully condemned $success_count item(s)!";
                    if ($error_count > 0) {
                        $success .= " $error_count item(s) failed.";
                    }
                    $_SESSION['condemned_item_ids'] = $condemned_item_ids; // Store for report generation
                } else {
                    $error = "Failed to condemn any items.";
                }
                
            } catch (Exception $e) {
                $db->rollback();
                $error = "Error condemning items: " . $db->error;
            }
        }
    }
}

// Function to generate pagination URL
function generatePaginationUrl($page, $current_params) {
    $params = [];
    if (!empty($current_params['search'])) $params['search'] = $current_params['search'];
    if (!empty($current_params['search_field'])) $params['search_field'] = $current_params['search_field'];
    if (!empty($current_params['sort'])) $params['sort'] = $current_params['sort'];
    if (!empty($current_params['order'])) $params['order'] = $current_params['order'];
    if (!empty($current_params['selected'])) $params['selected'] = $current_params['selected'];
    $params['page'] = $page;
    
    return 'condemn.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Condemn Items | Admin Inventory</title>
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
        
        /* Condemn Specific Styles */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
        }
        
        .search-container { 
            position: relative; 
            margin-bottom: 20px; 
            display: flex;
            align-items: center;
        }
        .search-icon { 
            position: absolute; 
            left: 215px; /* Adjusted for select box */
            top: 50%; 
            transform: translateY(-50%); 
            color: #6c757d; 
            z-index: 3; 
        }
        .search-form { display: flex; width: 100%; }
        .search-form .form-control { padding-left: 40px; }
        .item-checkbox { margin-right: 10px; }
        .item-row { cursor: pointer; transition: background-color 0.2s; }
        .item-row:hover { background-color: #f8f9fa; }
        .item-row.selected { background-color: #e3f2fd !important; }
        .sortable { cursor: pointer; }
        .sortable:hover { background-color: #f1f1f1; }
        .sort-arrow { margin-left: 5px; }
        .pagination { margin: 0; }
        .page-info { margin: 0 15px; line-height: 38px; }
        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
        .table-responsive { overflow-x: auto; }
        
        /* Fix for search input padding after select */
        .search-form .form-control[name="search"] {
            padding-left: 40px;
        }
    </style>
    <script>
    // Global variable to track selected items
    var selectedItems = new Set(<?php echo isset($_GET['selected']) ? json_encode($preselected) : '[]'; ?>);
    
    function toggleItemSelection(checkbox) {
        var row = checkbox.closest('tr');
        if (checkbox.checked) {
            row.classList.add('selected');
            selectedItems.add(checkbox.value);
        } else {
            row.classList.remove('selected');
            selectedItems.delete(checkbox.value);
        }
        updateSelectedCount();
        updateSelectedInput();
    }
    
    function selectAllItems(selectAllCheckbox) {
        var checkboxes = document.querySelectorAll('.item-checkbox');
        var rows = document.querySelectorAll('.item-row');
        
        checkboxes.forEach(function(checkbox, index) {
            var wasChecked = checkbox.checked;
            checkbox.checked = selectAllCheckbox.checked;
            
            if (selectAllCheckbox.checked) {
                // Only add if it wasn't already added (for multi-page selection)
                if (!wasChecked) { 
                    rows[index].classList.add('selected');
                    selectedItems.add(checkbox.value);
                }
            } else {
                // Only remove if it was checked before (to prevent unselecting items on other pages)
                if (wasChecked) {
                    rows[index].classList.remove('selected');
                    selectedItems.delete(checkbox.value);
                }
            }
        });
        updateSelectedCount();
        updateSelectedInput();
    }
    
    function updateSelectedCount() {
        var selectedCount = selectedItems.size;
        document.getElementById('selectedCount').textContent = selectedCount;
        document.getElementById('condemnBtn').disabled = selectedCount === 0;
    }
    
    function updateSelectedInput() {
        var selectedArray = Array.from(selectedItems);
        document.getElementById('selectedInput').value = selectedArray.join(',');
        document.getElementById('selectedItemsHidden').value = selectedArray.join(',');
    }

    // Before submitting search, capture selected IDs
    function prepareSearch() {
        updateSelectedInput();
        return true;
    }

    function clearSearch() {
        // Clear search but preserve selected items
        var selectedArray = Array.from(selectedItems);
        window.location.href = 'condemn.php?selected=' + selectedArray.join(',');
    }

    function sortTable(field) {
        var currentSort = '<?php echo $sort_field; ?>';
        var currentOrder = '<?php echo $sort_order; ?>';
        var newOrder = 'ASC';
        
        if (field === currentSort) {
            newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
        }
        
        var selectedArray = Array.from(selectedItems);
        var url = 'condemn.php?sort=' + field + '&order=' + newOrder + '&page=1';
        
        if (selectedArray.length > 0) {
            url += '&selected=' + selectedArray.join(',');
        }
        
        if ('<?php echo $search; ?>' !== '') {
            url += '&search=<?php echo urlencode($search); ?>&search_field=<?php echo $search_field; ?>';
        }
        
        window.location.href = url;
    }

    function changePage(page) {
        var selectedArray = Array.from(selectedItems);
        var url = 'condemn.php?page=' + page;
        
        if (selectedArray.length > 0) {
            url += '&selected=' + selectedArray.join(',');
        }
        
        if ('<?php echo $search; ?>' !== '') {
            url += '&search=<?php echo urlencode($search); ?>&search_field=<?php echo $search_field; ?>';
        }
        
        if ('<?php echo $sort_field; ?>' !== 'property_number') {
            url += '&sort=<?php echo $sort_field; ?>&order=<?php echo $sort_order; ?>';
        }
        
        window.location.href = url;
    }

    function getSortArrow(field) {
        var currentSort = '<?php echo $sort_field; ?>';
        var currentOrder = '<?php echo $sort_order; ?>';
        
        if (field === currentSort) {
            return currentOrder === 'ASC' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
        }
        return ' <i class="fas fa-sort"></i>';
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize checkboxes based on selectedItems Set
        var checkboxes = document.querySelectorAll('.item-checkbox');
        checkboxes.forEach(function(checkbox) {
            if (selectedItems.has(checkbox.value)) {
                checkbox.checked = true;
                checkbox.closest('tr').classList.add('selected');
            }
        });
        
        // Update select all checkbox state on load (only checks visible items)
        var selectAllCheckbox = document.getElementById('selectAll');
        if (checkboxes.length > 0) {
            var allChecked = true;
            checkboxes.forEach(function(checkbox) {
                if (!checkbox.checked) {
                    allChecked = false;
                }
            });
            selectAllCheckbox.checked = allChecked;
        }
        
        updateSelectedCount();
        updateSelectedInput(); // Initialize the hidden field
        
        // Add sort arrows to table headers
        var headers = document.querySelectorAll('.sortable');
        headers.forEach(function(header) {
            var field = header.getAttribute('data-field');
            header.innerHTML += getSortArrow(field);
        });

        // Add row click listener (re-added the old function for continuity)
        document.querySelectorAll('.item-row').forEach(row => {
            row.onclick = function() {
                // Find the checkbox inside this row
                let checkbox = this.querySelector('.item-checkbox');
                if (checkbox) {
                    // Manually toggle the checked state and call the change function
                    checkbox.checked = !checkbox.checked;
                    toggleItemSelection(checkbox);
                }
            };
        });
        
        // Prevent row click from firing the checkbox onchange twice
        document.querySelectorAll('.item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    });

    // Prevent default behavior of pagination links and use our function
    document.addEventListener('click', function(e) {
        if (e.target.closest('.page-link') && e.target.closest('.pagination')) {
            e.preventDefault();
        }
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
    <a href="items.php"><i class="fa-solid fa-box"></i> Items</a>
	<a href="item_reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
    <a href="requests.php"><i class="fa-solid fa-file-circle-check"></i> Requests</a>
    <a href="audit_logs.php"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a>
    <a href="tracker.php"><i class="fa-solid fa-location-dot"></i> Inventory Tracker</a>
    <a href="condemn.php" class="active"><i class="fa-solid fa-ban"></i> Condemn</a>
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
            <h1 class="h3 mb-0 text-gray-800">🗑️ Condemn Items (Assets)</h1>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?><br>
                <?php if (isset($_SESSION['condemned_item_ids']) && !empty($_SESSION['condemned_item_ids'])): ?>
                    <a href="condemnation_report.php?item_ids=<?php echo implode(',', $_SESSION['condemned_item_ids']); ?>" 
                       class="btn btn-primary mt-2">
                        <i class="fas fa-download"></i> Download Condemnation Report
                    </a>
                    <?php unset($_SESSION['condemned_item_ids']); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card p-3 mb-4">
            <div class="card-body">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <form method="GET" class="search-form" onsubmit="return prepareSearch();">
                        <select class="form-control mr-2" name="search_field" style="max-width: 200px;">
                            <option value="property_number" <?php echo $search_field == 'property_number' ? 'selected' : ''; ?>>Property Number</option>
                            <option value="product_name" <?php echo $search_field == 'product_name' ? 'selected' : ''; ?>>Product Name</option>
                            <option value="brand" <?php echo $search_field == 'brand' ? 'selected' : ''; ?>>Brand</option>
                            <option value="model" <?php echo $search_field == 'model' ? 'selected' : ''; ?>>Model</option>
                            <option value="memorandum_receipt" <?php echo $search_field == 'memorandum_receipt' ? 'selected' : ''; ?>>Memorandum Receipt</option>
                            <option value="serial_number" <?php echo $search_field == 'serial_number' ? 'selected' : ''; ?>>Serial Number</option>
                            <option value="category_name" <?php echo $search_field == 'category_name' ? 'selected' : ''; ?>>Category</option>
                        </select>
                        <input type="text" class="form-control" name="search" placeholder="Search..." 
                                value="<?php echo htmlspecialchars($search); ?>">
                        <input type="hidden" name="selected" id="selectedInput" value="<?php echo isset($_GET['selected']) ? htmlspecialchars($_GET['selected']) : ''; ?>">
                        <input type="hidden" name="sort" value="<?php echo $sort_field; ?>">
                        <input type="hidden" name="order" value="<?php echo $sort_order; ?>">
                        <input type="hidden" name="page" value="1">
                        <?php if (!empty($search)): ?>
                            <button type="button" class="btn btn-outline-secondary ml-2" onclick="clearSearch()">Clear</button>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary ml-2"><i class="fas fa-filter mr-1"></i> Filter</button>
                    </form>
                </div>

                <form method="POST" id="condemnForm">
                    <input type="hidden" name="selected_items" id="selectedItemsHidden" value="">
                    
                    <div class="mb-3">
                        <span class="badge badge-pill badge-primary p-2">
                            <i class="fas fa-check-circle mr-1"></i> Selected Items: <strong id="selectedCount">0</strong>
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead class="thead-dark">
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAll" onchange="selectAllItems(this)">
                                    </th>
                                    <th class="sortable" data-field="property_number" onclick="sortTable('property_number')">Property Number</th>
                                    <th class="sortable" data-field="product_name" onclick="sortTable('product_name')">Product Name</th>
                                    <th class="sortable" data-field="category_name" onclick="sortTable('category_name')">Category</th>
                                    <th class="sortable" data-field="brand" onclick="sortTable('brand')">Brand</th>
                                    <th class="sortable" data-field="model" onclick="sortTable('model')">Model</th>
                                    <th class="sortable" data-field="memorandum_receipt" onclick="sortTable('memorandum_receipt')">MR</th>
                                    <th class="sortable" data-field="serial_number" onclick="sortTable('serial_number')">Serial Number</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($paginated_assets)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No brand new assets found matching your criteria.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($paginated_assets as $asset): ?>
                                    <tr class="item-row <?php echo in_array($asset['id'], $preselected) ? 'selected' : ''; ?>">
                                        <td>
                                            <input type="checkbox" class="item-checkbox" id="item_<?php echo $asset['id']; ?>" 
                                                    value="<?php echo $asset['id']; ?>" 
                                                    onchange="toggleItemSelection(this)"
                                                    <?php echo in_array($asset['id'], $preselected) ? 'checked' : ''; ?>>
                                        </td>
                                        <td><?php echo htmlspecialchars($asset['property_number']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['brand']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['model']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['memorandum_receipt']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="page-info text-muted">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo $total_items; ?> items
                        </div>
                        <nav>
                            <ul class="pagination">
                                <li class="page-item <?php echo $current_page == 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="#" onclick="changePage(1); return false;"><i class="fas fa-angle-double-left"></i></a>
                                </li>
                                
                                <li class="page-item <?php echo $current_page == 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="#" onclick="changePage(<?php echo $current_page - 1; ?>); return false;"><i class="fas fa-angle-left"></i></a>
                                </li>
                                
                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="#" onclick="changePage(<?php echo $i; ?>); return false;"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor;
                                
                                if ($end_page < $total_pages) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                ?>
                                
                                <li class="page-item <?php echo $current_page == $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="#" onclick="changePage(<?php echo $current_page + 1; ?>); return false;"><i class="fas fa-angle-right"></i></a>
                                </li>
                                
                                <li class="page-item <?php echo $current_page == $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="#" onclick="changePage(<?php echo $total_pages; ?>); return false;"><i class="fas fa-angle-double-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                    
                    <hr>

                    <div class="form-group mt-4">
                        <label for="reason" class="font-weight-bold">Reason for Disposal (Condemnation)</label>
                        <select class="form-control" id="reason" name="reason" required>
                            <option value="">Select a reason</option>
                            <option value="The above items are obsolete and no longer available at the market.">
                                The above items are obsolete and no longer available at the market.
                            </option>
                            <option value="The above items are found beyond economical repair.">
                                The above items are found beyond economical repair.
                            </option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-danger btn-lg mt-3" id="condemnBtn" disabled>
                        <i class="fas fa-trash-alt mr-2"></i> Condemn Selected Items
                    </button>
                    <a href="items.php" class="btn btn-secondary btn-lg mt-3">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
