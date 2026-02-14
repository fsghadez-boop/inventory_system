<?php
require '../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (session_status() === PHP_SESSION_NONE) session_start();

// Require a logged-in user (view-only page)
if (!isset($_SESSION['user_id'])) {
    header("Location: /inventory_system/login.php");
    exit;
}

$admin_name = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User';

// Utility: sanitize GET/POST string
function g($k, $default = '') {
    return isset($_REQUEST[$k]) ? trim((string)$_REQUEST[$k]) : $default;
}

// Allowed fields for filtering and for pivot selection (maps label)
$allowedFields = [
	'office_name' => 'Office',
    'category_name' => 'Equipment Type',
    'os' => 'Operating System',
    'processor' => 'Processor',
    'ram' => 'Ram',
    'status' => 'Status',
	'storage_type' => 'Storage Type',
	'acquisition_date' => 'Date of Acquisition',
	'supplier_name' => 'Name of Supplier',
	'warranty' => 'Years of Warranty',
	'endpoint_protection' => 'end point',
	'miaa_property' => 'Property Type'
];

// Fields available for listing (all item columns that can be displayed)
$listableFields = [
    'property_number' => 'Property Number',
	'USERids' => 'Name of User',
	'category_name' => 'Equipment Type',
    'brand' => 'Brand',
    'model' => 'Model',
    'processor' => 'Processor',
    'ram' => 'Ram',
    'storage_type' => 'Storage Type',
	'storage_capacity' => 'Storage Capacity',
    'endpoint_protection' => 'Endpoint Protection',
    'os' => 'Operating System',
	'serial_number' => 'Serial Number',
	'computer_name' => 'Computer Name',
	'workgroup' => 'Workgroup',
	'po_number' => 'PO Number',
	'warranty' => 'Years of Warranty',
    'acquisition_date' => 'Acquisition Date'
];

// Determine which column filters should be visible.
$visibleFields = [];
if (isset($_GET['show_cols']) && is_array($_GET['show_cols'])) {
    foreach ($_GET['show_cols'] as $v) {
        $v = trim((string)$v);
        if ($v === '') continue;
        if (array_key_exists($v, $allowedFields)) $visibleFields[] = $v;
    }
}

// Helper: convert 1-based numeric column index to Excel column letters (1 -> A, 27 -> AA)
function colLetter($c) {
    $c = (int)$c;
    $c--; // make zero-based
    $letters = '';
    while ($c >= 0) {
        $letters = chr(65 + ($c % 26)) . $letters;
        $c = intval($c / 26) - 1;
    }
    return $letters;
}

// Build basic filters
$filter_category = (int) g('category', 0);
$filter_office = (int) g('office', 0);
$filter_date = g('date', '');
$search = g('search', '');

// Multi-select filters
$multiFilters = [];
foreach ($allowedFields as $col => $label) {
    if (isset($_GET[$col]) && is_array($_GET[$col])) {
        $arr = array_filter(array_map(function($v){ return trim((string)$v); }, $_GET[$col]), function($v){ return $v !== ''; });
        $multiFilters[$col] = array_values($arr);
    } else {
        $multiFilters[$col] = [];
    }
}

// Fetch auxiliary lists for filter dropdowns
$categories = [];
$res = $db->query("SELECT id, name, category_type FROM categories ORDER BY category_type, name ASC");
if ($res) { $categories = $res->fetch_all(MYSQLI_ASSOC); $res->free(); }

$categories_by_type = ['supply' => [], 'asset' => []];
foreach ($categories as $c) { $categories_by_type[strtolower($c['category_type'] ?? 'supply')][] = $c; }

$offices = [];
$res = $db->query("SELECT id, name FROM offices ORDER BY name ASC");
if ($res) { $offices = $res->fetch_all(MYSQLI_ASSOC); $res->free(); }

// Helper to get distinct values for allowedFields
$distinct_values = [];
foreach ($allowedFields as $col => $label) {
    if ($col === 'category_name') {
        $q = "SELECT DISTINCT TRIM(COALESCE(c.name, '')) AS val FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE c.name IS NOT NULL AND TRIM(c.name) <> '' ORDER BY val";
    } elseif ($col === 'office_name') {
        $q = "SELECT DISTINCT TRIM(COALESCE(o.name, '')) AS val FROM items i LEFT JOIN offices o ON i.office_id = o.id WHERE o.name IS NOT NULL AND TRIM(o.name) <> '' ORDER BY val";
    } else {
        $safeCol = $col;
        $q = "SELECT DISTINCT TRIM(COALESCE(`$safeCol`, '')) AS val FROM items WHERE `$safeCol` IS NOT NULL AND TRIM(COALESCE(`$safeCol`,'') ) <> '' ORDER BY val";
    }
    $vals = [];
    if ($res = $db->query($q)) {
        while ($row = $res->fetch_assoc()) {
            if ($row['val'] !== '') $vals[] = $row['val'];
        }
        $res->free();
    }
    $distinct_values[$col] = $vals;
}

// Build WHERE clause for filtered raw items and pivot source
$wheres = ["i.is_deleted = 0"];
$params = [];
$types = "";

