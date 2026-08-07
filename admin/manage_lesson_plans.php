<?php
/**
 * Admin: Manage Lesson Plans (list + create / import template)
 */
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/lesson_plan_helper.php';

$role = $_SESSION['admin_role'] ?? '';
$blocked = in_array($role, ['nsqf_manager', 'front_office', 'placement_coordinator'], true);
if ($blocked) {
    $_SESSION['message'] = 'Access denied.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . relative_url('dashboard.php'));
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$active_theme = loadActiveTheme($conn);
ensureLessonPlanTables($conn);

$redirectUrl = 'manage_lesson_plans.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $_SESSION['message'] = 'Invalid security token. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . $redirectUrl);
        exit();
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $payload = [
            'batch_id' => (int) ($_POST['batch_id'] ?? 0),
            'plan_title' => $_POST['plan_title'] ?? '',
            'course_name' => $_POST['course_name'] ?? '',
            'module_code' => $_POST['module_code'] ?? '',
            'semester' => $_POST['semester'] ?? '',
            'faculty_name' => $_POST['faculty_name'] ?? '',
            'days_per_week' => (int) ($_POST['days_per_week'] ?? 5),
            'total_weeks' => (int) ($_POST['total_weeks'] ?? 16),
            'total_hours' => $_POST['total_hours'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'created_by' => (string) ($_SESSION['admin'] ?? 'admin'),
        ];
        $result = saveLessonPlanHeader($conn, $payload, null);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success'] && !empty($result['id'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: edit_lesson_plan.php?id=' . (int) $result['id']);
            exit();
        }
        header('Location: ' . $redirectUrl);
        exit();
    }

    if ($action === 'import_template') {
        $batchId = (int) ($_POST['batch_id'] ?? 0);
        $template = (string) ($_POST['template'] ?? 'm1_r5');
        $result = importLessonPlanTemplate($conn, $batchId, $template, (string) ($_SESSION['admin'] ?? 'admin'));
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success'] && !empty($result['id'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: edit_lesson_plan.php?id=' . (int) $result['id']);
            exit();
        }
        header('Location: ' . $redirectUrl);
        exit();
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = deleteLessonPlan($conn, $id);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        header('Location: ' . $redirectUrl);
        exit();
    }
}

