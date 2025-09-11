<?php
function logDetailedChange($db, $item_id, $action, $performed_by, $field, $old_value, $new_value) {
    $log_details = "Edited item " . date('m-d-Y') . " $field: from {$old_value} -> {$new_value}";
    
    $stmt = $db->prepare("INSERT INTO audit_logs (item_id, action, performed_by, log_details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isis', $item_id, $action, $performed_by, $log_details);
    $stmt->execute();
}
?>