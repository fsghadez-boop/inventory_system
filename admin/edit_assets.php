<?php
require '../config/db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /inventory_system/login.php");
    exit();
}

// Get item ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch item details
$item = $db->query("SELECT i.*, c.name as category_name, c.category_type 
                   FROM items i 
                   JOIN categories c ON i.category_id = c.id 
                   WHERE i.id = $id")->fetch_assoc();

// Check if item is an asset
if (!$item || $item['category_type'] !== 'asset') {
    header("Location: items.php");
    exit();
}

// Handle form submission (similar to add_item_assets.php but for update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process the form data (similar to your add_item_assets.php update logic)
    // This would include all the field processing from add_item_assets.php
    
    // After successful update:
    $success = "Asset updated successfully!";
    
    // Refresh item data
    $item = $db->query("SELECT i.*, c.name as category_name, c.category_type 
                       FROM items i 
                       JOIN categories c ON i.category_id = c.id 
                       WHERE i.id = $id")->fetch_assoc();
}

// Fetch categories and offices for select lists
$cats = $db->query("SELECT * FROM categories WHERE category_type = 'asset' ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$offices = $db->query("SELECT * FROM offices ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Then include the form HTML (similar to add_item_assets.php but pre-filled with $item data)
// You'll need to modify the form to pre-populate values from $item
?>

<!doctype html>
<html>
<head>
<title>Edit Asset</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<!-- Include the same JavaScript functions as add_item_assets.php -->
</head>
<body onload="updateFormSections()">
<!-- Navigation and form similar to add_item_assets.php but with pre-filled values -->
</body>
</html>