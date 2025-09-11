<?php
require '../config/db.php';
session_start();

if (isset($_GET['id'])) {
    $item_id = intval($_GET['id']);
    
    $query = "SELECT i.*, c.name as category_name 
              FROM items i 
              JOIN categories c ON i.category_id = c.id 
              WHERE i.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $item = $result->fetch_assoc();
        echo json_encode($item);
    } else {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>
