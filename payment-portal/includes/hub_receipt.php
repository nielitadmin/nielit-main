<?php

/** User-facing document type (not a tax invoice). */
function hub_money_receipt_label(): string
{
    return 'Money Receipt';
}

/** Stylesheet for printable money receipts (cache-busted). */
function hub_receipt_stylesheet_url(): string
{
    $path = __DIR__ . '/../assets/css/hub-invoice.css';
    $ver = is_file($path) ? (string) filemtime($path) : '1';
    return hub_url('assets/css/hub-invoice.css?v=' . rawurlencode($ver));
}

function hub_receipt_print_head(): void
{
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700;1,9..40,400&family=Instrument+Sans:wght@600;700&display=swap" rel="stylesheet">
    <link href="<?php echo hub_receipt_stylesheet_url(); ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php
}

/** Custom drag-and-drop layout is opt-in; default is the modern structured receipt. */
function hub_receipt_uses_designer_layout(mysqli $conn): bool
{
    return hub_setting_get($conn, 'receipt_use_designer_layout') === '1';
}

/** Fixed notice on every money receipt (no signature required). */
function hub_receipt_system_notice(): string
{
    return 'This is a system generated money receipt and no need for any authority signature.';
}

/** QR image URL encoding the signed public receipt link (for verify / re-open). */
function hub_receipt_qr_image_url(string $absoluteReceiptUrl): string
{
    $absoluteReceiptUrl = trim($absoluteReceiptUrl);
    if ($absoluteReceiptUrl === '') {
        return '';
    }
    return 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=8&data=' . rawurlencode($absoluteReceiptUrl);
}

function hub_receipt_qr_block_html(mysqli $conn, array $payment): string
{
    $paymentId = (int) ($payment['id'] ?? 0);
    if ($paymentId <= 0) {
        return '';
    }
    $url = hub_invoice_public_url($conn, $paymentId);
    $qr = hub_receipt_qr_image_url($url);
    if ($qr === '') {
        return '';
    }
    ob_start();
    ?>
    <div class="hub-receipt-qr-block">
        <img src="<?php echo htmlspecialchars($qr); ?>" width="120" height="120" alt="QR code — scan to open this money receipt">
        <p class="hub-receipt-qr-caption">Scan to verify or download this receipt</p>
    </div>
    <?php
    return (string) ob_get_clean();
}

function hub_receipt_number_label(): string
{
    return 'Receipt No.';
}

function hub_receipt_migrate_template_wording(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $title = hub_setting_get($conn, 'invoice_title');
    $oldTitles = ['Fee receipt / Tax invoice', 'Tax invoice', 'Invoice', 'Fee invoice'];
    if ($title === null || $title === '' || in_array($title, $oldTitles, true)) {
        hub_setting_set($conn, 'invoice_title', hub_money_receipt_label());
    }
    $footer = hub_setting_get($conn, 'invoice_footer');
    if ($footer !== null && stripos($footer, 'invoice') !== false && stripos($footer, 'money receipt') === false) {
        hub_setting_set($conn, 'invoice_footer', 'Computer-generated money receipt. Payment received through the NIELIT Bhubaneswar online payment portal.');
    }
}

function hub_inr_amount_in_words(int $amountPaise): string
{
    $rupees = intdiv(max(0, $amountPaise), 100);
    $paise = abs($amountPaise) % 100;
    $words = hub_inr_number_to_words($rupees);
    if ($words === '') {
        $words = 'Zero';
    }
    $out = $words . ' Rupees';
    if ($paise > 0) {
        $out .= ' and ' . hub_inr_number_to_words($paise) . ' Paise';
    }
    return $out . ' Only';
}

