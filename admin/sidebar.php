<?php
// Get the current page name to set the active link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
    <a href="items.php" class="<?= $current_page == 'items.php' ? 'active' : '' ?>"><i class="fa-solid fa-box"></i> Items</a>
	<a href="item_reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
    <a href="requests.php" class="<?= $current_page == 'requests.php' ? 'active' : '' ?>"><i class="fa-solid fa-file-circle-check"></i> Requests</a>
    <a href="audit_logs.php" class="<?= $current_page == 'audit_logs.php' ? 'active' : '' ?>"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a>
    <a href="tracker.php" class="<?= $current_page == 'tracker.php' ? 'active' : '' ?>"><i class="fa-solid fa-location-dot"></i> Inventory Tracker</a>
    <a href="condemn.php" class="<?= $current_page == 'condemn.php' ? 'active' : '' ?>"><i class="fa-solid fa-ban"></i> Condemn</a>
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