$plans = listLessonPlansAdmin($conn);
$batches = [];
$batchRes = $conn->query(
    "SELECT b.id, b.batch_name, b.batch_code, c.course_name
     FROM batches b
     LEFT JOIN courses c ON c.id = b.course_id
     ORDER BY b.status = 'Active' DESC, b.start_date DESC"
);
if ($batchRes) {
    while ($b = $batchRes->fetch_assoc()) {
        $batches[] = $b;
    }
}

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lesson Plans — NIELIT Admin</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .lp-muted { color: #64748b; font-size: 0.875rem; }
        .lp-card { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(15,23,42,.08); padding: 1rem 1.25rem; margin-bottom: 1rem; }
        .lp-title { font-weight: 700; color: #0f172a; margin: 0 0 4px; }
        .lp-meta { font-size: 0.85rem; color: #64748b; }
        .lp-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-book-open"></i> Lesson Plans</h4>
                <p class="lp-muted mb-0">Monthly / weekly day-wise topics — faculty update daily as per timetable</p>
            </div>
        </div>

        <div class="admin-main">
            <div class="content-card" style="margin-bottom:1rem;">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <h5 class="card-title mb-0"><i class="fas fa-plus-circle"></i> Create / Import</h5>
                </div>
                <div style="padding:1rem;">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <form method="post" class="lp-card">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="create">
                                <h6 class="lp-title">New blank plan</h6>
                                <div class="mb-2">
                                    <label class="form-label">Batch *</label>
                                    <select name="batch_id" class="form-control" required>
                                        <option value="">Select batch…</option>
                                        <?php foreach ($batches as $b): ?>
                                            <option value="<?php echo (int) $b['id']; ?>">
                                                <?php
                                                echo htmlspecialchars(($b['batch_name'] ?? '') . ' (' . ($b['batch_code'] ?? '') . ')');
                                                if (!empty($b['course_name'])) {
                                                    echo ' — ' . htmlspecialchars($b['course_name']);
                                                }
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Plan Title *</label>
                                    <input type="text" name="plan_title" class="form-control" required
                                           placeholder="e.g. Detailed Lesson Plan - M1-R5 …">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Course Name</label>
                                        <input type="text" name="course_name" class="form-control" placeholder="'O' Level">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Module</label>
                                        <input type="text" name="module_code" class="form-control" placeholder="M1-R5">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Semester</label>
                                        <input type="text" name="semester" class="form-control" placeholder="1st">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Faculty Name</label>
                                        <input type="text" name="faculty_name" class="form-control" placeholder="Faculty name">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Days/Week</label>
                                        <input type="number" name="days_per_week" class="form-control" value="5" min="1" max="6">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Weeks</label>
                                        <input type="number" name="total_weeks" class="form-control" value="16" min="1" max="52">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Hours</label>
                                        <input type="number" name="total_hours" class="form-control" value="120" step="0.5" min="0">
                                    </div>
                                </div>
                                <label class="d-flex align-items-center gap-2 mb-3">
                                    <input type="checkbox" name="is_active" value="1" checked> Active
                                </label>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create &amp; Edit Topics</button>
                            </form>
                        </div>
                        <div class="col-lg-6">
                            <form method="post" class="lp-card" style="border-left:4px solid #f59e0b;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="import_template">
                                <input type="hidden" name="template" value="m1_r5">
                                <h6 class="lp-title">Import template: M1-R5 (O Level)</h6>
                                <p class="lp-meta mb-2">
                                    Loads the full 16-week / 5-day topic grid for
                                    <strong>Information Technology Tools and Network Basics</strong>
                                    (120 hours) — ready to edit faculty name and batch.
                                </p>
                                <div class="mb-3">
                                    <label class="form-label">Assign to Batch *</label>
                                    <select name="batch_id" class="form-control" required>
                                        <option value="">Select batch…</option>
                                        <?php foreach ($batches as $b): ?>
                                            <option value="<?php echo (int) $b['id']; ?>">
                                                <?php echo htmlspecialchars(($b['batch_name'] ?? '') . ' (' . ($b['batch_code'] ?? '') . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-file-import"></i> Import M1-R5 Template
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-list"></i> All Lesson Plans (<?php echo count($plans); ?>)</h5>
                </div>
                <div style="padding:1rem;">
                    <?php if (empty($plans)): ?>
                        <p class="lp-muted mb-0">No lesson plans yet. Create one or import the M1-R5 template.</p>
                    <?php else: ?>
                        <?php foreach ($plans as $plan): ?>
                            <div class="lp-card" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
                                <div style="flex:1;min-width:220px;">
                                    <h6 class="lp-title"><?php echo htmlspecialchars($plan['plan_title'] ?: 'Untitled plan'); ?></h6>
                                    <div class="lp-meta">
                                        <?php if (!empty($plan['module_code'])): ?>
                                            <strong><?php echo htmlspecialchars($plan['module_code']); ?></strong> ·
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($plan['course_name'] ?? '—'); ?>
                                        <?php if (!empty($plan['semester'])): ?>
                                            · Sem <?php echo htmlspecialchars($plan['semester']); ?>
                                        <?php endif; ?>
                                        <br>
                                        Batch: <?php echo htmlspecialchars(($plan['batch_name'] ?? '') . ' (' . ($plan['batch_code'] ?? '') . ')'); ?>
                                        · Faculty: <?php echo htmlspecialchars($plan['faculty_name'] ?: '—'); ?>
                                        · <?php echo (int) $plan['days_per_week']; ?> days/week
                                        · <?php echo (int) $plan['total_weeks']; ?> weeks
                                        · <?php echo htmlspecialchars((string) ($plan['total_hours'] ?? '—')); ?> hrs
                                        <?php if (!(int) ($plan['is_active'] ?? 1)): ?>
                                            · <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="lp-actions">
                                    <a class="btn btn-sm btn-primary" href="edit_lesson_plan.php?id=<?php echo (int) $plan['id']; ?>">
                                        <i class="fas fa-edit"></i> Edit Topics
                                    </a>
                                    <a class="btn btn-sm btn-success" href="lesson_plan_daily.php?plan_id=<?php echo (int) $plan['id']; ?>">
                                        <i class="fas fa-calendar-check"></i> Daily Update
                                    </a>
                                    <form method="post" style="margin:0;" onsubmit="return confirm('Delete this lesson plan and all topics?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $plan['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
<?php if ($message !== ''): ?>
showToast(<?php echo json_encode($message); ?>, <?php echo json_encode($message_type === 'danger' ? 'error' : ($message_type ?: 'success')); ?>);
<?php endif; ?>
</script>
</body>
</html>
