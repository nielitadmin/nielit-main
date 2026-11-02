<?php

function hub_invoice_template_defaults(): array
{
    return [
        'invoice_org_name' => 'National Institute of Electronics & Information Technology',
        'invoice_org_unit' => 'NIELIT Bhubaneswar Centre',
        'invoice_org_address' => "Plot No. 7, Infocity, Chandaka Industrial Estate\nBhubaneswar, Odisha — 751024",
        'invoice_org_gstin' => '',
        'invoice_org_phone' => '',
        'invoice_org_email' => 'admin@nielitbhubaneswar.in',
        'invoice_title' => 'Money Receipt',
        'invoice_footer' => 'Computer-generated money receipt. Payment received through the NIELIT Bhubaneswar online payment portal.',
        'invoice_show_gst' => '0',
        'invoice_public_secret' => '',
    ];
}

function hub_invoice_template_get(mysqli $conn): array
{
    $defaults = hub_invoice_template_defaults();
    $out = [];
    foreach ($defaults as $key => $default) {
        $val = hub_setting_get($conn, $key);
        $out[$key] = ($val !== null && $val !== '') ? $val : $default;
    }
    if ($out['invoice_public_secret'] === '') {
        $out['invoice_public_secret'] = hub_invoice_public_secret($conn);
    }
    return $out;
}

function hub_invoice_public_secret(mysqli $conn): string
{
    $existing = hub_setting_get($conn, 'invoice_public_secret');
    if ($existing !== null && $existing !== '') {
        return $existing;
    }
    $secret = bin2hex(random_bytes(16));
    hub_setting_set($conn, 'invoice_public_secret', $secret);
    return $secret;
}

function hub_invoice_template_save(mysqli $conn, array $post): void
{
    $keys = array_keys(hub_invoice_template_defaults());
    foreach ($keys as $key) {
        if ($key === 'invoice_public_secret') {
            continue;
        }
        if (!array_key_exists($key, $post)) {
            continue;
        }
        if ($key === 'invoice_show_gst') {
            hub_setting_set($conn, $key, !empty($post[$key]) ? '1' : '0');
            continue;
        }
        hub_setting_set($conn, $key, trim((string) $post[$key]));
    }
    hub_setting_set($conn, 'receipt_use_designer_layout', !empty($post['receipt_use_designer_layout']) ? '1' : '0');
}

function hub_invoice_public_signature(int $paymentId, string $secret): string
{
    return hash_hmac('sha256', 'hub-invoice:' . $paymentId, $secret);
}

function hub_invoice_public_url(mysqli $conn, int $paymentId): string
{
    $tpl = hub_invoice_template_get($conn);
    $sig = hub_invoice_public_signature($paymentId, (string) $tpl['invoice_public_secret']);
    return hub_url('pay/receipt.php?id=' . $paymentId . '&sig=' . $sig);
}

function hub_invoice_verify_public(int $paymentId, string $sig, string $secret): bool
{
    if ($sig === '') {
        return false;
    }
    return hash_equals(hub_invoice_public_signature($paymentId, $secret), $sig);
}

function hub_invoice_context_lines(array $context): array
{
    $lines = [];
    $map = [
        'centre' => 'Centre',
        'center' => 'Centre',
        'course' => 'Course',
        'course_name' => 'Course',
        'test' => 'Test',
        'batch' => 'Batch',
        'student_id' => 'Student ID',
    ];
    foreach ($map as $key => $label) {
        if (!empty($context[$key])) {
            $lines[] = ['label' => $label, 'value' => (string) $context[$key]];
        }
    }
    foreach ($context as $k => $v) {
        if (isset($map[$k]) || !is_scalar($v) || (string) $v === '') {
            continue;
        }
        $lines[] = ['label' => ucwords(str_replace('_', ' ', (string) $k)), 'value' => (string) $v];
    }
    return $lines;
}

function hub_invoice_format_ist(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    return date('d M Y, h:i A', $ts);
}

