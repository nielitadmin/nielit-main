<?php

function hub_credit_note_next_number(mysqli $conn, ?DateTime $when = null): string
{
    $fy = hub_financial_year($when);
    $conn->begin_transaction();
    $stmt = $conn->prepare('SELECT last_number FROM hub_credit_note_sequences WHERE financial_year = ? FOR UPDATE');
    if (!$stmt) {
        $conn->rollback();
        throw new RuntimeException('credit note sequence unavailable');
    }
    $stmt->bind_param('s', $fy);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $next = $row ? ((int) $row['last_number'] + 1) : 1;
    if ($row) {
        $up = $conn->prepare('UPDATE hub_credit_note_sequences SET last_number = ? WHERE financial_year = ?');
        if ($up) {
            $up->bind_param('is', $next, $fy);
            $up->execute();
            $up->close();
        }
    } else {
        $ins = $conn->prepare('INSERT INTO hub_credit_note_sequences (financial_year, last_number) VALUES (?, ?)');
        if ($ins) {
            $ins->bind_param('si', $fy, $next);
            $ins->execute();
            $ins->close();
        }
    }
    $conn->commit();
    return sprintf('%s-CN-%s-%06d', HUB_INVOICE_PREFIX, $fy, $next);
}

function hub_refund_initiate(mysqli $conn, int $paymentId, ?int $amountPaise, string $reason, string $initiatedBy = 'admin'): array
{
    $payment = hub_payment_find($conn, $paymentId);
    if (!$payment) {
        return ['ok' => false, 'error' => 'payment not found'];
    }
    $refundable = hub_payment_refundable_paise($payment);
    if ($refundable <= 0) {
        return ['ok' => false, 'error' => 'this payment is not refundable'];
    }
    if ($amountPaise === null || $amountPaise <= 0) {
        $amountPaise = $refundable;
    }
    if ($amountPaise > $refundable) {
        return ['ok' => false, 'error' => 'refund amount exceeds refundable balance'];
    }
    $rzpPaymentId = (string) ($payment['razorpay_payment_id'] ?? '');
    if ($rzpPaymentId === '') {
        return ['ok' => false, 'error' => 'no Razorpay payment id on record'];
    }

    $apiBody = ['notes' => ['hub_payment_id' => (string) $paymentId, 'reason' => $reason]];
    if ($amountPaise < (int) $payment['amount_paise']) {
        $apiBody['amount'] = $amountPaise;
    }
    $result = hub_razorpay_api($conn, 'POST', 'payments/' . rawurlencode($rzpPaymentId) . '/refund', $apiBody);
    if (empty($result['ok'])) {
        return ['ok' => false, 'error' => $result['error'] ?? 'Razorpay refund failed'];
    }
    $entity = $result['data'];
    $refundId = (string) ($entity['id'] ?? '');
    $status = (string) ($entity['status'] ?? 'pending');

    $stmt = $conn->prepare('INSERT INTO hub_refunds (payment_id, razorpay_refund_id, amount_paise, status, reason, initiated_by) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'could not record refund'];
    }
    $refStatus = $status === 'processed' ? 'processed' : 'pending';
    $stmt->bind_param('isisss', $paymentId, $refundId, $amountPaise, $refStatus, $reason, $initiatedBy);
    $stmt->execute();
    $refundRowId = (int) $stmt->insert_id;
    $stmt->close();

    if ($refStatus === 'processed') {
        $fin = hub_refund_finalize($conn, $refundRowId, $refundId, $amountPaise);
        return [
            'ok' => true,
            'refund_id' => $refundRowId,
            'razorpay_refund_id' => $refundId,
            'status' => 'processed',
            'credit_note_number' => $fin['credit_note_number'] ?? null,
        ];
    }

    return [
        'ok' => true,
        'refund_id' => $refundRowId,
        'razorpay_refund_id' => $refundId,
        'status' => $refStatus,
    ];
}

