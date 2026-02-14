<?php
require '../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$items = array();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /inventory_system/login.php");
    exit;
}

$admin_name = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';

/**
 * Normalize values that are explicit booleans in spreadsheets (yes, 1, true).
 * Returns 1 for true-like values, 0 otherwise.
 */
function normalizeBool($val) {
    $val = strtolower(trim((string)$val));
    if ($val === '') return 0;
    return ($val === 'yes' || $val === '1' || $val === 'true') ? 1 : 0;
}

/**
 * Presence-based normalization: return 1 for any non-empty value, 0 if blank.
 * This is used for fields where any non-blank cell should be considered "installed/updated".
 */
function normalizePresence($val) {
    $val = trim((string)$val);
    return ($val === '') ? 0 : 1;
}

$qrDir = __DIR__ . '/../qrcodes/';
$qrWebDir = '/inventory_system/qrcodes/';
if (!file_exists($qrDir)) {
    mkdir($qrDir, 0777, true);
}

$import_message = '';
$processed_count = 0;
$skipped_count = 0;
$duplicate_count = 0;
$error_rows = array();

if (isset($_POST['import_excel']) && isset($_FILES['excel_file'])) {
    $uploaded = $_FILES['excel_file'];
    if ($uploaded['error'] !== UPLOAD_ERR_OK) {
        $import_message = "Upload error: " . $uploaded['error'];
    } else {
        $file = $uploaded['tmp_name'];
        $ext = strtolower(pathinfo($uploaded['name'], PATHINFO_EXTENSION));

        try {
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                throw new Exception("PhpSpreadsheet not found. Run `composer require phpoffice/phpspreadsheet`.");
            }
            
            if ($ext === 'csv') {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Csv');
                $reader->setDelimiter(',');
                $spreadsheet = $reader->load($file);
            } else {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            }

            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            $headerRowIndex = 4;
            $firstDataRowIndex = $headerRowIndex + 1;

            // Base mapping - many columns are mapped here; brand/model will be optionally overridden below
            $excelToDb = [
                'AN' => 'property_number', 'F' => 'category_id', 'G' => 'brand', 'H' => 'model', 'I' => 'processor',
                'J' => 'storage_type', 'K' => 'storage_capacity', 'L' => 'ram', 'M' => 'graphics1', 'N' => 'graphics2',
                'O' => 'computer_name', 'P' => 'workgroup', 'Q' => 'os', 'R' => 'office_app', 'S' => 'ms_account',
                'T' => 'endpoint_protection', 'U' => 'endpoint_updated', 'V' => 'anydesk_id', 'W' => 'belarc_installed',
                'X' => 'accounts_updated', 'Y' => 'ultravnc_installed', 'Z' => 'snmp_installed', 'AA' => 'connection_type',
                'AB' => 'dhcp_type', 'AC' => 'static_app', 'AD' => 'ip_address1', 'AE' => 'ip_address2', 'AF' => 'lan_mac',
                'AG' => 'wlan_mac1', 'AH' => 'wlan_mac2', 'AI' => 'gateway', 'AK' => 'USERids', 'AJ' => 'office_id', 'AL' => 'miaa_property',
                'AM' => 'memorandum_receipt', 'AO' => 'po_number', 'AP' => 'serial_number', 'AS' => 'display_size',
                'AY' => 'printer_type', 'AV' => 'capacity_va', 'AZ' => 'network_equipment_type', 'BD' => 'network_equipment_other',
                'BB' => 'area_of_deployment', 'A' => 'created_at', 'E' => 'is_active'
            ];

            // fields that we will bind as integers
            $intFields = [ 'endpoint_updated', 'belarc_installed', 'accounts_updated', 'ultravnc_installed', 'snmp_installed', 'quantity', 'is_active', 'is_deleted', 'created_by', 'office_id', 'category_id' ];

            // Fields treated as "presence" flags: any non-blank -> 1
            $presenceFields = ['belarc_installed','accounts_updated','ultravnc_installed','snmp_installed'];

            // Fields treated as explicit booleans (yes/1/true)
            $booleanFields = ['endpoint_updated','is_active'];

            $rowCount = count($rows);
            for ($rIndex = $firstDataRowIndex; $rIndex <= $rowCount; $rIndex++) {
                $row = isset($rows[$rIndex]) ? $rows[$rIndex] : null;
                if (!$row || !is_array($row)) continue;

                $allEmpty = true;
                foreach ($excelToDb as $colLetter => $dbcol) { if (isset($row[$colLetter]) && trim((string)$row[$colLetter]) !== '') { $allEmpty = false; break; } }
                if ($allEmpty) continue;

                $rowData = [];
                $categoryInput = isset($row['F']) ? trim($row['F']) : null;
                if ($categoryInput !== null && $categoryInput !== '') {
                    if (is_numeric($categoryInput)) { 
                        $catStmt = $db->prepare("SELECT id FROM categories WHERE id = ?");
                        $catStmt->bind_param("i", $categoryInput); 
                    } else { 
                        $catStmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
                        $catStmt->bind_param("s", $categoryInput); 
                    }
                    $catStmt->execute(); $catStmt->bind_result($catId);
                    $rowData['category_id'] = $catStmt->fetch() ? $catId : null;
                    $catStmt->close();
                } else { $rowData['category_id'] = null; }

                if ($rowData['category_id'] === null) { 
                    $error_rows[] = "Row {$rIndex}: Invalid or missing category '{$categoryInput}'.";
                    $skipped_count++; 
                    continue; 
                }

                // Read all mapped columns first
                foreach ($excelToDb as $colLetter => $dbcol) {
                    if ($dbcol === 'category_id') continue;

                    // raw trimmed value (empty string if missing)
                    $rawVal = isset($row[$colLetter]) ? trim((string)$row[$colLetter]) : '';

                    // default processed value
                    $val = null;
                    
                    // Presence fields: any non-empty cell -> 1
                    if (in_array($dbcol, $presenceFields)) {
                        $val = normalizePresence($rawVal);
                    }
                    // Boolean-ish fields: interpret yes/1/true carefully
                    elseif (in_array($dbcol, $booleanFields)) {
                        $val = normalizeBool($rawVal);
                    }
                    // created_at: try to convert to datetime if present
                    elseif ($dbcol == 'created_at') {
                        if ($rawVal !== '') {
                            $ts = strtotime($rawVal);
                            $val = ($ts !== false) ? date('Y-m-d H:i:s', $ts) : null;
                        } else {
                            $val = null;
                        }
                    }
                    // office_id requires lookup/creation
                    elseif ($dbcol == 'office_id') {
                        $officeInput = isset($row['AJ']) ? trim($row['AJ']) : null;
                        if ($officeInput !== null && $officeInput !== '') {
                            if (is_numeric($officeInput)) {
                                $officeStmt = $db->prepare("SELECT id FROM offices WHERE id = ?");
                                $officeStmt->bind_param("i", $officeInput);
                                $officeStmt->execute();
                                $officeStmt->bind_result($officeId);
                                if ($officeStmt->fetch()) {
                                    $val = $officeId;
                                } else {
                                    $val = null;
                                    $error_rows[] = "Row {$rIndex}: Invalid office ID '{$officeInput}'";
                                    $skipped_count++;
                                    $officeStmt->close();
                                    continue 2; // skip to next row
                                }
                                $officeStmt->close();
                            } else { 
                                $officeStmt = $db->prepare("SELECT id FROM offices WHERE name = ?");
                                $officeStmt->bind_param("s", $officeInput);
                                $officeStmt->execute();
                                $officeStmt->bind_result($officeId);

                                if ($officeStmt->fetch()) {
                                    $val = $officeId;
                                    $officeStmt->close();
                                } else {
                                    $officeStmt->close(); 
                                    $insertOfficeStmt = $db->prepare("INSERT INTO offices (name) VALUES (?)");
                                    if ($insertOfficeStmt) {
                                        $insertOfficeStmt->bind_param("s", $officeInput);
                                        if ($insertOfficeStmt->execute()) {
                                            $val = $db->insert_id;
                                        } else {
                                            $val = null;
                                            $error_rows[] = "Row {$rIndex}: FAILED to create new office '{$officeInput}'.";
                                        }
                                        $insertOfficeStmt->close();
                                    } else {
                                        $val = null;
                                        $error_rows[] = "Row {$rIndex}: DB error preparing to create new office.";
                                    }
                                }
                            }
                        } else {
                            $val = null;
                        }
                    }
                    // default: treat empty as null, otherwise keep string
                    else {
                        $val = ($rawVal === '') ? null : $rawVal;
                    }

                    $rowData[$dbcol] = $val;
                }

                // --- Conditional brand/model mapping based on category name ---
                // Fetch category name to decide which columns to read for brand/model
                $catName = null;
                $cns = $db->prepare("SELECT name FROM categories WHERE id = ?");
                if ($cns) {
                    $cns->bind_param("i", $rowData['category_id']);
                    $cns->execute();
                    $cns->bind_result($catName);
                    $cns->fetch();
                    $cns->close();
                }

                // mapping per your specification (case-insensitive)
                $map = [
                    'CPU' => ['brand' => 'G',  'model' => 'H'],
                    'MONITOR' => ['brand' => 'AQ', 'model' => 'AR'],
                    'PRINTER' => ['brand' => 'AW', 'model' => 'AX'],
                    'LAPTOP' => ['brand' => 'G',  'model' => 'H'],
                    'UPS' => ['brand' => 'AT', 'model' => 'AU'],
                    'SCANNER' => ['brand' => 'BE', 'model' => 'BF'],
                    'SPEAKER' => ['brand' => 'BG', 'model' => 'BH'],
                    'REMOVABLE DEVICES' => ['brand' => 'BU', 'model' => 'BV'],
                    'SMART TV' => ['brand' => 'BR', 'model' => 'BS'],
                    'PORTABLE DEVICES' => ['brand' => 'BK', 'model' => 'BL'],
                    'NETWORK EQUIPMENT' => ['brand' => 'BC', 'model' => 'BA']
                ];

                $lookupKey = strtoupper(trim((string)($catName ?? $categoryInput)));
                $brandCol = 'G'; $modelCol = 'H'; // defaults
                if (isset($map[$lookupKey])) {
                    $brandCol = $map[$lookupKey]['brand'];
                    $modelCol = $map[$lookupKey]['model'];
                }

                // read brand/model from the mapped columns (if present and non-empty) and overwrite
                $mappedBrand = (isset($row[$brandCol]) && trim((string)$row[$brandCol]) !== '') ? trim((string)$row[$brandCol]) : null;
                $mappedModel = (isset($row[$modelCol]) && trim((string)$row[$modelCol]) !== '') ? trim((string)$row[$modelCol]) : null;

                if ($mappedBrand !== null) {
                    $rowData['brand'] = $mappedBrand;
                }
                if ($mappedModel !== null) {
                    $rowData['model'] = $mappedModel;
                }

                $rowData += ['quantity' => 1, 'unit' => 'pieces', 'status' => 'brand_new', 'updated_at' => date('Y-m-d H:i:s'), 'is_deleted' => 0, 'product_name' => null, 'other_details' => null, 'created_by' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null];         

                // Handle property number (case-insensitive, keep placeholders)
                if (isset($rowData['property_number'])) {
                    $property_number_clean = strtoupper(trim($rowData['property_number']));

                    if (in_array($property_number_clean, ['N/A', 'NA', 'NO STICKER', 'LOST & FOUND', 'LOST AND FOUND', 'TO FOLLOW'])) {
                        // Save placeholders as-is (uppercase for consistency)
                        $rowData['property_number'] = $property_number_clean;
                    } else {
                        // Normal property numbers
                        $rowData['property_number'] = trim($rowData['property_number']);
                    }
                }

                $qr_code_path = null;
                if (!empty($rowData['property_number'])) {
                    $qr_filename = 'qr_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $rowData['property_number']) . '.png';
                    $qr_full_path = $qrDir . $qr_filename;
                    $qr_web_path = $qrWebDir . $qr_filename;
                    if (!file_exists($qr_full_path)) { QRcode::png($rowData['property_number'], $qr_full_path, QR_ECLEVEL_L, 4); }
                    $qr_code_path = $qr_web_path;
                }
                $rowData['qr_code_path'] = $qr_code_path;

                $columns = array_keys($rowData); $colSql = array_map(fn($c) => "`$c`", $columns); $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $sql = "INSERT INTO items (" . implode(', ', $colSql) . ") VALUES ($placeholders)";

                $stmt = $db->prepare($sql);
                if (!$stmt) { $error_rows[] = "Prepare failed row {$rIndex}: " . $db->error; continue; }

                $types = ''; $values = [];
                foreach ($columns as $col) { 
                    if (in_array($col, $intFields)) { 
                        $types .= 'i'; 
                        $values[] = isset($rowData[$col]) ? (int)$rowData[$col] : 0; 
                    } else { 
                        $types .= 's'; 
                        $values[] = isset($rowData[$col]) ? $rowData[$col] : null; 
                    } 
                }
                
                $bind_args = [$types];
                for ($i = 0; $i < count($values); $i++) {
                    $bind_args[] = &$values[$i];
                }
                
                call_user_func_array([$stmt, 'bind_param'], $bind_args);

                if ($stmt->execute()) { $processed_count++; } 
                else {
                    if (strpos($stmt->error, 'Duplicate entry') !== false && strpos($stmt->error, 'property_number') !== false) { $duplicate_count++; $error_rows[] = "Duplicate property_number at row {$rIndex}: " . $rowData['property_number']; } 
                    else { $error_rows[] = "Insert failed row {$rIndex}: " . $stmt->error; }
                }
                $stmt->close();
            }
            $import_message = "Import completed. Processed: {$processed_count}. Skipped: {$skipped_count}. Duplicates: {$duplicate_count}.";
            if (!empty($error_rows)) { $import_message .= " Errors (first 5): " . implode(' | ', array_slice($error_rows, 0, 5)); }
        } catch (Exception $e) { $import_message = "Error during import: " . $e->getMessage(); }
    }
}

