<?php
require_once __DIR__ . '/../config/database.php';

echo "<h2>Creating OTP Logs Table</h2>\n";

// Create otp_logs table
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

if ($conn->query($sql) === TRUE) {
    echo "✅ OTP logs table created successfully<br>\n";
} else {
    echo "❌ Error creating otp_logs table: " . $conn->error . "<br>\n";
}

// Add cleanup event to automatically delete old logs (older than 24 hours)
$cleanup_event = "CREATE EVENT IF NOT EXISTS cleanup_otp_logs
ON SCHEDULE EVERY 1 HOUR
DO
DELETE FROM otp_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";

if ($conn->query($cleanup_event) === TRUE) {
    echo "✅ OTP cleanup event created successfully<br>\n";
} else {
    echo "⚠️ Note: Could not create cleanup event (may require SUPER privileges): " . $conn->error . "<br>\n";
}

echo "<br><strong>OTP Logs System Setup Complete!</strong><br>\n";
echo "You can now view OTP logs at: <a href='../admin/view_otp_logs.php'>admin/view_otp_logs.php</a><br>\n";

$conn->close();
?>