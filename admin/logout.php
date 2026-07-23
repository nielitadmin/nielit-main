<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/activity_logger.php';

$adminName = $_SESSION['admin'] ?? null;
$adminId = isset($_SESSION['admin_id']) ? (string) $_SESSION['admin_id'] : null;
$adminRole = $_SESSION['admin_role'] ?? null;

if ($adminName || $adminId) {
    logActivity($conn, [
        'actor_type' => 'admin',
        'actor_id' => $adminId,
        'actor_name' => $adminName,
        'actor_role' => $adminRole,
        'action' => 'admin_logout',
        'entity_type' => 'admin',
        'entity_id' => $adminId,
        'entity_name' => $adminName,
        'description' => 'Admin "' . ($adminName ?: $adminId) . '" logged out.',
    ]);
}

session_unset();
session_destroy();

header('Location: login.php');
exit();
