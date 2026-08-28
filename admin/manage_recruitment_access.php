<?php
/**
 * Master Admin: grant Recruitment (jobs + applications + emails) to other staff.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $_SESSION['message'] = 'Only a Master Admin can grant Recruitment access.';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];
$adminUser = (string) ($_SESSION['admin'] ?? '');
ensureRecruitmentAccessTable($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $_SESSION['message'] = 'Invalid security token. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: manage_recruitment_access.php');
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $targetId = (int) ($_POST['admin_id'] ?? 0);
    if ($action === 'grant') {
        $result = recruitmentGrantAccess($conn, $targetId, (string) ($_POST['access_level'] ?? 'view'), $adminUser);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    if ($action === 'revoke') {
        $result = recruitmentRevokeAccess($conn, $targetId);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    header('Location: manage_recruitment_access.php');
    exit;
}

$candidates = listRecruitmentAccessCandidates($conn);
$roleNames = [
    'course_coordinator' => 'Course Coordinator',
    'faculty' => 'Faculty',
    'front_office_desk' => 'Front Office',
    'placement_coordinator' => 'Placement Coordinator',
    'data_entry_operator' => 'Data Entry Operator',
    'nsqf_course_manager' => 'NSQF Course Manager',
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
    <title>Grant Recruitment Access - NIELIT Bhubaneswar</title>
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
                <h4><i class="fas fa-user-shield"></i> Grant Recruitment Access</h4>
                <small>Only Master Admin can open recruitment for other staff. Nobody else has access by default.</small>
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
                    <h5 class="card-title" style="margin:0;">Staff accounts</h5>
                </div>
                <div style="padding:1rem 1.25rem;">
                    <p class="att-muted mb-3">
                        By default nobody except Master Admin can open Job Openings or Applications.
                        Grant <strong>View</strong> to let them see jobs and applications, or <strong>Manage</strong> to also create jobs and shortlist / reject candidates.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-hover att-grant-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Current access</th>
                                    <th>Grant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($candidates)): ?>
                                    <tr><td colspan="4" class="text-muted text-center" style="padding:1.5rem;">No other admin accounts found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($candidates as $c): ?>
                                        <?php
                                        $role = (string) ($c['role'] ?? '');
                                        $grantLevel = (string) ($c['grant_level'] ?? '');
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) $c['username']); ?></td>
                                            <td><?php echo htmlspecialchars($roleNames[$role] ?? $role); ?></td>
                                            <td>
                                                <?php if ($grantLevel === 'edit'): ?>
                                                    <span class="badge bg-success">Granted: Manage</span>
                                                <?php elseif ($grantLevel === 'view'): ?>
                                                    <span class="badge bg-info text-dark">Granted: View</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No access</span>
                                                <?php endif; ?>
                                                <?php if ($grantLevel !== ''): ?>
                                                    <form method="post" style="display:inline;margin-left:8px;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                                        <input type="hidden" name="action" value="revoke">
                                                        <input type="hidden" name="admin_id" value="<?php echo (int) $c['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" style="margin:0;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                                    <input type="hidden" name="action" value="grant">
                                                    <input type="hidden" name="admin_id" value="<?php echo (int) $c['id']; ?>">
                                                    <select name="access_level" class="form-select form-select-sm" style="min-width:140px;">
                                                        <option value="view" <?php echo $grantLevel === 'view' ? 'selected' : ''; ?>>View</option>
                                                        <option value="edit" <?php echo $grantLevel === 'edit' ? 'selected' : ''; ?>>Manage</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">Grant</button>
                                                </form>
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
