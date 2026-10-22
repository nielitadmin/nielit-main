<?php
/**
 * Online course fees: gateway settings, Razorpay orders, and paid invoices.
 */

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

        $col = $conn->query("SHOW COLUMNS FROM courses LIKE 'online_payment_enabled'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE courses ADD COLUMN online_payment_enabled TINYINT(1) NOT NULL DEFAULT 0");
        }

        $exists = $conn->query("SELECT id FROM payment_gateway_accounts WHERE provider = 'razorpay' LIMIT 1");
        if ($exists && $exists->num_rows === 0) {
            $conn->query("INSERT INTO payment_gateway_accounts (provider, display_name, is_enabled) VALUES ('razorpay', 'Razorpay', 0)");
        }
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

    function payment_gateway_save_razorpay(mysqli $conn, int $enabled, string $keyId, ?string $keySecret): bool
    {
        payment_gateway_ensure_schema($conn);
        $current = payment_gateway_get($conn, 'razorpay');
        $secret = $keySecret !== null && $keySecret !== ''
            ? $keySecret
            : (string) ($current['key_secret'] ?? '');
        $stmt = $conn->prepare('UPDATE payment_gateway_accounts SET is_enabled = ?, key_id = ?, key_secret = ? WHERE provider = ?');
        if (!$stmt) {
            return false;
        }
        $provider = 'razorpay';
        $stmt->bind_param('isss', $enabled, $keyId, $secret, $provider);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function payment_course_offer(mysqli $conn, int $courseId): array
    {
        payment_gateway_ensure_schema($conn);
        $empty = ['enabled' => false, 'amount' => 0.0, 'course_name' => '', 'course_id' => $courseId];
        if ($courseId <= 0) {
            return $empty;
        }
        $stmt = $conn->prepare('SELECT id, course_name, fees, online_payment_enabled FROM courses WHERE id = ? LIMIT 1');
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
        $amount = (float) ($row['fees'] ?? 0);
        $enabled = (int) ($row['online_payment_enabled'] ?? 0) === 1 && $amount > 0;
        return [
            'enabled' => $enabled,
            'amount' => $amount,
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
            'payment_mode' => ['Razorpay', 's'],
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
}