// --- Data Fetching for Filters ---
$cat_result = $db->query("SELECT id, name, category_type FROM categories ORDER BY category_type, name ASC");
$categories = $cat_result ? $cat_result->fetch_all(MYSQLI_ASSOC) : [];
$categories_by_type = ['supply' => [], 'asset' => []];
foreach ($categories as $c) { $categories_by_type[strtolower($c['category_type'] ?? 'supply')][] = $c; }

$offices_result = $db->query("SELECT id, name FROM offices ORDER BY name ASC");
$offices = $offices_result ? $offices_result->fetch_all(MYSQLI_ASSOC) : [];

// Fetch unique creation dates (formatted as YYYY-MM-DD)
$dates_result = $db->query("SELECT DISTINCT DATE(created_at) as creation_date FROM items WHERE created_at IS NOT NULL ORDER BY creation_date DESC");
$creation_dates = $dates_result ? array_column($dates_result->fetch_all(MYSQLI_ASSOC), 'creation_date') : [];

// Fields for which we will build "dropdown multi-select" lists (distinct values from DB)
// Make sure these are the exact column names in your items table.
$distinct_fields = [
    'os','brand','model','processor','office_app','workgroup','storage_type','storage_capacity','ram','USERids','serial_number','po_number'
];

$distinct_values = [];
foreach ($distinct_fields as $df) {
    // Use the column name as-is but validate it's a safe identifier (letters, digits, underscore)
    $safeCol = $df;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $safeCol)) {
        // skip invalid column names
        $distinct_values[$df] = [];
        continue;
    }

    // Build query for distinct non-empty trimmed values
    $q = "SELECT DISTINCT TRIM(COALESCE(`$safeCol`, '')) AS val FROM items WHERE `$safeCol` IS NOT NULL AND TRIM(COALESCE(`$safeCol`,'')) <> '' ORDER BY val ASC";
    $res = $db->query($q);
    $vals = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if ($row['val'] !== '') $vals[] = $row['val'];
        }
        $res->free();
    }
    $distinct_values[$df] = $vals;
}

