<?php
/**
 * Simple API Key Creator
 * Creates an API key without complex UI
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/config/api_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    die("Please login as admin first");
}

$api_key_created = '';
$error = '';

// Create API key if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_key'])) {
    $name = $_POST['name'] ?? 'Mock Test Application';
    $description = $_POST['description'] ?? 'API access for mock test system';
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
            $api_key_created = $api_key;
        } else {
            $error = "Failed to create API key: " . $conn->error;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create API Key - NIELIT</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 600px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #f5c6cb; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .api-key { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; word-break: break-all; border: 2px solid #28a745; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ffeaa7; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔑 Create API Key</h1>
        <p>Create an API key for your mock test application to access student data.</p>
        
        <?php if ($error): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($api_key_created): ?>
            <div class="success">
                <h3>✅ API Key Created Successfully!</h3>
                <div class="warning">
                    <strong>⚠️ IMPORTANT:</strong> Copy this API key now - it will not be shown again!
                </div>
                <div class="api-key">
                    <?php echo htmlspecialchars($api_key_created); ?>
                </div>
                <br>
                <button onclick="copyToClipboard('<?php echo $api_key_created; ?>')">📋 Copy API Key</button>
                
                <h4>How to Use:</h4>
                <p><strong>Student Authentication:</strong></p>
                <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px;">POST http://localhost/public_html/api/v1/auth.php
Headers: X-API-Key: <?php echo htmlspecialchars($api_key_created); ?>
Body: {"username": "student_id", "password": "password"}</pre>
                
                <p><strong>Get Student Data:</strong></p>
                <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px;">GET http://localhost/public_html/api/v1/students.php?action=get&student_id=STUDENT_ID&api_key=<?php echo htmlspecialchars($api_key_created); ?></pre>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label>API Key Name:</label>
                    <input type="text" name="name" value="Mock Test Application" required>
                </div>
                
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" rows="3">API access for mock test system to authenticate students and access their data</textarea>
                </div>
                
                <div class="form-group">
                    <p><strong>Settings:</strong></p>
                    <ul>
                        <li>Permissions: Read Only (secure)</li>
                        <li>Rate Limit: 100 requests/hour</li>
                        <li>Status: Active</li>
                    </ul>
                </div>
                
                <button type="submit" name="create_key">🚀 Create API Key</button>
            </form>
        <?php endif; ?>
        
        <hr style="margin: 30px 0;">
        <p><strong>Next Steps:</strong></p>
        <ul>
            <li><a href="quick_api_test.php">🧪 Test your API key</a></li>
            <li><a href="../api/docs.php">📖 View API documentation</a></li>
            <li><a href="dashboard.php">🏠 Back to dashboard</a></li>
        </ul>
    </div>
    
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('API key copied to clipboard!');
            });
        }
    </script>
</body>
</html>