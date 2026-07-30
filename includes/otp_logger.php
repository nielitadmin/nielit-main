<?php
require_once __DIR__ . '/../config/database.php';

// Set timezone to Indian Standard Time for consistent logging
date_default_timezone_set('Asia/Kolkata');

/**
 * Ensure otp_logs table exists (safe no-op if already present).
 */
function ensureOtpLogsTable(): bool
{
    global $conn;
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    if (!$conn) {
        $ready = false;
        return false;
    }

    $sql = "CREATE TABLE IF NOT EXISTS otp_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        otp_code VARCHAR(10) NOT NULL,
        purpose VARCHAR(100) NOT NULL DEFAULT 'Login',
        username VARCHAR(100) NULL,
        status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_created_at (created_at)
    )";

    $ready = (bool) $conn->query($sql);
    if (!$ready) {
        error_log('ensureOtpLogsTable failed: ' . $conn->error);
    }
    return $ready;
}

/**
 * Log OTP code to database for debugging purposes
 *
 * @param string $email Email address where OTP was sent
 * @param string $otp_code The OTP code that was generated
 * @param string $purpose Purpose of the OTP (Login, Admin Creation, etc.)
 * @param string $username Optional username associated with the OTP
 * @param string $status Status of the OTP sending (sent/failed)
 * @return bool Success status
 */
function logOTP($email, $otp_code, $purpose = 'Login', $username = null, $status = 'sent') {
    global $conn;

    try {
        if (!$conn) {
            return false;
        }

        if (!ensureOtpLogsTable()) {
            return false;
        }

        // Set MySQL timezone to IST for consistent timestamps
        $conn->query("SET time_zone = '+05:30'");

        $stmt = $conn->prepare("INSERT INTO otp_logs (email, otp_code, purpose, username, status) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            error_log('logOTP prepare failed: ' . $conn->error);
            return false;
        }

        $stmt->bind_param("sssss", $email, $otp_code, $purpose, $username, $status);
        $ok = $stmt->execute();
        if (!$ok) {
            error_log('logOTP execute failed: ' . $stmt->error);
            // file fallback
            @file_put_contents(__DIR__ . '/../storage/logs/otp_debug.log', '['.date('Y-m-d H:i:s').'] DB insert failed: '. $stmt->error ." | data: ".json_encode([$email,$otp_code,$purpose,$username,$status]).PHP_EOL, FILE_APPEND | LOCK_EX);
            $stmt->close();
            return false;
        }

        $insertId = $stmt->insert_id;
        $stmt->close();

        // Return inserted id so callers can update status later. Returns int on success, false on failure.
        return $insertId ?: false;
    } catch (Throwable $e) {
        // Never break login/mail flow because of OTP logging.
        error_log('logOTP exception: ' . $e->getMessage());
        return false;
    }
}

function updateOtpStatus($id, $status) {
    global $conn;
    try {
        if (!$conn || empty($id)) return false;
        $stmt = $conn->prepare("UPDATE otp_logs SET status = ? WHERE id = ?");
        if (!$stmt) {
            error_log('updateOtpStatus prepare failed: ' . $conn->error);
            return false;
        }
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        if (!$ok) error_log('updateOtpStatus execute failed: ' . $stmt->error);
        $stmt->close();
        return $ok;
    } catch (Throwable $e) {
        error_log('updateOtpStatus exception: ' . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced OTP generation with logging
 *
 * @param int $length Length of OTP (default 6)
 * @return string Generated OTP
 */
function generate_otp_with_logging($length = 6) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Clean up old OTP logs (older than 24 hours)
 * This can be called periodically if the MySQL event doesn't work
 */
function cleanupOTPLogs() {
    global $conn;

    try {
        if (!$conn || !ensureOtpLogsTable()) {
            return false;
        }
        $conn->query("DELETE FROM otp_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
