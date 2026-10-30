<?php

function hub_platform_percent_default(): float
{
    return 2.4;
}

/** Infer institute receive + platform fee from gross collected (MIS 2.4% model). */
function hub_payment_infer_platform_split(int $amountPaise, ?float $percent = null): array
{
    $percent = $percent ?? hub_platform_percent_default();
    if ($amountPaise <= 0) {
        return ['fee_receive_paise' => 0, 'platform_fee_paise' => 0, 'platform_percent' => $percent];
    }
    $total = $amountPaise / 100.0;
    $receive = round($total / (1 + $percent / 100), 2);
    $platform = round($total - $receive, 2);
    return [
        'fee_receive_paise' => (int) round($receive * 100),
        'platform_fee_paise' => (int) round($platform * 100),
        'platform_percent' => $percent,
    ];
}

function hub_payment_context_amounts(array $row): array
{
    $ctx = json_decode((string) ($row['context_json'] ?? '{}'), true);
    if (!is_array($ctx)) {
        $ctx = [];
    }
    $percent = isset($ctx['platform_percent']) ? (float) $ctx['platform_percent'] : hub_platform_percent_default();
    $receive = (int) ($ctx['fee_receive_paise'] ?? $ctx['receive_paise'] ?? 0);
    $platform = (int) ($ctx['platform_fee_paise'] ?? 0);
    if ($receive <= 0 && $platform <= 0) {
        $inferred = hub_payment_infer_platform_split((int) ($row['amount_paise'] ?? 0), $percent);
        $receive = $inferred['fee_receive_paise'];
        $platform = $inferred['platform_fee_paise'];
    }
    return [
        'fee_receive_paise' => $receive,
        'platform_fee_paise' => $platform,
        'platform_percent' => $percent,
    ];
}

function hub_payment_fetch_razorpay_fees(mysqli $conn, string $razorpayPaymentId): array
{
    $razorpayPaymentId = trim($razorpayPaymentId);
    if ($razorpayPaymentId === '') {
        return ['ok' => false];
    }
    $result = hub_razorpay_fetch_payment($conn, $razorpayPaymentId);
    if (empty($result['ok']) || !is_array($result['data'])) {
        return ['ok' => false, 'error' => (string) ($result['error'] ?? 'fetch failed')];
    }
    $data = $result['data'];
    $amount = (int) ($data['amount'] ?? 0);
    $fee = (int) ($data['fee'] ?? 0);
    $tax = (int) ($data['tax'] ?? 0);
    $settlement = max(0, $amount - $fee - $tax);
    return [
        'ok' => true,
        'razorpay_fee_paise' => $fee,
        'razorpay_tax_paise' => $tax,
        'settlement_paise' => $settlement,
        'payment_method' => (string) ($data['method'] ?? ''),
    ];
}

