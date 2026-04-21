<?php
/**
 * Super Simple API Key Generator
 * Just creates an API key without fancy UI
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/config/api_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    echo "Please login as admin first";
    exit;
}

echo "<h1>API Key Generator</h1>";

// Create API key automatically
$name = 'Mock Test Application';
$description = 'API access for mock test system';
$permissions = 'read';
$rate_limit = 100;
$admin_id = $_SESSION['admin'];

try {
    $api_key = generateApiKey();
    $api_key_hash = hashApiKey($api_key);
    
    $stmt = $conn->prepare("
        INSERT INTO api_keys (name, description, api_key_hash, permissions, rate_limit, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("ssssis", $name, $description, $api_key_hash, $permissions, $rate_limit, $admin_id);
    
    if ($stmt->execute()) {
        echo "<h2>✅ SUCCESS! Your API Key:</h2>";
        echo "<div style='background: #f0f0f0; padding: 20px; font-family: monospace; font-size: 14px; word-break: break-all; border: 2px solid green;'>";
        echo $api_key;
        echo "</div>";
        
        echo "<h3>How to Use:</h3>";
        echo "<p><strong>Student Authentication:</strong></p>";
        echo "<pre>POST http://localhost/public_html/api/v1/auth.php
Headers: X-API-Key: " . $api_key . "
Body: {\"username\": \"student_id\", \"password\": \"password\"}</pre>";
        
        echo "<p><strong>Get Student Data:</strong></p>";
        echo "<pre>GET http://localhost/public_html/api/v1/students.php?action=get&student_id=STUDENT_ID&api_key=" . $api_key . "</pre>";
        
        echo "<h3>⚠️ IMPORTANT:</h3>";
        echo "<ul>";
        echo "<li>Copy this API key now - it won't be shown again!</li>";
        echo "<li>Keep it secure and don't share it publicly</li>";
        echo "<li>Use it in your mock test application</li>";
        echo "</ul>";
        
    } else {
        echo "<h2>❌ ERROR:</h2>";
        echo "<p>Failed to create API key: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<h2>❌ ERROR:</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>