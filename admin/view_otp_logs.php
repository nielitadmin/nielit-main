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

// Set timezone to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

// Also set MySQL timezone to IST
$conn->query("SET time_zone = '+05:30'");

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
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Core CSS -->
    <?php injectThemeCSS($active_theme); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin-theme.css" rel="stylesheet">
    <link href="../assets/css/toast-notifications.css" rel="stylesheet">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
    
    <style>
        :root {
            --navy: #0a1628;
            --navy-mid: #112240;
            --blue: #1a56db;
            --blue-light: #3b82f6;
            --gold: #f59e0b;
            --gold-light: #fcd34d;
            --cream: #fafaf8;
            --text: #0f172a;
            --muted: #64748b;
            --border: rgba(0,0,0,0.08);
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--cream);
            color: var(--text);
        }

        h1,h2,h3,h4,h5,h6 { 
            font-family: 'Sora', sans-serif; 
        }

        /* Modern Page Header */
        .modern-page-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
            padding: 2rem 0;
            position: relative;
            overflow: hidden;
        }

        .modern-page-header::before {
            content: '';
            position: absolute;
            right: -80px; top: -80px;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(245,158,11,0.15) 0%, transparent 70%);
            pointer-events: none;
        }

        .modern-page-header .container {
            position: relative;
            z-index: 2;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .page-subtitle {
            color: rgba(255,255,255,0.7);
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        .page-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn-refresh {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            backdrop-filter: blur(10px);
        }

        .btn-refresh:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.3);
            color: #fff;
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-container {
            margin-top: -3rem;
            margin-bottom: 3rem;
            position: relative;
            z-index: 10;
        }

        .stat-card {
            background: #fff;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            border: 1px solid var(--border);
            transition: all 0.4s cubic-bezier(0.34,1.56,0.64,1);
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--blue) 0%, var(--gold) 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 48px rgba(0,0,0,0.12);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-icon.success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
        }

        .stat-icon.danger {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
        }

        .stat-icon.info {
            background: rgba(26,86,219,0.1);
            color: var(--blue);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            font-family: 'Sora', sans-serif;
            color: var(--navy);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--muted);
            font-weight: 500;
            font-size: 0.95rem;
        }

        /* OTP Cards */
        .otp-logs-section {
            margin-bottom: 3rem;
        }

        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 0;
        }

        .logs-count {
            background: var(--blue);
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .otp-card {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid var(--border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            height: 100%;
            animation: slideInUp 0.6s ease-out;
        }

        .otp-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.1);
        }

        .otp-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .otp-purpose {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--navy);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .otp-purpose i {
            color: var(--blue);
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .status-badge.success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
        }

        .status-badge.danger {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
        }

        .otp-code-section {
            background: var(--cream);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .otp-code-section:hover {
            background: #f0f9ff;
            transform: scale(1.02);
        }

        .otp-code-label {
            font-size: 0.75rem;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .otp-code-display {
            font-size: 2rem;
            font-weight: 800;
            font-family: 'Sora', monospace;
            color: var(--navy);
            letter-spacing: 4px;
            margin-bottom: 0.5rem;
        }

        .copy-hint {
            font-size: 0.75rem;
            color: var(--blue);
            opacity: 0.7;
            transition: opacity 0.3s;
        }

        .otp-code-section:hover .copy-hint {
            opacity: 1;
        }

        .otp-details {
            display: grid;
            gap: 1rem;
        }

        .otp-detail-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .detail-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .detail-icon.email {
            background: rgba(26,86,219,0.1);
            color: var(--blue);
        }

        .detail-icon.time {
            background: rgba(245,158,11,0.1);
            color: var(--gold);
        }

        .detail-icon.user {
            background: rgba(16,185,129,0.1);
            color: var(--success);
        }

        .detail-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.75rem;
            color: var(--muted);
            font-weight: 500;
            margin-bottom: 0.2rem;
            display: block;
            line-height: 1.2;
        }

        .detail-value {
            font-weight: 600;
            color: var(--navy);
            font-size: 0.9rem;
            display: block;
            line-height: 1.4;
        }

        .time-ago {
            background: var(--blue);
            color: #fff;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: auto;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid var(--border);
        }

        .empty-illustration {
            width: 120px;
            height: 120px;
            background: rgba(26,86,219,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3rem;
            color: var(--blue);
        }

        .empty-state h4 {
            color: var(--navy);
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: var(--muted);
            margin-bottom: 0.5rem;
        }

        /* Security Info */
        .security-section {
            background: #fff;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid var(--border);
        }

        .security-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .security-header i {
            width: 48px;
            height: 48px;
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .security-header h5 {
            color: var(--navy);
            margin-bottom: 0;
            font-weight: 700;
        }

        .security-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .security-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            background: var(--cream);
            border-radius: 12px;
            transition: all 0.3s;
        }

        .security-item:hover {
            background: #f0f9ff;
            transform: translateY(-2px);
        }

        .security-item i {
            width: 36px;
            height: 36px;
            background: rgba(26,86,219,0.1);
            color: var(--blue);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-top: 0.2rem;
        }

        .security-item strong {
            color: var(--navy);
            font-weight: 600;
            display: block;
            margin-bottom: 0.25rem;
        }

        .security-item p {
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 0;
            line-height: 1.4;
        }

        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .animate-fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modern-page-header {
                padding: 1.5rem 0;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .stats-container {
                margin-top: -2rem;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .otp-code-display {
                font-size: 1.5rem;
                letter-spacing: 2px;
            }
            
            .security-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Copy feedback */
        .copy-success {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--success);
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }

        .copy-success.show {
            opacity: 1;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-content">
        <!-- Modern Page Header -->
        <div class="modern-page-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="page-title">
                            <i class="fas fa-key me-3"></i>OTP Logs
                        </h1>
                        <p class="page-subtitle">View recent OTP codes for debugging (Last 24 hours)</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="page-actions">
                            <button class="btn btn-refresh" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <!-- Stats Cards -->
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
            <div class="stats-container">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="stat-card animate-fade-in">
                            <div class="stat-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-value"><?php echo $sent_count; ?></div>
                            <div class="stat-label">Successfully Sent</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card animate-fade-in" style="animation-delay: 0.1s;">
                            <div class="stat-icon danger">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="stat-value"><?php echo $failed_count; ?></div>
                            <div class="stat-label">Failed Attempts</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card animate-fade-in" style="animation-delay: 0.2s;">
                            <div class="stat-icon info">
                                <i class="fas fa-database"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_otps; ?></div>
                            <div class="stat-label">Total Logs (24h)</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- OTP Logs Section -->
            <?php if ($otp_logs_result && $otp_logs_result->num_rows > 0): ?>
                <div class="otp-logs-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-history me-2"></i>Recent OTP Codes
                        </h3>
                        <span class="logs-count"><?php echo $total_otps; ?> entries</span>
                    </div>
                    
                    <div class="row g-4">
                        <?php 
                        $delay = 0;
                        while ($log = $otp_logs_result->fetch_assoc()): 
                        ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="otp-card" style="animation-delay: <?php echo $delay * 0.1; ?>s;">
                                    <!-- Card Header -->
                                    <div class="otp-card-header">
                                        <div class="otp-purpose">
                                            <i class="fas fa-envelope-open-text"></i>
                                            <?php echo htmlspecialchars($log['purpose']); ?>
                                        </div>
                                        <span class="status-badge <?php echo $log['status'] === 'sent' ? 'success' : 'danger'; ?>">
                                            <i class="fas fa-<?php echo $log['status'] === 'sent' ? 'check' : 'exclamation'; ?>"></i>
                                            <?php echo ucfirst($log['status']); ?>
                                        </span>
                                    </div>

                                    <!-- OTP Code Display -->
                                    <div class="otp-code-section" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($log['otp_code']); ?>')">
                                        <div class="otp-code-label">OTP Code</div>
                                        <div class="otp-code-display"><?php echo htmlspecialchars($log['otp_code']); ?></div>
                                        <div class="copy-hint">Click to copy</div>
                                        <div class="copy-success">Copied!</div>
                                    </div>

                                    <!-- Details -->
                                    <div class="otp-details">
                                        <div class="otp-detail-item">
                                            <div class="detail-icon email">
                                                <i class="fas fa-envelope"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-label">Email Address</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($log['email']); ?></div>
                                            </div>
                                        </div>

                                        <div class="otp-detail-item">
                                            <div class="detail-icon time">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-label">Generated At</div>
                                                <div class="detail-value"><?php 
                                                // Set timezone to Indian Standard Time
                                                date_default_timezone_set('Asia/Kolkata');
                                                echo date('d.m.Y, h:i A', strtotime($log['created_at'])); 
                                                ?></div>
                                            </div>
                                            <div class="time-ago">
                                                <?php 
                                                // Set timezone to Indian Standard Time for accurate time calculation
                                                date_default_timezone_set('Asia/Kolkata');
                                                $time_diff = time() - strtotime($log['created_at']);
                                                if ($time_diff < 60) {
                                                    echo $time_diff . 's ago';
                                                } elseif ($time_diff < 3600) {
                                                    echo floor($time_diff / 60) . 'm ago';
                                                } else {
                                                    echo floor($time_diff / 3600) . 'h ago';
                                                }
                                                ?>
                                            </div>
                                        </div>

                                        <?php if (isset($log['username']) && $log['username']): ?>
                                            <div class="otp-detail-item">
                                                <div class="detail-icon user">
                                                    <i class="fas fa-user-circle"></i>
                                                </div>
                                                <div class="detail-content">
                                                    <div class="detail-label">Username</div>
                                                    <div class="detail-value"><?php echo htmlspecialchars($log['username']); ?></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php 
                        $delay++;
                        endwhile; 
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state animate-fade-in">
                    <div class="empty-illustration">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h4>No OTP Logs Found</h4>
                    <p>No OTP codes have been generated in the last 24 hours.</p>
                    <small class="text-muted">OTP logs will appear here when admins attempt to login or when new admins are created.</small>
                </div>
            <?php endif; ?>

            <!-- Security Information -->
            <div class="security-section animate-fade-in" style="animation-delay: 0.3s;">
                <div class="security-header">
                    <i class="fas fa-shield-alt"></i>
                    <h5>Security Information</h5>
                </div>
                <div class="security-grid">
                    <div class="security-item">
                        <i class="fas fa-hourglass-half"></i>
                        <div>
                            <strong>10-Minute Validity</strong>
                            <p>OTP codes expire after 10 minutes for enhanced security</p>
                        </div>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-lock"></i>
                        <div>
                            <strong>Master Admin Only</strong>
                            <p>This page is restricted to Master Administrators only</p>
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
<script src="../assets/js/toast-notifications.js"></script>
<script>
// Enhanced copy to clipboard functionality with modern feedback
function copyToClipboard(element, text) {
    // Use modern clipboard API if available
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showCopyFeedback(element);
            toast.success(`OTP code ${text} copied to clipboard!`);
        }).catch(() => {
            fallbackCopy(element, text);
        });
    } else {
        fallbackCopy(element, text);
    }
}

// Fallback copy method for older browsers
function fallbackCopy(element, text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        showCopyFeedback(element);
        toast.success(`OTP code ${text} copied to clipboard!`);
    } catch (err) {
        toast.error('Failed to copy OTP code');
    }
    
    document.body.removeChild(textarea);
}

