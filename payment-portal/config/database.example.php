<?php

if (!defined('HUB_DB_LOADED')) {
    define('HUB_DB_LOADED', true);
}

define('HUB_DB_HOST', 'localhost');
define('HUB_DB_USER', 'root');
define('HUB_DB_PASS', '');
define('HUB_DB_NAME', 'nielit_payments');
define('HUB_DB_CHARSET', 'utf8mb4');

$hub_conn = new mysqli(HUB_DB_HOST, HUB_DB_USER, HUB_DB_PASS, HUB_DB_NAME);
if ($hub_conn->connect_error) {
    error_log('Payment hub DB: ' . $hub_conn->connect_error);
    http_response_code(500);
    exit('Payment portal database is not available.');
}
$hub_conn->set_charset(HUB_DB_CHARSET);
$hub_conn->query("SET time_zone = '+05:30'");
