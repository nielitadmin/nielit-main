<?php
/**
 * AJAX: save public Report Monitor header/footer branding for a centre (Raipur).
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
    echo json_encode(['success' => false, 'message' => 'Only Master Admin can edit public header and footer.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$centreId = (int) ($_POST['centre_id'] ?? 0);
if ($centreId <= 0) {
    $raipur = report_monitor_ensure_raipur_public_link($conn, (string) ($_SESSION['admin'] ?? 'admin'));
    $centreId = (int) ($raipur['centre_id'] ?? 0);
}

$input = [];
foreach (report_monitor_public_branding_field_keys() as $key) {
    $input[$key] = (string) ($_POST[$key] ?? '');
}

$result = report_monitor_save_public_centre_branding(
    $conn,
    $centreId,
    $input,
    (string) ($_SESSION['admin'] ?? 'admin')
);

if (empty($result['success'])) {
    http_response_code(400);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
