<?php
/**
 * Online course fees: gateway settings, Razorpay orders, and paid invoices.
 */

require_once __DIR__ . '/payment_hub_helper.php';

if (!function_exists('payment_gateway_ensure_schema')) {
    function payment_gateway_ensure_schema($conn): void
    {
        static $done = false;
        if ($done || !($conn instanceof mysqli)) {
            return;
        }
        $done = true;

        $conn->query("CREATE TABLE IF NOT EXISTS payment_gateway_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(40) NOT NULL,
            display_name VARCHAR(80) NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 0,
            key_id VARCHAR(191) NOT NULL DEFAULT '',
            key_secret VARCHAR(255) NOT NULL DEFAULT '',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_payment_gateway_provider (provider)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS online_course_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_code VARCHAR(64) NOT NULL,
            student_record_id INT NOT NULL DEFAULT 0,
            course_id INT NOT NULL DEFAULT 0,
            course_name VARCHAR(255) NOT NULL DEFAULT '',
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency VARCHAR(8) NOT NULL DEFAULT 'INR',
            status VARCHAR(20) NOT NULL DEFAULT 'created',
            gateway VARCHAR(40) NOT NULL DEFAULT 'razorpay',
            gateway_order_id VARCHAR(80) DEFAULT NULL,
            gateway_payment_id VARCHAR(80) DEFAULT NULL,
            invoice_number VARCHAR(40) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME DEFAULT NULL,
            KEY idx_online_pay_student (student_code, course_id),
            KEY idx_online_pay_order (gateway_order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $payCols = [];
        $payColRes = $conn->query('SHOW COLUMNS FROM online_course_payments');
        if ($payColRes) {
            while ($pc = $payColRes->fetch_assoc()) {
                $payCols[$pc['Field']] = true;
            }
        }
        if (!isset($payCols['hub_payment_id'])) {
            $conn->query('ALTER TABLE online_course_payments ADD COLUMN hub_payment_id BIGINT NULL DEFAULT NULL AFTER gateway_payment_id');
        }
        if (!isset($payCols['hub_invoice_number'])) {
            $conn->query('ALTER TABLE online_course_payments ADD COLUMN hub_invoice_number VARCHAR(64) NULL DEFAULT NULL AFTER hub_payment_id');
        }

        payment_hub_ensure_schema($conn);

        $col = $conn->query("SHOW COLUMNS FROM courses LIKE 'online_payment_enabled'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE courses ADD COLUMN online_payment_enabled TINYINT(1) NOT NULL DEFAULT 0");
        }
        $receiveCol = $conn->query("SHOW COLUMNS FROM courses LIKE 'online_receive_amount'");
        if ($receiveCol && $receiveCol->num_rows === 0) {
            $conn->query("ALTER TABLE courses ADD COLUMN online_receive_amount INT NOT NULL DEFAULT 0");
        }

        foreach (payment_gateway_catalog() as $provider => $meta) {
            $stmt = $conn->prepare('SELECT id FROM payment_gateway_accounts WHERE provider = ? LIMIT 1');
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('s', $provider);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($existing) {
                continue;
            }
            $insert = $conn->prepare('INSERT INTO payment_gateway_accounts (provider, display_name, is_enabled) VALUES (?, ?, 0)');
            if ($insert) {
                $name = $meta['name'];
                $insert->bind_param('ss', $provider, $name);
                $insert->execute();
                $insert->close();
            }
        }
    }

    function payment_gateway_catalog(): array
    {
        return [
            'razorpay' => [
                'name' => 'Razorpay',
                'key_label' => 'Key ID',
                'secret_label' => 'Key Secret',
                'hint' => 'Razorpay Dashboard → Account & Settings → API Keys',
            ],
            'cashfree' => [
                'name' => 'Cashfree',
                'key_label' => 'App ID',
                'secret_label' => 'Secret Key',
                'hint' => 'Cashfree → Developers → API Keys',
            ],
            'payu' => [
                'name' => 'PayU',
                'key_label' => 'Merchant Key',
                'secret_label' => 'Merchant Salt',
                'hint' => 'PayU Dashboard → Payment Gateway → Key and Salt',
            ],
            'stripe' => [
                'name' => 'Stripe',
                'key_label' => 'Publishable key',
                'secret_label' => 'Secret key',
                'hint' => 'Stripe Dashboard → Developers → API keys',
            ],
            'instamojo' => [
                'name' => 'Instamojo',
                'key_label' => 'API Key',
                'secret_label' => 'Auth Token',
                'hint' => 'Instamojo → API & Plugins',
            ],
        ];
    }

    function payment_gateway_get(mysqli $conn, string $provider = 'razorpay'): ?array
    {
        payment_gateway_ensure_schema($conn);
        $stmt = $conn->prepare('SELECT * FROM payment_gateway_accounts WHERE provider = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $provider);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    function payment_gateway_active_razorpay(mysqli $conn): ?array
    {
        $row = payment_gateway_get($conn, 'razorpay');
        if (!$row || empty($row['is_enabled']) || trim((string) $row['key_id']) === '' || trim((string) $row['key_secret']) === '') {
            return null;
        }
        return $row;
    }

    function payment_gateway_list(mysqli $conn): array
    {
        payment_gateway_ensure_schema($conn);
        $rows = [];
        $result = $conn->query('SELECT * FROM payment_gateway_accounts ORDER BY id ASC');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[$row['provider']] = $row;
            }
        }
        $ordered = [];
        foreach (payment_gateway_catalog() as $provider => $meta) {
            $ordered[] = array_merge($meta, $rows[$provider] ?? [
                'provider' => $provider,
                'display_name' => $meta['name'],
                'is_enabled' => 0,
                'key_id' => '',
                'key_secret' => '',
            ], ['provider' => $provider]);
        }
        return $ordered;
    }

    function payment_gateway_active_list(mysqli $conn): array
    {
        $active = [];
        foreach (payment_gateway_list($conn) as $row) {
            if (!empty($row['is_enabled']) && trim((string) ($row['key_id'] ?? '')) !== '' && trim((string) ($row['key_secret'] ?? '')) !== '') {
                $active[] = $row;
            }
        }
        return $active;
    }

    function payment_gateway_save(mysqli $conn, string $provider, int $enabled, string $keyId, ?string $keySecret): bool
    {
        payment_gateway_ensure_schema($conn);
        if (!isset(payment_gateway_catalog()[$provider])) {
            return false;
        }
        $current = payment_gateway_get($conn, $provider);
        $secret = $keySecret !== null && $keySecret !== ''
            ? $keySecret
            : (string) ($current['key_secret'] ?? '');
        $stmt = $conn->prepare('UPDATE payment_gateway_accounts SET is_enabled = ?, key_id = ?, key_secret = ? WHERE provider = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('isss', $enabled, $keyId, $secret, $provider);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function payment_gateway_save_razorpay(mysqli $conn, int $enabled, string $keyId, ?string $keySecret): bool
    {
        return payment_gateway_save($conn, 'razorpay', $enabled, $keyId, $keySecret);
    }

    function payment_parse_money($value): float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }
        $clean = preg_replace('/[^\d.]/', '', (string) $value);
        if ($clean === null || $clean === '' || $clean === '.') {
            return 0.0;
        }
        return round((float) $clean, 2);
    }

    function payment_platform_percent(): float
    {
        return 2.4;
    }

    function payment_round_rupees($value): int
    {
        $text = trim((string) $value);
        if ($text === '' || !preg_match('/^\d+$/', $text)) {
            return 0;
        }
        return (int) $text;
    }

    function payment_charge_from_receive(int $receive): array
    {
        $percent = payment_platform_percent();
        $fee = round($receive * $percent / 100, 2);
        return [
            'receive' => $receive,
            'platform_percent' => $percent,
            'platform_fee' => $fee,
            'total' => round($receive + $fee, 2),
        ];
    }

    function payment_save_course_online_flag(mysqli $conn, int $courseId, int $enabled, $receiveAmount): void
    {
        if ($courseId <= 0) {
            return;
        }
        payment_gateway_ensure_schema($conn);
        $receive = payment_round_rupees($receiveAmount);
        if ($enabled === 1 && $receive < 1) {
            $enabled = 0;
        }
        $stmt = $conn->prepare('UPDATE courses SET online_payment_enabled = ?, online_receive_amount = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('iii', $enabled, $receive, $courseId);
            $stmt->execute();
            $stmt->close();
        }
    }

    function payment_render_online_amount_fields(string $checkboxId, string $panelId, bool $checked, int $receiveAmount): void
    {
        $percent = payment_platform_percent();
        $value = $receiveAmount > 0 ? (string) $receiveAmount : '';
        ?>
        <div class="form-check mt-2">
            <input class="form-check-input online-pay-toggle" type="checkbox" name="online_payment_enabled" id="<?php echo htmlspecialchars($checkboxId); ?>" value="1" data-panel="<?php echo htmlspecialchars($panelId); ?>" <?php echo $checked ? 'checked' : ''; ?>>
            <label class="form-check-label" for="<?php echo htmlspecialchars($checkboxId); ?>">Accept online payment for this course</label>
        </div>
        <div id="<?php echo htmlspecialchars($panelId); ?>" class="online-pay-panel" style="display:<?php echo $checked ? 'block' : 'none'; ?>; margin-top:10px; padding:12px 14px; border:1px solid #c9d7ea; border-radius:8px; background:#f4f8fc;">
            <label class="form-label" for="<?php echo htmlspecialchars($panelId); ?>_amount">Amount you want to receive (whole rupees only)</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" class="form-control online-receive-input" name="online_receive_amount" id="<?php echo htmlspecialchars($panelId); ?>_amount" value="<?php echo htmlspecialchars($value); ?>" placeholder="10" autocomplete="off" style="max-width:220px;">
            <div class="online-pay-preview text-muted" data-percent="<?php echo htmlspecialchars((string) $percent); ?>" style="margin-top:8px; font-size:13px;">
                Example: ₹10 + <?php echo htmlspecialchars((string) $percent); ?>% = ₹10.24 shown to the student.
            </div>
        </div>
        <?php
    }

    function payment_online_amount_script(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <script>
        (function () {
            function money(amount) {
                return '₹' + amount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function refresh(checkbox) {
                var panel = document.getElementById(checkbox.getAttribute('data-panel'));
                if (!panel) {
                    return;
                }
                panel.style.display = checkbox.checked ? 'block' : 'none';
                var input = panel.querySelector('.online-receive-input');
                var preview = panel.querySelector('.online-pay-preview');
                if (!input || !preview) {
                    return;
                }
                input.required = checkbox.checked;
                var digits = String(input.value || '').replace(/\D/g, '');
                if (input.value !== digits) {
                    input.value = digits;
                }
                var percent = parseFloat(preview.getAttribute('data-percent') || '2.4');
                var receive = parseInt(digits, 10);
                if (!receive) {
                    preview.textContent = 'Example: ₹10 + ' + percent + '% = ₹10.24. That ₹10.24 is what the student sees.';
                    return;
                }
                var fee = Math.round(receive * percent) / 100;
                var total = Math.round((receive + fee) * 100) / 100;
                preview.textContent = 'You receive ' + money(receive) + '. Platform fee ' + percent + '% is ' + money(fee) + '. Student pays ' + money(total) + '.';
            }
            document.querySelectorAll('.online-pay-toggle').forEach(function (checkbox) {
                checkbox.addEventListener('change', function () { refresh(checkbox); });
                var panel = document.getElementById(checkbox.getAttribute('data-panel'));
                if (panel) {
                    var input = panel.querySelector('.online-receive-input');
                    if (input) {
                        input.addEventListener('input', function () { refresh(checkbox); });
                    }
                }
                refresh(checkbox);
            });
            window.paymentRefreshOnlinePanel = refresh;
        })();
        </script>
        <?php
    }

    function payment_course_offer(mysqli $conn, int $courseId): array
    {
        payment_gateway_ensure_schema($conn);
        $empty = ['enabled' => false, 'amount' => 0.0, 'course_name' => '', 'course_id' => $courseId];
        if ($courseId <= 0) {
            return $empty;
        }
        $sql = 'SELECT id, course_name, online_payment_enabled, online_receive_amount FROM courses WHERE id = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $empty;
        }
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return $empty;
        }
        $receive = (int) ($row['online_receive_amount'] ?? 0);
        $charge = payment_charge_from_receive($receive);
        $enabled = (int) ($row['online_payment_enabled'] ?? 0) === 1 && $receive > 0;
        return [
            'enabled' => $enabled,
            'amount' => $charge['total'],
            'receive_amount' => $receive,
            'platform_fee' => $charge['platform_fee'],
            'platform_percent' => $charge['platform_percent'],
            'course_name' => (string) ($row['course_name'] ?? ''),
            'course_id' => (int) $row['id'],
        ];
    }

    function payment_student_paid_amount(mysqli $conn, string $studentCode, int $courseId): float
    {
        payment_gateway_ensure_schema($conn);
        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS paid
            FROM online_course_payments
            WHERE student_code = ? AND course_id = ? AND status = 'paid'");
        if (!$stmt) {
            return 0.0;
        }
        $stmt->bind_param('si', $studentCode, $courseId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (float) ($row['paid'] ?? 0);
    }

    function payment_student_status_label(mysqli $conn, string $studentCode, int $courseId): string
    {
        $offer = payment_course_offer($conn, $courseId);
        if (!$offer['enabled']) {
            return 'not_required';
        }
        return payment_student_paid_amount($conn, $studentCode, $courseId) + 0.009 >= $offer['amount']
            ? 'paid'
            : 'unpaid';
    }

    function payment_student_course_invoices(mysqli $conn, string $studentCode, int $courseId): array
    {
        payment_gateway_ensure_schema($conn);
        if ($studentCode === '' || $courseId <= 0) {
            return [];
        }
        $stmt = $conn->prepare("SELECT id, invoice_number, amount, paid_at, course_name, gateway
            FROM online_course_payments
            WHERE student_code = ? AND course_id = ? AND status = 'paid'
            ORDER BY paid_at DESC, id DESC");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('si', $studentCode, $courseId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows ?: [];
    }

    function payment_render_invoice_page(array $row, string $studentCode): void
    {
        $gateway = trim((string) ($row['gateway'] ?? ''));
        $gatewayLabel = $gateway !== '' ? ucfirst($gateway) : 'Online';
        $paidAt = (string) ($row['paid_at'] ?? '');
        $paidLabel = $paidAt !== '' ? date('d M Y, h:i A', strtotime($paidAt)) : '';
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo htmlspecialchars((string) ($row['invoice_number'] ?? '')); ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 32px; }
        h1 { margin-bottom: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        td { padding: 8px 0; vertical-align: top; }
        td:first-child { width: 160px; color: #64748b; }
        .box { border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print invoice</button>
    <div class="box">
        <h1>Fee invoice</h1>
        <p style="color:#64748b;">NIELIT Bhubaneswar</p>
        <table>
            <tr><td>Invoice</td><td><strong><?php echo htmlspecialchars((string) ($row['invoice_number'] ?? '')); ?></strong></td></tr>
            <tr><td>Student</td><td><?php echo htmlspecialchars((string) ($row['name'] ?? $studentCode)); ?> (<?php echo htmlspecialchars($studentCode); ?>)</td></tr>
            <tr><td>Course</td><td><?php echo htmlspecialchars((string) ($row['course_name'] ?? '')); ?></td></tr>
            <tr><td>Amount paid</td><td><strong>₹<?php echo number_format((float) ($row['amount'] ?? 0), 2); ?></strong></td></tr>
            <tr><td>Paid on</td><td><?php echo htmlspecialchars($paidLabel); ?></td></tr>
            <tr><td>Gateway</td><td><?php echo htmlspecialchars($gatewayLabel); ?></td></tr>
            <tr><td>Payment ID</td><td><?php echo htmlspecialchars((string) ($row['gateway_payment_id'] ?? '')); ?></td></tr>
            <tr><td>Status</td><td>Paid</td></tr>
        </table>
    </div>
</body>
</html>
        <?php
    }

    function payment_student_invoices(mysqli $conn, string $studentCode): array
    {
        payment_gateway_ensure_schema($conn);
        $stmt = $conn->prepare("SELECT * FROM online_course_payments
            WHERE student_code = ? AND status = 'paid'
            ORDER BY paid_at DESC, id DESC");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $studentCode);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows ?: [];
    }

    function payment_razorpay_request(string $keyId, string $keySecret, string $path, array $payload): array
    {
        $url = 'https://api.razorpay.com/v1/' . ltrim($path, '/');
        $body = json_encode($payload);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_USERPWD => $keyId . ':' . $keySecret,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 30,
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\nAuthorization: Basic " . base64_encode($keyId . ':' . $keySecret) . "\r\n",
                    'content' => $body,
                    'timeout' => 30,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = @file_get_contents($url, false, $context);
            $code = 0;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
                $code = (int) $match[1];
            }
        }
        $decoded = json_decode((string) $raw, true);
        return ['code' => $code, 'body' => is_array($decoded) ? $decoded : []];
    }

    function payment_create_razorpay_order(mysqli $conn, string $studentCode, int $recordId, int $courseId, string $studentName, string $email, string $phone): array
    {
        $gateway = payment_gateway_active_razorpay($conn);
        if (!$gateway) {
            return ['ok' => false, 'error' => 'Online payment is not configured yet. Please contact the centre.'];
        }
        $offer = payment_course_offer($conn, $courseId);
        if (!$offer['enabled']) {
            return ['ok' => false, 'error' => 'This course does not collect fees online.'];
        }
        $paid = payment_student_paid_amount($conn, $studentCode, $courseId);
        $due = round($offer['amount'] - $paid, 2);
        if ($due <= 0) {
            return ['ok' => false, 'error' => 'This course fee is already paid.'];
        }

        $paise = (int) round($due * 100);
        $receipt = 'crs' . $courseId . 's' . substr(preg_replace('/[^A-Za-z0-9]/', '', $studentCode), 0, 12);
        $response = payment_razorpay_request($gateway['key_id'], $gateway['key_secret'], 'orders', [
            'amount' => $paise,
            'currency' => 'INR',
            'receipt' => substr($receipt, 0, 40),
            'notes' => [
                'student_code' => $studentCode,
                'course_id' => (string) $courseId,
            ],
        ]);
        $orderId = (string) ($response['body']['id'] ?? '');
        if ($orderId === '' || $response['code'] < 200 || $response['code'] >= 300) {
            $message = (string) ($response['body']['error']['description'] ?? 'Could not start the payment.');
            return ['ok' => false, 'error' => $message];
        }

        $courseName = $offer['course_name'];
        $stmt = $conn->prepare("INSERT INTO online_course_payments
            (student_code, student_record_id, course_id, course_name, amount, status, gateway, gateway_order_id)
            VALUES (?, ?, ?, ?, ?, 'created', 'razorpay', ?)");
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save the payment order.'];
        }
        $stmt->bind_param('siisds', $studentCode, $recordId, $courseId, $courseName, $due, $orderId);
        $stmt->execute();
        $stmt->close();

        return [
            'ok' => true,
            'key_id' => (string) $gateway['key_id'],
            'order_id' => $orderId,
            'amount' => $paise,
            'currency' => 'INR',
            'name' => 'NIELIT Bhubaneswar',
            'description' => $courseName,
            'prefill' => [
                'name' => $studentName,
                'email' => $email,
                'contact' => $phone,
            ],
        ];
    }

    function payment_verify_razorpay(mysqli $conn, string $studentCode, string $orderId, string $paymentId, string $signature): array
    {
        $gateway = payment_gateway_active_razorpay($conn);
        if (!$gateway) {
            return ['ok' => false, 'error' => 'Payment gateway is not active.'];
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, (string) $gateway['key_secret']);
        if (!hash_equals($expected, $signature)) {
            return ['ok' => false, 'error' => 'Payment verification failed.'];
        }

        $stmt = $conn->prepare("SELECT * FROM online_course_payments WHERE gateway_order_id = ? AND student_code = ? LIMIT 1");
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Payment order was not found.'];
        }
        $stmt->bind_param('ss', $orderId, $studentCode);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return ['ok' => false, 'error' => 'Payment order was not found.'];
        }
        if (($row['status'] ?? '') === 'paid') {
            return ['ok' => true, 'invoice_id' => (int) $row['id'], 'already' => true];
        }

        $invoice = 'INV-' . date('Ymd') . '-' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT);
        $update = $conn->prepare("UPDATE online_course_payments
            SET status = 'paid', gateway_payment_id = ?, invoice_number = ?, paid_at = NOW()
            WHERE id = ? AND status <> 'paid'");
        if (!$update) {
            return ['ok' => false, 'error' => 'Could not record the payment.'];
        }
        $id = (int) $row['id'];
        $update->bind_param('ssi', $paymentId, $invoice, $id);
        $update->execute();
        $update->close();

        payment_mirror_legacy_payment($conn, $studentCode, (float) $row['amount'], $paymentId);

        return ['ok' => true, 'invoice_id' => $id];
    }

    function payment_mirror_legacy_payment(mysqli $conn, string $studentCode, float $amount, string $paymentId): void
    {
        $table = $conn->query("SHOW TABLES LIKE 'payments'");
        if (!$table || $table->num_rows === 0) {
            return;
        }
        $columns = [];
        $cols = $conn->query('SHOW COLUMNS FROM payments');
        if ($cols) {
            while ($col = $cols->fetch_assoc()) {
                $columns[$col['Field']] = true;
            }
        }
        if (!isset($columns['student_id'], $columns['amount'])) {
            return;
        }
        $fields = ['student_id', 'amount'];
        $types = 'sd';
        $values = [$studentCode, $amount];
        $optional = [
            'transaction_id' => [$paymentId, 's'],
            'payment_mode' => ['Online', 's'],
            'payment_date' => [date('Y-m-d'), 's'],
            'status' => ['paid', 's'],
        ];
        foreach ($optional as $field => $spec) {
            if (isset($columns[$field])) {
                $fields[] = $field;
                $types .= $spec[1];
                $values[] = $spec[0];
            }
        }
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $sql = 'INSERT INTO payments (' . implode(',', $fields) . ') VALUES (' . $placeholders . ')';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return;
        }
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
    }

    function payment_public_base(): string
    {
        return defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
    }

    function payment_http(string $method, string $url, array $headers, ?string $body): array
    {
        $headerLines = $headers;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_TIMEOUT => 30,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headerLines),
                    'content' => $body ?? '',
                    'timeout' => 30,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = @file_get_contents($url, false, $context);
            $code = 0;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
                $code = (int) $match[1];
            }
        }
        $decoded = json_decode((string) $raw, true);
        return ['code' => $code, 'raw' => (string) $raw, 'body' => is_array($decoded) ? $decoded : []];
    }

    function payment_insert_attempt(mysqli $conn, string $studentCode, int $recordId, int $courseId, string $courseName, float $amount, string $gateway, string $orderId): int
    {
        $stmt = $conn->prepare("INSERT INTO online_course_payments
            (student_code, student_record_id, course_id, course_name, amount, status, gateway, gateway_order_id)
            VALUES (?, ?, ?, ?, ?, 'created', ?, ?)");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('siisdss', $studentCode, $recordId, $courseId, $courseName, $amount, $gateway, $orderId);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    function payment_mark_paid_by_order(mysqli $conn, string $orderId, string $paymentId): array
    {
        $stmt = $conn->prepare('SELECT * FROM online_course_payments WHERE gateway_order_id = ? LIMIT 1');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Payment order was not found.'];
        }
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return ['ok' => false, 'error' => 'Payment order was not found.'];
        }
        if (($row['status'] ?? '') === 'paid') {
            return ['ok' => true, 'invoice_id' => (int) $row['id'], 'already' => true];
        }
        $invoice = 'INV-' . date('Ymd') . '-' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT);
        $update = $conn->prepare("UPDATE online_course_payments
            SET status = 'paid', gateway_payment_id = ?, invoice_number = ?, paid_at = NOW()
            WHERE id = ? AND status <> 'paid'");
        if (!$update) {
            return ['ok' => false, 'error' => 'Could not record the payment.'];
        }
        $id = (int) $row['id'];
        $update->bind_param('ssi', $paymentId, $invoice, $id);
        $update->execute();
        $update->close();
        payment_mirror_legacy_payment($conn, (string) $row['student_code'], (float) $row['amount'], $paymentId);
        return ['ok' => true, 'invoice_id' => $id];
    }

    function payment_due_for_checkout(mysqli $conn, string $provider, string $studentCode, int $courseId): array
    {
        $catalog = payment_gateway_catalog();
        $gateway = payment_gateway_get($conn, $provider);
        if (!$gateway || empty($gateway['is_enabled']) || trim((string) $gateway['key_id']) === '' || trim((string) $gateway['key_secret']) === '') {
            return ['ok' => false, 'error' => ($catalog[$provider]['name'] ?? 'This gateway') . ' is not switched on.'];
        }
        $offer = payment_course_offer($conn, $courseId);
        if (!$offer['enabled']) {
            return ['ok' => false, 'error' => 'This course does not collect fees online.'];
        }
        $due = round($offer['amount'] - payment_student_paid_amount($conn, $studentCode, $courseId), 2);
        if ($due <= 0) {
            return ['ok' => false, 'error' => 'This course fee is already paid.'];
        }
        return ['ok' => true, 'gateway' => $gateway, 'offer' => $offer, 'due' => $due];
    }

    function payment_start_checkout(mysqli $conn, string $provider, string $studentCode, int $recordId, int $courseId, string $studentName, string $email, string $phone): array
    {
        if ($provider === 'razorpay' && payment_hub_is_ready()) {
            return payment_hub_start_checkout($conn, $studentCode, $recordId, $courseId, $studentName, $email, $phone);
        }

        if ($provider === 'razorpay') {
            $order = payment_create_razorpay_order($conn, $studentCode, $recordId, $courseId, $studentName, $email, $phone);
            if (!empty($order['ok'])) {
                $order['mode'] = 'razorpay';
            }
            return $order;
        }

        $ready = payment_due_for_checkout($conn, $provider, $studentCode, $courseId);
        if (empty($ready['ok'])) {
            return $ready;
        }
        $gateway = $ready['gateway'];
        $due = (float) $ready['due'];
        $courseName = (string) $ready['offer']['course_name'];
        $base = payment_public_base();
        $returnUrl = $base . '/student/payment_return.php';
        $localRef = 'N' . date('ymdHis') . substr(preg_replace('/\D/', '', $studentCode), -4) . $courseId;

        if ($provider === 'stripe') {
            $attemptId = payment_insert_attempt($conn, $studentCode, $recordId, $courseId, $courseName, $due, 'stripe', $localRef);
            if ($attemptId <= 0) {
                return ['ok' => false, 'error' => 'Could not save the payment order.'];
            }
            $body = http_build_query([
                'mode' => 'payment',
                'success_url' => $returnUrl . '?gateway=stripe&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $base . '/student/fees.php',
                'client_reference_id' => (string) $attemptId,
                'customer_email' => $email,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => 'inr',
                'line_items[0][price_data][unit_amount]' => (int) round($due * 100),
                'line_items[0][price_data][product_data][name]' => $courseName,
            ]);
            $response = payment_http('POST', 'https://api.stripe.com/v1/checkout/sessions', [
                'Authorization: Bearer ' . $gateway['key_secret'],
                'Content-Type: application/x-www-form-urlencoded',
            ], $body);
            $sessionId = (string) ($response['body']['id'] ?? '');
            $url = (string) ($response['body']['url'] ?? '');
            if ($sessionId === '' || $url === '') {
                return ['ok' => false, 'error' => (string) ($response['body']['error']['message'] ?? 'Could not start Stripe checkout.')];
            }
            $update = $conn->prepare('UPDATE online_course_payments SET gateway_order_id = ? WHERE id = ?');
            if ($update) {
                $update->bind_param('si', $sessionId, $attemptId);
                $update->execute();
                $update->close();
            }
            return ['ok' => true, 'mode' => 'redirect', 'url' => $url];
        }

        if ($provider === 'cashfree') {
            $attemptId = payment_insert_attempt($conn, $studentCode, $recordId, $courseId, $courseName, $due, 'cashfree', $localRef);
            if ($attemptId <= 0) {
                return ['ok' => false, 'error' => 'Could not save the payment order.'];
            }
            $orderId = 'CF' . $attemptId . date('His');
            $update = $conn->prepare('UPDATE online_course_payments SET gateway_order_id = ? WHERE id = ?');
            if ($update) {
                $update->bind_param('si', $orderId, $attemptId);
                $update->execute();
                $update->close();
            }
            $sandbox = stripos((string) $gateway['key_id'], 'TEST') !== false;
            $api = $sandbox ? 'https://sandbox.cashfree.com/pg/orders' : 'https://api.cashfree.com/pg/orders';
            $response = payment_http('POST', $api, [
                'Content-Type: application/json',
                'x-api-version: 2023-08-01',
                'x-client-id: ' . $gateway['key_id'],
                'x-client-secret: ' . $gateway['key_secret'],
            ], json_encode([
                'order_id' => $orderId,
                'order_amount' => $due,
                'order_currency' => 'INR',
                'customer_details' => [
                    'customer_id' => substr(preg_replace('/[^A-Za-z0-9]/', '', $studentCode), 0, 20) ?: 'student',
                    'customer_name' => $studentName !== '' ? $studentName : 'Student',
                    'customer_email' => $email !== '' ? $email : 'student@nielitbhubaneswar.in',
                    'customer_phone' => preg_replace('/\D/', '', $phone) ?: '9999999999',
                ],
                'order_meta' => [
                    'return_url' => $returnUrl . '?gateway=cashfree&order_id={order_id}',
                ],
            ]));
            $session = (string) ($response['body']['payment_session_id'] ?? '');
            if ($session === '') {
                return ['ok' => false, 'error' => (string) ($response['body']['message'] ?? 'Could not start Cashfree checkout.')];
            }
            return ['ok' => true, 'mode' => 'cashfree', 'payment_session_id' => $session, 'sandbox' => $sandbox];
        }

        if ($provider === 'payu') {
            $txnid = 'PU' . date('ymdHis') . $courseId;
            $attemptId = payment_insert_attempt($conn, $studentCode, $recordId, $courseId, $courseName, $due, 'payu', $txnid);
            if ($attemptId <= 0) {
                return ['ok' => false, 'error' => 'Could not save the payment order.'];
            }
            $amount = number_format($due, 2, '.', '');
            $product = substr(preg_replace('/[|\r\n]/', ' ', $courseName), 0, 100);
            $first = substr(preg_replace('/[^A-Za-z ]/', '', $studentName), 0, 40) ?: 'Student';
            $buyerEmail = $email !== '' ? $email : 'student@nielitbhubaneswar.in';
            $hash = strtolower(hash('sha512', $gateway['key_id'] . '|' . $txnid . '|' . $amount . '|' . $product . '|' . $first . '|' . $buyerEmail . '|||||||||||' . $gateway['key_secret']));
            $sandbox = stripos((string) $gateway['key_id'], 'test') !== false;
            return [
                'ok' => true,
                'mode' => 'payu',
                'action' => $sandbox ? 'https://test.payu.in/_payment' : 'https://secure.payu.in/_payment',
                'fields' => [
                    'key' => (string) $gateway['key_id'],
                    'txnid' => $txnid,
                    'amount' => $amount,
                    'productinfo' => $product,
                    'firstname' => $first,
                    'email' => $buyerEmail,
                    'phone' => preg_replace('/\D/', '', $phone),
                    'surl' => $returnUrl . '?gateway=payu',
                    'furl' => $returnUrl . '?gateway=payu',
                    'hash' => $hash,
                ],
            ];
        }

        if ($provider === 'instamojo') {
            $digits = preg_replace('/\D/', '', $phone);
            if (strlen($digits) > 10) {
                $digits = substr($digits, -10);
            }
            if (strlen($digits) !== 10) {
                return ['ok' => false, 'error' => 'Add a 10-digit mobile number on the student profile before paying with Instamojo.'];
            }
            $requestId = 'IM' . date('ymdHis') . $courseId;
            $attemptId = payment_insert_attempt($conn, $studentCode, $recordId, $courseId, $courseName, $due, 'instamojo', $requestId);
            if ($attemptId <= 0) {
                return ['ok' => false, 'error' => 'Could not save the payment order.'];
            }
            $response = payment_http('POST', 'https://www.instamojo.com/api/1.1/payment-requests/', [
                'X-Api-Key: ' . $gateway['key_id'],
                'X-Auth-Token: ' . $gateway['key_secret'],
                'Content-Type: application/x-www-form-urlencoded',
            ], http_build_query([
                'purpose' => substr($courseName, 0, 100),
                'amount' => number_format($due, 2, '.', ''),
                'buyer_name' => $studentName !== '' ? $studentName : 'Student',
                'email' => $email !== '' ? $email : 'student@nielitbhubaneswar.in',
                'phone' => $digits,
                'redirect_url' => $returnUrl . '?gateway=instamojo',
                'allow_repeated_payments' => 'false',
                'send_email' => 'false',
                'send_sms' => 'false',
            ]));
            $request = $response['body']['payment_request'] ?? [];
            $longUrl = (string) ($request['longurl'] ?? '');
            $realId = (string) ($request['id'] ?? '');
            if ($longUrl === '' || $realId === '') {
                return ['ok' => false, 'error' => (string) ($response['body']['message'] ?? 'Could not start Instamojo checkout.')];
            }
            $update = $conn->prepare('UPDATE online_course_payments SET gateway_order_id = ? WHERE id = ?');
            if ($update) {
                $update->bind_param('si', $realId, $attemptId);
                $update->execute();
                $update->close();
            }
            return ['ok' => true, 'mode' => 'redirect', 'url' => $longUrl];
        }

        return ['ok' => false, 'error' => 'That payment gateway is not available.'];
    }

    function payment_finish_return(mysqli $conn, array $request): array
    {
        if (trim((string) ($request['hub_token'] ?? '')) !== '') {
            return payment_hub_finish_return($conn, $request);
        }

        $provider = strtolower(trim((string) ($request['gateway'] ?? '')));
        $gateway = payment_gateway_get($conn, $provider);
        if (!$gateway) {
            return ['ok' => false, 'error' => 'Payment could not be confirmed.'];
        }

        if ($provider === 'stripe') {
            $sessionId = trim((string) ($request['session_id'] ?? ''));
            $response = payment_http('GET', 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId), [
                'Authorization: Bearer ' . $gateway['key_secret'],
            ], null);
            if (($response['body']['payment_status'] ?? '') !== 'paid') {
                return ['ok' => false, 'error' => 'Stripe payment was not completed.'];
            }
            return payment_mark_paid_by_order($conn, $sessionId, (string) ($response['body']['payment_intent'] ?? $sessionId));
        }

        if ($provider === 'cashfree') {
            $orderId = trim((string) ($request['order_id'] ?? ''));
            $sandbox = stripos((string) $gateway['key_id'], 'TEST') !== false;
            $api = ($sandbox ? 'https://sandbox.cashfree.com/pg/orders/' : 'https://api.cashfree.com/pg/orders/') . rawurlencode($orderId);
            $response = payment_http('GET', $api, [
                'x-api-version: 2023-08-01',
                'x-client-id: ' . $gateway['key_id'],
                'x-client-secret: ' . $gateway['key_secret'],
            ], null);
            if (strtoupper((string) ($response['body']['order_status'] ?? '')) !== 'PAID') {
                return ['ok' => false, 'error' => 'Cashfree payment was not completed.'];
            }
            return payment_mark_paid_by_order($conn, $orderId, (string) ($response['body']['cf_order_id'] ?? $orderId));
        }

        if ($provider === 'payu') {
            $status = strtolower((string) ($request['status'] ?? ''));
            $txnid = (string) ($request['txnid'] ?? '');
            $postedHash = (string) ($request['hash'] ?? '');
            $salt = (string) $gateway['key_secret'];
            $key = (string) $gateway['key_id'];
            $reverse = $salt . '|' . ($request['status'] ?? '') . '|||||||||||' . ($request['email'] ?? '') . '|' . ($request['firstname'] ?? '') . '|' . ($request['productinfo'] ?? '') . '|' . ($request['amount'] ?? '') . '|' . $txnid . '|' . $key;
            $additional = trim((string) ($request['additionalCharges'] ?? ''));
            if ($additional !== '') {
                $reverse = $additional . '|' . $reverse;
            }
            $expected = strtolower(hash('sha512', $reverse));
            if ($status !== 'success' || !hash_equals($expected, strtolower($postedHash))) {
                return ['ok' => false, 'error' => 'PayU payment was not confirmed.'];
            }
            return payment_mark_paid_by_order($conn, $txnid, (string) ($request['mihpayid'] ?? $txnid));
        }

        if ($provider === 'instamojo') {
            $requestId = trim((string) ($request['payment_request_id'] ?? ''));
            $paymentId = trim((string) ($request['payment_id'] ?? ''));
            $response = payment_http('GET', 'https://www.instamojo.com/api/1.1/payment-requests/' . rawurlencode($requestId) . '/' . rawurlencode($paymentId) . '/', [
                'X-Api-Key: ' . $gateway['key_id'],
                'X-Auth-Token: ' . $gateway['key_secret'],
            ], null);
            $status = strtolower((string) ($response['body']['payment_request']['payment']['status'] ?? ''));
            if ($status !== 'credit') {
                return ['ok' => false, 'error' => 'Instamojo payment was not completed.'];
            }
            return payment_mark_paid_by_order($conn, $requestId, $paymentId);
        }

        return ['ok' => false, 'error' => 'Payment could not be confirmed.'];
    }

    function payment_student_build_online_fee_courses(mysqli $conn, string $studentCode, array $enrollmentRows): array
    {
        payment_gateway_ensure_schema($conn);
        $onlineFeeCourses = [];
        $seenOnlineCourse = [];
        foreach ($enrollmentRows as $erow) {
            $onlineCourseId = (int) ($erow['course_id'] ?? 0);
            if ($onlineCourseId <= 0 || isset($seenOnlineCourse[$onlineCourseId])) {
                continue;
            }
            $offer = payment_course_offer($conn, $onlineCourseId);
            if (empty($offer['enabled'])) {
                continue;
            }
            $onlineAmount = (float) $offer['amount'];
            if ($onlineAmount <= 0) {
                continue;
            }
            $seenOnlineCourse[$onlineCourseId] = true;
            $alreadyPaid = payment_student_paid_amount($conn, $studentCode, $onlineCourseId);
            $onlineFeeCourses[] = [
                'course_id' => $onlineCourseId,
                'record_id' => (int) ($erow['id'] ?? 0),
                'course_name' => (string) ($erow['course_name'] ?? 'Course'),
                'amount' => $onlineAmount,
                'receive_amount' => (int) ($offer['receive_amount'] ?? 0),
                'platform_fee' => (float) ($offer['platform_fee'] ?? 0),
                'platform_percent' => (float) ($offer['platform_percent'] ?? payment_platform_percent()),
                'paid' => $alreadyPaid,
                'due' => max(0, round($onlineAmount - $alreadyPaid, 2)),
            ];
        }
        return $onlineFeeCourses;
    }

    function payment_student_total_paid_reconciled(mysqli $conn, string $studentCode, array $paymentRecords): float
    {
        $total = 0.0;
        $seenTx = [];
        foreach ($paymentRecords as $p) {
            $total += (float) ($p['amount'] ?? 0);
            $tx = trim((string) ($p['transaction_id'] ?? ''));
            if ($tx !== '') {
                $seenTx[$tx] = true;
            }
        }
        payment_gateway_ensure_schema($conn);
        $stmt = $conn->prepare("SELECT amount, gateway_payment_id, invoice_number, hub_payment_id
            FROM online_course_payments WHERE student_code = ? AND status = 'paid'");
        if (!$stmt) {
            return $total;
        }
        $stmt->bind_param('s', $studentCode);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tx = trim((string) ($row['gateway_payment_id'] ?? ''));
            if ($tx === '' && (int) ($row['hub_payment_id'] ?? 0) > 0) {
                $tx = 'hub-' . (int) $row['hub_payment_id'];
            }
            if ($tx === '') {
                $tx = 'inv-' . trim((string) ($row['invoice_number'] ?? ''));
            }
            if ($tx !== '' && isset($seenTx[$tx])) {
                continue;
            }
            if ($tx !== '') {
                $seenTx[$tx] = true;
            }
            $total += (float) ($row['amount'] ?? 0);
        }
        $stmt->close();
        return $total;
    }

    function payment_student_online_payment_history(mysqli $conn, string $studentCode, int $limit = 30): array
    {
        payment_gateway_ensure_schema($conn);
        $limit = max(1, min(100, $limit));
        $stmt = $conn->prepare("SELECT * FROM online_course_payments
            WHERE student_code = ? ORDER BY id DESC LIMIT " . $limit);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $studentCode);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows ?: [];
    }

    function payment_student_online_status_meta(string $status): array
    {
        $status = strtolower(trim($status));
        $map = [
            'paid' => ['label' => 'Paid', 'class' => 'success', 'icon' => 'check-circle'],
            'created' => ['label' => 'Awaiting payment', 'class' => 'warning', 'icon' => 'hourglass-half'],
            'failed' => ['label' => 'Failed', 'class' => 'danger', 'icon' => 'times-circle'],
            'expired' => ['label' => 'Expired', 'class' => 'secondary', 'icon' => 'clock'],
        ];
        return $map[$status] ?? ['label' => ucfirst($status ?: 'Unknown'), 'class' => 'secondary', 'icon' => 'info-circle'];
    }

    function payment_student_expire_stale_online_attempts(mysqli $conn, string $studentCode, int $minutes = 45): void
    {
        payment_gateway_ensure_schema($conn);
        $minutes = max(15, min(180, $minutes));
        $stmt = $conn->prepare("UPDATE online_course_payments
            SET status = 'expired'
            WHERE student_code = ? AND status = 'created'
            AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        if ($stmt) {
            $stmt->bind_param('si', $studentCode, $minutes);
            $stmt->execute();
            $stmt->close();
        }
    }
}
