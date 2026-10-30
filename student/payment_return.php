<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/payment_gateway_helper.php';

$result = payment_finish_return($conn, array_merge($_GET, $_POST));
$target = APP_URL . '/student/fees.php';
if (!empty($result['ok'])) {
    $qs = 'paid=1';
    if (!empty($result['invoice_id'])) {
        $qs .= '&invoice_id=' . (int) $result['invoice_id'];
    }
    header('Location: ' . $target . '?' . $qs);
    exit;
}
header('Location: ' . $target . '?pay_error=' . rawurlencode((string) ($result['error'] ?? 'Payment was not completed.')));
exit;