// --- Advanced search: read GET params ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$filter_category_type = isset($_GET['category_type']) ? trim($_GET['category_type']) : '';
$filter_office = isset($_GET['office']) ? (int)$_GET['office'] : 0;
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';

// Read multi-select arrays (each will be an array of selected values)
$multiFilters = [];
foreach ($distinct_fields as $df) {
    if (isset($_GET[$df]) && is_array($_GET[$df])) {
        // Normalize string values
        $arr = array_filter(array_map(function($v){ return trim((string)$v); }, $_GET[$df]), function($v){ return $v !== ''; });
        $multiFilters[$df] = array_values($arr);
    } else {
        $multiFilters[$df] = [];
    }
}

// Also support single-value advanced inputs (legacy support)
$adv_single = [
    'status' => (isset($_GET['status']) ? trim($_GET['status']) : ''),
    'is_active' => (isset($_GET['is_active']) && $_GET['is_active'] !== '' ? (int)$_GET['is_active'] : null),
    'office_id' => (isset($_GET['office_id']) && $_GET['office_id'] !== '' ? (int)$_GET['office_id'] : null),
    'USERids' => (isset($_GET['USERids']) ? trim($_GET['USERids']) : '')
];

// Created from/to
$created_from = isset($_GET['created_from']) ? trim($_GET['created_from']) : '';
$created_to = isset($_GET['created_to']) ? trim($_GET['created_to']) : '';

