<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/payment_gateway_helper.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$studentCode = (string) $_SESSION['student_id'];
$paymentId = (int) ($_GET['id'] ?? 0);
payment_gateway_ensure_schema($conn);

$stmt = $conn->prepare("SELECT p.*, s.name
    FROM online_course_payments p
    LEFT JOIN students s ON s.student_id = p.student_code
    WHERE p.id = ? AND p.student_code = ? AND p.status = 'paid'
    LIMIT 1");
$row = null;
if ($stmt) {
    $stmt->bind_param('is', $paymentId, $studentCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$row) {
    http_response_code(404);
    echo 'Money receipt not found.';
    exit;
}

require_once __DIR__ . '/../includes/payment_hub_helper.php';
if (($row['gateway'] ?? '') === 'payment_hub' && (int) ($row['hub_payment_id'] ?? 0) > 0) {
    $hubCfg = payment_hub_config();
    $hubPayId = (int) $row['hub_payment_id'];
    $invSecret = trim((string) ($hubCfg['invoice_public_secret'] ?? ''));
    if ($invSecret === '') {
        $dbCfg = __DIR__ . '/../payment-portal/config/database.php';
        if (is_file($dbCfg)) {
            try {
                $hp = @new mysqli('localhost', 'root', '', 'nielit_payments');
                if (!$hp->connect_error) {
                    $s = $hp->query("SELECT setting_value FROM hub_settings WHERE setting_key = 'invoice_public_secret' LIMIT 1");
                    if ($s && ($sr = $s->fetch_assoc())) {
                        $invSecret = trim((string) $sr['setting_value']);
                    }
                    $hp->close();
                }
            } catch (\Throwable $e) {}
        }
    }
    if ($invSecret !== '') {
        $sig = hash_hmac('sha256', 'hub-invoice:' . $hubPayId, $invSecret);
        $url = rtrim((string) $hubCfg['api_base'], '/') . '/pay/receipt.php?id=' . $hubPayId . '&sig=' . rawurlencode($sig);
        header('Location: ' . $url);
        exit;
    }
}

payment_render_invoice_page($row, $studentCode, $conn);
