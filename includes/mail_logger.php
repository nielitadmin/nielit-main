<?php
require_once __DIR__ . '/../config/database.php';

// Ensure timezone consistency
date_default_timezone_set('Asia/Kolkata');

function ensureMailLogsTable(): bool
{
    global $conn;
    static $ready = null;
    if ($ready !== null) return $ready;
    if (!$conn) return false;

    $sql = "CREATE TABLE IF NOT EXISTS mail_send_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        profile_label VARCHAR(255) DEFAULT '',
        recipient VARCHAR(255) DEFAULT '',
        subject VARCHAR(255) DEFAULT '',
        status ENUM('ok','failed') NOT NULL DEFAULT 'failed',
        error TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_profile (profile_label(100)),
        INDEX idx_recipient (recipient)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $ready = (bool) $conn->query($sql);
    if (!$ready) error_log('ensureMailLogsTable failed: ' . $conn->error);
    return $ready;
}

function logMailSend(string $profileLabel, string $recipient, string $subject, string $status = 'failed', string $error = ''): bool
{
    global $conn;
    try {
        if (!$conn) return false;
        if (!ensureMailLogsTable()) return false;

        $stmt = $conn->prepare('INSERT INTO mail_send_logs (profile_label, recipient, subject, status, error) VALUES (?, ?, ?, ?, ?)');
        if (!$stmt) {
            error_log('logMailSend prepare failed: ' . $conn->error);
            return false;
        }
        $stmt->bind_param('sssss', $profileLabel, $recipient, $subject, $status, $error);
        $ok = $stmt->execute();
        if (!$ok) error_log('logMailSend execute failed: ' . $stmt->error);
        $stmt->close();
        return $ok;
    } catch (Throwable $e) {
        error_log('logMailSend exception: ' . $e->getMessage());
        return false;
    }
}

?>
