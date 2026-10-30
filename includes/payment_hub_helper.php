<?php

if (!function_exists('payment_hub_config')) {
    function payment_hub_config(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $defaults = [
            'enabled' => false,
            'api_base' => 'https://payment.nielitbhubaneswar.in',
            'source' => 'mis',
            'shared_secret' => '',
            'invoice_public_secret' => '',
        ];
        $path = __DIR__ . '/../config/payment_hub.php';
        if (!is_file($path)) {
            $cached = $defaults;
            return $cached;
        }
        $loaded = require $path;
        $cached = is_array($loaded) ? array_merge($defaults, $loaded) : $defaults;
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
        if ($invoice === '') {
            $invoice = null;
        }
        $update = $conn->prepare("UPDATE online_course_payments
            SET status = 'paid', paid_at = NOW(), gateway_payment_id = ?, invoice_number = COALESCE(?, invoice_number),
                hub_payment_id = ?, hub_invoice_number = ?
            WHERE id = ? AND status <> 'paid'");
        if (!$update) {
            return ['ok' => false, 'error' => 'Could not update local payment.'];
        }
        $id = (int) $row['id'];
        $update->bind_param('ssisi', $rzp, $invoice, $hubPaymentId, $invoice, $id);
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
        $response = payment_hub_http_get_signed($statusUrl, (string) $cfg['shared_secret']);
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