function hub_invoice_render_html(mysqli $conn, array $payment, bool $allowPrint = true, ?bool $useDesignerLayout = null): void
{
    if ($useDesignerLayout === null) {
        $useDesignerLayout = hub_receipt_uses_designer_layout($conn);
    }
    if ($useDesignerLayout) {
        $layout = hub_invoice_layout_get($conn);
        if ($layout !== null) {
            hub_invoice_render_layout_html($conn, $payment, $layout, $allowPrint);
            return;
        }
    }

    $checkout = hub_checkout_for_payment($conn, $payment);
    $tpl = hub_invoice_template_get($conn);
    $payer = json_decode((string) ($payment['payer_json'] ?? ''), true);
    if (!is_array($payer)) {
        $payer = [];
    }
    $context = json_decode((string) ($payment['context_json'] ?? ''), true);
    if (!is_array($context)) {
        $context = [];
    }
    $contextLines = hub_invoice_context_lines($context);
    $amount = (int) ($payment['amount_paise'] ?? 0);
    $invoiceNo = (string) ($payment['invoice_number'] ?? '');
    $status = (string) ($payment['status'] ?? '');
    $logoUrl = hub_brand_logo_url();
    $showGst = ($tpl['invoice_show_gst'] ?? '0') === '1';
    $payerName = (string) ($payer['name'] ?? 'Payer');
    $purposeLabel = hub_payment_purpose_label($payment);
    $amountWords = hub_inr_amount_in_words($amount);
    $paidLabel = hub_receipt_paid_datetime_label((string) ($payment['paid_at'] ?? ''));
    $paidDateOnly = hub_receipt_paid_date_only((string) ($payment['paid_at'] ?? ''));
    $modeLabel = hub_receipt_payment_mode_label($payment);
    $docTitle = (string) ($tpl['invoice_title'] ?? hub_money_receipt_label());
    if ($docTitle === '') {
        $docTitle = hub_money_receipt_label();
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($docTitle); ?> <?php echo htmlspecialchars($invoiceNo); ?></title>
    <?php hub_receipt_print_head(); ?>
</head>
<body class="hub-invoice-doc hub-money-receipt-doc">
<?php if ($allowPrint): ?>
<div class="hub-invoice-toolbar no-print">
    <button type="button" class="hub-receipt-print-btn" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save PDF</button>
</div>
<?php endif; ?>
<article class="hub-invoice-sheet hub-money-receipt-sheet">
    <div class="hub-receipt-ribbon"><?php echo htmlspecialchars($docTitle); ?></div>
    <header class="hub-invoice-head">
        <div class="hub-invoice-brand">
            <div class="hub-receipt-logo-wrap">
                <img class="hub-receipt-logo" src="<?php echo htmlspecialchars($logoUrl); ?>" alt="NIELIT Bhubaneswar logo">
            </div>
            <div>
                <p class="org-name"><?php echo htmlspecialchars((string) $tpl['invoice_org_name']); ?></p>
                <p class="org-unit"><?php echo htmlspecialchars((string) $tpl['invoice_org_unit']); ?></p>
                <p class="org-address pre-line small muted"><?php echo htmlspecialchars((string) $tpl['invoice_org_address']); ?></p>
                <?php if (trim((string) $tpl['invoice_org_gstin']) !== ''): ?>
                    <p class="small"><strong>GSTIN:</strong> <?php echo htmlspecialchars((string) $tpl['invoice_org_gstin']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="hub-invoice-meta">
            <p class="hub-receipt-doc-label"><?php echo htmlspecialchars(hub_receipt_number_label()); ?></p>
            <p class="inv-no"><?php echo htmlspecialchars($invoiceNo); ?></p>
            <p class="hub-receipt-date"><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($paidDateOnly); ?></p>
        </div>
    </header>

    <section class="hub-receipt-amount-panel">
        <p class="hub-receipt-amount-label">Amount received</p>
        <p class="hub-receipt-amount-value">₹ <?php echo number_format($amount / 100, 2); ?></p>
        <p class="hub-receipt-amount-words"><?php echo htmlspecialchars($amountWords); ?></p>
    </section>

    <section class="hub-receipt-body">
        <div class="hub-receipt-row">
            <span class="hub-receipt-k">Received from</span>
            <span class="hub-receipt-v"><strong><?php echo htmlspecialchars($payerName); ?></strong>
            <?php if (!empty($payer['student_id'])): ?>
                <span class="muted"> · <?php echo htmlspecialchars((string) $payer['student_id']); ?></span>
            <?php endif; ?>
            </span>
        </div>
        <?php if (!empty($payer['email']) || !empty($payer['phone'])): ?>
        <div class="hub-receipt-row muted small">
            <span class="hub-receipt-k">Contact</span>
            <span class="hub-receipt-v">
                <?php if (!empty($payer['email'])): ?><?php echo htmlspecialchars((string) $payer['email']); ?><?php endif; ?>
                <?php if (!empty($payer['email']) && !empty($payer['phone'])): ?> · <?php endif; ?>
                <?php if (!empty($payer['phone'])): ?><?php echo htmlspecialchars((string) $payer['phone']); ?><?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
        <div class="hub-receipt-row">
            <span class="hub-receipt-k">Towards</span>
            <span class="hub-receipt-v"><strong><?php echo htmlspecialchars($purposeLabel); ?></strong></span>
        </div>
        <div class="hub-receipt-row">
            <span class="hub-receipt-k">Mode of transaction</span>
            <span class="hub-receipt-v"><span class="hub-receipt-mode-pill"><?php echo htmlspecialchars($modeLabel); ?></span></span>
        </div>
    </section>

    <?php echo hub_receipt_payment_table_html($payment); ?>

    <?php if ($contextLines !== []): ?>
    <section class="hub-invoice-context">
        <h2>Reference details</h2>
        <dl>
            <?php foreach ($contextLines as $line): ?>
                <dt><?php echo htmlspecialchars($line['label']); ?></dt>
                <dd><?php echo htmlspecialchars($line['value']); ?></dd>
            <?php endforeach; ?>
        </dl>
    </section>
    <?php endif; ?>

    <section class="hub-invoice-payment-meta">
        <dl>
            <dt>Transaction status</dt>
            <dd><?php echo htmlspecialchars(strtoupper($status)); ?></dd>
            <dt>Paid on (IST)</dt>
            <dd><?php echo htmlspecialchars($paidLabel); ?></dd>
            <dt>Portal reference</dt>
            <dd class="mono"><?php echo htmlspecialchars((string) ($payment['source_reference'] ?? '—')); ?></dd>
            <?php if ($checkout): ?>
            <dt>Checkout token</dt>
            <dd class="mono small"><?php echo htmlspecialchars((string) ($checkout['token'] ?? '')); ?></dd>
            <?php endif; ?>
            <?php if ($showGst): ?>
            <dt>GST note</dt>
            <dd class="muted small">GST breakup for accounts — configure rates when required.</dd>
            <?php endif; ?>
        </dl>
    </section>

    <div class="hub-receipt-qr-system">
        <?php echo hub_receipt_qr_block_html($conn, $payment); ?>
        <p class="hub-receipt-system-notice"><?php echo htmlspecialchars(hub_receipt_system_notice()); ?></p>
    </div>

    <footer class="hub-invoice-foot">
        <p class="hub-receipt-legal-note">This is a computer-generated <strong>money receipt</strong> for payment received. It is not a tax invoice.</p>
        <?php if (trim((string) $tpl['invoice_footer']) !== ''): ?>
        <p><?php echo htmlspecialchars((string) $tpl['invoice_footer']); ?></p>
        <?php endif; ?>
    </footer>
</article>
</body>
</html>
    <?php
}
