<?php

function hub_invoice_layout_page(): array
{
    return ['width' => 794, 'height' => 1123, 'padding' => 36];
}

function hub_invoice_layout_default_blocks(): array
{
    return [
        ['id' => 'b_logo', 'type' => 'logo', 'x' => 36, 'y' => 36, 'w' => 72, 'h' => 72, 'visible' => true],
        ['id' => 'b_org', 'type' => 'data', 'key' => 'org_header', 'x' => 120, 'y' => 40, 'w' => 420, 'h' => 56, 'fontSize' => 13, 'align' => 'left', 'bold' => true, 'visible' => true],
        ['id' => 'b_title', 'type' => 'data', 'key' => 'title', 'x' => 520, 'y' => 36, 'w' => 238, 'h' => 32, 'fontSize' => 16, 'align' => 'right', 'bold' => true, 'visible' => true],
        ['id' => 'b_invno', 'type' => 'data', 'key' => 'invoice_number', 'x' => 520, 'y' => 72, 'w' => 238, 'h' => 28, 'fontSize' => 12, 'align' => 'right', 'bold' => true, 'visible' => true],
        ['id' => 'b_bill', 'type' => 'data', 'key' => 'payer_block', 'x' => 36, 'y' => 130, 'w' => 340, 'h' => 120, 'fontSize' => 11, 'align' => 'left', 'bold' => false, 'visible' => true],
        ['id' => 'b_issuer', 'type' => 'data', 'key' => 'issuer_block', 'x' => 400, 'y' => 130, 'w' => 358, 'h' => 120, 'fontSize' => 11, 'align' => 'left', 'bold' => false, 'visible' => true],
        ['id' => 'b_lines', 'type' => 'data', 'key' => 'line_items', 'x' => 36, 'y' => 270, 'w' => 722, 'h' => 160, 'fontSize' => 11, 'align' => 'left', 'bold' => false, 'visible' => true],
        ['id' => 'b_ctx', 'type' => 'data', 'key' => 'context_block', 'x' => 36, 'y' => 450, 'w' => 360, 'h' => 140, 'fontSize' => 11, 'align' => 'left', 'bold' => false, 'visible' => true],
        ['id' => 'b_pay', 'type' => 'data', 'key' => 'payment_meta', 'x' => 410, 'y' => 450, 'w' => 348, 'h' => 140, 'fontSize' => 10, 'align' => 'left', 'bold' => false, 'visible' => true],
        ['id' => 'b_foot', 'type' => 'data', 'key' => 'footer', 'x' => 36, 'y' => 1020, 'w' => 722, 'h' => 60, 'fontSize' => 10, 'align' => 'center', 'bold' => false, 'visible' => true],
    ];
}

function hub_invoice_layout_default(): array
{
    return [
        'version' => 1,
        'page' => hub_invoice_layout_page(),
        'blocks' => hub_invoice_layout_default_blocks(),
    ];
}

function hub_invoice_layout_get(mysqli $conn): ?array
{
    $raw = hub_setting_get($conn, 'invoice_layout_json');
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['blocks']) || !is_array($data['blocks'])) {
        return null;
    }
    if (empty($data['page'])) {
        $data['page'] = hub_invoice_layout_page();
    }
    return $data;
}

function hub_invoice_layout_get_or_default(mysqli $conn): array
{
    return hub_invoice_layout_get($conn) ?? hub_invoice_layout_default();
}

function hub_invoice_layout_save(mysqli $conn, string $json): bool
{
    $json = trim($json);
    if ($json === '') {
        return hub_setting_set($conn, 'invoice_layout_json', '');
    }
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['blocks']) || !is_array($data['blocks'])) {
        return false;
    }
    return hub_setting_set($conn, 'invoice_layout_json', $json);
}