// search across several columns
if ($search !== '') {
    $wheres[] = "(i.property_number LIKE ? OR i.brand LIKE ? OR i.model LIKE ? OR i.USERids LIKE ? OR i.serial_number LIKE ? OR c.name LIKE ? OR o.name LIKE ?)";
    $like = "%" . $search . "%";
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
    $types .= str_repeat('s', 7);
}
if ($filter_category > 0) { $wheres[] = "i.category_id = ?"; $params[] = $filter_category; $types .= "i"; }
if ($filter_office > 0) { $wheres[] = "i.office_id = ?"; $params[] = $filter_office; $types .= "i"; }
if ($filter_date !== '') { $wheres[] = "DATE(i.created_at) = ?"; $params[] = $filter_date; $types .= "s"; }

// Apply multi-select filters: WHERE IN lists
foreach ($multiFilters as $col => $vals) {
    if (empty($vals)) continue;
    $placeholders = implode(',', array_fill(0, count($vals), '?'));
    if ($col === 'category_name') {
        $wheres[] = "c.name IN ($placeholders)";
    } elseif ($col === 'office_name') {
        $wheres[] = "o.name IN ($placeholders)";
    } else {
        $wheres[] = "i.`$col` IN ($placeholders)";
    }
    foreach ($vals as $v) { $params[] = $v; $types .= "s"; }
}

$whereSql = implode(' AND ', $wheres);

// Raw items query (for display and raw export)
$rawSql = "SELECT i.*, c.name AS category_name, c.category_type, o.name AS office_name
           FROM items i
           LEFT JOIN categories c ON i.category_id = c.id
           LEFT JOIN offices o ON i.office_id = o.id
           WHERE $whereSql
           ORDER BY i.id DESC";

