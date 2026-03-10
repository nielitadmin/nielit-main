<?php
require_once __DIR__ . '/../config/database.php';

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
        $stmt = $conn->prepare("INSERT INTO otp_logs (email, otp_code, purpose, username, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $email, $otp_code, $purpose, $username, $status);
        return $stmt->execute();
    } catch (Exception $e) {
        // Silently fail if logging doesn't work - don't break the main functionality
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
        $conn->query("DELETE FROM otp_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>