function hub_invoice_render_data(mysqli $conn, array $payment, array $tpl, ?array $checkout): array
{
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
    $showGst = ($tpl['invoice_show_gst'] ?? '0') === '1';

    ob_start();
    ?>
    <strong><?php echo htmlspecialchars((string) ($tpl['invoice_org_name'])); ?></strong><br>
    <span style="color:#0369a1;font-weight:600;"><?php echo htmlspecialchars((string) ($tpl['invoice_org_unit'])); ?></span>
    <?php
    $orgHeader = ob_get_clean();

    ob_start();
    ?><strong>Bill to</strong><br>
    <?php echo htmlspecialchars((string) ($payer['name'] ?? 'Payer')); ?><br>
    <?php if (!empty($payer['email'])): ?><?php echo htmlspecialchars((string) $payer['email']); ?><br><?php endif; ?>
    <?php if (!empty($payer['phone'])): ?><?php echo htmlspecialchars((string) $payer['phone']); ?><br><?php endif; ?>
    <?php if (!empty($payer['student_id'])): ?>ID: <?php echo htmlspecialchars((string) $payer['student_id']); ?><?php endif; ?>
    <?php
    $payerBlock = ob_get_clean();

    ob_start();
    ?><strong>Issuer</strong><br>
    <span style="white-space:pre-line;"><?php echo htmlspecialchars((string) $tpl['invoice_org_address']); ?></span><br>
    <?php if (trim((string) $tpl['invoice_org_gstin']) !== ''): ?>GSTIN: <?php echo htmlspecialchars((string) $tpl['invoice_org_gstin']); ?><br><?php endif; ?>
    <?php if (trim((string) $tpl['invoice_org_phone']) !== ''): ?>Phone: <?php echo htmlspecialchars((string) $tpl['invoice_org_phone']); ?><br><?php endif; ?>
    <?php echo htmlspecialchars((string) $tpl['invoice_org_email']); ?>
    <?php
    $issuerBlock = ob_get_clean();

    ob_start();
    ?>
    <table style="width:100%;border-collapse:collapse;font-size:inherit;">
        <thead><tr>
            <th style="border:1px solid #e2e8f0;padding:6px;text-align:left;background:#f8fafc;">Description</th>
            <th style="border:1px solid #e2e8f0;padding:6px;text-align:left;background:#f8fafc;">Source</th>
            <th style="border:1px solid #e2e8f0;padding:6px;text-align:right;background:#f8fafc;">INR</th>
        </tr></thead>
        <tbody><tr>
            <td style="border:1px solid #e2e8f0;padding:6px;"><strong><?php echo htmlspecialchars((string) ($payment['purpose'] ?? 'Online fee')); ?></strong>
            <?php if (trim((string) ($payment['description'] ?? '')) !== ''): ?><br><span style="color:#64748b;"><?php echo htmlspecialchars((string) $payment['description']); ?></span><?php endif; ?></td>
            <td style="border:1px solid #e2e8f0;padding:6px;"><?php echo htmlspecialchars((string) ($payment['source_name'] ?? $payment['source_code'] ?? '')); ?></td>
            <td style="border:1px solid #e2e8f0;padding:6px;text-align:right;"><strong><?php echo number_format($amount / 100, 2); ?></strong></td>
        </tr></tbody>
        <tfoot><tr>
            <td colspan="2" style="border:1px solid #e2e8f0;padding:6px;text-align:right;"><strong>Total</strong></td>
            <td style="border:1px solid #e2e8f0;padding:6px;text-align:right;"><strong>₹ <?php echo number_format($amount / 100, 2); ?></strong></td>
        </tr></tfoot>
    </table>
    <?php if ($showGst): ?><p style="color:#64748b;font-size:0.9em;margin:6px 0 0;">GST breakup — configure with accounts.</p><?php endif; ?>
    <?php
    $lineItems = ob_get_clean();

    ob_start();
    if ($contextLines !== []) {
        echo '<strong>Details</strong><br>';
        foreach ($contextLines as $line) {
            echo htmlspecialchars($line['label']) . ': ' . htmlspecialchars($line['value']) . '<br>';
        }
    }
    $contextBlock = ob_get_clean();

    ob_start();
    ?>
    <strong>Payment</strong><br>
    Status: <?php echo htmlspecialchars(strtoupper((string) ($payment['status'] ?? ''))); ?><br>
    Paid: <?php echo htmlspecialchars(hub_invoice_format_ist((string) ($payment['paid_at'] ?? ''))); ?><br>
    Razorpay: <?php echo htmlspecialchars((string) ($payment['razorpay_payment_id'] ?? '—')); ?><br>
    Ref: <?php echo htmlspecialchars((string) ($payment['source_reference'] ?? '')); ?>
    <?php
    $paymentMeta = ob_get_clean();

    return [
        'org_header' => $orgHeader,
        'title' => (string) $tpl['invoice_title'],
        'invoice_number' => (string) ($payment['invoice_number'] ?? ''),
        'payer_block' => $payerBlock,
        'issuer_block' => $issuerBlock,
        'line_items' => $lineItems,
        'context_block' => $contextBlock,
        'payment_meta' => $paymentMeta,
        'footer' => (string) $tpl['invoice_footer'],
        'logo_url' => hub_brand_logo_url(),
    ];
}

