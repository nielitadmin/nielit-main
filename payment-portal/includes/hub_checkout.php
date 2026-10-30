<?php

function hub_checkout_token_new(): string
{
    return bin2hex(random_bytes(32));
}

function hub_checkout_find_by_token(mysqli $conn, string $token): ?array
{
    $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
    if (strlen($token) !== 64) {
        return null;
    }
    $stmt = $conn->prepare('SELECT c.*, s.code AS source_code, s.display_name AS source_name, s.shared_secret AS source_secret
        FROM hub_checkouts c
        INNER JOIN hub_sources s ON s.id = c.source_id
        WHERE c.token = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hub_reference_already_paid(mysqli $conn, int $sourceId, string $reference): bool
{
    $stmt = $conn->prepare("SELECT id FROM hub_payments WHERE source_id = ? AND source_reference = ? AND status IN ('paid', 'partial_refund') LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $sourceId, $reference);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool) $row;
}

function hub_checkout_create_from_request(mysqli $conn, array $source, array $payload): array
{
    $reference = trim((string) ($payload['reference'] ?? ''));
    $amount = (int) ($payload['amount'] ?? 0);
    $currency = strtoupper(trim((string) ($payload['currency'] ?? 'INR')));
    $returnUrl = trim((string) ($payload['return_url'] ?? ''));
    $notifyUrl = trim((string) ($payload['notify_url'] ?? ''));

    if ($reference === '') {
        return ['ok' => false, 'error' => 'reference is required'];
    }
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'amount must be a positive integer (paise)'];
    }
    if ($currency !== 'INR') {
        return ['ok' => false, 'error' => 'only INR is supported in phase 1'];
    }
    if ($returnUrl === '' || !hub_source_return_allowed($source, $returnUrl)) {
        return ['ok' => false, 'error' => 'return_url is not allowed for this source'];
    }
    if (hub_reference_already_paid($conn, (int) $source['id'], $reference)) {
        return ['ok' => false, 'error' => 'this reference is already paid'];
    }

    $payer = $payload['payer'] ?? [];
    if (!is_array($payer)) {
        $payer = [];
    }
    $context = $payload['context'] ?? [];
    if (!is_array($context)) {
        $context = [];
    }

    $token = hub_checkout_token_new();
    $ttl = (int) HUB_CHECKOUT_TTL_MINUTES;
    $expires = gmdate('Y-m-d H:i:s', time() + ($ttl * 60));

    $purpose = trim((string) ($payload['purpose'] ?? ''));
    $description = trim((string) ($payload['description'] ?? ''));
    $payerJson = json_encode($payer, JSON_UNESCAPED_UNICODE);
    $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);

    $stmt = $conn->prepare('INSERT INTO hub_checkouts (token, source_id, source_reference, amount_paise, currency, purpose, description, payer_json, context_json, return_url, notify_url, status, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'created\', ?)');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'could not create checkout'];
    }
    $sourceId = (int) $source['id'];
    $stmt->bind_param('sisissssssss', $token, $sourceId, $reference, $amount, $currency, $purpose, $description, $payerJson, $contextJson, $returnUrl, $notifyUrl, $expires);
    $ok = $stmt->execute();
    $checkoutId = $ok ? (int) $stmt->insert_id : 0;
    $stmt->close();

    if (!$ok || $checkoutId <= 0) {
        return ['ok' => false, 'error' => 'checkout insert failed'];
    }

    return [
        'ok' => true,
        'checkout_id' => $checkoutId,
        'token' => $token,
        'pay_url' => hub_url('pay/?token=' . $token),
        'expires_at' => $expires,
    ];
}

function hub_checkout_mark(mysqli $conn, int $checkoutId, string $status, ?string $razorpayOrderId = null): void
{
    if ($razorpayOrderId !== null) {
        $stmt = $conn->prepare('UPDATE hub_checkouts SET status = ?, razorpay_order_id = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('ssi', $status, $razorpayOrderId, $checkoutId);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }
    $stmt = $conn->prepare('UPDATE hub_checkouts SET status = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('si', $status, $checkoutId);
        $stmt->execute();
        $stmt->close();
    }
}

function hub_checkout_is_expired(array $checkout): bool
{
    if (($checkout['status'] ?? '') === 'expired') {
        return true;
    }
    $exp = strtotime((string) ($checkout['expires_at'] ?? ''));
    return $exp > 0 && $exp < time();
}

function hub_payment_status_payload(mysqli $conn, array $checkout): array
{
    $paid = null;
    $stmt = $conn->prepare('SELECT * FROM hub_payments WHERE checkout_id = ? ORDER BY id DESC LIMIT 1');
    if ($stmt) {
        $cid = (int) $checkout['id'];
        $stmt->bind_param('i', $cid);
        $stmt->execute();
        $paid = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    $creditNotes = [];
    $refunds = [];
    if ($paid) {
        $creditNotes = hub_credit_notes_for_payment($conn, (int) $paid['id']);
        $refunds = hub_refunds_for_payment($conn, (int) $paid['id']);
    }
    if (hub_checkout_is_expired($checkout) && !in_array((string) ($checkout['status'] ?? ''), ['paid', 'expired'], true)) {
        hub_checkout_mark($conn, (int) $checkout['id'], 'expired');
        $checkout['status'] = 'expired';
    }
    return [
        'token' => $checkout['token'],
        'source' => $checkout['source_code'] ?? '',
        'reference' => $checkout['source_reference'],
        'checkout_status' => $checkout['status'],
        'checkout_status_label' => hub_checkout_status_label((string) $checkout['status']),
        'amount' => (int) $checkout['amount_paise'],
        'currency' => $checkout['currency'],
        'payment_status' => $paid['status'] ?? null,
        'payment_status_label' => $paid ? hub_payment_status_label((string) $paid['status']) : null,
        'hub_payment_id' => $paid ? (int) ($paid['id'] ?? 0) : null,
        'invoice_number' => $paid['invoice_number'] ?? null,
        'razorpay_payment_id' => $paid['razorpay_payment_id'] ?? null,
        'razorpay_order_id' => $checkout['razorpay_order_id'] ?? null,
        'paid_at' => $paid['paid_at'] ?? null,
        'refunded_amount' => $paid ? (int) ($paid['refunded_amount_paise'] ?? 0) : 0,
        'refundable_amount' => $paid ? hub_payment_refundable_paise($paid) : 0,
        'credit_notes' => array_map(static function ($cn) {
            return [
                'number' => $cn['credit_note_number'],
                'amount' => (int) $cn['amount_paise'],
                'issued_at' => $cn['issued_at'],
            ];
        }, $creditNotes),
        'refunds' => array_map(static function ($r) {
            return [
                'razorpay_refund_id' => $r['razorpay_refund_id'],
                'amount' => (int) $r['amount_paise'],
                'status' => $r['status'],
                'credit_note_number' => $r['credit_note_number'],
            ];
        }, $refunds),
    ];
}