function hub_payment_refresh_razorpay_fees(mysqli $conn, int $paymentRowId): bool
{
    $payment = hub_payment_find($conn, $paymentRowId);
    if (!$payment || trim((string) ($payment['razorpay_payment_id'] ?? '')) === '') {
        return false;
    }
    $fees = hub_payment_fetch_razorpay_fees($conn, (string) $payment['razorpay_payment_id']);
    if (empty($fees['ok'])) {
        return false;
    }
    $fee = (int) $fees['razorpay_fee_paise'];
    $tax = (int) $fees['razorpay_tax_paise'];
    $settle = (int) $fees['settlement_paise'];
    $method = (string) ($fees['payment_method'] ?? '');
    $receive = (int) ($payment['fee_receive_paise'] ?? 0);
    $platform = (int) ($payment['platform_fee_paise'] ?? 0);
    if ($receive <= 0 || $platform <= 0) {
        $split = hub_payment_context_amounts($payment);
        if ($receive <= 0) {
            $receive = (int) $split['fee_receive_paise'];
        }
        if ($platform <= 0) {
            $platform = (int) $split['platform_fee_paise'];
        }
    }
    $stmt = $conn->prepare('UPDATE hub_payments SET razorpay_fee_paise = ?, razorpay_tax_paise = ?, settlement_paise = ?, fee_receive_paise = ?, platform_fee_paise = ?, payment_method = COALESCE(NULLIF(payment_method, \'\'), ?) WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iiiiisi', $fee, $tax, $settle, $receive, $platform, $method, $paymentRowId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function hub_payment_purpose_label(array $row): string
{
    $purpose = trim((string) ($row['purpose'] ?? ''));
    $description = trim((string) ($row['description'] ?? ''));
    if ($purpose === '' && $description === '') {
        return 'Online payment';
    }
    if ($description === '' || strcasecmp($description, $purpose) === 0) {
        return $purpose !== '' ? $purpose : $description;
    }
    if ($purpose === '') {
        return $description;
    }
    return $purpose . ' — ' . $description;
}

/** Normalized breakdown for list, detail, and CSV. */
function hub_payment_breakdown(array $row): array
{
    $collected = (int) ($row['amount_paise'] ?? 0);
    $platform = hub_payment_context_amounts($row);
    $feeReceive = (int) ($row['fee_receive_paise'] ?? 0);
    $platformFee = (int) ($row['platform_fee_paise'] ?? 0);
    if ($feeReceive <= 0) {
        $feeReceive = $platform['fee_receive_paise'];
    }
    if ($platformFee <= 0) {
        $platformFee = $platform['platform_fee_paise'];
    }
    $rzpFee = (int) ($row['razorpay_fee_paise'] ?? 0);
    $rzpTax = (int) ($row['razorpay_tax_paise'] ?? 0);
    $settlement = (int) ($row['settlement_paise'] ?? 0);
    if ($settlement <= 0 && $collected > 0 && ($rzpFee > 0 || $rzpTax > 0)) {
        $settlement = max(0, $collected - $rzpFee - $rzpTax);
    }

    return [
        'purpose_label' => hub_payment_purpose_label($row),
        'purpose' => trim((string) ($row['purpose'] ?? '')),
        'description' => trim((string) ($row['description'] ?? '')),
        'collected_paise' => $collected,
        'fee_receive_paise' => $feeReceive,
        'platform_fee_paise' => $platformFee,
        'platform_percent' => (float) ($platform['platform_percent'] ?? hub_platform_percent_default()),
        'razorpay_fee_paise' => $rzpFee,
        'razorpay_tax_paise' => $rzpTax,
        'settlement_paise' => $settlement,
        'has_razorpay_fees' => $rzpFee > 0 || $rzpTax > 0 || $settlement > 0,
    ];
}

function hub_payment_store_amount_columns(
    mysqli $conn,
    int $paymentRowId,
    int $feeReceivePaise,
    int $platformFeePaise,
    int $razorpayFeePaise,
    int $razorpayTaxPaise,
    int $settlementPaise
): void {
    $stmt = $conn->prepare('UPDATE hub_payments SET fee_receive_paise = ?, platform_fee_paise = ?, razorpay_fee_paise = ?, razorpay_tax_paise = ?, settlement_paise = ? WHERE id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iiiiii', $feeReceivePaise, $platformFeePaise, $razorpayFeePaise, $razorpayTaxPaise, $settlementPaise, $paymentRowId);
    $stmt->execute();
    $stmt->close();
}

function hub_payment_amounts_from_checkout(array $checkout): array
{
    $ctx = json_decode((string) ($checkout['context_json'] ?? '{}'), true);
    if (!is_array($ctx)) {
        $ctx = [];
    }
    $receive = (int) ($ctx['fee_receive_paise'] ?? $ctx['receive_paise'] ?? 0);
    $platform = (int) ($ctx['platform_fee_paise'] ?? 0);
    $percent = isset($ctx['platform_percent']) ? (float) $ctx['platform_percent'] : hub_platform_percent_default();
    if ($receive <= 0 && $platform <= 0) {
        $inferred = hub_payment_infer_platform_split((int) ($checkout['amount_paise'] ?? 0), $percent);
        $receive = $inferred['fee_receive_paise'];
        $platform = $inferred['platform_fee_paise'];
    }
    return [
        'fee_receive_paise' => $receive,
        'platform_fee_paise' => $platform,
    ];
}
