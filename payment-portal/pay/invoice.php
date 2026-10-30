<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$paymentId = (int) ($_GET['id'] ?? 0);
$sig = trim((string) ($_GET['sig'] ?? ''));
$adminOk = false;

if ($paymentId <= 0) {
    http_response_code(404);
    exit('Money receipt not found.');
}

hub_admin_session_start();
if (!empty($_SESSION['hub_admin_id'])) {
    $adminOk = true;
}

$payment = hub_payment_find($hub_conn, $paymentId);
if (!$payment || empty($payment['invoice_number'])) {
    http_response_code(404);
    exit('Money receipt not found.');
}

if (!in_array((string) ($payment['status'] ?? ''), ['paid', 'partial_refund', 'refunded'], true)) {
    http_response_code(404);
    exit('Money receipt is available only for completed payments.');
}

if (!$adminOk) {
    $tpl = hub_invoice_template_get($hub_conn);
    if (!hub_invoice_verify_public($paymentId, $sig, (string) $tpl['invoice_public_secret'])) {
        http_response_code(403);
        exit('Invalid or missing receipt link.');
    }
}

hub_invoice_render_html($hub_conn, $payment, true);