function hub_invoice_layout_block_style(array $block): string
{
    $x = (int) ($block['x'] ?? 0);
    $y = (int) ($block['y'] ?? 0);
    $w = max(40, (int) ($block['w'] ?? 100));
    $h = max(24, (int) ($block['h'] ?? 40));
    $fontSize = max(8, min(32, (int) ($block['fontSize'] ?? 11)));
    $align = in_array($block['align'] ?? 'left', ['left', 'center', 'right'], true) ? $block['align'] : 'left';
    $bold = !empty($block['bold']) ? 'font-weight:700;' : '';
    return "left:{$x}px;top:{$y}px;width:{$w}px;height:{$h}px;font-size:{$fontSize}px;text-align:{$align};{$bold}";
}

function hub_invoice_layout_block_inner(array $block, array $data, bool $designerPreview = false): string
{
    $type = (string) ($block['type'] ?? 'data');
    if ($type === 'logo') {
        $url = htmlspecialchars((string) ($data['logo_url'] ?? ''));
        return '<img src="' . $url . '" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain;">';
    }
    if ($type === 'static') {
        $text = (string) ($block['text'] ?? 'Custom text');
        if ($designerPreview) {
            $text = $text === '' ? 'Custom text' : $text;
        }
        return nl2br(htmlspecialchars($text));
    }
    $key = (string) ($block['key'] ?? '');
    if ($key === '' || !isset($data[$key])) {
        return $designerPreview ? '<span style="color:#94a3b8;">{' . htmlspecialchars($key) . '}</span>' : '';
    }
    $val = $data[$key];
    if (str_contains($key, '_block') || $key === 'line_items' || $key === 'org_header') {
        return (string) $val;
    }
    return htmlspecialchars((string) $val);
}

function hub_invoice_render_layout_html(mysqli $conn, array $payment, array $layout, bool $allowPrint = true): void
{
    $tpl = hub_invoice_template_get($conn);
    $checkout = hub_checkout_for_payment($conn, $payment);
    $data = hub_invoice_render_data($conn, $payment, $tpl, $checkout);
    $page = $layout['page'] ?? hub_invoice_layout_page();
    $w = (int) ($page['width'] ?? 794);
    $h = (int) ($page['height'] ?? 1123);
    $invoiceNo = htmlspecialchars((string) ($payment['invoice_number'] ?? ''));
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $invoiceNo; ?></title>
    <link href="<?php echo hub_asset('assets/css/hub-invoice.css'); ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="hub-invoice-doc">
<?php if ($allowPrint): ?>
<div class="hub-invoice-toolbar no-print">
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save PDF</button>
</div>
<?php endif; ?>
<article class="hub-invoice-sheet hub-invoice-sheet-layout" style="width:<?php echo $w; ?>px;min-height:<?php echo $h; ?>px;">
    <?php foreach ($layout['blocks'] as $block): ?>
        <?php if (empty($block['visible'])) {
            continue;
        } ?>
        <div class="hub-inv-block" style="<?php echo hub_invoice_layout_block_style($block); ?>">
            <div class="hub-inv-block-inner"><?php echo hub_invoice_layout_block_inner($block, $data, false); ?></div>
        </div>
    <?php endforeach; ?>
</article>
</body>
</html>
    <?php
}

function hub_invoice_palette(): array
{
    return [
        ['type' => 'logo', 'label' => 'NIELIT logo'],
        ['type' => 'data', 'key' => 'org_header', 'label' => 'Organisation header'],
        ['type' => 'data', 'key' => 'title', 'label' => 'Document title'],
        ['type' => 'data', 'key' => 'invoice_number', 'label' => 'Invoice number'],
        ['type' => 'data', 'key' => 'payer_block', 'label' => 'Bill to'],
        ['type' => 'data', 'key' => 'issuer_block', 'label' => 'Issuer / address'],
        ['type' => 'data', 'key' => 'line_items', 'label' => 'Fee table'],
        ['type' => 'data', 'key' => 'context_block', 'label' => 'Course / details'],
        ['type' => 'data', 'key' => 'payment_meta', 'label' => 'Payment info'],
        ['type' => 'data', 'key' => 'footer', 'label' => 'Footer note'],
        ['type' => 'static', 'label' => 'Custom text box'],
    ];
}
