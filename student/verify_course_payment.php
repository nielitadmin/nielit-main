<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/payment_gateway_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in to the student portal.']);
    exit;
}

$result = payment_verify_razorpay(
    $conn,
    (string) $_SESSION['student_id'],
    trim((string) ($_POST['razorpay_order_id'] ?? '')),
    trim((string) ($_POST['razorpay_payment_id'] ?? '')),
    trim((string) ($_POST['razorpay_signature'] ?? ''))
);
if (empty($result['ok'])) {
    http_response_code(422);
}
echo json_encode($result);
