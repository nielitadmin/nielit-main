<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/payment_gateway_helper.php';
require_once __DIR__ . '/../includes/payment_hub_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$paymentId = (int) ($_GET['id'] ?? 0);
payment_gateway_ensure_schema($conn);

$stmt = $conn->prepare("SELECT p.*, s.name
    FROM online_course_payments p
    LEFT JOIN students s ON s.student_id = p.student_code
    WHERE p.id = ? AND p.status = 'paid'
    LIMIT 1");
$row = null;
if ($stmt) {
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$row) {
    http_response_code(404);
    echo 'Invoice not found.';
    exit;
}

$receiptUrl = payment_hub_receipt_url_for_row($conn, $row);
header('Location: ' . $receiptUrl);
exit;