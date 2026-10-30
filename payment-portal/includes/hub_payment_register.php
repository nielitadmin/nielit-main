<?php

function hub_payments_filters_from_request(array $input): array
{
    return [
        'date_from' => trim((string) ($input['date_from'] ?? '')),
        'date_to' => trim((string) ($input['date_to'] ?? '')),
        'source' => strtolower(trim((string) ($input['source'] ?? ''))),
        'centre' => trim((string) ($input['centre'] ?? '')),
        'status' => strtolower(trim((string) ($input['status'] ?? ''))),
        'q' => trim((string) ($input['q'] ?? '')),
    ];
}

function hub_payments_where_clause(array $filters): array
{
    $where = ['1=1'];
    $types = '';
    $params = [];

    if ($filters['date_from'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
        $where[] = 'COALESCE(p.paid_at, p.created_at) >= ?';
        $types .= 's';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if ($filters['date_to'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
        $where[] = 'COALESCE(p.paid_at, p.created_at) <= ?';
        $types .= 's';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    if ($filters['source'] !== '') {
        $where[] = 's.code = ?';
        $types .= 's';
        $params[] = $filters['source'];
    }
    if ($filters['centre'] !== '') {
        $where[] = "JSON_UNQUOTE(JSON_EXTRACT(p.context_json, '$.centre')) LIKE ?";
        $types .= 's';
        $params[] = '%' . $filters['centre'] . '%';
    }
    if ($filters['status'] !== '') {
        $where[] = 'p.status = ?';
        $types .= 's';
        $params[] = $filters['status'];
    }
    if ($filters['q'] !== '') {
        $like = '%' . $filters['q'] . '%';
        $where[] = '(p.source_reference LIKE ? OR p.invoice_number LIKE ? OR p.razorpay_payment_id LIKE ?
            OR p.payer_json LIKE ? OR p.purpose LIKE ? OR p.description LIKE ?)';
        $types .= 'ssssss';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    return [implode(' AND ', $where), $types, $params];
}

function hub_payments_search(mysqli $conn, array $filters, int $limit = 200, int $offset = 0): array
{
    [$sqlWhere, $types, $params] = hub_payments_where_clause($filters);
    $limit = max(1, min(500, $limit));
    $offset = max(0, $offset);
    $sql = "SELECT p.*, s.code AS source_code, s.display_name AS source_name
        FROM hub_payments p
        INNER JOIN hub_sources s ON s.id = p.source_id
        WHERE {$sqlWhere}
        ORDER BY p.id DESC
        LIMIT {$limit} OFFSET {$offset}";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function hub_payments_count(mysqli $conn, array $filters): int
{
    [$sqlWhere, $types, $params] = hub_payments_where_clause($filters);
    $sql = "SELECT COUNT(*) AS c FROM hub_payments p INNER JOIN hub_sources s ON s.id = p.source_id WHERE {$sqlWhere}";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($row['c'] ?? 0);
}

function hub_payment_context_field(array $row, string $key, string $default = ''): string
{
    $ctx = json_decode((string) ($row['context_json'] ?? '{}'), true);
    if (!is_array($ctx) || !isset($ctx[$key])) {
        return $default;
    }
    return trim((string) $ctx[$key]);
}

function hub_payment_payer_field(array $row, string $key, string $default = ''): string
{
    $payer = json_decode((string) ($row['payer_json'] ?? '{}'), true);
    if (!is_array($payer) || !isset($payer[$key])) {
        return $default;
    }
    return trim((string) $payer[$key]);
}

function hub_payments_export_csv(mysqli $conn, array $filters): void
{
    $rows = hub_payments_search($conn, $filters, 5000, 0);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="hub-payments-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    if (!$out) {
        return;
    }
    fputcsv($out, [
        'receipt_number',
        'date',
        'source',
        'reference',
        'payer_name',
        'payer_email',
        'payer_external_id',
        'purpose',
        'description',
        'centre',
        'collected_inr',
        'institute_fee_inr',
        'platform_fee_inr',
        'razorpay_fee_inr',
        'razorpay_gst_inr',
        'centre_bank_inr',
        'razorpay_net_settlement_inr',
        'method',
        'razorpay_payment_id',
        'status',
        'legacy',
    ]);
    foreach ($rows as $r) {
        $bd = hub_payment_breakdown($r);
        $date = (string) ($r['paid_at'] ?? $r['created_at'] ?? '');
        if ($date !== '') {
            $date = substr($date, 0, 10);
        }
        fputcsv($out, [
            (string) ($r['invoice_number'] ?? ''),
            $date,
            (string) ($r['source_code'] ?? ''),
            (string) ($r['source_reference'] ?? ''),
            hub_payment_payer_field($r, 'name'),
            hub_payment_payer_field($r, 'email'),
            hub_payment_payer_field($r, 'external_id'),
            (string) ($r['purpose'] ?? ''),
            (string) ($r['description'] ?? ''),
            hub_payment_centre_label($r),
            number_format($bd['collected_paise'] / 100, 2, '.', ''),
            number_format($bd['fee_receive_paise'] / 100, 2, '.', ''),
            number_format($bd['platform_fee_paise'] / 100, 2, '.', ''),
            number_format($bd['razorpay_fee_paise'] / 100, 2, '.', ''),
            number_format($bd['razorpay_tax_paise'] / 100, 2, '.', ''),
            number_format($bd['centre_bank_paise'] / 100, 2, '.', ''),
            number_format($bd['razorpay_net_settlement_paise'] / 100, 2, '.', ''),
            (string) ($r['payment_method'] ?? ''),
            (string) ($r['razorpay_payment_id'] ?? ''),
            (string) ($r['status'] ?? ''),
            !empty($r['is_legacy']) ? 'yes' : 'no',
        ]);
    }
    fclose($out);
}
