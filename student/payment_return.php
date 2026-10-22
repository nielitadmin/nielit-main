<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/payment_gateway_helper.php';

$result = payment_finish_return($conn, array_merge($_GET, $_POST));
$target = APP_URL . '/student/fees.php';
if (!empty($result['ok'])) {
    header('Location: ' . $target . '?paid=1');
    exit;
}
header('Location: ' . $target . '?pay_error=' . rawurlencode((string) ($result['error'] ?? 'Payment was not completed.')));
exit;
