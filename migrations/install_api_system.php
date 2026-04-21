<?php
/**
 * NIELIT Bhubaneswar - API System Database Migration
 * Creates all required tables for the API system
 */

require_once __DIR__ . '/../config/config.php';

echo "=== NIELIT API System Installation ===\n";
echo "Creating API system database tables...\n\n";

// API Keys Table
$api_keys_sql = "
CREATE TABLE IF NOT EXISTS `api_keys` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text,
    `api_key_hash` varchar(64) NOT NULL UNIQUE,
    `permissions` enum('read','read_write','admin') DEFAULT 'read',
    `rate_limit` int(11) DEFAULT 100,
    `is_active` tinyint(1) DEFAULT 1,
    `created_by` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_used` timestamp NULL,
    `revoked_at` timestamp NULL,
    PRIMARY KEY (`id`),
    KEY `idx_api_key_hash` (`api_key_hash`),
    KEY `idx_is_active` (`is_active`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// API Requests Log Table
$api_requests_sql = "
CREATE TABLE IF NOT EXISTS `api_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `api_key_id` int(11) NOT NULL,
    `endpoint` varchar(255) NOT NULL,
    `method` varchar(10) NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` text,
    `response_status` int(11) DEFAULT NULL,
    `response_time` decimal(10,3) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_api_key_id` (`api_key_id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_endpoint` (`endpoint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// Authentication Tokens Table
$auth_tokens_sql = "
CREATE TABLE IF NOT EXISTS `auth_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `student_id` int(11) NOT NULL,
    `token_hash` varchar(64) NOT NULL UNIQUE,
    `expires_at` timestamp NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_student_id` (`student_id`),
    KEY `idx_token_hash` (`token_hash`),
    KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// Failed Login Attempts Table
$failed_logins_sql = "
CREATE TABLE IF NOT EXISTS `failed_logins` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(255) NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `attempted_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_username` (`username`),
    KEY `idx_ip_address` (`ip_address`),
    KEY `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// Execute table creation
$tables = [
    'api_keys' => $api_keys_sql,
    'api_requests' => $api_requests_sql,
    'auth_tokens' => $auth_tokens_sql,
    'failed_logins' => $failed_logins_sql
];

$success_count = 0;
$total_tables = count($tables);

foreach ($tables as $table_name => $sql) {
    echo "Creating table: $table_name... ";
    
    if ($conn->query($sql)) {
        echo "✓ SUCCESS\n";
        $success_count++;
    } else {
        echo "✗ FAILED: " . $conn->error . "\n";
    }
}

echo "\n=== Installation Summary ===\n";
echo "Tables created: $success_count/$total_tables\n";

if ($success_count === $total_tables) {
    echo "✓ API System installation completed successfully!\n\n";
    
    echo "=== Next Steps ===\n";
    echo "1. Access API Key Management: /api/admin/manage_api_keys.php\n";
    echo "2. Create your first API key for the mock test application\n";
    echo "3. Test API endpoints:\n";
    echo "   - Students API: /api/v1/students.php\n";
    echo "   - Authentication API: /api/v1/auth.php\n\n";
    
    echo "=== API Usage Examples ===\n";
    echo "1. Get student list:\n";
    echo "   GET /api/v1/students.php?action=list&api_key=YOUR_API_KEY\n\n";
    
    echo "2. Authenticate student:\n";
    echo "   POST /api/v1/auth.php\n";
    echo "   Headers: X-API-Key: YOUR_API_KEY\n";
    echo "   Body: {\"username\":\"student_id_or_email\",\"password\":\"password\"}\n\n";
    
    echo "3. Get student by ID:\n";
    echo "   GET /api/v1/students.php?action=get&student_id=STUDENT_ID&api_key=YOUR_API_KEY\n\n";
    
} else {
    echo "✗ Installation completed with errors. Please check the error messages above.\n";
}

echo "=== API Security Notes ===\n";
echo "- API keys are hashed and stored securely\n";
echo "- Rate limiting is enforced (default: 100 requests/hour)\n";
echo "- All API requests are logged for monitoring\n";
echo "- Authentication tokens expire after 1 hour\n";
echo "- Failed login attempts are tracked\n";
echo "- CORS is configured for allowed origins\n\n";

$conn->close();
?>