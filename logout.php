<?php
session_start();
require 'config/db.php';

// Check if the user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Log the logout action
    $sql = "INSERT INTO audit_logs (performed_by, action, log_details, created_at) 
            VALUES (?, 'logout', 'User logged out', NOW())";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id); // Bind user ID
    $stmt->execute();
}

// Destroy the session to log out the user
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
