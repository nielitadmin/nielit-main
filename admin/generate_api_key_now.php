<?php
/**
 * INSTANT API KEY GENERATOR
 * No UI dependencies - just generates and shows the key
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/config/api_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    echo "ERROR: Please login as admin first at: admin/login.php";
    exit;
}

echo "=== NIELIT API KEY GENERATOR ===\n\n";

// Generate API key
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
        echo "SUCCESS! Your API Key has been generated:\n\n";
        echo "API KEY: " . $api_key . "\n\n";
        
        echo "=== HOW TO USE IN YOUR MOCK TEST APPLICATION ===\n\n";
        
        echo "1. STUDENT AUTHENTICATION:\n";
        echo "   URL: http://localhost/public_html/api/v1/auth.php\n";
        echo "   Method: POST\n";
        echo "   Headers: X-API-Key: " . $api_key . "\n";
        echo "   Body: {\"username\": \"STUDENT_ID\", \"password\": \"PASSWORD\"}\n\n";
        
        echo "2. GET STUDENT DATA:\n";
        echo "   URL: http://localhost/public_html/api/v1/students.php?action=get&student_id=STUDENT_ID&api_key=" . $api_key . "\n";
        echo "   Method: GET\n\n";
        
        echo "3. LIST ALL STUDENTS:\n";
        echo "   URL: http://localhost/public_html/api/v1/students.php?action=list&api_key=" . $api_key . "\n";
        echo "   Method: GET\n\n";
        
        echo "=== IMPORTANT NOTES ===\n";
        echo "- Copy this API key and save it securely\n";
        echo "- This key provides READ access to student data\n";
        echo "- Rate limit: 100 requests per hour\n";
        echo "- Use student_id as username for authentication\n\n";
        
        echo "=== NEXT STEPS ===\n";
        echo "1. Copy the API key above\n";
        echo "2. Configure your mock test application to use this key\n";
        echo "3. Test the API endpoints\n";
        echo "4. View full documentation at: api/docs.php\n\n";
        
    } else {
        echo "ERROR: Failed to create API key: " . $conn->error . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "=== END ===\n";
?>