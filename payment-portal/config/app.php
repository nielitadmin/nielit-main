<?php

if (!defined('HUB_APP_LOADED')) {
    define('HUB_APP_LOADED', true);
}

define('HUB_APP_NAME', 'NIELIT Bhubaneswar Payment Portal');
define('HUB_INVOICE_PREFIX', 'NIELIT-BBSR');

// Local XAMPP path; production: https://payment.nielitbhubaneswar.in
define('HUB_APP_URL', getenv('HUB_APP_URL') ?: 'http://localhost/public_html/payment-portal');

define('HUB_ENV', getenv('HUB_ENV') ?: 'development');
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
