<?php

if (!function_exists('payment_hub_ensure_schema')) {
    function payment_hub_ensure_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done || !($conn instanceof mysqli)) {
            return;
        }
        $done = true;
        $conn->query("CREATE TABLE IF NOT EXISTS payment_hub_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            is_enabled TINYINT(1) NOT NULL DEFAULT 0,
            api_base VARCHAR(255) NOT NULL DEFAULT 'https://payment.nielitbhubaneswar.in',
            source_code VARCHAR(32) NOT NULL DEFAULT 'mis',
            shared_secret VARCHAR(255) NOT NULL DEFAULT '',
            invoice_public_secret VARCHAR(255) NOT NULL DEFAULT '',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->query('INSERT IGNORE INTO payment_hub_settings (id) VALUES (1)');
    }

    function payment_hub_default_api_base(): string
    {
        if (defined('APP_URL') && defined('APP_ENV') && APP_ENV === 'development') {
            $localPortal = dirname(__DIR__) . '/payment-portal/index.php';
            if (is_file($localPortal)) {
                return rtrim((string) APP_URL, '/') . '/payment-portal';
            }
        }
        return 'https://payment.nielitbhubaneswar.in';
    }

    function payment_hub_load_db_row(mysqli $conn): ?array
    {
        payment_hub_ensure_schema($conn);
        $result = $conn->query('SELECT * FROM payment_hub_settings WHERE id = 1 LIMIT 1');
        $row = $result ? $result->fetch_assoc() : null;
        return $row ?: null;
    }

    function payment_hub_save_settings(mysqli $conn, array $input): bool
    {
        payment_hub_ensure_schema($conn);
        $enabled = !empty($input['enabled']) ? 1 : 0;
        $apiBase = rtrim(trim((string) ($input['api_base'] ?? '')), '/');
        if ($apiBase === '') {
            $apiBase = payment_hub_default_api_base();
        }
        $source = strtolower(trim((string) ($input['source'] ?? 'mis')));
        if ($source === '') {
            $source = 'mis';
        }
        $secret = trim((string) ($input['shared_secret'] ?? ''));
        $invoiceSecret = trim((string) ($input['invoice_public_secret'] ?? ''));

        if ($secret === '') {
            $existing = payment_hub_load_db_row($conn);
            $secret = trim((string) ($existing['shared_secret'] ?? ''));
        }
        if ($invoiceSecret === '' && !empty($input['keep_invoice_secret'])) {
            $existing = payment_hub_load_db_row($conn);
            $invoiceSecret = trim((string) ($existing['invoice_public_secret'] ?? ''));
        }

        $stmt = $conn->prepare('UPDATE payment_hub_settings SET is_enabled = ?, api_base = ?, source_code = ?, shared_secret = ?, invoice_public_secret = ? WHERE id = 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('issss', $enabled, $apiBase, $source, $secret, $invoiceSecret);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            $GLOBALS['payment_hub_config_bust'] = true;
        }
        return $ok;
    }

    function payment_hub_public_url(?array $cfg = null): string
    {
        $cfg = $cfg ?? payment_hub_config();
        return rtrim((string) ($cfg['api_base'] ?? payment_hub_default_api_base()), '/');
    }

    function payment_hub_admin_url(string $path = 'admin/dashboard.php'): string
    {
        return payment_hub_public_url() . '/' . ltrim($path, '/');
    }

    function payment_hub_integration_urls(): array
    {
        $base = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
        return [
            'notify_url' => $base . '/api/payment_hub_notify.php',
            'return_url' => $base . '/student/payment_return.php',
            'checkout_api' => payment_hub_checkout_url(payment_hub_public_url()),
        ];
    }

    function payment_hub_test_connection(array $cfg): array
    {
        if (!function_exists('payment_http')) {
            return ['ok' => false, 'error' => 'HTTP client unavailable'];
        }
        $checkoutUrl = payment_hub_checkout_url((string) ($cfg['api_base'] ?? ''));
        $response = payment_http('POST', $checkoutUrl, ['Content-Type: application/json'], '{}');
        $code = (int) ($response['code'] ?? 0);
        if (in_array($code, [400, 401, 403, 422], true)) {
            return ['ok' => true, 'message' => 'Checkout API responded (HTTP ' . $code . '). Portal URL and route look correct.'];
        }
        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'message' => 'Checkout API returned HTTP ' . $code . '.'];
        }
        if ($code === 0) {
            return ['ok' => false, 'error' => 'Could not reach the portal. Check api_base URL and SSL from this server.'];
        }
        return ['ok' => false, 'error' => 'Unexpected response HTTP ' . $code . ' from checkout API.'];
    }

    function payment_hub_import_file_config(mysqli $conn): void
    {
        $row = payment_hub_load_db_row($conn);
        if (!$row || trim((string) ($row['shared_secret'] ?? '')) !== '') {
            return;
        }
        $path = __DIR__ . '/../config/payment_hub.php';
        if (!is_file($path)) {
            return;
        }
        $file = require $path;
        if (!is_array($file) || trim((string) ($file['shared_secret'] ?? '')) === '') {
            return;
        }
        payment_hub_save_settings($conn, [
            'enabled' => !empty($file['enabled']),
            'api_base' => (string) ($file['api_base'] ?? payment_hub_default_api_base()),
            'source' => (string) ($file['source'] ?? 'mis'),
            'shared_secret' => (string) $file['shared_secret'],
            'invoice_public_secret' => (string) ($file['invoice_public_secret'] ?? ''),
        ]);
    }
}

