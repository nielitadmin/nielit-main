<?php
/**
 * Signed callbacks from payment.nielitbhubaneswar.in when a checkout is paid, failed, or refunded.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/payment_gateway_helper.php';
require_once __DIR__ . '/../includes/payment_hub_helper.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$cfg = payment_hub_config();
if (!payment_hub_is_ready()) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Hub integration disabled']);
    exit;
}

$raw = (string) file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid JSON']);
    exit;
}

$sig = trim((string) ($_SERVER['HTTP_X_PAYMENT_SIGNATURE'] ?? $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? ''));
if (!payment_hub_verify_notify($raw, (string) $cfg['shared_secret'], $sig)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'invalid signature']);
    exit;
}

$event = strtolower(trim((string) ($data['event'] ?? $data['status'] ?? '')));
$reference = trim((string) ($data['reference'] ?? ''));
$localId = payment_hub_reference_to_local_id($reference);
if ($localId === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'unknown reference']);
    exit;
}

payment_gateway_ensure_schema($conn);
$stmt = $conn->prepare('SELECT * FROM online_course_payments WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'lookup failed']);
    exit;
}
$stmt->bind_param('i', $localId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'payment not found']);
    exit;
}

if ($event === 'paid') {
    $result = payment_hub_apply_paid_row($conn, $row, $data);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result);
    exit;
}

if ($event === 'failed' || $event === 'cancelled') {
    if (($row['status'] ?? '') !== 'paid') {
        $up = $conn->prepare("UPDATE online_course_payments SET status = 'failed' WHERE id = ? AND status = 'created'");
        if ($up) {
            $up->bind_param('i', $localId);
            $up->execute();
            $up->close();
        }
    }
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => true, 'ignored' => $event]);
