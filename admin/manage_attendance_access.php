<?php
/**
 * Master Admin: grant Student Attendance to faculty / course coordinators per centre.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/attendance_in_out_helper.php';
require_once __DIR__ . '/../includes/attendance_access_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $_SESSION['message'] = 'Only a Master Admin can grant Student Attendance access.';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];
$adminUser = (string) ($_SESSION['admin'] ?? '');
ensureAttendanceAccessTables($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $_SESSION['message'] = 'Invalid security token. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: manage_attendance_access.php');
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $targetId = (int) ($_POST['admin_id'] ?? 0);
    if ($action === 'grant') {
        $result = attendanceGrantAccess($conn, $targetId, $adminUser, (int) ($_POST['centre_id'] ?? 0));
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    if ($action === 'revoke') {
        $result = attendanceRevokeAccess($conn, $targetId, (int) ($_POST['centre_id'] ?? 0));
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    header('Location: manage_attendance_access.php');
    exit;
}

$candidates = listAttendanceAccessCandidates($conn);
$allCentres = listAttendanceGrantCentres($conn);
$roleNames = [
    'course_coordinator' => 'Course Coordinator',
    'faculty' => 'Faculty',
];
$active_theme = loadActiveTheme($conn);
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grant Student Attendance - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .att-muted { color:#64748b; font-size:0.875rem; }
        .att-grant-table td { vertical-align: middle; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-user-shield"></i> Grant Student Attendance</h4>
                <small>Assign QR and fingerprint attendance to faculty or course coordinators by centre</small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($adminUser); ?></span>
                        <span class="user-role">Master Admin</span>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($adminUser, 0, 1)); ?></div>
                </div>
            </div>
        </div>
        <div class="admin-main">
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">Faculty and course coordinators</h5>
                </div>
                <div style="padding:1rem 1.25rem;">
                    <p class="att-muted">Grant a user for a specific training centre (for example Bhubaneswar or Balasore). They will see Student Attendance in their menu and can take QR / fingerprint attendance only for that centre’s courses and sections.</p>
                    <?php if (empty($allCentres)): ?>
                        <p class="att-muted">Add training centres first under Training Centres, then grant access per centre.</p>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-hover att-grant-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Centres granted</th>
                                    <th>Grant centre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($candidates)): ?>
                                    <tr><td colspan="4" class="text-muted text-center" style="padding:1.5rem;">No course coordinator or faculty accounts found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($candidates as $c): ?>
                                        <?php
                                        $grantedIds = [];
                                        foreach ($c['grants'] ?? [] as $g) {
                                            $grantedIds[(int) $g['centre_id']] = true;
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) $c['username']); ?></td>
                                            <td><?php echo htmlspecialchars($roleNames[$c['role']] ?? (string) $c['role']); ?></td>
                                            <td>
                                                <?php if (empty($c['grants'])): ?>
                                                    <span class="badge bg-secondary">Not granted</span>
                                                <?php else: ?>
                                                    <?php foreach ($c['grants'] as $g): ?>
                                                        <form method="post" style="display:inline-flex;align-items:center;gap:6px;margin:0 8px 6px 0;">
                                                            <span class="badge bg-success"><?php echo htmlspecialchars((string) ($g['centre_name'] ?: ('Centre #' . (int) $g['centre_id']))); ?></span>
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                                            <input type="hidden" name="action" value="revoke">
                                                            <input type="hidden" name="admin_id" value="<?php echo (int) $c['id']; ?>">
                                                            <input type="hidden" name="centre_id" value="<?php echo (int) $g['centre_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                                                        </form>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $remaining = [];
                                                foreach ($allCentres as $ctr) {
                                                    if (empty($grantedIds[(int) $ctr['id']])) {
                                                        $remaining[] = $ctr;
                                                    }
                                                }
                                                ?>
                                                <?php if (empty($remaining)): ?>
                                                    <span class="att-muted">All centres granted</span>
                                                <?php else: ?>
                                                    <form method="post" style="margin:0;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                                        <input type="hidden" name="action" value="grant">
                                                        <input type="hidden" name="admin_id" value="<?php echo (int) $c['id']; ?>">
                                                        <select name="centre_id" class="form-select form-select-sm" required style="min-width:160px;">
                                                            <option value="">Select centre…</option>
                                                            <?php foreach ($remaining as $ctr): ?>
                                                                <option value="<?php echo (int) $ctr['id']; ?>"><?php echo htmlspecialchars((string) $ctr['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-primary">Grant</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
<?php if ($message !== ''): ?>
showToast(<?php echo json_encode($message); ?>, <?php echo json_encode($message_type === 'danger' ? 'error' : $message_type); ?>);
<?php endif; ?>
</script>
</body>
</html>
