<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/support_ticket_helper.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$row = getSupportTicketAttachment($conn, $id);
if (!$row) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$ticket = getSupportTicket($conn, (int) $row['ticket_id']);
$studentId = (string) $_SESSION['student_id'];
if (!$ticket || !supportTicketCanView($ticket, false, '', $studentId)) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$path = supportTicketAttachmentAbsPath($row);
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    echo 'File missing.';
    exit;
}

$mime = (string) ($row['mime_type'] ?? 'application/octet-stream');
$name = str_replace(['"', "\r", "\n"], '', (string) ($row['original_name'] ?? 'attachment'));
$inline = supportTicketIsImageAttachment($row) || $mime === 'application/pdf';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
