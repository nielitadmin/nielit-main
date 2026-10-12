<?php
/**
 * Public Report Monitor — NIELIT Raipur only.
 * Share: https://nielitbhubaneswar.in/public/report_raipur.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/report_monitor_helper.php';

$link = report_monitor_ensure_raipur_public_link($conn, 'public');
if (!$link) {
    http_response_code(503);
    echo 'NIELIT Raipur is not set up as a centre yet, so this exclusive report link cannot be opened.';
    exit;
}

$_GET['share'] = 'raipur';
$_GET['centre_id'] = (string) ((int) ($link['centre_id'] ?? 0));
if (!isset($_GET['quarter']) || trim((string) $_GET['quarter']) === '') {
    $_GET['quarter'] = 'FY';
}

require __DIR__ . '/../admin/report_monitor.php';
