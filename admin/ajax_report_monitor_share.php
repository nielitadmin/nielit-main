<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/report_monitor_helper.php';

if (!isset($_SESSION['admin']) || ($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$year = (int) ($_GET['year'] ?? $_POST['year'] ?? 0);
$quarter = trim((string) ($_GET['quarter'] ?? $_POST['quarter'] ?? 'FY'));
$centreId = (int) ($_GET['centre_id'] ?? $_POST['centre_id'] ?? 0);

if (!in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4', 'FY'], true)) {
    $quarter = 'FY';
}

$token = report_monitor_regenerate_public_token($conn, (string) ($_SESSION['admin'] ?? 'admin'));
$url = report_monitor_build_public_url($token, $year, $quarter, $centreId);

echo json_encode([
    'success' => true,
    'token' => $token,
    'url' => $url,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
