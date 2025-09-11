<?php
$db = new mysqli('127.0.0.1:3307', 'root', 'misd@rea5', 'inventory_system');
session_start();
if ($db->connect_errno) {
    die('DB connection error: '. $db->connect_error);
}
?>
