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
        'invoice_title' => 'Fee receipt / Tax invoice',
        'invoice_footer' => 'Received through the NIELIT Bhubaneswar payment portal. This is a computer-generated document.',
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
}

function hub_invoice_public_signature(int $paymentId, string $secret): string
{
    return hash_hmac('sha256', 'hub-invoice:' . $paymentId, $secret);
}

function hub_invoice_public_url(mysqli $conn, int $paymentId): string
{
    $tpl = hub_invoice_template_get($conn);
    $sig = hub_invoice_public_signature($paymentId, (string) $tpl['invoice_public_secret']);
    return hub_url('pay/invoice.php?id=' . $paymentId . '&sig=' . $sig);
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

function hub_invoice_render_html(mysqli $conn, array $payment, bool $allowPrint = true): void
{
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
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice <?php echo htmlspecialchars($invoiceNo); ?></title>
    <link href="<?php echo hub_asset('assets/css/hub-invoice.css'); ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="hub-invoice-doc">
<?php if ($allowPrint): ?>
<div class="hub-invoice-toolbar no-print">
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save PDF</button>
</div>
<?php endif; ?>
<article class="hub-invoice-sheet">
    <header class="hub-invoice-head">
        <div class="hub-invoice-brand">
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="NIELIT" width="64" height="64">
            <div>
                <p class="org-name"><?php echo htmlspecialchars((string) $tpl['invoice_org_name']); ?></p>
                <p class="org-unit"><?php echo htmlspecialchars((string) $tpl['invoice_org_unit']); ?></p>
            </div>
        </div>
        <div class="hub-invoice-meta">
            <h1><?php echo htmlspecialchars((string) $tpl['invoice_title']); ?></h1>
            <p class="inv-no"><?php echo htmlspecialchars($invoiceNo); ?></p>
        </div>
    </header>
    <div class="hub-invoice-grid">
        <section>
            <h2>Bill to</h2>
            <p class="strong"><?php echo htmlspecialchars((string) ($payer['name'] ?? 'Payer')); ?></p>
            <?php if (!empty($payer['email'])): ?><p><?php echo htmlspecialchars((string) $payer['email']); ?></p><?php endif; ?>
            <?php if (!empty($payer['phone'])): ?><p><?php echo htmlspecialchars((string) $payer['phone']); ?></p><?php endif; ?>
            <?php if (!empty($payer['student_id'])): ?><p>ID: <?php echo htmlspecialchars((string) $payer['student_id']); ?></p><?php endif; ?>
        </section>
        <section>
            <h2>Issuer</h2>
            <p class="pre-line"><?php echo htmlspecialchars((string) $tpl['invoice_org_address']); ?></p>
            <?php if (trim((string) $tpl['invoice_org_gstin']) !== ''): ?>
                <p>GSTIN: <?php echo htmlspecialchars((string) $tpl['invoice_org_gstin']); ?></p>
            <?php endif; ?>
            <?php if (trim((string) $tpl['invoice_org_phone']) !== ''): ?>
                <p>Phone: <?php echo htmlspecialchars((string) $tpl['invoice_org_phone']); ?></p>
            <?php endif; ?>
            <p><?php echo htmlspecialchars((string) $tpl['invoice_org_email']); ?></p>
        </section>
    </div>
    <table class="hub-invoice-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Source</th>
                <th class="text-end">Amount (INR)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars((string) ($payment['purpose'] ?? 'Online fee')); ?></strong>
                    <?php if (trim((string) ($payment['description'] ?? '')) !== ''): ?>
                        <br><span class="muted"><?php echo htmlspecialchars((string) $payment['description']); ?></span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars((string) ($payment['source_name'] ?? $payment['source_code'] ?? '')); ?></td>
                <td class="text-end strong"><?php echo number_format($amount / 100, 2); ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="text-end strong">Total</td>
                <td class="text-end strong">₹ <?php echo number_format($amount / 100, 2); ?></td>
            </tr>
            <?php if ($showGst): ?>
            <tr>
                <td colspan="3" class="muted small">GST breakup to be configured by accounts — enable after rates are supplied.</td>
            </tr>
            <?php endif; ?>
        </tfoot>
    </table>
    <?php if ($contextLines !== []): ?>
    <section class="hub-invoice-context">
        <h2>Details</h2>
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
            <dt>Payment status</dt>
            <dd><?php echo htmlspecialchars(strtoupper($status)); ?></dd>
            <dt>Paid on (IST)</dt>
            <dd><?php echo htmlspecialchars(hub_invoice_format_ist((string) ($payment['paid_at'] ?? ''))); ?></dd>
            <dt>Razorpay payment ID</dt>
            <dd class="mono"><?php echo htmlspecialchars((string) ($payment['razorpay_payment_id'] ?? '—')); ?></dd>
            <dt>Reference</dt>
            <dd class="mono"><?php echo htmlspecialchars((string) ($payment['source_reference'] ?? '')); ?></dd>
            <?php if ($checkout): ?>
            <dt>Checkout token</dt>
            <dd class="mono small"><?php echo htmlspecialchars((string) ($checkout['token'] ?? '')); ?></dd>
            <?php endif; ?>
        </dl>
    </section>
    <footer class="hub-invoice-foot">
        <p><?php echo htmlspecialchars((string) $tpl['invoice_footer']); ?></p>
    </footer>
</article>
</body>
</html>
    <?php
}
