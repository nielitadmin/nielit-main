<?php

function hub_webhook_event_id(array $payload, string $raw): string
{
    if (!empty($payload['id'])) {
        return (string) $payload['id'];
    }
    $header = (string) ($_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] ?? '');
    if ($header !== '') {
        return $header;
    }
    return hash('sha256', $raw);
}

function hub_webhook_store(mysqli $conn, string $eventId, string $eventType, string $raw): bool
{
    $stmt = $conn->prepare('INSERT IGNORE INTO hub_webhook_events (event_id, event_type, payload_json) VALUES (?, ?, ?)');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('sss', $eventId, $eventType, $raw);
    $stmt->execute();
    $inserted = $stmt->affected_rows > 0;
    $stmt->close();
    return $inserted;
}

function hub_webhook_process(mysqli $conn, array $payload): void
{
    $event = (string) ($payload['event'] ?? '');

    if ($event === 'payment.captured') {
        $entity = $payload['payload']['payment']['entity'] ?? null;
        if (!is_array($entity)) {
            return;
        }
        $orderId = (string) ($entity['order_id'] ?? '');
        $paymentId = (string) ($entity['id'] ?? '');
        $method = (string) ($entity['method'] ?? '');
        if ($orderId === '' || $paymentId === '') {
            return;
        }
        $checkout = hub_checkout_by_order_id($conn, $orderId);
        if ($checkout) {
            hub_mark_paid_from_capture($conn, $checkout, $orderId, $paymentId, $method, $entity);
        }
        return;
    }

    if ($event === 'payment.failed') {
        $entity = $payload['payload']['payment']['entity'] ?? null;
        if (!is_array($entity)) {
            return;
        }
        hub_mark_failed_from_gateway($conn, $entity);
        return;
    }

    if ($event === 'refund.processed') {
        $entity = $payload['payload']['refund']['entity'] ?? null;
        if (!is_array($entity)) {
            return;
        }
        hub_refund_from_webhook($conn, $entity);
    }
}

function hub_checkout_by_order_id(mysqli $conn, string $orderId): ?array
{
    $stmt = $conn->prepare('SELECT c.*, s.code AS source_code, s.display_name AS source_name, s.shared_secret AS source_secret
        FROM hub_checkouts c
        INNER JOIN hub_sources s ON s.id = c.source_id
        WHERE c.razorpay_order_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hub_mark_failed_from_gateway(mysqli $conn, array $entity): void
{
    $orderId = (string) ($entity['order_id'] ?? '');
    $paymentId = (string) ($entity['id'] ?? '');
    $errorDesc = (string) ($entity['error_description'] ?? ($entity['error_reason'] ?? 'Payment failed'));

    $checkout = $orderId !== '' ? hub_checkout_by_order_id($conn, $orderId) : null;
    if (!$checkout) {
        return;
    }
    if (in_array((string) ($checkout['status'] ?? ''), ['paid', 'expired'], true)) {
        return;
    }

    hub_checkout_mark($conn, (int) $checkout['id'], 'failed');

    $stmt = $conn->prepare("SELECT id FROM hub_payments WHERE checkout_id = ? AND status = 'failed' LIMIT 1");
    if ($stmt) {
        $cid = (int) $checkout['id'];
        $stmt->bind_param('i', $cid);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($exists) {
            hub_notify_source($conn, $checkout, 'failed', [
                'razorpay_payment_id' => $paymentId,
                'error' => $errorDesc,
            ]);
            return;
        }
    }

    $stmt = $conn->prepare("INSERT INTO hub_payments (checkout_id, source_id, source_reference, amount_paise, currency, purpose, description, payer_json, context_json, status, razorpay_order_id, razorpay_payment_id, payment_method, invoice_number, paid_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'failed', ?, ?, '', NULL, NULL)");
    if ($stmt) {
        $cid = (int) $checkout['id'];
        $sid = (int) $checkout['source_id'];
        $ref = (string) $checkout['source_reference'];
        $amount = (int) $checkout['amount_paise'];
        $currency = (string) $checkout['currency'];
        $purpose = (string) $checkout['purpose'];
        $description = (string) $checkout['description'];
        $payerJson = (string) $checkout['payer_json'];
        $contextJson = (string) $checkout['context_json'];
        $stmt->bind_param(
            'iisisssssss',
            $cid,
            $sid,
            $ref,
            $amount,
            $currency,
            $purpose,
            $description,
            $payerJson,
            $contextJson,
            $orderId,
            $paymentId
        );
        $stmt->execute();
        $stmt->close();
    }

    hub_notify_source($conn, $checkout, 'failed', [
        'razorpay_payment_id' => $paymentId,
        'error' => $errorDesc,
    ]);
}

function hub_checkout_mark_cancelled(mysqli $conn, string $token): void
{
    $checkout = hub_checkout_find_by_token($conn, $token);
    if (!$checkout) {
        return;
    }
    if (in_array((string) ($checkout['status'] ?? ''), ['paid', 'failed', 'expired', 'cancelled'], true)) {
        return;
    }
    hub_checkout_mark($conn, (int) $checkout['id'], 'cancelled');
    hub_notify_source($conn, $checkout, 'cancelled', []);
}