// Prepare and execute
$items = [];
$stmt = $db->prepare($rawSql);
if ($stmt) {
    if (!empty($params)) {
        $bind = [$types];
        for ($i = 0; $i < count($params); $i++) $bind[] = &$params[$i];
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $items = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// ---------- Pivot logic ----------
$pivot_row = g('pivot_row', '');
$pivot_col = g('pivot_col', '');
$agg = g('agg', 'count');
$value_field = g('value_field', 'quantity');

// Fields to list (for 'list' aggregation) - multi-select
$list_fields = [];
$list_fields_raw = isset($_GET['list_field']) && is_array($_GET['list_field']) ? $_GET['list_field'] : [];
foreach ($list_fields_raw as $field) {
    $field = trim((string)$field);
    if ($field !== '' && array_key_exists($field, $listableFields)) {
        $list_fields[] = $field;
    }
}

// Validate pivot fields
if ($pivot_row !== '' && !array_key_exists($pivot_row, $allowedFields)) {
    $pivot_row = '';
}
if ($pivot_col !== '' && !array_key_exists($pivot_col, $allowedFields)) {
    $pivot_col = '';
}
if (!in_array($agg, ['count', 'sum', 'list'])) $agg = 'count';

// Build pivot source query
$pivot_table = [];
$pivot_rows = [];
$pivot_columns = [];
$pivot_row_totals = [];
$pivot_col_totals = [];
$pivot_grand_total = 0;

if ($pivot_row !== '') {
    // Map special columns to SQL expressions
    $colExpr = function($c) {
        if ($c === 'category_name') return "TRIM(COALESCE(c.name, ''))";
        if ($c === 'office_name') return "TRIM(COALESCE(o.name, ''))";
        return "TRIM(COALESCE(i.`$c`, ''))";
    };

    $rowExpr = $colExpr($pivot_row);
    $colExprSql = $pivot_col !== '' ? $colExpr($pivot_col) : null;

    if ($agg === 'list') {
        // For list aggregation, fetch all items and group them client-side
        $pivotSql = "SELECT i.*, c.name AS category_name, c.category_type, o.name AS office_name,
                            {$rowExpr} AS row_val" . ($pivot_col !== '' ? ", {$colExprSql} AS col_val" : "") . "
                     FROM items i
                     LEFT JOIN categories c ON i.category_id = c.id
                     LEFT JOIN offices o ON i.office_id = o.id
                     WHERE $whereSql
                     ORDER BY row_val" . ($pivot_col !== '' ? ", col_val" : "") . ", i.id";
        
        $pstmt = $db->prepare($pivotSql);
        if ($pstmt) {
            if (!empty($params)) {
                $bind = [$types];
                for ($i=0;$i<count($params);$i++) $bind[] = &$params[$i];
                call_user_func_array([$pstmt, 'bind_param'], $bind);
            }
            $pstmt->execute();
            $pres = $pstmt->get_result();
            if ($pres) {
                while ($row = $pres->fetch_assoc()) {
                    $rval = $row['row_val'] !== '' ? $row['row_val'] : '(blank)';
                    if ($pivot_col !== '') {
                        $cval = $row['col_val'] !== '' ? $row['col_val'] : '(blank)';
                        if (!isset($pivot_table[$rval])) $pivot_table[$rval] = [];
                        if (!isset($pivot_table[$rval][$cval])) $pivot_table[$rval][$cval] = [];
                        $pivot_table[$rval][$cval][] = $row;
                        if (!in_array($cval, $pivot_columns, true)) $pivot_columns[] = $cval;
                    } else {
                        if (!isset($pivot_table[$rval])) $pivot_table[$rval] = [];
                        $pivot_table[$rval][] = $row;
                    }
                    if (!in_array($rval, $pivot_rows, true)) $pivot_rows[] = $rval;
                }
                $pres->free();
            }
            $pstmt->close();
        }
        
        sort($pivot_columns, SORT_NATURAL | SORT_FLAG_CASE);
        sort($pivot_rows, SORT_NATURAL | SORT_FLAG_CASE);
    } else {
        // For count/sum aggregation - traditional crosstab
        if ($agg === 'count') {
            $selectAgg = "COUNT(*) AS agg_val";
        } else {
            $vf = in_array($value_field, ['quantity']) ? $value_field : 'quantity';
            $selectAgg = "SUM(COALESCE(i.`$vf`,0)) AS agg_val";
        }

        if ($pivot_col !== '') {
            $pivotSql = "SELECT {$rowExpr} AS row_val, {$colExprSql} AS col_val, {$selectAgg}
                         FROM items i
                         LEFT JOIN categories c ON i.category_id = c.id
                         LEFT JOIN offices o ON i.office_id = o.id
                         WHERE $whereSql
                         GROUP BY row_val, col_val
                         ORDER BY row_val, col_val";
        } else {
            $pivotSql = "SELECT {$rowExpr} AS row_val, {$selectAgg}
                         FROM items i
                         LEFT JOIN categories c ON i.category_id = c.id
                         LEFT JOIN offices o ON i.office_id = o.id
                         WHERE $whereSql
                         GROUP BY row_val
                         ORDER BY row_val";
        }

        $pstmt = $db->prepare($pivotSql);
        if ($pstmt) {
            if (!empty($params)) {
                $bind = [$types];
                for ($i=0;$i<count($params);$i++) $bind[] = &$params[$i];
                call_user_func_array([$pstmt, 'bind_param'], $bind);
            }
            $pstmt->execute();
            $pres = $pstmt->get_result();
            if ($pres) {
                while ($row = $pres->fetch_assoc()) {
                    $rval = $row['row_val'] !== '' ? $row['row_val'] : '(blank)';
                    $val_raw = $row['agg_val'];
                    $val = is_numeric($val_raw) ? (float)$val_raw : 0.0;
                    
                    if ($pivot_col !== '') {
                        $cval = $row['col_val'] !== '' ? $row['col_val'] : '(blank)';
                        if (!isset($pivot_table[$rval])) $pivot_table[$rval] = [];
                        $pivot_table[$rval][$cval] = $val;
                        
                        // Track totals
                        $pivot_row_totals[$rval] = ($pivot_row_totals[$rval] ?? 0) + $val;
                        $pivot_col_totals[$cval] = ($pivot_col_totals[$cval] ?? 0) + $val;
                        $pivot_grand_total += $val;
                        
                        if (!in_array($cval, $pivot_columns, true)) $pivot_columns[] = $cval;
                        if (!in_array($rval, $pivot_rows, true)) $pivot_rows[] = $rval;
                    } else {
                        if (!isset($pivot_table[$rval])) $pivot_table[$rval] = [];
                        $pivot_table[$rval]['__value'] = $val;
                        $pivot_row_totals[$rval] = $val;
                        $pivot_grand_total += $val;
                        if (!in_array($rval, $pivot_rows, true)) $pivot_rows[] = $rval;
                    }
                }
                $pres->free();
            }
            $pstmt->close();
        }

        sort($pivot_columns, SORT_NATURAL | SORT_FLAG_CASE);
        sort($pivot_rows, SORT_NATURAL | SORT_FLAG_CASE);
    }
}

// -------- Export handlers --------
$export = g('export', '');
if ($export === 'raw_csv' || $export === 'raw_xlsx') {
    if ($export === 'raw_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=items_raw_export.csv');
        $out = fopen('php://output', 'w');
        if (!empty($items)) {
            fputcsv($out, array_keys($items[0]));
            foreach ($items as $it) {
                $row = array_map(function($v){ return $v === null ? '' : $v; }, $it);
                fputcsv($out, $row);
            }
        } else {
            fputcsv($out, ['No data']);
        }
        fclose($out);
        exit;
    } else {
        // Raw XLSX export
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            if (!empty($items)) {
                $cols = array_keys($items[0]);
                $colIndex = 1;
                foreach ($cols as $c) {
                    $cell = colLetter($colIndex) . '1';
                    $sheet->setCellValue($cell, $c);
                    $colIndex++;
                }
                $rowNum = 2;
                foreach ($items as $it) {
                    $colIndex = 1;
                    foreach ($cols as $c) {
                        $cell = colLetter($colIndex) . $rowNum;
                        $sheet->setCellValue($cell, $it[$c]);
                        $colIndex++;
                    }
                    $rowNum++;
                }
            } else {
                $sheet->setCellValue('A1', 'No data');
            }
            $writer = new Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="items_raw_export.xlsx"');
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            $export_error = "Export failed: " . $e->getMessage();
        }
    }
}

