<?php
/**
 * Log Form Submission Data
 * NIELIT Bhubaneswar - Form Submission Logger
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log all form submissions
$log_file = __DIR__ . '/form_submission.log';
$timestamp = date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_entry = "\n=== FORM SUBMISSION LOG ===\n";
    $log_entry .= "Timestamp: $timestamp\n";
    $log_entry .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
    $log_entry .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n";
    
    $log_entry .= "\n--- POST DATA ---\n";
    foreach ($_POST as $key => $value) {
        if (is_array($value)) {
            $log_entry .= "$key: " . json_encode($value) . "\n";
        } else {
            // Don't log sensitive data in full
            if (in_array($key, ['password', 'aadhar'])) {
                $log_entry .= "$key: [REDACTED]\n";
            } else {
                $log_entry .= "$key: " . substr($value, 0, 100) . (strlen($value) > 100 ? '...' : '') . "\n";
            }
        }
    }
    
    $log_entry .= "\n--- FILES DATA ---\n";
    foreach ($_FILES as $key => $file) {
        if (is_array($file['name'])) {
            $log_entry .= "$key: Multiple files\n";
        } else {
            $log_entry .= "$key: " . $file['name'] . " (Size: " . $file['size'] . ", Error: " . $file['error'] . ")\n";
        }
    }
    
    $log_entry .= "\n--- SESSION DATA ---\n";
    session_start();
    foreach ($_SESSION as $key => $value) {
        if (is_string($value)) {
            $log_entry .= "$key: " . substr($value, 0, 100) . (strlen($value) > 100 ? '...' : '') . "\n";
        } else {
            $log_entry .= "$key: " . gettype($value) . "\n";
        }
    }
    
    $log_entry .= "\n=== END LOG ENTRY ===\n\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    echo "<h2>Form Submission Logged</h2>";
    echo "<p>Form submission has been logged to: $log_file</p>";
    echo "<p>Timestamp: $timestamp</p>";
    
    // Now forward to actual submission handler
    echo "<p>Forwarding to actual submission handler...</p>";
    echo "<script>setTimeout(function(){ window.location.href = 'submit_registration.php'; }, 2000);</script>";
    
} else {
    echo "<h2>Form Submission Logger</h2>";
    echo "<p>This script logs form submissions. Access via POST method.</p>";
    
    // Show recent logs if file exists
    if (file_exists($log_file)) {
        echo "<h3>Recent Submission Logs</h3>";
        $logs = file_get_contents($log_file);
        echo "<pre style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; border-radius: 5px; max-height: 400px; overflow-y: auto;'>";
        echo htmlspecialchars(substr($logs, -5000)); // Show last 5000 characters
        echo "</pre>";
    }
}
?>