// Build WHERE clause and bind params
$wheres = ["i.is_deleted = 0"]; $params = []; $types = "";

if ($search !== '') { 
    $wheres[] = "(i.property_number LIKE ? OR i.brand LIKE ? OR i.model LIKE ? OR i.product_name LIKE ? OR i.USERids LIKE ? OR i.serial_number LIKE ?)";
    $like = "%" . $search . "%";
    array_push($params, $like, $like, $like, $like, $like, $like);
    $types .= str_repeat("s", 6);
}
if ($filter_category > 0) { $wheres[] = "i.category_id = ?"; $params[] = $filter_category; $types .= "i"; }
if ($filter_category_type !== '' && in_array($filter_category_type, ['supply','asset'])) { $wheres[] = "c.category_type = ?"; $params[] = $filter_category_type; $types .= "s"; }
if ($filter_office > 0) { $wheres[] = "i.office_id = ?"; $params[] = $filter_office; $types .= "i"; }
if ($adv_single['USERids'] !== '') { $wheres[] = "i.USERids LIKE ?"; $params[] = "%" . $adv_single['USERids'] . "%"; $types .= "s"; }

// New date filter condition (single date)
if ($filter_date !== '') { $wheres[] = "DATE(i.created_at) = ?"; $params[] = $filter_date; $types .= "s"; }