if ($export === 'pivot_csv' || $export === 'pivot_xlsx') {
    if ($pivot_row === '') {
        // nothing to export
    } else {
        if ($agg !== 'list') {
            // Count/Sum export (traditional pivot table)
            if ($pivot_col !== '') {
                $headers = array_merge([$allowedFields[$pivot_row]], $pivot_columns, ['Row Total']);
                if ($export === 'pivot_csv') {
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename=pivot_export.csv');
                    $out = fopen('php://output', 'w');
                    fputcsv($out, $headers);
                    foreach ($pivot_rows as $rval) {
                        $row = [$rval];
                        foreach ($pivot_columns as $c) {
                            $val = $pivot_table[$rval][$c] ?? 0;
                            if ($agg === 'count') {
                                $row[] = number_format((int)$val);
                            } else {
                                $row[] = rtrim(rtrim(number_format((float)$val, 2, '.', ','), '0'), '.');
                            }
                        }
                        $rt = $pivot_row_totals[$rval] ?? 0;
                        $row[] = $agg === 'count' ? number_format((int)$rt) : rtrim(rtrim(number_format((float)$rt, 2, '.', ','), '0'), '.');
                        fputcsv($out, $row);
                    }
                    $totalsRow = ['Column Total'];
                    foreach ($pivot_columns as $c) {
                        $val = $pivot_col_totals[$c] ?? 0;
                        $totalsRow[] = $agg === 'count' ? number_format((int)$val) : rtrim(rtrim(number_format((float)$val, 2, '.', ','), '0'), '.');
                    }
                    $totalsRow[] = $agg === 'count' ? number_format((int)$pivot_grand_total) : rtrim(rtrim(number_format((float)$pivot_grand_total, 2, '.', ','), '0'), '.');
                    fputcsv($out, $totalsRow);
                    fclose($out);
                    exit;
                } else {
                    // Pivot XLSX export
                    try {
                        $ss = new Spreadsheet();
                        $s = $ss->getActiveSheet();
                        $col = 1;
                        foreach ($headers as $h) {
                            $s->setCellValue(colLetter($col) . '1', $h);
                            $col++;
                        }
                        $r = 2;
                        foreach ($pivot_rows as $rval) {
                            $col = 1;
                            $s->setCellValue(colLetter($col) . $r, $rval); $col++;
                            foreach ($pivot_columns as $c) {
                                $val = $pivot_table[$rval][$c] ?? 0;
                                if ($agg === 'count') {
                                    $display = number_format((int)$val);
                                } else {
                                    $display = rtrim(rtrim(number_format((float)$val, 2, '.', ','), '0'), '.');
                                }
                                $s->setCellValue(colLetter($col) . $r, $display);
                                $col++;
                            }
                            $rt = $pivot_row_totals[$rval] ?? 0;
                            $display = $agg === 'count' ? number_format((int)$rt) : rtrim(rtrim(number_format((float)$rt, 2, '.', ','), '0'), '.');
                            $s->setCellValue(colLetter($col) . $r, $display);
                            $r++;
                        }
                        $col = 1;
                        $s->setCellValue(colLetter($col) . $r, 'Column Total'); $col++;
                        foreach ($pivot_columns as $c) {
                            $val = $pivot_col_totals[$c] ?? 0;
                            $display = $agg === 'count' ? number_format((int)$val) : rtrim(rtrim(number_format((float)$val, 2, '.', ','), '0'), '.');
                            $s->setCellValue(colLetter($col) . $r, $display);
                            $col++;
                        }
                        $s->setCellValue(colLetter($col) . $r, $agg === 'count' ? number_format((int)$pivot_grand_total) : rtrim(rtrim(number_format((float)$pivot_grand_total, 2, '.', ','), '0'), '.'));
                        $writer = new Xlsx($ss);
                        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                        header('Content-Disposition: attachment; filename="pivot_export.xlsx"');
                        $writer->save('php://output');
                        exit;
                    } catch (Exception $e) {
                        $export_error = "Pivot export failed: " . $e->getMessage();
                    }
                }
            } else {
                $headers = [$allowedFields[$pivot_row], $agg === 'sum' ? "Sum of $value_field" : 'Count'];
                if ($export === 'pivot_csv') {
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename=pivot_export.csv');
                    $out = fopen('php://output', 'w');
                    fputcsv($out, $headers);
                    foreach ($pivot_rows as $rval) {
                        $val = $pivot_row_totals[$rval] ?? 0;
                        if ($agg === 'count') {
                            $displayVal = number_format((int)$val);
                        } else {
                            $displayVal = rtrim(rtrim(number_format((float)$val, 2, '.', ','), '0'), '.');
                        }
                        $row = [$rval, $displayVal];
                        fputcsv($out, $row);
                    }
                    $displayTotal = $agg === 'count' ? number_format((int)$pivot_grand_total) : rtrim(rtrim(number_format((float)$pivot_grand_total, 2, '.', ','), '0'), '.');
                    fputcsv($out, ['Grand Total', $displayTotal]);
                    fclose($out);
                    exit;
                } else {
                    try {
                        $ss = new Spreadsheet();
                        $s = $ss->getActiveSheet();
                        $s->setCellValue(colLetter(1) . '1', $headers[0]);
                        $s->setCellValue(colLetter(2) . '1', $headers[1]);
                        $r = 2;
                        foreach ($pivot_rows as $rval) {
                            $val = $pivot_row_totals[$rval] ?? 0;
                            if ($agg === 'count') {
                                $displayVal = number_format((int)$val);
                            } else {
                                $displayVal = rtrim(rtrim(number_format((float)$val, 2, '.', ','), '0'), '.');
                            }
                            $s->setCellValue(colLetter(1) . $r, $rval);
                            $s->setCellValue(colLetter(2) . $r, $displayVal);
                            $r++;
                        }
                        $displayTotal = $agg === 'count' ? number_format((int)$pivot_grand_total) : rtrim(rtrim(number_format((float)$pivot_grand_total, 2, '.', ','), '0'), '.');
                        $s->setCellValue(colLetter(1) . $r, 'Grand Total');
                        $s->setCellValue(colLetter(2) . $r, $displayTotal);
                        $writer = new Xlsx($ss);
                        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                        header('Content-Disposition: attachment; filename="pivot_export.xlsx"');
                        $writer->save('php://output');
                        exit;
                    } catch (Exception $e) {
                        $export_error = "Pivot export failed: " . $e->getMessage();
                    }
                }
            }
        } else {
            // List export
            if ($export === 'pivot_csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=pivot_export.csv');
                $out = fopen('php://output', 'w');
                
                $headers = [$allowedFields[$pivot_row]];
                if ($pivot_col !== '') $headers[] = $allowedFields[$pivot_col];
                $headers = array_merge($headers, array_map(function($f) { return $GLOBALS['listableFields'][$f]; }, $list_fields));
                fputcsv($out, $headers);
                
                foreach ($pivot_rows as $rval) {
                    if ($pivot_col !== '') {
                        foreach ($pivot_columns as $cval) {
                            $items_in_cell = $pivot_table[$rval][$cval] ?? [];
                            foreach ($items_in_cell as $item) {
                                $row = [$rval, $cval];
                                foreach ($list_fields as $field) {
                                    $row[] = $item[$field] ?? '';
                                }
                                fputcsv($out, $row);
                            }
                        }
                    } else {
                        $items_in_cell = $pivot_table[$rval] ?? [];
                        foreach ($items_in_cell as $item) {
                            $row = [$rval];
                            foreach ($list_fields as $field) {
                                $row[] = $item[$field] ?? '';
                            }
                            fputcsv($out, $row);
                        }
                    }
                }
                fclose($out);
                exit;
            } elseif ($export === 'pivot_xlsx') {
                try {
                    $ss = new Spreadsheet();
                    $s = $ss->getActiveSheet();
                    
                    $headers = [$allowedFields[$pivot_row]];
                    if ($pivot_col !== '') $headers[] = $allowedFields[$pivot_col];
                    $headers = array_merge($headers, array_map(function($f) { return $GLOBALS['listableFields'][$f]; }, $list_fields));
                    
                    $col = 1;
                    foreach ($headers as $h) {
                        $s->setCellValue(colLetter($col) . '1', $h);
                        $col++;
                    }
                    
                    $r = 2;
                    foreach ($pivot_rows as $rval) {
                        if ($pivot_col !== '') {
                            foreach ($pivot_columns as $cval) {
                                $items_in_cell = $pivot_table[$rval][$cval] ?? [];
                                foreach ($items_in_cell as $item) {
                                    $col = 1;
                                    $s->setCellValue(colLetter($col) . $r, $rval); $col++;
                                    $s->setCellValue(colLetter($col) . $r, $cval); $col++;
                                    foreach ($list_fields as $field) {
                                        $s->setCellValue(colLetter($col) . $r, $item[$field] ?? '');
                                        $col++;
                                    }
                                    $r++;
                                }
                            }
                        } else {
                            $items_in_cell = $pivot_table[$rval] ?? [];
                            foreach ($items_in_cell as $item) {
                                $col = 1;
                                $s->setCellValue(colLetter($col) . $r, $rval); $col++;
                                foreach ($list_fields as $field) {
                                    $s->setCellValue(colLetter($col) . $r, $item[$field] ?? '');
                                    $col++;
                                }
                                $r++;
                            }
                        }
                    }
                    
                    $writer = new Xlsx($ss);
                    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    header('Content-Disposition: attachment; filename="pivot_export.xlsx"');
                    $writer->save('php://output');
                    exit;
                } catch (Exception $e) {
                    $export_error = "Pivot export failed: " . $e->getMessage();
                }
            }
        }
    }
}

