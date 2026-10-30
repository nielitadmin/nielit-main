<?php

if (!defined('HUB_APP_LOADED')) {
    define('HUB_APP_LOADED', true);
}

define('HUB_APP_NAME', 'NIELIT Bhubaneswar Payment Portal');
define('HUB_BRAND_SHORT', 'NIELIT Payment Hub');
define('HUB_TAGLINE', 'Secure online fees for NIELIT Bhubaneswar—student MIS, mock tests, and partner portals checkout here with one gateway, money receipts, and a full refund record.');
define('HUB_TAGLINE_AUTH', 'Staff-only control centre for Razorpay, money receipt numbering, and refunds across every connected portal.');
define('HUB_INVOICE_PREFIX', 'NIELIT-BBSR');

$hubHttpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$hubIsProduction = (strpos($hubHttpHost, 'payment.nielitbhubaneswar.in') !== false);

if (getenv('HUB_APP_URL')) {
    define('HUB_APP_URL', rtrim((string) getenv('HUB_APP_URL'), '/'));
} elseif ($hubIsProduction) {
    define('HUB_APP_URL', 'https://payment.nielitbhubaneswar.in');
} else {
    define('HUB_APP_URL', 'http://localhost/public_html/payment-portal');
}

if (getenv('HUB_ENV')) {
    define('HUB_ENV', (string) getenv('HUB_ENV'));
} else {
    define('HUB_ENV', $hubIsProduction ? 'production' : 'development');
}

define('HUB_CHECKOUT_TTL_MINUTES', 30);

if (HUB_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

function hub_url(string $path = ''): string
{
    $base = rtrim(HUB_APP_URL, '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}
