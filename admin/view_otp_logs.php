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
    <!-- OTP Logs styles are provided by admin-theme.css (so they follow active theme) -->
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
            <!-- Summary Stats -->
            <?php 
            $total_otps = $otp_logs_result ? $otp_logs_result->num_rows : 0;
            $otp_logs_result->data_seek(0);
            $sent_count = 0;
            $failed_count = 0;
            while ($log = $otp_logs_result->fetch_assoc()) {
                if ($log['status'] === 'sent') $sent_count++;
                else $failed_count++;
            }
            $otp_logs_result->data_seek(0);
            ?>
            <div class="otp-stats-container">
                <div class="otp-stat-card">
                    <div class="stat-icon stat-sent">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $sent_count; ?></div>
                        <div class="stat-label">Sent</div>
                    </div>
                </div>
                <div class="otp-stat-card">
                    <div class="stat-icon stat-failed">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $failed_count; ?></div>
                        <div class="stat-label">Failed</div>
                    </div>
                </div>
                <div class="otp-stat-card">
                    <div class="stat-icon stat-total">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $total_otps; ?></div>
                        <div class="stat-label">Total (24h)</div>
                    </div>
                </div>
            </div>

            <?php if ($otp_logs_result && $otp_logs_result->num_rows > 0): ?>
                <div class="otp-logs-header">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Recent OTP Codes</h5>
                    <span class="otp-filter-badge"><?php echo $total_otps; ?> entries</span>
                </div>
                <div class="row">
                    <?php while ($log = $otp_logs_result->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="otp-card-modern">
                                <!-- Card Header -->
                                <div class="otp-card-header">
                                    <div class="otp-purpose">
                                        <i class="fas fa-envelope-open-text"></i>
                                        <?php echo htmlspecialchars($log['purpose']); ?>
                                    </div>
                                    <span class="status-badge <?php echo $log['status'] === 'sent' ? 'status-sent' : 'status-failed'; ?>">
                                        <i class="fas fa-<?php echo $log['status'] === 'sent' ? 'check' : 'exclamation'; ?>"></i>
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </div>

                                <!-- OTP Code Display -->
                                <div class="otp-code-container">
                                    <div class="otp-code-label">Code</div>
                                    <div class="otp-code-display" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($log['otp_code']); ?>')" title="Click to copy">
                                        <?php echo htmlspecialchars($log['otp_code']); ?>
                                    </div>
                                    <div class="copy-hint">Click to copy</div>
                                </div>

                                <!-- Email Info -->
                                <div class="otp-email-info">
                                    <i class="fas fa-envelope"></i>
                                    <div>
                                        <div class="otp-email-label">Email</div>
                                        <div class="otp-email-value"><?php echo htmlspecialchars($log['email']); ?></div>
                                    </div>
                                </div>

                                <!-- Card Footer -->
                                <div class="otp-card-footer">
                                    <div class="otp-time-info">
                                        <i class="fas fa-clock"></i>
                                        <div>
                                            <div class="time-label">Time</div>
                                            <div class="time-value"><?php echo date('M d, g:i A', strtotime($log['created_at'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="otp-time-ago">
                                        <?php 
                                        $time_diff = time() - strtotime($log['created_at']);
                                        if ($time_diff < 60) {
                                            echo '<span class="badge badge-info">' . $time_diff . 's ago</span>';
                                        } elseif ($time_diff < 3600) {
                                            echo '<span class="badge badge-info">' . floor($time_diff / 60) . 'm ago</span>';
                                        } else {
                                            echo '<span class="badge badge-info">' . floor($time_diff / 3600) . 'h ago</span>';
                                        }
                                        ?>
                                    </div>
                                </div>

                                <?php if (isset($log['username']) && $log['username']): ?>
                                    <div class="otp-user-info">
                                        <i class="fas fa-user-circle"></i>
                                        <span><?php echo htmlspecialchars($log['username']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="otp-empty-state">
                    <div class="empty-illustration">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h4>No OTP Logs Found</h4>
                    <p>No OTP codes have been generated in the last 24 hours.</p>
                    <small>OTP logs will appear here when admins attempt to login or when new admins are created.</small>
                </div>
            <?php endif; ?>

            <!-- Security Info Box -->
            <div class="otp-security-box">
                <div class="security-header">
                    <i class="fas fa-shield-alt"></i>
                    <h5>Security Information</h5>
                </div>
                <div class="security-grid">
                    <div class="security-item">
                        <i class="fas fa-hourglass-half"></i>
                        <div>
                            <strong>10-Minute Validity</strong>
                            <p>OTP codes expire after 10 minutes for security</p>
                        </div>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-lock"></i>
                        <div>
                            <strong>Master Admin Only</strong>
                            <p>This page is restricted to Master Admins only</p>
                        </div>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-trash-alt"></i>
                        <div>
                            <strong>Auto Cleanup</strong>
                            <p>Logs are automatically cleaned after 24 hours</p>
                        </div>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-bug"></i>
                        <div>
                            <strong>Debug Tool</strong>
                            <p>Use for troubleshooting email delivery issues</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Copy to clipboard functionality
function copyToClipboard(element, text) {
    // Create a temporary textarea
    const textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    
    // Show visual feedback
    const hint = element.nextElementSibling;
    if (hint) {
        hint.textContent = 'Copied!';
        hint.style.opacity = '1';
        setTimeout(() => {
            hint.textContent = 'Click to copy';
            hint.style.opacity = '0';
        }, 2000);
    }
}
</script>
</body>
</html>