// ---------- Page rendering starts here ----------
?>
<!doctype html>
<html lang="en">
<head>
    <title>Reports & Pivot | Inventory System</title>
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
        .table th, .table td { vertical-align: top !important; font-size: 0.9rem; }
        .table .btn-sm { padding: 0.2rem 0.4rem; font-size: 0.8rem; }
        .modal-backdrop.show {
            z-index: 1040;
        }
        select[multiple] { height: auto; min-height: 120px; }
        .item-detail {
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 0.85rem;
            line-height: 1.4;
            max-height: 400px;
            overflow-y: auto;
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
    <a href="item_reports.php" class="active"><i class="fa-solid fa-chart-column"></i> Reports</a>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">📊 Reports & Pivot Table Builder</h1>
            <div>
                <a href="items.php" class="btn btn-primary"><i class="fa-solid fa-box mr-1"></i> Manage Items</a>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-body">
                <?php if (!empty($export_error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($export_error) ?></div>
                <?php endif; ?>

                <form method="get" class="mb-3">
                    <div class="form-row">
                        <div class="col-md-3 mb-2">
                            <input type="text" name="search" class="form-control" placeholder="Search" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2 mb-2">
                            <select name="category" class="form-control">
                                <option value="0">All Categories</option>
                                <?php if (!empty($categories_by_type['supply'])): ?>
                                    <optgroup label="Supplies">
                                        <?php foreach ($categories_by_type['supply'] as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $filter_category == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if (!empty($categories_by_type['asset'])): ?>
                                    <optgroup label="Assets">
                                        <?php foreach ($categories_by_type['asset'] as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $filter_category == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <select name="office" class="form-control">
                                <option value="0">All Offices</option>
                                <?php foreach ($offices as $o): ?>
                                    <option value="<?= $o['id'] ?>" <?= $filter_office == $o['id'] ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <select name="date" class="form-control">
                                <option value="">All Dates</option>
                                <?php
                                $dates_result = $db->query("SELECT DISTINCT DATE(created_at) as creation_date FROM items WHERE created_at IS NOT NULL ORDER BY creation_date DESC");
                                $creation_dates = $dates_result ? array_column($dates_result->fetch_all(MYSQLI_ASSOC), 'creation_date') : [];
                                ?>
                                <?php foreach ($creation_dates as $date): ?>
                                    <option value="<?= $date ?>" <?= $filter_date == $date ? 'selected' : '' ?>><?= date('M d, Y', strtotime($date)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2 d-flex">
                            <button type="submit" class="btn btn-info mr-2">Apply Filters</button>
                            <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#advancedFiltersModal" title="Advanced Filters"><i class="fa-solid fa-filter"></i></button>
                        </div>
                    </div>

                    <hr>

                    <h6>Visible Column Filters</h6>
                    <div class="form-row mb-2">
                        <?php foreach ($allowedFields as $col => $label): ?>
                            <div class="form-check form-check-inline col-md-2">
                                <input class="form-check-input show-col-toggle" type="checkbox"
                                       id="show_<?= $col ?>" name="show_cols[]" value="<?= $col ?>"
                                       <?= in_array($col, $visibleFields) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="show_<?= $col ?>"><?= htmlspecialchars($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr>

                    <h6>Column Filters (multi-select)</h6>
                    <div class="form-row">
                        <?php foreach ($allowedFields as $col => $label): ?>
                            <?php $hiddenClass = in_array($col, $visibleFields) ? '' : 'd-none'; ?>
                            <div class="col-md-4 mb-3 col-filter <?= $hiddenClass ?>" data-field="<?= $col ?>">
                                <label class="font-weight-bold"><?= htmlspecialchars($label) ?></label>
                                <select name="<?= $col ?>[]" class="form-control" multiple>
                                    <?php foreach ($distinct_values[$col] as $val): ?>
                                        <option value="<?= htmlspecialchars($val) ?>" <?= in_array($val, $multiFilters[$col]) ? 'selected' : '' ?>><?= htmlspecialchars($val) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr>

                    <div class="form-row align-items-end">
                        <div class="col-md-2 mb-2">
                            <label>Pivot: Row</label>
                            <select name="pivot_row" class="form-control">
                                <option value="">-- Not selected --</option>
                                <?php foreach ($allowedFields as $col => $label): ?>
                                    <option value="<?= $col ?>" <?= $pivot_row === $col ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label>Pivot: Column</label>
                            <select name="pivot_col" class="form-control">
                                <option value="">-- None --</option>
                                <?php foreach ($allowedFields as $col => $label): ?>
                                    <option value="<?= $col ?>" <?= $pivot_col === $col ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label>Aggregation</label>
                            <select name="agg" class="form-control" id="aggSelect">
                                <option value="count" <?= $agg === 'count' ? 'selected' : '' ?>>Count</option>
                                <option value="sum" <?= $agg === 'sum' ? 'selected' : '' ?>>Sum (numeric)</option>
                                <option value="list" <?= $agg === 'list' ? 'selected' : '' ?>>List Items</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2" id="valueFieldDiv" style="<?= $agg === 'sum' ? '' : 'display:none;' ?>">
                            <label>Value field (for Sum)</label>
                            <select name="value_field" class="form-control">
                                <option value="quantity" <?= $value_field === 'quantity' ? 'selected' : '' ?>>quantity</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2" id="listFieldDiv" style="<?= $agg === 'list' ? '' : 'display:none;' ?>">
                            <label>Fields to display</label>
                            <select name="list_field[]" class="form-control" multiple>
                                <?php foreach ($listableFields as $field => $label): ?>
                                    <option value="<?= $field ?>" <?= in_array($field, $list_fields) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Select fields to display per item.</small>
                        </div>
                        <div class="col-md-1 mb-2 d-flex">
                            <button type="submit" class="btn btn-success btn-sm">Generate</button>
                        </div>
                    </div>

                    <div class="form-row mt-2">
                        <div class="col-md-2 mb-2 d-flex">
                            <a href="item_reports.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                        </div>
                    </div>

                    <div class="form-row mt-3">
                        <div class="col-md-6">
                            <a href="<?= htmlspecialchars(add_query_arg($_SERVER['QUERY_STRING'] ?? '', 'export=raw_csv')) ?>" class="btn btn-outline-primary btn-sm">Export Raw CSV</a>
                            <a href="<?= htmlspecialchars(add_query_arg($_SERVER['QUERY_STRING'] ?? '', 'export=raw_xlsx')) ?>" class="btn btn-outline-primary btn-sm">Export Raw XLSX</a>
                        </div>
                        <div class="col-md-6 text-right">
                            <?php if ($pivot_row !== ''): ?>
                                <a href="<?= htmlspecialchars(add_query_arg($_SERVER['QUERY_STRING'] ?? '', 'export=pivot_csv')) ?>" class="btn btn-outline-info btn-sm">Export Pivot CSV</a>
                                <a href="<?= htmlspecialchars(add_query_arg($_SERVER['QUERY_STRING'] ?? '', 'export=pivot_xlsx')) ?>" class="btn btn-outline-info btn-sm">Export Pivot XLSX</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <hr>

                <!-- Display pivot if requested -->
                <?php if ($pivot_row === ''): ?>
                    <div class="alert alert-secondary">Select a Pivot Row field and click "Generate" to build a report. You may also apply filters above to limit the dataset.</div>
                <?php else: ?>
                    <h5>Pivot Result</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th><?= htmlspecialchars($allowedFields[$pivot_row]) ?></th>
                                    <?php if ($pivot_col !== ''): ?>
                                        <th><?= htmlspecialchars($allowedFields[$pivot_col]) ?></th>
                                    <?php endif; ?>
                                    <?php if ($agg !== 'list'): ?>
                                        <th><?= $agg === 'sum' ? "Sum of $value_field" : "Count" ?></th>
                                    <?php else: ?>
                                        <th>Items</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pivot_rows)): ?>
                                    <tr><td colspan="<?= ($pivot_col !== '' ? 2 : 1) + 1 ?>" class="text-center">No data for selected pivot and filters.</td></tr>
                                <?php elseif ($agg !== 'list'): ?>
                                    <!-- Count/Sum aggregation: Traditional pivot table -->
                                    <?php if ($pivot_col !== ''): ?>
                                        <!-- Two-dimensional pivot -->
                                        <tr>
                                            <th><?= htmlspecialchars($allowedFields[$pivot_row]) ?></th>
                                            <?php foreach ($pivot_columns as $c): ?>
                                                <th><?= htmlspecialchars($c) ?></th>
                                            <?php endforeach; ?>
                                            <th>Row Total</th>
                                        </tr>
                                        <?php foreach ($pivot_rows as $rval): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($rval) ?></strong></td>
                                                <?php foreach ($pivot_columns as $c): ?>
                                                    <?php
                                                        $val = $pivot_table[$rval][$c] ?? 0;
                                                        if ($agg === 'count') {
                                                            $display = number_format((int)$val);
                                                        } else {
                                                            $display = rtrim(rtrim(number_format((float)$val, 2, '.', ','), '0'), '.');
                                                        }
                                                    ?>
                                                    <td><?= $display ?></td>
                                                <?php endforeach; ?>
                                                <td><strong><?php
                                                    $rt = $pivot_row_totals[$rval] ?? 0;
                                                    echo $agg === 'count' ? number_format((int)$rt) : rtrim(rtrim(number_format((float)$rt, 2, '.', ','), '0'), '.');
                                                ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="font-weight-bold" style="background-color: #f0f0f0;">
                                            <td>Column Total</td>
                                            <?php foreach ($pivot_columns as $c): ?>
                                                <td><?= $agg === 'count' ? number_format((int)($pivot_col_totals[$c] ?? 0)) : rtrim(rtrim(number_format((float)($pivot_col_totals[$c] ?? 0), 2, '.', ','), '0'), '.') ?></td>
                                            <?php endforeach; ?>
                                            <td><?= $agg === 'count' ? number_format((int)$pivot_grand_total) : rtrim(rtrim(number_format((float)$pivot_grand_total, 2, '.', ','), '0'), '.') ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <!-- One-dimensional pivot -->
                                        <?php foreach ($pivot_rows as $rval): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($rval) ?></strong></td>
                                                <td><?php
                                                    $val = $pivot_row_totals[$rval] ?? 0;
                                                    echo $agg === 'count' ? number_format((int)$val) : rtrim(rtrim(number_format((float)$val, 2, '.', ','), '0'), '.');
                                                ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="font-weight-bold" style="background-color: #f0f0f0;">
                                            <td>Grand Total</td>
                                            <td><?= $agg === 'count' ? number_format((int)$pivot_grand_total) : rtrim(rtrim(number_format((float)$pivot_grand_total, 2, '.', ','), '0'), '.') ?></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- List Items aggregation -->
                                    <?php foreach ($pivot_rows as $rval): ?>
                                        <?php if ($pivot_col !== ''): ?>
                                            <?php foreach ($pivot_columns as $cval): ?>
                                                <?php 
                                                    $items_in_cell = $pivot_table[$rval][$cval] ?? [];
                                                    if (!empty($items_in_cell)) {
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($rval) ?></td>
                                                    <td><?= htmlspecialchars($cval) ?></td>
                                                    <td>
                                                        <div class="item-detail">
                                                            <?php foreach ($items_in_cell as $idx => $item): ?>
                                                                <?php if ($idx > 0) echo "\n"; ?>
                                                                <?php foreach ($list_fields as $field): ?>
                                                                    <strong><?= htmlspecialchars($listableFields[$field] ?? $field) ?>:</strong> <?= htmlspecialchars($item[$field] ?? '') ?><?php echo "\n"; ?>
                                                                <?php endforeach; ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php 
                                                    }
                                                ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <?php 
                                                $items_in_cell = $pivot_table[$rval] ?? [];
                                                if (!empty($items_in_cell)) {
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($rval) ?></td>
                                                <td>
                                                    <div class="item-detail">
                                                        <?php foreach ($items_in_cell as $idx => $item): ?>
                                                            <?php if ($idx > 0) echo "\n"; ?>
                                                            <?php foreach ($list_fields as $field): ?>
                                                                <strong><?= htmlspecialchars($listableFields[$field] ?? $field) ?>:</strong> <?= htmlspecialchars($item[$field] ?? '') ?><?php echo "\n"; ?>
                                                            <?php endforeach; ?>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php 
                                                }
                                            ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <hr>

                <!-- Display raw filtered items (read-only) -->
                <h5>Filtered Items (<?= count($items) ?>)</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Property Number</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Office</th>
                                <th>Status</th>
                                <th>Qty</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="10" class="text-center">No items found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $it): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($it['id']) ?></td>
                                        <td><?= htmlspecialchars($it['property_number'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($it['product_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($it['category_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($it['brand'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($it['model'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($it['office_name'] ?? '') ?></td>
                                        <td>
                                            <?php
                                            $status_class = ['brand_new'=>'success', 'for_replacement'=>'warning', 'for_condemn'=>'danger', 'condemned'=>'dark'];
                                            $class = isset($status_class[$it['status']]) ? $status_class[$it['status']] : 'secondary';
                                            echo "<span class='badge badge-{$class}'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $it['status']))) . "</span>";
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($it['quantity']) ?></td>
                                        <td><?= !empty($it['created_at']) ? date('m/d/Y H:i', strtotime($it['created_at'])) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
<small class="text-muted">Tips: Use the Pivot builder above to create item listing reports. Select fields to display for each item. Use the Export buttons to download the results.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Filters Modal -->
<div class="modal fade" id="advancedFiltersModal" tabindex="-1" aria-labelledby="advancedFiltersModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <form method="get" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="advancedFiltersModalLabel"><i class="fa-solid fa-filter mr-2"></i> Advanced Filters</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p>Use the multi-select dropdowns below to select one or more values for each column. Leave blank to ignore.</p>

        <div class="row mb-2">
            <?php foreach ($allowedFields as $col => $label): ?>
                <div class="form-check form-check-inline col-md-2">
                    <input class="form-check-input show-col-toggle" type="checkbox" id="modal_show_<?= $col ?>" name="show_cols[]" value="<?= $col ?>" <?= in_array($col, $visibleFields) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="modal_show_<?= $col ?>"><?= htmlspecialchars($label) ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row">
            <?php foreach ($allowedFields as $col => $label): ?>
                <?php $hiddenClass = in_array($col, $visibleFields) ? '' : 'd-none'; ?>
                <div class="col-md-4 mb-3 col-filter <?= $hiddenClass ?>" data-field="<?= $col ?>">
                    <label class="font-weight-bold"><?= htmlspecialchars($label) ?></label>
                    <select name="<?= $col ?>[]" class="form-control" multiple>
                        <?php foreach ($distinct_values[$col] as $val): ?>
                            <option value="<?= htmlspecialchars($val) ?>" <?= in_array($val, $multiFilters[$col]) ? 'selected' : '' ?>><?= htmlspecialchars($val) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Hold Ctrl (or Cmd) to select multiple.</small>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Preserve filters -->
        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
        <input type="hidden" name="category" value="<?= htmlspecialchars($filter_category) ?>">
        <input type="hidden" name="office" value="<?= htmlspecialchars($filter_office) ?>">
        <input type="hidden" name="date" value="<?= htmlspecialchars($filter_date) ?>">
        <input type="hidden" name="pivot_row" value="<?= htmlspecialchars($pivot_row) ?>">
        <input type="hidden" name="pivot_col" value="<?= htmlspecialchars($pivot_col) ?>">
        <input type="hidden" name="agg" value="<?= htmlspecialchars($agg) ?>">
        <input type="hidden" name="value_field" value="<?= htmlspecialchars($value_field) ?>">
        <?php foreach ($list_fields as $field): ?>
            <input type="hidden" name="list_field[]" value="<?= htmlspecialchars($field) ?>">
        <?php endforeach; ?>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Apply Advanced Filters</button>
        <a href="item_reports.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle visibility and synchronize checkboxes
    function toggleFieldVisibility(field, checked) {
        var els = document.querySelectorAll('.col-filter[data-field="' + field + '"]');
        els.forEach(function(el) {
            if (checked) el.classList.remove('d-none'); else el.classList.add('d-none');
        });
    }

    document.querySelectorAll('.show-col-toggle').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var fld = this.value;
            var isChecked = this.checked;
            document.querySelectorAll('input[name="show_cols[]"][value="' + fld + '"]').forEach(function(other) {
                if (other !== cb) other.checked = isChecked;
            });
            toggleFieldVisibility(fld, isChecked);
        });
    });

    // Toggle visibility of value_field and list_field based on aggregation
    var aggSelect = document.getElementById('aggSelect');
    var valueFieldDiv = document.getElementById('valueFieldDiv');
    var listFieldDiv = document.getElementById('listFieldDiv');

    if (aggSelect) {
        aggSelect.addEventListener('change', function() {
            if (this.value === 'sum') {
                valueFieldDiv.style.display = '';
                listFieldDiv.style.display = 'none';
            } else if (this.value === 'list') {
                valueFieldDiv.style.display = 'none';
                listFieldDiv.style.display = '';
            } else {
                valueFieldDiv.style.display = 'none';
                listFieldDiv.style.display = 'none';
            }
        });
    }

    // When the advanced filters modal is opened, focus the first input
    $('#advancedFiltersModal').on('shown.bs.modal', function () {
        $(this).find('input,select,textarea').filter(':visible:first').focus();
    });
});
</script>
</body>
</html>

<?php
// Simple PHP helper to attach/replace a query parameter in an existing query string.
function add_query_arg($currentQueryString, $newParam) {
    parse_str($currentQueryString, $params);
    if (strpos($newParam, '&') !== false) {
        parse_str($newParam, $newParts);
        $params = array_merge($params, $newParts);
    } else {
        $parts = explode('=', $newParam, 2);
        if (count($parts) === 2) $params[$parts[0]] = $parts[1];
    }
    return '?' . http_build_query($params);
}
?>