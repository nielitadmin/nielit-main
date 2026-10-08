<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_once __DIR__ . '/../batch_module/includes/batch_placement_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id'])) {
    if (!init_admin_session($_SESSION['admin'])) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
}

$role = $_SESSION['admin_role'] ?? '';
if (!canManageBatchPlacement($role)) {
    http_response_code(403);
    echo 'You do not have permission to verify placements.';
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$adminUser = (string) ($_SESSION['admin'] ?? '');
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$statusOptions = batch_placement_status_options();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'danger';
    } else {
        $studentRecordId = (int) ($_POST['student_record_id'] ?? 0);
        $action = strtolower(trim((string) ($_POST['verify_action'] ?? '')));
        $note = trim((string) ($_POST['rejection_note'] ?? ''));
        if ($action === 'approve') {
            $result = verifyStudentPlacement($conn, $studentRecordId, true, $adminId, '');
        } elseif ($action === 'reject') {
            $result = verifyStudentPlacement($conn, $studentRecordId, false, $adminId, $note);
        } else {
            $result = ['success' => false, 'message' => 'Unknown action.'];
        }
        $message = (string) ($result['message'] ?? 'Could not update placement.');
        $messageType = !empty($result['success']) ? 'success' : 'danger';
    }
}

$pending = listPendingStudentPlacements($conn);
$active_theme = loadActiveTheme($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Placements - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .vp-card { background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); margin-bottom:1rem; }
        .vp-card .card-body { padding:1.1rem 1.25rem; }
        .vp-meta { color:#64748b; font-size:.875rem; margin-bottom:.35rem; }
        .vp-actions { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-start; }
        .vp-actions textarea { min-width:220px; flex:1; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-clipboard-check"></i> Verify Placements</h4>
                <small>Student submissions stay pending until a placement officer approves them. Official course status updates only after verification.</small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($adminUser); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars(function_exists('get_role_display_name') ? get_role_display_name() : 'Administrator'); ?></span>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($adminUser, 0, 1)); ?></div>
                </div>
            </div>
        </div>
        <div class="admin-main">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (count($pending) === 0): ?>
                <div class="vp-card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5>No pending placement submissions</h5>
                        <p class="text-muted mb-0">When students submit placement details, they will appear here for verification.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($pending as $row):
                    $status = strtolower(trim((string) ($row['placement_status'] ?? 'not_placed')));
                    $statusLabel = $statusOptions[$status] ?? ucfirst(str_replace('_', ' ', $status));
                    $package = batch_placement_format_package(
                        $row['placement_package_amount'] ?? null,
                        $row['placement_package_type'] ?? 'annual'
                    );
                    $batchLabel = trim((string) ($row['batch_name'] ?? ''));
                    if ($batchLabel === '') {
                        $batchLabel = 'Not assigned';
                    } elseif (!empty($row['batch_code'])) {
                        $batchLabel .= ' (' . $row['batch_code'] . ')';
                    }
                ?>
                <div class="vp-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars((string) ($row['name'] ?? 'Student')); ?></h5>
                                <div class="vp-meta">ID: <?php echo htmlspecialchars((string) ($row['student_id'] ?? '')); ?></div>
                            </div>
                            <span class="badge bg-warning text-dark">Awaiting verification</span>
                        </div>
                        <p class="mb-1"><strong>Courses:</strong> <?php echo htmlspecialchars((string) ($row['course_name'] ?? 'Course')); ?></p>
                        <p class="mb-1"><strong>Batch:</strong> <?php echo htmlspecialchars($batchLabel !== '' ? $batchLabel : '—'); ?></p>
                        <p class="mb-1"><strong>Submitted status:</strong> <?php echo htmlspecialchars($statusLabel); ?></p>
                        <?php if (!empty($row['placement_company'])): ?>
                            <p class="mb-1"><strong>Company:</strong> <?php echo htmlspecialchars((string) $row['placement_company']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($row['placement_role'])): ?>
                            <p class="mb-1"><strong>Role:</strong> <?php echo htmlspecialchars((string) $row['placement_role']); ?></p>
                        <?php endif; ?>
                        <?php if ($package !== ''): ?>
                            <p class="mb-1"><strong>Package:</strong> <?php echo htmlspecialchars($package); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($row['placement_location'])): ?>
                            <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars((string) $row['placement_location']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($row['placement_date'])): ?>
                            <p class="mb-1"><strong>Date:</strong> <?php echo htmlspecialchars((string) $row['placement_date']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($row['placement_remarks'])): ?>
                            <p class="mb-2"><strong>Remarks:</strong> <?php echo nl2br(htmlspecialchars((string) $row['placement_remarks'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($row['placement_updated_at'])): ?>
                            <p class="vp-meta">Submitted: <?php echo htmlspecialchars((string) $row['placement_updated_at']); ?></p>
                        <?php endif; ?>

                        <div class="vp-actions mt-3">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="student_record_id" value="<?php echo (int) $row['student_record_id']; ?>">
                                <input type="hidden" name="verify_action" value="approve">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i> Approve &amp; update
                                </button>
                            </form>
                            <form method="post" class="d-flex gap-2 flex-wrap flex-grow-1">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="student_record_id" value="<?php echo (int) $row['student_record_id']; ?>">
                                <input type="hidden" name="verify_action" value="reject">
                                <textarea class="form-control" name="rejection_note" rows="1" placeholder="Rejection note (optional)" maxlength="500"></textarea>
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
