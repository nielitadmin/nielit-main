<?php
/**
 * Maintenance Mode Check
 * Include this file at the top of public pages to redirect to maintenance page when enabled
 */

// Don't check maintenance mode for admin pages or maintenance page itself
$current_file = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Skip check for admin pages and maintenance page
if ($current_dir === 'admin' || $current_file === 'maintenance.php') {
    return;
}

// Skip check if user is logged in as admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    return;
}

// Check if maintenance mode is enabled
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/url_helper.php';

try {
    $maintenance_query = $conn->query("SELECT is_enabled FROM maintenance_mode WHERE id = 1");
    
    if ($maintenance_query) {
        $maintenance = $maintenance_query->fetch_assoc();
        
        if ($maintenance && $maintenance['is_enabled'] == 1) {
            header("Location: " . app_url('maintenance'));
            exit();
        }
    }
} catch (Exception $e) {
    // If table doesn't exist, continue normally
    return;
}
?>
