<?php

function hub_invoice_next_number(mysqli $conn, ?DateTime $when = null): string
{
    $fy = hub_financial_year($when);
    $conn->begin_transaction();
    $stmt = $conn->prepare('SELECT last_number FROM hub_invoice_sequences WHERE financial_year = ? FOR UPDATE');
    if (!$stmt) {
        $conn->rollback();
        throw new RuntimeException('invoice sequence unavailable');
    }
    $stmt->bind_param('s', $fy);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $next = $row ? ((int) $row['last_number'] + 1) : 1;
    if ($row) {
        $up = $conn->prepare('UPDATE hub_invoice_sequences SET last_number = ? WHERE financial_year = ?');
        if ($up) {
            $up->bind_param('is', $next, $fy);
            $up->execute();
            $up->close();
        }
    } else {
        $ins = $conn->prepare('INSERT INTO hub_invoice_sequences (financial_year, last_number) VALUES (?, ?)');
        if ($ins) {
            $ins->bind_param('si', $fy, $next);
            $ins->execute();
            $ins->close();
        }
    }
    $conn->commit();
    return sprintf('%s-%s-%06d', HUB_INVOICE_PREFIX, $fy, $next);
}

function hub_mark_paid_from_capture(mysqli $conn, array $checkout, string $orderId, string $paymentId, ?string $method = null): array
{
    $dup = $conn->prepare("SELECT * FROM hub_payments WHERE checkout_id = ? AND status = 'paid' LIMIT 1");
    if ($dup) {
        $cid = (int) $checkout['id'];
        $dup->bind_param('i', $cid);
        $dup->execute();
        $existingPay = $dup->get_result()->fetch_assoc();
        $dup->close();
        if ($existingPay) {
            hub_checkout_mark($conn, (int) $checkout['id'], 'paid', $orderId);
            return ['ok' => true, 'payment' => $existingPay, 'duplicate' => true];
        }
    }

    if (hub_reference_already_paid($conn, (int) $checkout['source_id'], (string) $checkout['source_reference'])) {
        $stmt = $conn->prepare("SELECT * FROM hub_payments WHERE source_id = ? AND source_reference = ? AND status = 'paid' LIMIT 1");
        if ($stmt) {
            $sid = (int) $checkout['source_id'];
            $ref = (string) $checkout['source_reference'];
            $stmt->bind_param('is', $sid, $ref);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($existing) {
                return ['ok' => true, 'payment' => $existing, 'duplicate' => true];
            }
        }
    }

    $invoiceNo = hub_invoice_next_number($conn);
    $paidAt = gmdate('Y-m-d H:i:s', time() + 19800);
    $fy = hub_financial_year();

    $conn->begin_transaction();
    hub_checkout_mark($conn, (int) $checkout['id'], 'paid', $orderId);

    $stmt = $conn->prepare("INSERT INTO hub_payments (checkout_id, source_id, source_reference, amount_paise, currency, purpose, description, payer_json, context_json, status, razorpay_order_id, razorpay_payment_id, payment_method, invoice_number, paid_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, ?, ?, ?)");
    if (!$stmt) {
        $conn->rollback();
        return ['ok' => false, 'error' => 'payment insert failed'];
    }
    $cid = (int) $checkout['id'];
    $sid = (int) $checkout['source_id'];
    $ref = (string) $checkout['source_reference'];
    $amount = (int) $checkout['amount_paise'];
    $currency = (string) $checkout['currency'];
    $purpose = (string) $checkout['purpose'];
    $description = (string) $checkout['description'];
    $payerJson = (string) $checkout['payer_json'];
    $contextJson = (string) $checkout['context_json'];
    $method = $method ?? '';
    $stmt->bind_param(
        'iisissssssssss',
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
        $paymentId,
        $method,
        $invoiceNo,
        $paidAt
    );
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        return ['ok' => false, 'error' => 'payment insert failed'];
    }
    $paymentIdRow = (int) $stmt->insert_id;
    $stmt->close();

    $inv = $conn->prepare('INSERT INTO hub_invoices (payment_id, invoice_number, financial_year, issued_at) VALUES (?, ?, ?, ?)');
    if ($inv) {
        $inv->bind_param('isss', $paymentIdRow, $invoiceNo, $fy, $paidAt);
        $inv->execute();
        $inv->close();
    }

    $conn->commit();

    $payment = [
        'id' => $paymentIdRow,
        'invoice_number' => $invoiceNo,
        'razorpay_payment_id' => $paymentId,
        'paid_at' => $paidAt,
        'status' => 'paid',
        'refunded_amount_paise' => 0,
    ];

    $checkoutFull = $checkout;
    if (empty($checkoutFull['source_secret'])) {
        $reload = hub_checkout_find_by_token($conn, (string) $checkout['token']);
        if ($reload) {
            $checkoutFull = $reload;
        }
    }
    hub_notify_payment_event($conn, $payment, $checkoutFull, 'paid', [
        'invoice_number' => $invoiceNo,
    ]);

    return ['ok' => true, 'payment' => $payment];
}