function hub_inr_number_to_words(int $num): string
{
    if ($num === 0) {
        return '';
    }
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = static function (int $n) use ($ones, $tens): string {
        if ($n < 20) {
            return $ones[$n];
        }
        $t = intdiv($n, 10);
        $o = $n % 10;
        return trim($tens[$t] . ($o ? ' ' . $ones[$o] : ''));
    };
    $three = static function (int $n) use ($two): string {
        $h = intdiv($n, 100);
        $r = $n % 100;
        $p = $h ? $two($h) . ' Hundred' : '';
        $s = $r ? $two($r) : '';
        if ($p && $s) {
            return $p . ' ' . $s;
        }
        return $p . $s;
    };
    $parts = [];
    $crore = intdiv($num, 10000000);
    $num %= 10000000;
    $lakh = intdiv($num, 100000);
    $num %= 100000;
    $thousand = intdiv($num, 1000);
    $num %= 1000;
    if ($crore) {
        $parts[] = $three($crore) . ' Crore';
    }
    if ($lakh) {
        $parts[] = $three($lakh) . ' Lakh';
    }
    if ($thousand) {
        $parts[] = $three($thousand) . ' Thousand';
    }
    if ($num) {
        $parts[] = $three($num);
    }
    return trim(implode(' ', $parts));
}

function hub_payment_method_label(?string $method): string
{
    $method = strtolower(trim((string) $method));
    if ($method === '') {
        return '—';
    }
    $map = [
        'upi' => 'UPI',
        'card' => 'Debit / credit card',
        'netbanking' => 'Net banking',
        'wallet' => 'Wallet',
        'emi' => 'EMI',
        'paylater' => 'Pay later',
        'bank_transfer' => 'Bank transfer',
        'nach' => 'NACH',
        'cardless_emi' => 'Cardless EMI',
        'app' => 'Payment app',
    ];
    return $map[$method] ?? ucwords(str_replace('_', ' ', $method));
}

/** Razorpay / gateway method shown on receipts and admin lists. */
function hub_receipt_payment_mode_label(array $payment): string
{
    $method = strtolower(trim((string) ($payment['payment_method'] ?? '')));
    if ($method === '') {
        return 'Online (method not recorded)';
    }
    return hub_payment_method_label($method);
}

function hub_payment_methods_distinct(mysqli $conn): array
{
    $out = [];
    $res = $conn->query(
        "SELECT DISTINCT LOWER(TRIM(payment_method)) AS m FROM hub_payments
         WHERE payment_method IS NOT NULL AND TRIM(payment_method) != ''
         ORDER BY m"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $m = (string) ($row['m'] ?? '');
            if ($m !== '') {
                $out[] = $m;
            }
        }
    }
    return $out;
}

function hub_receipt_payment_table_html(array $payment): string
{
    $bd = hub_payment_breakdown($payment);
    $trn = trim((string) ($payment['razorpay_payment_id'] ?? ''));
    if ($trn === '') {
        $trn = '—';
    }
    $paidDate = hub_receipt_paid_date_only((string) ($payment['paid_at'] ?? ''));
    $bank = hub_receipt_bank_label();
    $taxable = number_format($bd['fee_receive_paise'] / 100, 2);
    $gstCol = number_format($bd['platform_fee_paise'] / 100, 2);
    ob_start();
    ?>
    <table class="hub-receipt-bank-table">
        <thead>
            <tr>
                <th>TRN / UTR No.</th>
                <th>Date</th>
                <th>Bank Name</th>
                <th class="text-end">Taxable Amount</th>
                <th class="text-end">Platform charge</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="mono"><?php echo htmlspecialchars($trn); ?></td>
                <td><?php echo htmlspecialchars($paidDate); ?></td>
                <td><?php echo htmlspecialchars($bank); ?></td>
                <td class="text-end">₹ <?php echo $taxable; ?></td>
                <td class="text-end">₹ <?php echo $gstCol; ?></td>
            </tr>
        </tbody>
    </table>
    <p class="hub-receipt-table-note muted small">Amount collected: ₹ <?php echo number_format($bd['collected_paise'] / 100, 2); ?> (institute share + platform charge). This document is a money receipt, not a tax invoice.</p>
    <?php
    return (string) ob_get_clean();
}

function hub_receipt_paid_date_only(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    return date('d/m/Y', $ts);
}

function hub_receipt_paid_datetime_label(?string $datetime): string
{
    return hub_invoice_format_ist($datetime);
}
