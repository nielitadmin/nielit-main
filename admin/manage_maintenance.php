<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: " . APP_URL . "/admin/login.php");
    exit();
}

$page_title = "Maintenance Mode Management";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_maintenance') {
        $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
        $maintenance_title = $_POST['maintenance_title'] ?? 'Site Under Maintenance';
        $maintenance_message = $_POST['maintenance_message'] ?? 'We are currently performing scheduled maintenance. We will be back soon!';
        $end_time = $_POST['end_time'] ?? '';
        $show_countdown = isset($_POST['show_countdown']) ? 1 : 0;
        $show_contact = isset($_POST['show_contact']) ? 1 : 0;
        
        // Check if maintenance_mode table exists
        $check_table = $conn->query("SHOW TABLES LIKE 'maintenance_mode'");
        
        if ($check_table->num_rows == 0) {
            // Create table if it doesn't exist
            $create_table = "CREATE TABLE `maintenance_mode` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `is_enabled` tinyint(1) DEFAULT 0,
                `maintenance_title` varchar(255) DEFAULT 'Site Under Maintenance',
                `maintenance_message` text,
                `end_time` datetime DEFAULT NULL,
                `show_countdown` tinyint(1) DEFAULT 1,
                `show_contact` tinyint(1) DEFAULT 1,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $conn->query($create_table);
            
            // Insert default record
            $conn->query("INSERT INTO maintenance_mode (id) VALUES (1)");
        }
        
        // Update maintenance settings
        $stmt = $conn->prepare("UPDATE maintenance_mode SET 
            is_enabled = ?, 
            maintenance_title = ?, 
            maintenance_message = ?, 
            end_time = ?, 
            show_countdown = ?, 
            show_contact = ? 
            WHERE id = 1");
        
        $stmt->bind_param("isssii", $is_enabled, $maintenance_title, $maintenance_message, $end_time, $show_countdown, $show_contact);
        
        if ($stmt->execute()) {
            $success_message = "Maintenance mode settings updated successfully!";
        } else {
            $error_message = "Error updating maintenance mode settings.";
        }
    }
}

// Check if table exists first
$table_check = $conn->query("SHOW TABLES LIKE 'maintenance_mode'");
$table_exists = ($table_check && $table_check->num_rows > 0);

// Fetch current maintenance settings
if ($table_exists) {
    $maintenance_settings = $conn->query("SELECT * FROM maintenance_mode WHERE id = 1");
    $settings = $maintenance_settings ? $maintenance_settings->fetch_assoc() : null;

    // If no settings exist, create default
    if (!$settings) {
        $conn->query("INSERT INTO maintenance_mode (id) VALUES (1)");
        $maintenance_settings = $conn->query("SELECT * FROM maintenance_mode WHERE id = 1");
        $settings = $maintenance_settings ? $maintenance_settings->fetch_assoc() : null;
    }
} else {
    // Table doesn't exist, show installation message
    $settings = null;
}

