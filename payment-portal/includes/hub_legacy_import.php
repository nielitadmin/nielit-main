<?php

function hub_legacy_import_csv(mysqli $conn, string $csvText): array
{
    $lines = preg_split('/\r\n|\r|\n/', $csvText) ?: [];
    if ($lines === []) {
        return ['ok' => false, 'error' => 'Empty file'];
    }
    $header = str_getcsv(array_shift($lines));
    $map = [];
    foreach ($header as $i => $col) {
        $map[strtolower(trim($col))] = $i;
    }
    $required = ['source', 'reference', 'amount_inr', 'paid_at'];
    foreach ($required as $key) {
        if (!isset($map[$key])) {
            return ['ok' => false, 'error' => 'CSV must include columns: source, reference, amount_inr, paid_at'];
        }
    }

    $imported = 0;
    $skipped = 0;
    $errors = [];

    $col = static function (array $cols, array $map, string $key, string $default = ''): string {
        if (!isset($map[$key])) {
            return $default;
        }
        return trim((string) ($cols[$map[$key]] ?? $default));
    };

    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $cols = str_getcsv($line);
        $sourceCode = strtolower(trim((string) ($cols[$map['source']] ?? '')));
        $reference = trim((string) ($cols[$map['reference']] ?? ''));
        $amountInr = (float) ($cols[$map['amount_inr']] ?? 0);
        $paidAt = trim((string) ($cols[$map['paid_at']] ?? ''));
        if ($sourceCode === '' || $reference === '' || $amountInr <= 0 || $paidAt === '') {
            $errors[] = 'Line ' . ($lineNum + 2) . ': missing source, reference, amount, or date';
            continue;
        }
        $source = hub_source_by_code($conn, $sourceCode);
        if (!$source) {
            $errors[] = 'Line ' . ($lineNum + 2) . ': unknown source ' . $sourceCode;
            continue;
        }
        if (hub_reference_already_paid($conn, (int) $source['id'], $reference)) {
            $skipped++;
            continue;
        }

        $paise = (int) round($amountInr * 100);
        $payer = [
            'name' => $col($cols, $map, 'payer_name', 'Legacy import'),
            'email' => $col($cols, $map, 'payer_email'),
            'phone' => $col($cols, $map, 'payer_phone'),
            'external_id' => $col($cols, $map, 'payer_external_id'),
        ];
        $context = [
            'centre' => $col($cols, $map, 'centre'),
        ];
        $purpose = $col($cols, $map, 'purpose', 'Legacy fee');
        $description = $col($cols, $map, 'description');
        $invoiceNo = $col($cols, $map, 'invoice_number');
        if ($invoiceNo === '') {
            try {
                $invoiceNo = hub_invoice_next_number($conn);
            } catch (Throwable $e) {
                $errors[] = 'Line ' . ($lineNum + 2) . ': could not allocate invoice number';
                continue;
            }
        }

        $token = hub_checkout_token_new();
        $expires = gmdate('Y-m-d H:i:s', time() - 3600);
        $payerJson = json_encode($payer, JSON_UNESCAPED_UNICODE);
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
        $returnUrl = hub_source_prefixes_array($source)[0] ?? 'https://nielitbhubaneswar.in/';

        $conn->begin_transaction();
        $cStmt = $conn->prepare("INSERT INTO hub_checkouts (token, source_id, source_reference, amount_paise, currency, purpose, description, payer_json, context_json, return_url, notify_url, status, expires_at)
            VALUES (?, ?, ?, ?, 'INR', ?, ?, ?, ?, ?, '', 'paid', ?)");
        if (!$cStmt) {
            $conn->rollback();
            $errors[] = 'Line ' . ($lineNum + 2) . ': database error (checkout)';
            continue;
        }
        $sid = (int) $source['id'];
        $cStmt->bind_param('sisissssss', $token, $sid, $reference, $paise, $purpose, $description, $payerJson, $contextJson, $returnUrl, $expires);
        if (!$cStmt->execute()) {
            $cStmt->close();
            $conn->rollback();
            $errors[] = 'Line ' . ($lineNum + 2) . ': could not insert checkout';
            continue;
        }
        $checkoutId = (int) $cStmt->insert_id;
        $cStmt->close();

        $paidAtSql = date('Y-m-d H:i:s', strtotime($paidAt));
        $fy = hub_financial_year(new DateTime($paidAtSql));
        $pStmt = $conn->prepare("INSERT INTO hub_payments (checkout_id, source_id, source_reference, amount_paise, currency, purpose, description, payer_json, context_json, status, invoice_number, paid_at, is_legacy)
            VALUES (?, ?, ?, ?, 'INR', ?, ?, ?, ?, 'paid', ?, ?, 1)");
        if (!$pStmt) {
            $conn->rollback();
            $errors[] = 'Line ' . ($lineNum + 2) . ': database error (payment)';
            continue;
        }
        $pStmt->bind_param('iisissssss', $checkoutId, $sid, $reference, $paise, $purpose, $description, $payerJson, $contextJson, $invoiceNo, $paidAtSql);
        if (!$pStmt->execute()) {
            $pStmt->close();
            $conn->rollback();
            $errors[] = 'Line ' . ($lineNum + 2) . ': could not insert payment';
            continue;
        }
        $paymentId = (int) $pStmt->insert_id;
        $pStmt->close();

        $iStmt = $conn->prepare('INSERT INTO hub_invoices (payment_id, invoice_number, financial_year, issued_at) VALUES (?, ?, ?, ?)');
        if ($iStmt) {
            $iStmt->bind_param('isss', $paymentId, $invoiceNo, $fy, $paidAtSql);
            $iStmt->execute();
            $iStmt->close();
        }

        $conn->commit();
        $imported++;
    }

    return [
        'ok' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
    ];
}
