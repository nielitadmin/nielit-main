<?php

function hub_schema_ensure(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $conn->query("CREATE TABLE IF NOT EXISTS hub_sources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(32) NOT NULL,
        display_name VARCHAR(120) NOT NULL,
        allowed_return_prefixes TEXT NOT NULL,
        shared_secret VARCHAR(128) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_hub_source_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS hub_settings (
        setting_key VARCHAR(64) PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS hub_admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(64) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_hub_admin_user (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS hub_checkouts (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        token CHAR(64) NOT NULL,
        source_id INT NOT NULL,
        source_reference VARCHAR(120) NOT NULL,
        amount_paise INT NOT NULL,
        currency CHAR(3) NOT NULL DEFAULT 'INR',
        purpose VARCHAR(180) NOT NULL DEFAULT '',
        description VARCHAR(500) NOT NULL DEFAULT '',
        payer_json TEXT NOT NULL,
        context_json TEXT NOT NULL,
        return_url VARCHAR(500) NOT NULL,
        notify_url VARCHAR(500) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'created',
        razorpay_order_id VARCHAR(80) DEFAULT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_hub_checkout_token (token),
        KEY idx_hub_checkout_status (status),
        KEY idx_hub_checkout_order (razorpay_order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS hub_payments (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        checkout_id BIGINT NOT NULL,
        source_id INT NOT NULL,
        source_reference VARCHAR(120) NOT NULL,
        amount_paise INT NOT NULL,
        currency CHAR(3) NOT NULL DEFAULT 'INR',
        purpose VARCHAR(180) NOT NULL DEFAULT '',
        description VARCHAR(500) NOT NULL DEFAULT '',
        payer_json TEXT NOT NULL,
        context_json TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'paid',
        razorpay_order_id VARCHAR(80) DEFAULT NULL,
        razorpay_payment_id VARCHAR(80) DEFAULT NULL,
        payment_method VARCHAR(40) DEFAULT NULL,
        invoice_number VARCHAR(64) DEFAULT NULL,
        paid_at DATETIME DEFAULT NULL,
        fee_receive_paise INT NOT NULL DEFAULT 0,
        platform_fee_paise INT NOT NULL DEFAULT 0,
        razorpay_fee_paise INT NOT NULL DEFAULT 0,
        razorpay_tax_paise INT NOT NULL DEFAULT 0,
        settlement_paise INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_hub_pay_source (source_id, source_reference),
        KEY idx_hub_pay_invoice (invoice_number),
        KEY idx_hub_pay_rzp (razorpay_payment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS hub_invoices (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        payment_id BIGINT NOT NULL,
        invoice_number VARCHAR(64) NOT NULL,
        financial_year VARCHAR(9) NOT NULL,
        issued_at DATETIME NOT NULL,
        pdf_path VARCHAR(255) DEFAULT NULL,
        UNIQUE KEY uq_hub_invoice_no (invoice_number),
        KEY idx_hub_invoice_pay (payment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS hub_webhook_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        event_id VARCHAR(80) NOT NULL,
        event_type VARCHAR(80) NOT NULL,
        payload_json MEDIUMTEXT NOT NULL,
        processed TINYINT(1) NOT NULL DEFAULT 0,
        received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_hub_webhook_event (event_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS hub_invoice_sequences (
        financial_year VARCHAR(9) PRIMARY KEY,
        last_number INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS hub_credit_note_sequences (
        financial_year VARCHAR(9) PRIMARY KEY,
        last_number INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS hub_refunds (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        payment_id BIGINT NOT NULL,
        razorpay_refund_id VARCHAR(80) NOT NULL,
        amount_paise INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        credit_note_number VARCHAR(64) DEFAULT NULL,
        reason VARCHAR(255) NOT NULL DEFAULT '',
        initiated_by VARCHAR(64) NOT NULL DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME DEFAULT NULL,
        KEY idx_hub_refund_payment (payment_id),
        UNIQUE KEY uq_hub_rzp_refund (razorpay_refund_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS hub_credit_notes (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        payment_id BIGINT NOT NULL,
        refund_id BIGINT NOT NULL,
        credit_note_number VARCHAR(64) NOT NULL,
        financial_year VARCHAR(9) NOT NULL,
        amount_paise INT NOT NULL,
        issued_at DATETIME NOT NULL,
        UNIQUE KEY uq_hub_credit_note (credit_note_number),
        KEY idx_hub_cn_payment (payment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    hub_schema_migrate_columns($conn);

    hub_schema_seed_defaults($conn);
}

function hub_schema_migrate_columns(mysqli $conn): void
{
    $cols = [];
    $res = $conn->query('SHOW COLUMNS FROM hub_payments');
    if ($res) {
        while ($c = $res->fetch_assoc()) {
            $cols[$c['Field']] = true;
        }
    }
    if (!isset($cols['refunded_amount_paise'])) {
        $conn->query('ALTER TABLE hub_payments ADD COLUMN refunded_amount_paise INT NOT NULL DEFAULT 0 AFTER paid_at');
    }
    if (!isset($cols['is_legacy'])) {
        $conn->query('ALTER TABLE hub_payments ADD COLUMN is_legacy TINYINT(1) NOT NULL DEFAULT 0 AFTER refunded_amount_paise');
    }
    if (!isset($cols['fee_receive_paise'])) {
        $conn->query('ALTER TABLE hub_payments ADD COLUMN fee_receive_paise INT NOT NULL DEFAULT 0 AFTER is_legacy');
    }
    if (!isset($cols['platform_fee_paise'])) {
        $conn->query('ALTER TABLE hub_payments ADD COLUMN platform_fee_paise INT NOT NULL DEFAULT 0 AFTER fee_receive_paise');
    }
    if (!isset($cols['razorpay_fee_paise'])) {
        $conn->query('ALTER TABLE hub_payments ADD COLUMN razorpay_fee_paise INT NOT NULL DEFAULT 0 AFTER platform_fee_paise');
    }
    if (!isset($cols['razorpay_tax_paise'])) {
        $conn->query('ALTER TABLE hub_payments ADD COLUMN razorpay_tax_paise INT NOT NULL DEFAULT 0 AFTER razorpay_fee_paise');
    }
    if (!isset($cols['settlement_paise'])) {
        $conn->query('ALTER TABLE hub_payments ADD COLUMN settlement_paise INT NOT NULL DEFAULT 0 AFTER razorpay_tax_paise');
    }
}

function hub_schema_seed_defaults(mysqli $conn): void
{
    global $hub_secrets;

    $count = $conn->query('SELECT COUNT(*) AS c FROM hub_sources');
    $row = $count ? $count->fetch_assoc() : null;
    if ($row && (int) $row['c'] === 0) {
        $misSecret = bin2hex(random_bytes(24));
        $mockSecret = bin2hex(random_bytes(24));
        $prefixesMis = json_encode(['https://nielitbhubaneswar.in/', 'http://localhost/public_html/']);
        $prefixesMock = json_encode(['https://mock.nielitbhubaneswar.in/', 'http://localhost/']);
        $stmt = $conn->prepare('INSERT INTO hub_sources (code, display_name, allowed_return_prefixes, shared_secret) VALUES (?, ?, ?, ?), (?, ?, ?, ?)');
        if ($stmt) {
            $mis = 'mis';
            $misName = 'MIS (nielitbhubaneswar.in)';
            $mock = 'mock';
            $mockName = 'Mock test site';
            $stmt->bind_param('ssssssss', $mis, $misName, $prefixesMis, $misSecret, $mock, $mockName, $prefixesMock, $mockSecret);
            $stmt->execute();
            $stmt->close();
        }
    }

    $adminCount = $conn->query('SELECT COUNT(*) AS c FROM hub_admin_users');
    $ar = $adminCount ? $adminCount->fetch_assoc() : null;
    if ($ar && (int) $ar['c'] === 0 && is_array($hub_secrets)) {
        $user = (string) ($hub_secrets['admin_username'] ?? 'hubadmin');
        $pass = (string) ($hub_secrets['admin_password'] ?? 'ChangeMeOnFirstLogin!');
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO hub_admin_users (username, password_hash) VALUES (?, ?)');
        if ($stmt) {
            $stmt->bind_param('ss', $user, $hash);
            $stmt->execute();
            $stmt->close();
        }
    }
}
