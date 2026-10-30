<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$filters = hub_payments_filters_from_request($_GET);
hub_payments_export_csv($hub_conn, $filters);
exit;
