<?php

function hub_default_centre_name(): string
{
    return defined('HUB_DEFAULT_CENTRE') ? (string) HUB_DEFAULT_CENTRE : 'Bhubaneswar';
}

function hub_centre_code_to_name(string $code): string
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return '';
    }
    $map = [
        'BBSR' => 'Bhubaneswar',
        'BBS' => 'Bhubaneswar',
    ];
    return $map[$code] ?? $code;
}

function hub_infer_centre_from_external_id(string $externalId): string
{
    $externalId = trim($externalId);
    if ($externalId === '') {
        return '';
    }
    if (preg_match('/NIELIT\/\d{4}\/([A-Za-z]+)\//', $externalId, $m)) {
        return hub_centre_code_to_name((string) $m[1]);
    }
    return '';
}

function hub_checkout_normalize_context(array $context, array $payer): array
{
    $centre = trim((string) ($context['centre'] ?? $context['center'] ?? ''));
    if ($centre === '') {
        $centre = hub_infer_centre_from_external_id((string) ($payer['external_id'] ?? $payer['student_id'] ?? ''));
    }
    if ($centre === '') {
        $centre = hub_default_centre_name();
    }
    $context['centre'] = $centre;
    unset($context['center']);
    return $context;
}

function hub_payment_centre_label(array $row): string
{
    $ctx = json_decode((string) ($row['context_json'] ?? '{}'), true);
    if (!is_array($ctx)) {
        $ctx = [];
    }
    $centre = trim((string) ($ctx['centre'] ?? $ctx['center'] ?? ''));
    if ($centre !== '') {
        return $centre;
    }
    $payer = json_decode((string) ($row['payer_json'] ?? '{}'), true);
    if (!is_array($payer)) {
        $payer = [];
    }
    $inferred = hub_infer_centre_from_external_id((string) ($payer['external_id'] ?? $payer['student_id'] ?? ''));
    if ($inferred !== '') {
        return $inferred;
    }
    return hub_default_centre_name();
}

function hub_schema_backfill_payment_centre(mysqli $conn): void
{
    if (hub_setting_get($conn, 'centre_backfill_v1') === '1') {
        return;
    }
    $res = $conn->query('SELECT id, context_json, payer_json FROM hub_payments');
    if (!$res) {
        return;
    }
    $stmt = $conn->prepare('UPDATE hub_payments SET context_json = ? WHERE id = ?');
    if (!$stmt) {
        return;
    }
    while ($row = $res->fetch_assoc()) {
        $ctx = json_decode((string) ($row['context_json'] ?? '{}'), true);
        if (!is_array($ctx)) {
            $ctx = [];
        }
        $centre = trim((string) ($ctx['centre'] ?? $ctx['center'] ?? ''));
        if ($centre !== '') {
            continue;
        }
        $payer = json_decode((string) ($row['payer_json'] ?? '{}'), true);
        if (!is_array($payer)) {
            $payer = [];
        }
        $ctx = hub_checkout_normalize_context($ctx, $payer);
        $json = json_encode($ctx, JSON_UNESCAPED_UNICODE);
        $id = (int) $row['id'];
        $stmt->bind_param('si', $json, $id);
        $stmt->execute();
    }
    $stmt->close();
    hub_setting_set($conn, 'centre_backfill_v1', '1');
}
