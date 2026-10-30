<?php

function hub_payment_find(mysqli $conn, int $paymentId): ?array
{
    $stmt = $conn->prepare('SELECT p.*, s.code AS source_code, s.display_name AS source_name, s.shared_secret AS source_secret
        FROM hub_payments p
        INNER JOIN hub_sources s ON s.id = p.source_id
        WHERE p.id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hub_payment_by_razorpay_id(mysqli $conn, string $paymentId): ?array
{
    $stmt = $conn->prepare('SELECT p.*, s.code AS source_code, s.display_name AS source_name, s.shared_secret AS source_secret
        FROM hub_payments p
        INNER JOIN hub_sources s ON s.id = p.source_id
        WHERE p.razorpay_payment_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $paymentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hub_checkout_for_payment(mysqli $conn, array $payment): ?array
{
    $stmt = $conn->prepare('SELECT c.*, s.code AS source_code, s.display_name AS source_name, s.shared_secret AS source_secret
        FROM hub_checkouts c
        INNER JOIN hub_sources s ON s.id = c.source_id
        WHERE c.id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $cid = (int) $payment['checkout_id'];
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hub_payment_refundable_paise(array $payment): int
{
    if (!in_array((string) ($payment['status'] ?? ''), ['paid', 'partial_refund'], true)) {
        return 0;
    }
    $amount = (int) ($payment['amount_paise'] ?? 0);
    $refunded = (int) ($payment['refunded_amount_paise'] ?? 0);
    return max(0, $amount - $refunded);
}

function hub_credit_notes_for_payment(mysqli $conn, int $paymentId): array
{
    $rows = [];
    $stmt = $conn->prepare('SELECT * FROM hub_credit_notes WHERE payment_id = ? ORDER BY id ASC');
    if ($stmt) {
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    }
    return $rows;
}

function hub_refunds_for_payment(mysqli $conn, int $paymentId): array
{
    $rows = [];
    $stmt = $conn->prepare('SELECT * FROM hub_refunds WHERE payment_id = ? ORDER BY id ASC');
    if ($stmt) {
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    }
    return $rows;
}