// helper to add WHERE IN for arrays
$appendWhereIn = function($col, $values, &$wheres, &$params, &$types, $isInt = false) {
    if (empty($values)) return;
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $wheres[] = "i.`$col` IN ($placeholders)";
    foreach ($values as $v) {
        if ($isInt) {
            $params[] = (int)$v;
            $types .= "i";
        } else {
            $params[] = $v;
            $types .= "s";
        }
    }
};

// Apply multi-select filters
foreach ($multiFilters as $col => $vals) {
    if (!empty($vals)) {
        // USERids may be numeric or text; treat as text IN
        $appendWhereIn($col, $vals, $wheres, $params, $types, false);
    }
}

// Apply single-value advanced filters
if ($adv_single['status'] !== '') { $wheres[] = "i.status = ?"; $params[] = $adv_single['status']; $types .= "s"; }
if ($adv_single['is_active'] !== null) { $wheres[] = "i.is_active = ?"; $params[] = $adv_single['is_active']; $types .= "i"; }
if ($adv_single['office_id'] !== null) { $wheres[] = "i.office_id = ?"; $params[] = $adv_single['office_id']; $types .= "i"; }

// created_from / created_to handling
if ($created_from !== '' && $created_to !== '') {
    $fromTs = strtotime($created_from);
    $toTs = strtotime($created_to);
    if ($fromTs !== false && $toTs !== false) {
        $toTsEnd = strtotime('+1 day', $toTs) - 1;
        $wheres[] = "i.created_at BETWEEN ? AND ?";
        $params[] = date('Y-m-d H:i:s', $fromTs);
        $params[] = date('Y-m-d H:i:s', $toTsEnd);
        $types .= "ss";
    }
} elseif ($created_from !== '') {
    $fromTs = strtotime($created_from);
    if ($fromTs !== false) {
        $wheres[] = "i.created_at >= ?";
        $params[] = date('Y-m-d H:i:s', $fromTs);
        $types .= "s";
    }
} elseif ($created_to !== '') {
    $toTs = strtotime($created_to);
    if ($toTs !== false) {
        $toTsEnd = strtotime('+1 day', $toTs) - 1;
        $wheres[] = "i.created_at <= ?";
        $params[] = date('Y-m-d H:i:s', $toTsEnd);
        $types .= "s";
    }
}

$q = "SELECT i.*, c.name AS category_name, c.category_type, o.name as office_name FROM items i LEFT JOIN categories c ON i.category_id = c.id LEFT JOIN offices o ON i.office_id = o.id WHERE " . implode(' AND ', $wheres) . " ORDER BY i.id DESC";

$stmt = $db->prepare($q);
if ($stmt) { 
    if (!empty($params)) { 
        $bind_params = array();
        $bind_params[] = &$types;
        for ($i=0; $i<count($params); $i++) {
            $bind_params[] = &$params[$i];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind_params);
    } 
    $stmt->execute(); 
    $result = $stmt->get_result(); 
    $items = $result ? $result->fetch_all(MYSQLI_ASSOC) : []; 
    $stmt->close(); 
}
else { $items = array(); $import_message = (!empty($import_message) ? $import_message . ' ' : '') . "Database fetch failed: " . $db->error; }

