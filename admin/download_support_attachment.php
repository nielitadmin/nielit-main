<?php
/**
 * Stream a support-ticket attachment after access checks.
 */
require_once __DIR__ . '/../includes/url_helper.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/support_ticket_helper.php';

$isMaster = admin_can_manage_support_tickets();
$adminUser = (string) ($_SESSION['admin'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
$row = getSupportTicketAttachment($conn, $id);
if (!$row) {
    http_response_code(404);
    echo 'File not found.';
    exit();
}

$ticket = getSupportTicket($conn, (int) $row['ticket_id']);
if (!$ticket || !supportTicketCanView($ticket, $isMaster, $adminUser)) {
    http_response_code(403);
    echo 'Access denied.';
    exit();
}

$path = supportTicketAttachmentAbsPath($row);
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    echo 'File missing.';
    exit();
}

$mime = (string) ($row['mime_type'] ?? 'application/octet-stream');
$name = str_replace(['"', "\r", "\n"], '', (string) ($row['original_name'] ?? 'attachment'));
$inline = supportTicketIsImageAttachment($row) || $mime === 'application/pdf';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit();