// Load active theme for CSS
$active_theme = loadActiveTheme($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode Management - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: var(--bg-body, #f5f5f5);
        }
        
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
        
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .page-header h1 {
            margin: 0 0 10px 0;
            color: #1e3a8a;
            font-size: 28px;
        }
        
        .page-header p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .card-header h5 {
            margin: 0;
            color: #1e293b;
            font-size: 18px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert i {
            font-size: 20px;
            margin-right: 12px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            font-family: inherit;
        }
        
        .form-check {
            margin-bottom: 12px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            cursor: pointer;
        }
        
        .form-check-label {
            cursor: pointer;
            color: #334155;
        }
        
        .form-switch .form-check-input {
            width: 48px;
            height: 24px;
            background-color: #cbd5e1;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .form-switch .form-check-input:checked {
            background-color: #2563eb;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-info {
            background: #0ea5e9;
            color: white;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -12px;
        }
        
        .col-md-12 {
            width: 100%;
            padding: 0 12px;
        }
        
        .col-md-6 {
            width: 50%;
            padding: 0 12px;
        }
        
        .col-md-4 {
            width: 33.333%;
            padding: 0 12px;
        }
        
        @media (max-width: 768px) {
            .col-md-6, .col-md-4 {
                width: 100%;
            }
        }
        
        .mb-3 {
            margin-bottom: 20px;
        }
        
        .mb-4 {
            margin-bottom: 25px;
        }
        
        .mt-3 {
            margin-top: 20px;
        }
        
        .mt-4 {
            margin-top: 25px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #94a3b8;
        }
        
        .bg-light {
            background: #f8fafc;
        }
        
        .fa-2x {
            font-size: 2em;
        }
        
        .fa-3x {
            font-size: 3em;
        }
        
        small {
            font-size: 13px;
        }
        
        code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #be123c;
        }
        
        .d-block {
            display: block;
        }
        
        .d-flex {
            display: flex;
        }
        
        .align-items-center {
            align-items: center;
        }
        
        .me-3 {
            margin-right: 15px;
        }
        
        .mb-0 {
            margin-bottom: 0;
        }
        
        h4.alert-heading {
            margin: 0 0 12px 0;
            font-size: 18px;
        }
        
        .alert hr {
            margin: 15px 0;
            border: none;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        
        .alert h5 {
            margin: 15px 0 10px 0;
            font-size: 16px;
        }
        
        .alert ol {
            margin: 10px 0;
            padding-left: 25px;
        }
        
        .alert li {
            margin: 8px 0;
        }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-tools"></i> Maintenance Mode Management</h1>
        <p>Control site maintenance mode with countdown timer</p>
    </div>

    <?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!$table_exists): ?>
    <!-- Installation Required Message -->
    <div class="alert alert-warning" role="alert">
        <h4 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Database Table Not Found</h4>
        <p>The maintenance mode system needs to be installed first. Please run the migration script.</p>
        <hr>
        <h5>Installation Steps:</h5>
        <ol>
            <li>Go to <strong>System Settings → DB Migrations</strong> in the admin panel.</li>
            <li>Run <code>install_maintenance_mode.php</code> and wait for completion.</li>
            <li>Refresh this page.</li>
        </ol>
        <a href="<?php echo APP_URL; ?>/admin/manage_migrations" class="btn btn-primary mt-3">
            <i class="fas fa-database"></i> Open DB Migrations
        </a>
    </div>
    <?php else: ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-cog"></i> Maintenance Mode Settings
            </h5>
        </div>
        <div class="card-body">
            <!-- Current Status Display -->
            <div class="alert <?php echo $settings['is_enabled'] ? 'alert-warning' : 'alert-success'; ?> mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-<?php echo $settings['is_enabled'] ? 'exclamation-triangle' : 'check-circle'; ?> fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-0">
                            <?php echo $settings['is_enabled'] ? 'Maintenance Mode is ACTIVE' : 'Site is LIVE'; ?>
                        </h5>
                        <small>
                            <?php if ($settings['is_enabled']): ?>
                                Public users will see the maintenance page. Admins can still access admin panel.
                            <?php else: ?>
                                All users can access the site normally.
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update_maintenance">

                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" 
                                   id="is_enabled" name="is_enabled" 
                                   <?php echo $settings['is_enabled'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_enabled">
                                <strong>Enable Maintenance Mode</strong>
                                <br>
                                <small class="text-muted">When enabled, public users will see the maintenance page</small>
                            </label>
                        </div>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label for="maintenance_title" class="form-label">
                            <i class="fas fa-heading"></i> Maintenance Title
                        </label>
                        <input type="text" class="form-control" id="maintenance_title" 
                               name="maintenance_title" 
                               value="<?php echo htmlspecialchars($settings['maintenance_title'] ?? 'Site Under Maintenance'); ?>">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label for="maintenance_message" class="form-label">
                            <i class="fas fa-comment-alt"></i> Maintenance Message
                        </label>
                        <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="4"><?php echo htmlspecialchars($settings['maintenance_message'] ?? 'We are currently performing scheduled maintenance. We will be back soon!'); ?></textarea>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="end_time" class="form-label">
                            <i class="fas fa-clock"></i> Estimated End Time (Optional)
                        </label>
                        <input type="datetime-local" class="form-control" id="end_time" 
                               name="end_time" 
                               value="<?php echo $settings['end_time'] ? date('Y-m-d\TH:i', strtotime($settings['end_time'])) : ''; ?>">
                        <small class="text-muted">Leave empty to hide countdown timer</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label d-block">Display Options</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="show_countdown" 
                                   name="show_countdown" 
                                   <?php echo $settings['show_countdown'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="show_countdown">
                                Show Countdown Timer
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="show_contact" 
                                   name="show_contact" 
                                   <?php echo $settings['show_contact'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="show_contact">
                                Show Contact Information
                            </label>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                        <a href="<?php echo APP_URL; ?>/maintenance.php" target="_blank" class="btn btn-secondary">
                            <i class="fas fa-eye"></i> Preview Maintenance Page
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-3x text-primary mb-3"></i>
                            <h6>1 Hour Maintenance</h6>
                            <button class="btn btn-sm btn-primary" onclick="setQuickMaintenance(1)">
                                Set for 1 Hour
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                            <h6>4 Hours Maintenance</h6>
                            <button class="btn btn-sm btn-warning" onclick="setQuickMaintenance(4)">
                                Set for 4 Hours
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <i class="fas fa-moon fa-3x text-info mb-3"></i>
                            <h6>Overnight Maintenance</h6>
                            <button class="btn btn-sm btn-info" onclick="setQuickMaintenance(12)">
                                Set for 12 Hours
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function setQuickMaintenance(hours) {
    const now = new Date();
    now.setHours(now.getHours() + hours);
    
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hour = String(now.getHours()).padStart(2, '0');
    const minute = String(now.getMinutes()).padStart(2, '0');
    
    const datetime = `${year}-${month}-${day}T${hour}:${minute}`;
    
    document.getElementById('end_time').value = datetime;
    document.getElementById('is_enabled').checked = true;
    document.getElementById('show_countdown').checked = true;
}
</script>

</body>
</html>
