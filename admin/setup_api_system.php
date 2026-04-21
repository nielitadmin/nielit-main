<?php
/**
 * NIELIT Bhubaneswar - API System Setup (Web Interface)
 * Creates API system tables through web interface
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Check if master admin
if ($_SESSION['admin_role'] !== 'master_admin') {
    die("Access denied. Master admin privileges required.");
}

$setup_complete = false;
$errors = [];
$success_messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_api'])) {
    
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
        if ($conn->query($sql)) {
            $success_messages[] = "✓ Table '$table_name' created successfully";
            $success_count++;
        } else {
            $errors[] = "✗ Failed to create table '$table_name': " . $conn->error;
        }
    }

    if ($success_count === $total_tables) {
        $setup_complete = true;
        $success_messages[] = "🎉 API System setup completed successfully!";
    }
}

// Check if tables already exist
$tables_exist = [];
$check_tables = ['api_keys', 'api_requests', 'auth_tokens', 'failed_logins'];

foreach ($check_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $tables_exist[$table] = $result->num_rows > 0;
}

$all_tables_exist = !in_array(false, $tables_exist);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API System Setup - NIELIT Bhubaneswar</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Admin Theme CSS -->
    <link href="../assets/css/admin-theme.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-content">
            <div class="admin-main">
                <div class="container-fluid py-4">
                    <!-- Page Header -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h2><i class="fas fa-cogs"></i> API System Setup</h2>
                            <p class="text-muted">Set up the API system for external applications</p>
                        </div>
                    </div>

                    <!-- Status Messages -->
                    <?php if (!empty($success_messages)): ?>
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle"></i> Success!</h5>
                            <?php foreach ($success_messages as $message): ?>
                                <div><?php echo $message; ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-exclamation-triangle"></i> Errors</h5>
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo $error; ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Setup Status -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-database"></i> Database Tables Status</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php foreach ($tables_exist as $table => $exists): ?>
                                            <div class="col-md-3 mb-3">
                                                <div class="d-flex align-items-center">
                                                    <?php if ($exists): ?>
                                                        <i class="fas fa-check-circle text-success me-2"></i>
                                                        <span class="text-success"><?php echo $table; ?></span>
                                                    <?php else: ?>
                                                        <i class="fas fa-times-circle text-danger me-2"></i>
                                                        <span class="text-danger"><?php echo $table; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Setup Form -->
                    <?php if (!$all_tables_exist): ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-play"></i> Setup API System</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>The API system requires database tables to be created. Click the button below to set up the API system.</p>
                                        
                                        <div class="alert alert-info">
                                            <h6><i class="fas fa-info-circle"></i> What will be created:</h6>
                                            <ul class="mb-0">
                                                <li><strong>api_keys</strong> - Stores API keys for external applications</li>
                                                <li><strong>api_requests</strong> - Logs all API requests for monitoring</li>
                                                <li><strong>auth_tokens</strong> - Manages authentication tokens</li>
                                                <li><strong>failed_logins</strong> - Tracks failed login attempts</li>
                                            </ul>
                                        </div>
                                        
                                        <form method="POST">
                                            <button type="submit" name="setup_api" class="btn btn-primary btn-lg">
                                                <i class="fas fa-rocket"></i> Setup API System
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0"><i class="fas fa-check-circle"></i> API System Ready</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-success mb-4">✓ All API system tables are already created and ready to use!</p>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-key"></i> Next Steps:</h6>
                                                <ol>
                                                    <li>Go to <a href="../api/admin/manage_api_keys.php" class="btn btn-sm btn-outline-primary">API Management</a></li>
                                                    <li>Create your first API key</li>
                                                    <li>Test the API endpoints</li>
                                                </ol>
                                            </div>
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-link"></i> API Endpoints:</h6>
                                                <ul class="list-unstyled">
                                                    <li><code>/api/v1/students.php</code> - Student data</li>
                                                    <li><code>/api/v1/auth.php</code> - Authentication</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- API Documentation -->
                    <?php if ($all_tables_exist || $setup_complete): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-book"></i> API Usage Examples</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>1. Get Student List</h6>
                                                <pre class="bg-light p-2 rounded"><code>GET /api/v1/students.php?action=list&api_key=YOUR_API_KEY</code></pre>
                                                
                                                <h6>2. Authenticate Student</h6>
                                                <pre class="bg-light p-2 rounded"><code>POST /api/v1/auth.php
Headers: X-API-Key: YOUR_API_KEY
Body: {"username":"student_id","password":"password"}</code></pre>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>3. Get Student by ID</h6>
                                                <pre class="bg-light p-2 rounded"><code>GET /api/v1/students.php?action=get&student_id=STUDENT_ID&api_key=YOUR_API_KEY</code></pre>
                                                
                                                <h6>4. Search Students</h6>
                                                <pre class="bg-light p-2 rounded"><code>GET /api/v1/students.php?action=search&q=search_term&api_key=YOUR_API_KEY</code></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>