if (!function_exists('payment_hub_config')) {
    function payment_hub_config(): array
    {
        static $cached = null;
        if (!empty($GLOBALS['payment_hub_config_bust'])) {
            $cached = null;
            unset($GLOBALS['payment_hub_config_bust']);
        }
        if ($cached !== null) {
            return $cached;
        }
        $defaults = [
            'enabled' => false,
            'api_base' => payment_hub_default_api_base(),
            'source' => 'mis',
            'shared_secret' => '',
            'invoice_public_secret' => '',
        ];

        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            payment_hub_import_file_config($conn);
            $row = payment_hub_load_db_row($conn);
            if ($row) {
                $cached = [
                    'enabled' => (int) ($row['is_enabled'] ?? 0) === 1,
                    'api_base' => rtrim((string) ($row['api_base'] ?: $defaults['api_base']), '/'),
                    'source' => (string) ($row['source_code'] ?? 'mis'),
                    'shared_secret' => (string) ($row['shared_secret'] ?? ''),
                    'invoice_public_secret' => (string) ($row['invoice_public_secret'] ?? ''),
                ];
                return $cached;
            }
        }

        $path = __DIR__ . '/../config/payment_hub.php';
        if (is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                $cached = array_merge($defaults, $loaded);
                if (!empty($cached['api_base'])) {
                    $cached['api_base'] = rtrim((string) $cached['api_base'], '/');
                }
                return $cached;
            }
        }
        $cached = $defaults;
        return $cached;
    }

    function payment_hub_is_ready(): bool
    {
        $cfg = payment_hub_config();
        return !empty($cfg['enabled'])
            && trim((string) $cfg['shared_secret']) !== ''
            && trim((string) $cfg['api_base']) !== '';
    }

    function payment_hub_sign(string $body, string $secret): string
    {
        return hash_hmac('sha256', $body, $secret);
    }

    function payment_hub_verify_notify(string $rawBody, string $secret, string $headerSig): bool
    {
        if ($headerSig === '') {
            return false;
        }
        return hash_equals(payment_hub_sign($rawBody, $secret), $headerSig);
    }

    function payment_hub_reference(int $localPaymentId): string
    {
        return 'MIS-FEE-' . $localPaymentId;
    }

    function payment_hub_reference_to_local_id(string $reference): ?int
    {
        if (preg_match('/^MIS-FEE-(\d+)$/', $reference, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    function payment_hub_checkout_url(string $apiBase): string
    {
        $base = rtrim($apiBase, '/');
        if (is_file(__DIR__ . '/../payment-portal/api/v1/checkout.php')) {
            // Local dev: same repo layout
        }
        return $base . '/api/v1/checkout.php';
    }

    function payment_hub_status_url(string $apiBase, string $token): string
    {
        return rtrim($apiBase, '/') . '/api/v1/payment_status.php?token=' . rawurlencode($token);
    }

    function payment_hub_http_post_json(string $url, array $payload, string $secret): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sig = payment_hub_sign($body, $secret);
        if (!function_exists('payment_http')) {
            return ['code' => 0, 'body' => []];
        }
        return payment_http('POST', $url, [
            'Content-Type: application/json',
            'X-Payment-Signature: ' . $sig,
        ], $body);
    }

    function payment_hub_http_get_signed(string $url, string $secret): array
    {
        $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');
        $sig = payment_hub_sign($query, $secret);
        if (!function_exists('payment_http')) {
            return ['code' => 0, 'body' => []];
        }
        return payment_http('GET', $url, [
            'X-Payment-Signature: ' . $sig,
        ], null);
    }

    function payment_hub_start_checkout(
        mysqli $conn,
        string $studentCode,
        int $recordId,
        int $courseId,
        string $studentName,
        string $email,
        string $phone
    ): array {
        $cfg = payment_hub_config();
        $offer = payment_course_offer($conn, $courseId);
        if (!$offer['enabled']) {
            return ['ok' => false, 'error' => 'This course does not collect fees online.'];
        }
        $paid = payment_student_paid_amount($conn, $studentCode, $courseId);
        $due = round($offer['amount'] - $paid, 2);
        if ($due <= 0) {
            return ['ok' => false, 'error' => 'This course fee is already paid.'];
        }

        $courseName = (string) $offer['course_name'];
        $attemptId = payment_insert_attempt($conn, $studentCode, $recordId, $courseId, $courseName, $due, 'payment_hub', 'pending');
        if ($attemptId <= 0) {
            return ['ok' => false, 'error' => 'Could not save the payment order.'];
        }
        $reference = payment_hub_reference($attemptId);
        $paise = (int) round($due * 100);
        $base = payment_public_base();
        $payload = [
            'source' => (string) $cfg['source'],
            'reference' => $reference,
            'amount' => $paise,
            'currency' => 'INR',
            'purpose' => 'Course fee',
            'description' => $courseName,
            'payer' => [
                'name' => $studentName !== '' ? $studentName : 'Student',
                'email' => $email !== '' ? $email : 'student@nielitbhubaneswar.in',
                'phone' => preg_replace('/\D/', '', $phone),
                'external_id' => $studentCode,
            ],
            'context' => [
                'centre' => defined('STUDENT_ID_CENTRE_CODE') ? 'Bhubaneswar' : '',
                'course_id' => $courseId,
                'scheme' => '',
            ],
            'return_url' => $base . '/student/payment_return.php',
            'notify_url' => $base . '/api/payment_hub_notify.php',
        ];

        $response = payment_hub_http_post_json(
            payment_hub_checkout_url((string) $cfg['api_base']),
            $payload,
            (string) $cfg['shared_secret']
        );
        $payUrl = (string) ($response['body']['pay_url'] ?? '');
        $token = (string) ($response['body']['checkout_token'] ?? '');
        if ($payUrl === '' || $response['code'] < 200 || $response['code'] >= 300) {
            $message = (string) ($response['body']['error'] ?? 'Could not start payment on the central portal.');
            return ['ok' => false, 'error' => $message];
        }

        $update = $conn->prepare('UPDATE online_course_payments SET gateway_order_id = ? WHERE id = ?');
        if ($update) {
            $update->bind_param('si', $token, $attemptId);
            $update->execute();
            $update->close();
        }

        return ['ok' => true, 'mode' => 'redirect', 'url' => $payUrl];
    }

    function payment_hub_apply_paid_row(mysqli $conn, array $row, array $payload): array
    {
        if (($row['status'] ?? '') === 'paid') {
            return ['ok' => true, 'already' => true, 'invoice_id' => (int) $row['id']];
        }
        $hubPaymentId = (int) ($payload['payment_id'] ?? 0);
        $invoice = trim((string) ($payload['invoice_number'] ?? ''));
        $rzp = trim((string) ($payload['razorpay_payment_id'] ?? ''));
        $invoiceVal = $invoice !== '' ? $invoice : null;
        $update = $conn->prepare("UPDATE online_course_payments
            SET status = 'paid', paid_at = NOW(), gateway_payment_id = ?, invoice_number = COALESCE(?, invoice_number),
                hub_payment_id = ?, hub_invoice_number = ?
            WHERE id = ? AND status <> 'paid'");
        if (!$update) {
            return ['ok' => false, 'error' => 'Could not update local payment.'];
        }
        $id = (int) $row['id'];
        $hubInv = $invoiceVal ?? '';
        $update->bind_param('ssisi', $rzp, $invoiceVal, $hubPaymentId, $hubInv, $id);
        $update->execute();
        $update->close();

        payment_mirror_legacy_payment($conn, (string) $row['student_code'], (float) $row['amount'], $rzp !== '' ? $rzp : ('hub-' . $hubPaymentId));

        return ['ok' => true, 'invoice_id' => $id];
    }

    function payment_hub_finish_return(mysqli $conn, array $request): array
    {
        $cfg = payment_hub_config();
        if (!payment_hub_is_ready()) {
            return ['ok' => false, 'error' => 'Central payment portal is not configured.'];
        }
        $token = trim((string) ($request['hub_token'] ?? ''));
        if ($token === '') {
            return ['ok' => false, 'error' => 'Missing payment reference.'];
        }

        $stmt = $conn->prepare("SELECT * FROM online_course_payments WHERE gateway_order_id = ? AND gateway = 'payment_hub' LIMIT 1");
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Payment order was not found.'];
        }
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return ['ok' => false, 'error' => 'Payment order was not found.'];
        }
        if (($row['status'] ?? '') === 'paid') {
            return ['ok' => true, 'invoice_id' => (int) $row['id'], 'already' => true];
        }

        $statusUrl = payment_hub_status_url((string) $cfg['api_base'], $token);
        $response = payment_http('GET', $statusUrl, [], null);
        $status = $response['body']['status'] ?? null;
        if (!is_array($status)) {
            return ['ok' => false, 'error' => 'Could not confirm payment with the portal.'];
        }
        $paymentStatus = (string) ($status['payment_status'] ?? '');
        if ($paymentStatus !== 'paid') {
            $hubStatus = (string) ($request['hub_status'] ?? '');
            if ($hubStatus === 'failed') {
                return ['ok' => false, 'error' => 'Payment failed or was cancelled.'];
            }
            return ['ok' => false, 'error' => 'Payment is still processing. Refresh this page in a moment.'];
        }

        return payment_hub_apply_paid_row($conn, $row, [
            'payment_id' => (int) ($status['hub_payment_id'] ?? 0),
            'invoice_number' => (string) ($status['invoice_number'] ?? $request['hub_invoice'] ?? ''),
            'razorpay_payment_id' => (string) ($status['razorpay_payment_id'] ?? ''),
        ]);
    }
}
