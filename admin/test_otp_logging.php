<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login_new.php');
    exit();
}
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'master_admin') {
    header('Location: dashboard.php');
    exit();
}

require_once '../includes/otp_logger.php';

echo "<h2>Testing OTP Logging System</h2>";

// Test logging a few sample OTPs
$test_otps = [
    ['email' => 'test1@example.com', 'otp' => '123456', 'purpose' => 'Test Login', 'username' => 'testuser1', 'status' => 'sent'],
    ['email' => 'test2@example.com', 'otp' => '789012', 'purpose' => 'Test Admin Creation', 'username' => 'testuser2', 'status' => 'sent'],
    ['email' => 'failed@example.com', 'otp' => '345678', 'purpose' => 'Test Failed', 'username' => 'testuser3', 'status' => 'failed'],
];

foreach ($test_otps as $test) {
    $result = logOTP($test['email'], $test['otp'], $test['purpose'], $test['username'], $test['status']);
    if ($result) {
        echo "✅ Successfully logged OTP: {$test['otp']} for {$test['email']}<br>";
    } else {
        echo "❌ Failed to log OTP: {$test['otp']} for {$test['email']}<br>";
    }
}

echo "<br><strong>Test Complete!</strong><br>";
echo "<a href='view_otp_logs.php'>View OTP Logs</a> | <a href='dashboard.php'>Back to Dashboard</a>";
?>