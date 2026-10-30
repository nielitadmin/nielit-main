<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
$checkout = hub_checkout_find_by_token($hub_conn, $token);
if (!$checkout) {
    http_response_code(404);
    exit('Payment link not found.');
}

$orderId = trim((string) ($_POST['razorpay_order_id'] ?? ''));
$paymentId = trim((string) ($_POST['razorpay_payment_id'] ?? ''));
$signature = trim((string) ($_POST['razorpay_signature'] ?? ''));

$returnBase = (string) $checkout['return_url'];
$glue = strpos($returnBase, '?') !== false ? '&' : '?';

if (!hub_razorpay_verify_payment_signature($hub_conn, $orderId, $paymentId, $signature)) {
    header('Location: ' . $returnBase . $glue . 'hub_status=failed&hub_token=' . urlencode($token));
    exit;
}

$paid = hub_mark_paid_from_capture($hub_conn, $checkout, $orderId, $paymentId, null);
$invoice = $paid['payment']['invoice_number'] ?? '';

header('Location: ' . $returnBase . $glue . 'hub_status=paid&hub_token=' . urlencode($token) . '&hub_invoice=' . urlencode($invoice));
exit;
