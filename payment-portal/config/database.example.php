<?php

if (!defined('HUB_DB_LOADED')) {
    define('HUB_DB_LOADED', true);
}

/**
 * Production (Hostinger / cPanel) — copy to config/database.php on the server.
 * Do not commit database.php (listed in .gitignore).
 */
// define('HUB_DB_HOST', 'localhost');
// define('HUB_DB_USER', 'u664913565_payment');
// define('HUB_DB_PASS', 'your-db-password');
// define('HUB_DB_NAME', 'u664913565_payment');

/** Local XAMPP */
define('HUB_DB_HOST', 'localhost');
define('HUB_DB_USER', 'root');
define('HUB_DB_PASS', '');
define('HUB_DB_NAME', 'nielit_payments');

define('HUB_DB_CHARSET', 'utf8mb4');

$hub_conn = new mysqli(HUB_DB_HOST, HUB_DB_USER, HUB_DB_PASS, HUB_DB_NAME);
if ($hub_conn->connect_error) {
    error_log('Payment hub DB: ' . $hub_conn->connect_error);
    http_response_code(500);
    exit('Payment portal database is not available. Create the MySQL database and config/database.php — see payment-portal/README.md.');
}
$hub_conn->set_charset(HUB_DB_CHARSET);
$hub_conn->query("SET time_zone = '+05:30'");
