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

require_once '../config/database.php';
require_once '../includes/theme_loader.php';

$active_theme = loadActiveTheme($conn);

// Get OTP logs from the last 24 hours
$otp_logs_result = $conn->query("SELECT * FROM otp_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY created_at DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Logs - NIELIT Admin</title>
    <?php injectThemeCSS($active_theme); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin-theme.css" rel="stylesheet">
    <link rel="icon" href="<?php echo getThemeFavicon($active_theme); ?>" type="image/x-icon">
    <style>
        .otp-card { 
            background: white; 
            border-radius: 15px; 
            padding: 1.5rem; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
            border: none; 
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .otp-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 15px 35px rgba(0,0,0,0.15); 
        }
        .otp-code { 
            font-size: 2rem; 
            font-weight: 700; 
            color: #2563eb; 
            letter-spacing: 4px; 
            font-family: 'Courier New', monospace;
        }
        .page-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 2rem 0; 
            margin-bottom: 2rem; 
            border-radius: 0 0 30px 30px; 
        }
        .status-badge { 
            padding: 0.5rem 1rem; 
            border-radius: 20px; 
            font-weight: 600; 
            font-size: 0.75rem; 
            text-transform: uppercase; 
        }
        .status-sent { 
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); 
            color: white; 
        }
        .status-failed { 
            background: linear-gradient(135deg, #e74c3c 0%, #f1948a 100%); 
            color: white; 
        }
        .empty-state { 
            text-align: center; 
            padding: 3rem; 
            color: #6c757d; 
        }
        .empty-state i { 
            font-size: 4rem; 
            margin-bottom: 1rem; 
            opacity: 0.3; 
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="page-header">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1"><i class="fas fa-key"></i> OTP Logs</h1>
                        <p class="mb-0 opacity-75">View recent OTP codes for debugging (Last 24 hours)</p>
                    </div>
                    <div>
                        <button class="btn btn-light" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-body">
            <?php if ($otp_logs_result && $otp_logs_result->num_rows > 0): ?>
                <div class="row">
                    <?php while ($log = $otp_logs_result->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="otp-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($log['purpose']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($log['email']); ?></small>
                                    </div>
                                    <span class="status-badge <?php echo $log['status'] === 'sent' ? 'status-sent' : 'status-failed'; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="text-center mb-3">
                                    <div class="otp-code"><?php echo htmlspecialchars($log['otp_code']); ?></div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i> 
                                        <?php echo date('M d, Y g:i A', strtotime($log['created_at'])); ?>
                                    </small>
                                    <small class="text-muted">
                                        <?php 
                                        $time_diff = time() - strtotime($log['created_at']);
                                        if ($time_diff < 60) {
                                            echo $time_diff . 's ago';
                                        } elseif ($time_diff < 3600) {
                                            echo floor($time_diff / 60) . 'm ago';
                                        } else {
                                            echo floor($time_diff / 3600) . 'h ago';
                                        }
                                        ?>
                                    </small>
                                </div>
                                
                                <?php if (isset($log['username']) && $log['username']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($log['username']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="otp-card">
                    <div class="empty-state">
                        <i class="fas fa-key"></i>
                        <h5>No OTP Logs Found</h5>
                        <p class="text-muted">No OTP codes have been generated in the last 24 hours.</p>
                        <small class="text-muted">OTP logs will appear here when admins attempt to login or when new admins are created.</small>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Info Box -->
            <div class="otp-card mt-4">
                <div class="d-flex align-items-start">
                    <div class="me-3">
                        <i class="fas fa-info-circle text-primary" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h6><i class="fas fa-shield-alt"></i> Security Information:</h6>
                        <ul class="mb-0 small text-muted">
                            <li>OTP codes are valid for 10 minutes only</li>
                            <li>This page is only accessible to Master Admins</li>
                            <li>Logs are automatically cleaned after 24 hours</li>
                            <li>Use this for debugging email delivery issues</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>