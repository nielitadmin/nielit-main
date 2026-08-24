<?php
/**
 * Master Admin: manage the static IPs allowed to use the student self-service
 * fingerprint kiosk (student/self_fingerprint.php). Default is deny — the page
 * only works once an IP is added here.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/student_kiosk_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $_SESSION['message'] = 'Access denied. Only Master Admins can manage the student kiosk.';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit;
}

ensureStudentKioskTables($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];
$adminName = (string) ($_SESSION['admin'] ?? 'master_admin');

$notice = '';
$noticeType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $notice = 'Invalid security token. Refresh and try again.';
        $noticeType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'add') {
            $res = studentKioskAddIp($conn, (string) ($_POST['ip_address'] ?? ''), (string) ($_POST['label'] ?? ''), $adminName);
            $notice = $res['message'];
            $noticeType = $res['success'] ? 'success' : 'danger';
        } elseif ($action === 'add_current') {
            $res = studentKioskAddIp($conn, studentKioskClientIp(), (string) ($_POST['label'] ?? 'This computer'), $adminName);
            $notice = $res['message'];
            $noticeType = $res['success'] ? 'success' : 'danger';
        } elseif ($action === 'toggle') {
            studentKioskSetIpActive($conn, (int) ($_POST['id'] ?? 0), (string) ($_POST['to'] ?? '') === '1');
            $notice = 'Updated.';
            $noticeType = 'success';
        } elseif ($action === 'delete') {
            studentKioskDeleteIp($conn, (int) ($_POST['id'] ?? 0));
            $notice = 'Removed.';
            $noticeType = 'success';
        }
    }
}

$ips = studentKioskListIps($conn);
$currentIp = studentKioskClientIp();
$kioskUrl = app_url('student/self_fingerprint');
$page_title = 'Student Fingerprint Kiosk';
$active_theme = loadActiveTheme($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .ip-mono { font-family: Consolas, Monaco, monospace; }
        .kiosk-hint { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px 14px; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-main">
        <div class="container-fluid py-4">
            <div class="mb-4">
                <h2><i class="fas fa-fingerprint"></i> <?php echo htmlspecialchars($page_title); ?></h2>
                <p class="text-muted mb-0">Allow the student self-registration / self-attendance page only from specific static IP addresses. Any other (dynamic) IP is blocked.</p>
            </div>

            <?php if ($notice !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($noticeType); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($notice); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="kiosk-hint mb-4">
                <div><strong>Kiosk page:</strong> <a href="<?php echo htmlspecialchars($kioskUrl); ?>" target="_blank" rel="noopener" class="ip-mono"><?php echo htmlspecialchars($kioskUrl); ?></a></div>
                <div class="mt-1"><strong>This computer's IP:</strong> <span class="ip-mono"><?php echo htmlspecialchars($currentIp !== '' ? $currentIp : 'unknown'); ?></span></div>
                <div class="text-muted small mt-1">Use the institute's <strong>public static IP</strong> (as seen by the server), not a private LAN IP. If empty above, open this page from the kiosk's network.</div>
            </div>

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Add allowed IP</h5></div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="add">
                                <div class="mb-3">
                                    <label class="form-label">IP address</label>
                                    <input class="form-control ip-mono" name="ip_address" placeholder="e.g. 103.25.xxx.xxx" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Label (optional)</label>
                                    <input class="form-control" name="label" placeholder="e.g. Lab-1 kiosk">
                                </div>
                                <button class="btn btn-primary" type="submit"><i class="fas fa-plus"></i> Add IP</button>
                            </form>
                            <hr>
                            <form method="post" class="mb-0">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="add_current">
                                <input type="hidden" name="label" value="Added from this computer">
                                <button class="btn btn-outline-secondary btn-sm" type="submit" <?php echo $currentIp === '' ? 'disabled' : ''; ?>>
                                    <i class="fas fa-location-crosshairs"></i> Allow this computer's IP (<?php echo htmlspecialchars($currentIp !== '' ? $currentIp : 'n/a'); ?>)
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Allowed IPs (<?php echo count($ips); ?>)</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>IP</th><th>Label</th><th>Status</th><th class="text-end">Actions</th></tr>
                                </thead>
                                <tbody>
                                <?php if (empty($ips)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No IPs allowed yet — the kiosk page is blocked for everyone.</td></tr>
                                <?php else: foreach ($ips as $row): ?>
                                    <tr>
                                        <td class="ip-mono"><?php echo htmlspecialchars((string) $row['ip_address']); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['label'] ?? '')); ?></td>
                                        <td>
                                            <?php if ((int) $row['is_active'] === 1): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                <input type="hidden" name="to" value="<?php echo (int) $row['is_active'] === 1 ? '0' : '1'; ?>">
                                                <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                    <?php echo (int) $row['is_active'] === 1 ? 'Disable' : 'Enable'; ?>
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Remove this IP?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
