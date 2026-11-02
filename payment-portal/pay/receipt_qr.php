<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$paymentId = (int) ($_GET['id'] ?? 0);
$sig = trim((string) ($_GET['sig'] ?? ''));

if ($paymentId <= 0 || $sig === '') {
    http_response_code(400);
    exit('Bad request.');
}

$payment = hub_payment_find($hub_conn, $paymentId);
if (!$payment || empty($payment['invoice_number'])) {
    http_response_code(404);
    exit('Not found.');
}

$tpl = hub_invoice_template_get($hub_conn);
if (!hub_invoice_verify_public($paymentId, $sig, (string) $tpl['invoice_public_secret'])) {
    http_response_code(403);
    exit('Forbidden.');
}

$targetUrl = hub_invoice_public_url($hub_conn, $paymentId);
$remote = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=8&format=png&data='
    . rawurlencode($targetUrl);

$ctx = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'NIELIT-Payment-Hub/1.0',
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
]);

$png = @file_get_contents($remote, false, $ctx);
if ($png === false || strlen($png) < 64) {
    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    echo '<?xml version="1.0" encoding="UTF-8"?>'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="140" height="140" viewBox="0 0 140 140">'
        . '<rect width="140" height="140" fill="#f8fafc" stroke="#93c5fd"/>'
        . '<text x="70" y="72" text-anchor="middle" font-family="sans-serif" font-size="11" fill="#64748b">QR unavailable</text>'
        . '</svg>';
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
echo $png;