// Count how many items are currently displayed (after filters)
$display_count = is_array($items) ? count($items) : 0;

// Also get total (unfiltered) count of non-deleted items for context
$total_count = 0;
$resTotal = $db->query("SELECT COUNT(*) AS cnt FROM items WHERE is_deleted = 0");
if ($resTotal) {
    $row = $resTotal->fetch_assoc();
    $total_count = isset($row['cnt']) ? (int)$row['cnt'] : 0;
    $resTotal->free();
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Manage Items | Inventory System</title>
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
        .navbar .nav-link {
            color: #555 !important;
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
        .card-body .table-responsive {
            max-height: 65vh;
            overflow-y: auto;
        }
        .qrcode-img { max-width: 50px; height: auto; }
        .table th, .table td { vertical-align: middle !important; font-size: 0.9rem; }
        .table .btn-sm { padding: 0.2rem 0.4rem; font-size: 0.8rem; }
        .modal-backdrop.show {
            z-index: 1040;
        }
        /* make multi-select better looking inside modal */
        select[multiple] { height: auto; min-height: 120px; }
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
    <a href="items.php" class="active"><i class="fa-solid fa-box"></i> Items</a>
	<a href="item_reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
    <a href="requests.php"><i class="fa-solid fa-file-circle-check"></i> Requests</a>
    <a href="audit_logs.php"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a>
    <a href="tracker.php"><i class="fa-solid fa-location-dot"></i> Inventory Tracker</a>
    <a href="condemn.php"><i class="fa-solid fa-ban"></i> Condemn</a>
    <a href="#usersSub" data-toggle="collapse" aria-expanded="false"><i class="fa-solid fa-users-cog"></i> Manage Users</a>
    <div class="collapse" id="usersSub">
        <a href="#" class="pl-4">Edit Profile</a>
        <a href="#" class="pl-4">Edit User</a>
        <a href="#" class="pl-4">Register New User</a>
    </div>
    <a href="#categoriesSub" data-toggle="collapse" aria-expanded="false"><i class="fa-solid fa-tags"></i> Manage Categories</a>
    <div class="collapse" id="categoriesSub">
        <a href="manage_supplies_category.php" class="pl-4">Supplies</a>
        <a href="manage_assets_category.php" class="pl-4">Assets</a>
        <a href="offices.php" class="pl-4">Offices</a>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <?php if (!empty($import_message)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($import_message) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">📦 Inventory Items</h1>
            <div>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addItemModal">
                    <i class="fa-solid fa-plus mr-1"></i> Add New Item
                </button>
                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#importExcelModal">
                    <i class="fa-solid fa-file-excel mr-1"></i> Import from Excel
                </button>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <form method="get" class="form-row align-items-center">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="category_type" class="form-control">
                            <option value="">All Types</option>
                            <option value="supply" <?= $filter_category_type === 'supply' ? 'selected' : '' ?>>Supplies</option>
                            <option value="asset" <?= $filter_category_type === 'asset' ? 'selected' : '' ?>>Assets</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                            <select name="category" id="categorySelect" class="form-control">
                                <option value="0">All Categories</option>
                                <?php if (!empty($categories_by_type['supply'])): ?>
                                    <optgroup label="Supplies">
                                        <?php foreach ($categories_by_type['supply'] as $c): ?>
                                            <option value="<?= $c['id'] ?>" data-type="supply" <?= $filter_category == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                    <?php if (!empty($categories_by_type['asset'])): ?>
                                    <optgroup label="Assets">
                                        <?php foreach ($categories_by_type['asset'] as $c): ?>
                                            <option value="<?= $c['id'] ?>" data-type="asset" <?= $filter_category == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                    </div>
                        <div class="col-md-2">
                               <select name="office" class="form-control">
                                      <option value="0">All Offices</option>
                                            <?php foreach ($offices as $office): ?>
                                               <option value="<?= $office['id'] ?>" <?= $filter_office == $office['id'] ? 'selected' : '' ?>><?= htmlspecialchars($office['name']) ?></option>
                                            <?php endforeach; ?>
                               </select>
                        </div>
                    <div class="col-md-2">
                        <select name="date" class="form-control">
                            <option value="">All Dates</option>
                            <?php foreach ($creation_dates as $date): ?>
                                <option value="<?= $date ?>" <?= $filter_date == $date ? 'selected' : '' ?>><?= date('M d, Y', strtotime($date)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <div class="btn-group" role="group" style="width:100%">
                            <button type="submit" class="btn btn-info btn-block">Apply</button>
                            <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#advancedSearchModal" title="Advanced Search"><i class="fa-solid fa-filter"></i></button>
                        </div>
                    </div>
                </form>
                <!-- Display counts: number of displayed items & total non-deleted items -->
                <div class="mt-2">
                    <small class="text-muted">Showing <strong><?= htmlspecialchars($display_count) ?></strong> item<?= $display_count !== 1 ? 's' : '' ?> (Total: <strong><?= htmlspecialchars($total_count) ?></strong>)</small>
                </div>
            </div>
            <div class="card-body">
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>#</th>
                    <th>Property Number</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Category</th>
                    <th>Name of User</th> 
                    <th>Status</th>
                    <th>QR</th>
                    <th>Acquisition Date</th> 
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="11" class="text-center">No items found.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $i): ?>
                        <tr>
                            <td><?= $i['id'] ?></td>
                            <td><?= htmlspecialchars($i['property_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($i['brand']) ?></td>
                            <td><?= htmlspecialchars($i['model']) ?></td>
                            <td><?= htmlspecialchars($i['category_name']) ?></td>
                            <td><?= htmlspecialchars($i['USERids'] ?? 'N/A') ?></td>
                            <td>
                                <?php
                                $status_class = ['brand_new'=>'success', 'for_replacement'=>'warning', 'for_condemn'=>'danger', 'condemned'=>'dark'];
                                $class = isset($status_class[$i['status']]) ? $status_class[$i['status']] : 'secondary';
                                echo "<span class='badge badge-{$class}'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $i['status']))) . "</span>";
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($i['qr_code_path'])): ?>
                                    <img src="<?= htmlspecialchars($i['qr_code_path']) ?>" class="qrcode-img" alt="QR Code">
                                <?php endif; ?>
                            </td>
                            <td><?= !empty($i['acquisition_date']) ? date('m/d/Y', strtotime($i['acquisition_date'])) : '-' ?></td>
                            <td>
                                <?php 
                                $category_type = strtolower($i['category_type'] ?? '');
                                $edit_link = ($category_type === 'supply') ? 'edit_item.php' : 'edit_assets.php'; 
                                ?>
                                <a href="<?= $edit_link ?>?id=<?= $i['id'] ?>" class="btn btn-sm btn-primary" title="Edit"><i class="fa-solid fa-pencil"></i></a>
                                <a href="delete_item.php?id=<?= $i['id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this item?')"><i class="fa-solid fa-trash"></i></a>
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
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Item Type</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body text-center">
                <p>What type of item would you like to add?</p>
                <div class="mt-3">
                    <a href="add_item_assets.php" class="btn btn-primary btn-lg mr-3">Add Asset</a>
                    <a href="add_item.php" class="btn btn-success btn-lg">Add Supply</a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Import Excel Modal -->
<div class="modal fade" id="importExcelModal" tabindex="-1">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Import Items from Excel</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <form method="post" enctype="multipart/form-data" id="importForm"> <div class="modal-body">
            <p>Select an Excel (.xlsx) or CSV (.csv) file. Data should start at row 5, with headers on row 4.</p>
            <div class="custom-file">
                <input type="file" name="excel_file" class="custom-file-input" id="excelFile" accept=".csv,.xlsx" required>
                <label class="custom-file-label" for="excelFile">Choose file...</label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" name="import_excel" class="btn btn-success">Upload and Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Advanced Search Modal (with multi-select dropdowns) -->
<div class="modal fade" id="advancedSearchModal" tabindex="-1" aria-labelledby="advancedSearchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <form method="get" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="advancedSearchModalLabel"><i class="fa-solid fa-filter mr-2"></i> Advanced Search</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p>Use the multi-select dropdowns below (like Excel filters) to select one or more values for each column. Leave blank to ignore.</p>

        <div class="row">
            <!-- Render one column per distinct field (multi-select) -->
            <?php foreach ($distinct_fields as $df): ?>
                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold"><?= htmlspecialchars(ucwords(str_replace(['_','USERids'],' ', $df))) ?></label>
                    <select name="<?= $df ?>[]" class="form-control" multiple>
                        <?php foreach ($distinct_values[$df] as $val): ?>
                            <option value="<?= htmlspecialchars($val) ?>" <?= in_array($val, $multiFilters[$df]) ? 'selected' : '' ?>><?= htmlspecialchars($val) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Hold Ctrl (or Cmd) to select multiple, or click-and-drag.</small>
                </div>
            <?php endforeach; ?>
        </div>

        <hr>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">Any</option>
                    <option value="brand_new" <?= (isset($adv_single['status']) && $adv_single['status'] === 'brand_new') ? 'selected' : '' ?>>Brand New</option>
                    <option value="for_replacement" <?= (isset($adv_single['status']) && $adv_single['status'] === 'for_replacement') ? 'selected' : '' ?>>For Replacement</option>
                    <option value="for_condemn" <?= (isset($adv_single['status']) && $adv_single['status'] === 'for_condemn') ? 'selected' : '' ?>>For Condemn</option>
                    <option value="condemned" <?= (isset($adv_single['status']) && $adv_single['status'] === 'condemned') ? 'selected' : '' ?>>Condemned</option>
                </select>
            </div>

            <div class="form-group col-md-4">
                <label>Is Active</label>
                <select name="is_active" class="form-control">
                    <option value="">Any</option>
                    <option value="1" <?= ($adv_single['is_active'] === 1) ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= ($adv_single['is_active'] === 0 && $adv_single['is_active'] !== null) ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="form-group col-md-4">
                <label>Office</label>
                <select name="office_id" class="form-control">
                    <option value="">Any Office</option>
                    <?php foreach ($offices as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= ($adv_single['office_id'] == $o['id']) ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Created From</label>
                <input type="date" name="created_from" class="form-control" value="<?= htmlspecialchars($created_from) ?>">
            </div>
            <div class="form-group col-md-4">
                <label>Created To</label>
                <input type="date" name="created_to" class="form-control" value="<?= htmlspecialchars($created_to) ?>">
            </div>
            <div class="form-group col-md-4">
                <label>Free text (Search)</label>
                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>

        <!-- Keep other top-level filters in the form so they persist when advanced search is submitted -->
        <input type="hidden" name="category" value="<?= htmlspecialchars($filter_category) ?>">
        <input type="hidden" name="category_type" value="<?= htmlspecialchars($filter_category_type) ?>">
        <input type="hidden" name="office" value="<?= htmlspecialchars($filter_office) ?>">
        <input type="hidden" name="date" value="<?= htmlspecialchars($filter_date) ?>">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Apply Advanced Filters</button>
        <a href="items.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div id="loadingOverlay" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); z-index: 1060; color: white;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
        <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
            <span class="sr-only">Loading...</span>
        </div>
        <h4 class="mt-3">Processing Import... 📊</h4>
        <p>This may take a few moments depending on file size. Please do not close your browser or navigate away.</p>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('.custom-file-input').on('change', function() {
       let fileName = $(this).val().split('\\').pop();
       $(this).next('.custom-file-label').addClass("selected").html(fileName || "Choose file...");
    });

    const typeSelect = document.querySelector('select[name="category_type"]');
    const categorySelect = document.getElementById('categorySelect');

    function filterCategories() {
        const selectedType = typeSelect ? typeSelect.value : '';
        
        for (const optgroup of categorySelect.getElementsByTagName('optgroup')) {
            let hasVisibleOptions = false;
            for (const option of optgroup.getElementsByTagName('option')) {
                const optionType = option.getAttribute('data-type') || '';
                const shouldDisplay = !selectedType || optionType === selectedType;
                option.style.display = shouldDisplay ? 'block' : 'none';
                if (shouldDisplay) hasVisibleOptions = true;
            }
            optgroup.style.display = hasVisibleOptions ? 'block' : 'none';
        }
    }
    
    if (typeSelect) {
        typeSelect.addEventListener('change', filterCategories);
        filterCategories(); // Initial filter on page load
    }

    const importForm = document.getElementById('importForm');
    const loadingOverlay = document.getElementById('loadingOverlay');

    if (importForm) {
        importForm.addEventListener('submit', function(e) {
            const excelFile = document.getElementById('excelFile');
            
            if (!excelFile.files || excelFile.files.length === 0) {
                return; 
            }

            $('#importExcelModal').modal('hide');
            
            loadingOverlay.style.display = 'block';
        });
    }

    // When the advanced search modal is opened, focus the first input
    $('#advancedSearchModal').on('shown.bs.modal', function () {
        $(this).find('input,select,textarea').filter(':visible:first').focus();
    });
});
</script>
</body>
</html>
