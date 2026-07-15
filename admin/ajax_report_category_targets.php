<?php
/**
 * AJAX endpoint: save Report Monitor category admission targets.
 */

ob_start();
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/report_monitor_helper.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only Master Admin can manage targets.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$action = $_POST['action'] ?? '';
if ($action !== 'save_category_targets') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

$fyStartYear = isset($_POST['financial_year_start']) ? (int) $_POST['financial_year_start'] : 0;
$targetScope = (string) ($_POST['target_scope'] ?? 'nielit');
$centreId = isset($_POST['centre_id']) ? (int) $_POST['centre_id'] : 0;
if ($targetScope === 'training_partner') {
    $centreId = report_monitor_tp_target_centre_id();
}

if ($fyStartYear < 2020 || $fyStartYear > 2100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid financial year.']);
    exit;
}

$targetsInput = [];
foreach (report_monitor_get_category_target_keys() as $targetKey) {
    $targetsInput[$targetKey] = isset($_POST['targets'][$targetKey])
        ? max(0, (int) $_POST['targets'][$targetKey])
        : 0;
}

$adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;

$result = report_monitor_save_category_targets(
    $conn,
    $fyStartYear,
    $centreId,
    $targetsInput,
    $adminId > 0 ? $adminId : null
);

if (empty($result['success'])) {
    http_response_code(500);
} else {
    $flashMessage = $targetScope === 'training_partner'
        ? 'Training partner category targets saved successfully.'
        : ($result['message'] ?? 'Category admission targets saved successfully.');
    $_SESSION['report_targets_flash'] = $flashMessage;
    $_SESSION['report_targets_flash_type'] = 'success';
}

echo json_encode([
    'success' => !empty($result['success']),
    'message' => $result['message'] ?? 'Could not save targets.',
]);
exit;
