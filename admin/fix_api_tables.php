<?php
/**
 * Fix API Tables - Remove Foreign Key Constraints
 * This fixes the API tables to work without foreign key constraints
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Check if master admin
if ($_SESSION['admin_role'] !== 'master_admin') {
    die("Access denied. Master admin privileges required.");
}

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_tables'])) {
    
    // Drop and recreate api_requests table without foreign key
    $fix_api_requests = "
    DROP TABLE IF EXISTS api_requests;
    CREATE TABLE `api_requests` (
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
    
    // Drop and recreate auth_tokens table without foreign key
    $fix_auth_tokens = "
    DROP TABLE IF EXISTS auth_tokens;
    CREATE TABLE `auth_tokens` (
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
    
    // Execute fixes
    $fixes = [
        'api_requests' => $fix_api_requests,
        'auth_tokens' => $fix_auth_tokens
    ];
    
    foreach ($fixes as $table => $sql) {
        if ($conn->multi_query($sql)) {
            // Clear any remaining results
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->next_result());
            
            $results[$table] = ['success' => true];
        } else {
            $results[$table] = ['success' => false, 'error' => $conn->error];
        }
    }
}
$active_theme = loadActiveTheme($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix API Tables - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
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
                            <h2><i class="fas fa-wrench"></i> Fix API Tables</h2>
                            <p class="text-muted">Fix foreign key constraint issues in API tables</p>
                        </div>
                    </div>

                    <!-- Fix Results -->
                    <?php if (!empty($results)): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <?php foreach ($results as $table => $result): ?>
                                    <div class="alert <?php echo $result['success'] ? 'alert-success' : 'alert-danger'; ?>">
                                        <?php if ($result['success']): ?>
                                            <i class="fas fa-check"></i> Table '<?php echo $table; ?>' fixed successfully!
                                        <?php else: ?>
                                            <i class="fas fa-times"></i> Failed to fix table '<?php echo $table; ?>': <?php echo $result['error']; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (array_sum(array_column($results, 'success')) === count($results)): ?>
                                    <div class="alert alert-success">
                                        <h5><i class="fas fa-check-circle"></i> All Tables Fixed!</h5>
                                        <p>Your API system should now work correctly. You can now:</p>
                                        <ul>
                                            <li><a href="../api/admin/manage_api_keys.php">Manage API Keys</a></li>
                                            <li><a href="quick_api_test.php">Run Quick API Test</a></li>
                                            <li><a href="../api/docs.php">View API Documentation</a></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Fix Form -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-tools"></i> Fix API Tables</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-warning">
                                            <h6><i class="fas fa-exclamation-triangle"></i> What this will do:</h6>
                                            <ul class="mb-0">
                                                <li>Remove foreign key constraints from API tables</li>
                                                <li>Recreate tables without foreign key dependencies</li>
                                                <li>Fix the "Call to a member function execute() on bool" error</li>
                                                <li><strong>Note:</strong> This will clear existing API request logs and auth tokens</li>
                                            </ul>
                                        </div>
                                        
                                        <form method="POST">
                                            <button type="submit" name="fix_tables" class="btn btn-warning btn-lg">
                                                <i class="fas fa-wrench"></i> Fix API Tables
                                            </button>
                                        </form>
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