// Show visual feedback for copy action
function showCopyFeedback(element) {
    const copySuccess = element.querySelector('.copy-success');
    const copyHint = element.querySelector('.copy-hint');
    
    if (copySuccess && copyHint) {
        copySuccess.classList.add('show');
        copyHint.style.opacity = '0';
        
        setTimeout(() => {
            copySuccess.classList.remove('show');
            copyHint.style.opacity = '0.7';
        }, 2000);
    }
}

// Auto-refresh functionality
let autoRefreshInterval;
let refreshCountdown = 30; // 30 seconds

function startAutoRefresh() {
    autoRefreshInterval = setInterval(() => {
        refreshCountdown--;
        
        if (refreshCountdown <= 0) {
            location.reload();
        }
    }, 1000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

// Initialize auto-refresh (optional - can be enabled/disabled)
// startAutoRefresh();

// Add hover effects and animations
document.addEventListener('DOMContentLoaded', function() {
    // Animate cards on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe all OTP cards
    document.querySelectorAll('.otp-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
    
    // Add ripple effect to clickable elements
    document.querySelectorAll('.otp-code-section').forEach(element => {
        element.addEventListener('click', function(e) {
            const ripple = document.createElement('div');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(26, 86, 219, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Add CSS for ripple animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + R for refresh
    if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
        e.preventDefault();
        location.reload();
    }
    
    // Escape to clear any active states
    if (e.key === 'Escape') {
        document.querySelectorAll('.copy-success.show').forEach(el => {
            el.classList.remove('show');
        });
    }
});

// Show loading state on refresh
window.addEventListener('beforeunload', function() {
    document.body.style.opacity = '0.7';
    document.body.style.pointerEvents = 'none';
});
</script>
</body>
</html>