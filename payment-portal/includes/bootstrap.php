<?php

require_once __DIR__ . '/../config/app.php';

$hubSecretsPath = __DIR__ . '/../config/secrets.php';
if (!is_file($hubSecretsPath)) {
    $hubSecretsPath = __DIR__ . '/../config/secrets.example.php';
}
$hub_secrets = require $hubSecretsPath;

$dbPath = __DIR__ . '/../config/database.php';
if (!is_file($dbPath)) {
    $dbPath = __DIR__ . '/../config/database.example.php';
}
require_once $dbPath;

require_once __DIR__ . '/hub_schema.php';
require_once __DIR__ . '/hub_signature.php';
require_once __DIR__ . '/hub_financial_year.php';
require_once __DIR__ . '/hub_source.php';
require_once __DIR__ . '/hub_settings.php';
require_once __DIR__ . '/hub_checkout.php';
require_once __DIR__ . '/hub_razorpay.php';
require_once __DIR__ . '/hub_outcomes.php';
require_once __DIR__ . '/hub_payment.php';
require_once __DIR__ . '/hub_notify.php';
require_once __DIR__ . '/hub_invoice.php';
require_once __DIR__ . '/hub_invoice_layout.php';
require_once __DIR__ . '/hub_invoice_template.php';
require_once __DIR__ . '/hub_refund.php';
require_once __DIR__ . '/hub_webhook_handlers.php';
require_once __DIR__ . '/hub_admin_auth.php';
require_once __DIR__ . '/hub_dashboard_stats.php';
require_once __DIR__ . '/hub_payment_register.php';
require_once __DIR__ . '/hub_legacy_import.php';
require_once __DIR__ . '/hub_layout.php';
require_once __DIR__ . '/hub_pay_ui.php';

hub_schema_ensure($hub_conn);

function hub_json_response(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function hub_read_raw_body(): string
{
    return (string) file_get_contents('php://input');
}