function hub_refund_finalize(mysqli $conn, int $refundRowId, string $razorpayRefundId, int $amountPaise): array
{
    $stmt = $conn->prepare('SELECT r.*, p.amount_paise AS payment_amount, p.refunded_amount_paise AS already_refunded, p.id AS pid
        FROM hub_refunds r INNER JOIN hub_payments p ON p.id = r.payment_id WHERE r.id = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'refund lookup failed'];
    }
    $stmt->bind_param('i', $refundRowId);
    $stmt->execute();
    $refund = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$refund) {
        return ['ok' => false, 'error' => 'refund not found'];
    }
    if (($refund['status'] ?? '') === 'processed' && !empty($refund['credit_note_number'])) {
        return ['ok' => true, 'duplicate' => true, 'credit_note_number' => $refund['credit_note_number']];
    }

    $paymentId = (int) $refund['pid'];
    $payment = hub_payment_find($conn, $paymentId);
    if (!$payment) {
        return ['ok' => false, 'error' => 'payment not found'];
    }

    $creditNote = hub_credit_note_next_number($conn);
    $fy = hub_financial_year();
    $issuedAt = date('Y-m-d H:i:s');

    $conn->begin_transaction();

    $newRefunded = (int) $refund['already_refunded'] + $amountPaise;
    $paymentAmount = (int) $refund['payment_amount'];
    $newStatus = $newRefunded >= $paymentAmount ? 'refunded' : 'partial_refund';

    $up = $conn->prepare('UPDATE hub_payments SET refunded_amount_paise = ?, status = ? WHERE id = ?');
    if ($up) {
        $up->bind_param('isi', $newRefunded, $newStatus, $paymentId);
        $up->execute();
        $up->close();
    }

    $upR = $conn->prepare("UPDATE hub_refunds SET status = 'processed', credit_note_number = ?, processed_at = ? WHERE id = ?");
    if ($upR) {
        $upR->bind_param('ssi', $creditNote, $issuedAt, $refundRowId);
        $upR->execute();
        $upR->close();
    }

    $ins = $conn->prepare('INSERT INTO hub_credit_notes (payment_id, refund_id, credit_note_number, financial_year, amount_paise, issued_at) VALUES (?, ?, ?, ?, ?, ?)');
    if ($ins) {
        $ins->bind_param('iissis', $paymentId, $refundRowId, $creditNote, $fy, $amountPaise, $issuedAt);
        $ins->execute();
        $ins->close();
    }

    $conn->commit();

    $payment['refunded_amount_paise'] = $newRefunded;
    $payment['status'] = $newStatus;
    $checkout = hub_checkout_for_payment($conn, $payment);
    if ($checkout) {
        hub_notify_payment_event($conn, $payment, $checkout, $newStatus === 'refunded' ? 'refunded' : 'partial_refund', [
            'credit_note_number' => $creditNote,
            'refund_amount' => $amountPaise,
            'razorpay_refund_id' => $razorpayRefundId,
        ]);
    }

    return ['ok' => true, 'credit_note_number' => $creditNote, 'payment_status' => $newStatus];
}

function hub_refund_from_webhook(mysqli $conn, array $entity): array
{
    $refundId = (string) ($entity['id'] ?? '');
    $amount = (int) ($entity['amount'] ?? 0);
    $paymentId = (string) ($entity['payment_id'] ?? '');
    if ($refundId === '' || $amount <= 0) {
        return ['ok' => false, 'error' => 'invalid refund entity'];
    }

    $stmt = $conn->prepare('SELECT id FROM hub_refunds WHERE razorpay_refund_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $refundId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            return hub_refund_finalize($conn, (int) $existing['id'], $refundId, $amount);
        }
    }

    $payment = hub_payment_by_razorpay_id($conn, $paymentId);
    if (!$payment) {
        return ['ok' => false, 'error' => 'payment not found for refund webhook'];
    }

    $stmt = $conn->prepare('INSERT INTO hub_refunds (payment_id, razorpay_refund_id, amount_paise, status, reason, initiated_by) VALUES (?, ?, ?, \'pending\', ?, ?)');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'refund insert failed'];
    }
    $reason = 'Razorpay dashboard or external';
    $by = 'webhook';
    $pid = (int) $payment['id'];
    $stmt->bind_param('isiss', $pid, $refundId, $amount, $reason, $by);
    $stmt->execute();
    $rowId = (int) $stmt->insert_id;
    $stmt->close();

    return hub_refund_finalize($conn, $rowId, $refundId, $amount);
}
