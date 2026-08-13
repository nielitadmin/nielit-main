<?php
/**
 * Admin: Manage Course Action Plans (list + create / import template)
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
require_once __DIR__ . '/../includes/teaching_access.php';

admin_require_teaching_tools();
$canManagePlans = admin_can_manage_lesson_plans();
$ownPlansOnly = admin_lesson_plan_own_only();
$ownCreatedBy = $ownPlansOnly ? admin_current_username() : null;

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
            'centre_id' => (int) ($_POST['centre_id'] ?? 0),
            'plan_title' => $_POST['plan_title'] ?? '',
            'course_name' => $_POST['course_name'] ?? '',
            'module_code' => $_POST['module_code'] ?? '',
            'semester' => $_POST['semester'] ?? '',
            'faculty_name' => $_POST['faculty_name'] ?? '',
            'days_per_week' => (int) ($_POST['days_per_week'] ?? 5),
            'total_weeks' => (int) ($_POST['total_weeks'] ?? 16),
            'total_hours' => $_POST['total_hours'] ?? '',
            'plan_start_date' => $_POST['plan_start_date'] ?? '',
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
        $centreId = (int) ($_POST['centre_id'] ?? 0);
        $template = (string) ($_POST['template'] ?? 'm1_r5');
        $planStart = (string) ($_POST['plan_start_date'] ?? '');
        $result = importLessonPlanTemplate(
            $conn,
            $batchId,
            $template,
            (string) ($_SESSION['admin'] ?? 'admin'),
            $planStart,
            $centreId
        );
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
        $existing = $id > 0 ? getLessonPlan($conn, $id) : null;
        if (!admin_can_access_lesson_plan($existing)) {
            $_SESSION['message'] = 'You can only delete course action plans that you created.';
            $_SESSION['message_type'] = 'danger';
            header('Location: ' . $redirectUrl);
            exit();
        }
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

$filterCentre = isset($_GET['centre_id']) ? (int) $_GET['centre_id'] : 0;
if ($ownPlansOnly && ($ownCreatedBy === null || $ownCreatedBy === '')) {
    $plans = [];
} else {
    $plans = listLessonPlansAdmin($conn, null, $filterCentre > 0 ? $filterCentre : null, $ownCreatedBy);
}
$batches = [];
$batchRes = $conn->query(
    "SELECT b.id, b.batch_name, b.batch_code, b.start_date, c.course_name, c.centre_id
     FROM batches b
     LEFT JOIN courses c ON c.id = b.course_id
     ORDER BY b.status = 'Active' DESC, b.start_date DESC"
);
if ($batchRes) {
    while ($b = $batchRes->fetch_assoc()) {
        $batches[] = $b;
    }
}
$centres = [];
$centreRes = $conn->query('SELECT id, name FROM centres WHERE is_active = 1 ORDER BY name ASC');
if ($centreRes) {
    while ($c = $centreRes->fetch_assoc()) {
        $centres[] = $c;
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
    <title>Course Action Plans — NIELIT Admin</title>
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
                <h4><i class="fas fa-book-open"></i> Course Action Plans</h4>
                <p class="lp-muted mb-0"><?php echo $ownPlansOnly
                    ? 'You only see course action plans that you created. Create or import a plan to start.'
                    : 'Monthly / weekly day-wise topics — faculty update daily as per timetable'; ?></p>
            </div>
        </div>

        <div class="admin-main">
            <?php if ($canManagePlans): ?>
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
                                    <label class="form-label">Centre <span class="lp-muted">(optional)</span></label>
                                    <select name="centre_id" class="form-control">
                                        <option value="">No centre / leave blank</option>
                                        <?php foreach ($centres as $c): ?>
                                            <option value="<?php echo (int) $c['id']; ?>">
                                                <?php echo htmlspecialchars($c['name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Batch <span class="lp-muted">(optional)</span></label>
                                    <select name="batch_id" id="createBatchSelect" class="form-control">
                                        <option value="" data-start-date="">No batch / leave blank</option>
                                        <?php foreach ($batches as $b): ?>
                                            <?php $bStart = !empty($b['start_date']) ? date('Y-m-d', strtotime($b['start_date'])) : ''; ?>
                                            <option value="<?php echo (int) $b['id']; ?>" data-start-date="<?php echo htmlspecialchars($bStart); ?>">
                                                <?php
                                                echo htmlspecialchars(($b['batch_name'] ?? '') . ' (' . ($b['batch_code'] ?? '') . ')');
                                                if (!empty($b['course_name'])) {
                                                    echo ' — ' . htmlspecialchars($b['course_name']);
                                                }
                                                if ($bStart !== '') {
                                                    echo ' — ' . htmlspecialchars(date('d M Y', strtotime($bStart)));
                                                }
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Plan Title *</label>
                                    <input type="text" name="plan_title" class="form-control" required
                                           placeholder="e.g. Course Action Plan - M1-R5 …">
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
                                    <div class="col-md-3">
                                        <label class="form-label">Plan Start Date</label>
                                        <input type="date" name="plan_start_date" id="createPlanStartDate" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                                        <small class="lp-muted" id="createPlanStartHint">Enter start date (no batch selected)</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Hours</label>
                                        <input type="number" name="total_hours" class="form-control" value="120" step="0.5" min="0">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-2">
                                        <label class="form-label">Days/Week</label>
                                        <input type="number" name="days_per_week" class="form-control" value="5" min="1" max="6">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Weeks</label>
                                        <input type="number" name="total_weeks" class="form-control" value="16" min="1" max="52">
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
                                    <label class="form-label">Centre <span class="lp-muted">(optional)</span></label>
                                    <select name="centre_id" class="form-control">
                                        <option value="">No centre / leave blank</option>
                                        <?php foreach ($centres as $c): ?>
                                            <option value="<?php echo (int) $c['id']; ?>">
                                                <?php echo htmlspecialchars($c['name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Assign to Batch <span class="lp-muted">(optional)</span></label>
                                    <select name="batch_id" id="importBatchSelect" class="form-control">
                                        <option value="" data-start-date="">No batch / leave blank</option>
                                        <?php foreach ($batches as $b): ?>
                                            <?php $bStart = !empty($b['start_date']) ? date('Y-m-d', strtotime($b['start_date'])) : ''; ?>
                                            <option value="<?php echo (int) $b['id']; ?>" data-start-date="<?php echo htmlspecialchars($bStart); ?>">
                                                <?php echo htmlspecialchars(($b['batch_name'] ?? '') . ' (' . ($b['batch_code'] ?? '') . ')'); ?>
                                                <?php if ($bStart !== ''): ?> — <?php echo htmlspecialchars(date('d M Y', strtotime($bStart))); ?><?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Plan Start Date</label>
                                    <input type="date" name="plan_start_date" id="importPlanStartDate" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                                    <small class="lp-muted" id="importPlanStartHint">Enter start date (no batch selected)</small>
                                </div>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-file-import"></i> Import M1-R5 Template
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="content-card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <h5 class="card-title mb-0"><i class="fas fa-list"></i> <?php echo $ownPlansOnly ? 'My Course Action Plans' : 'All Course Action Plans'; ?> (<?php echo count($plans); ?>)</h5>
                    <form method="get" style="margin:0;display:flex;gap:8px;align-items:center;">
                        <label class="lp-muted mb-0">Centre</label>
                        <select name="centre_id" class="form-control form-control-sm" style="min-width:180px;" onchange="this.form.submit()">
                            <option value="0">All centres</option>
                            <?php foreach ($centres as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>" <?php echo $filterCentre === (int) $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div style="padding:1rem;">
                    <?php if (empty($plans)): ?>
                        <p class="lp-muted mb-0"><?php echo $canManagePlans ? 'No course action plans yet. Create one or import the M1-R5 template.' : 'No course action plans yet.'; ?></p>
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
                                        Batch: <?php
                                        if (!empty($plan['batch_name'])) {
                                            echo htmlspecialchars(($plan['batch_name'] ?? '') . ' (' . ($plan['batch_code'] ?? '') . ')');
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                        · Centre: <?php echo htmlspecialchars($plan['centre_name'] ?: '—'); ?>
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
                                        <i class="fas fa-<?php echo $canManagePlans ? 'edit' : 'eye'; ?>"></i>
                                        <?php echo $canManagePlans ? 'Edit Topics' : 'View Topics'; ?>
                                    </a>
                                    <a class="btn btn-sm btn-secondary" href="print_lesson_plan.php?id=<?php echo (int) $plan['id']; ?>" target="_blank">
                                        <i class="fas fa-print"></i> Print
                                    </a>
                                    <a class="btn btn-sm btn-success" href="lesson_plan_daily.php?plan_id=<?php echo (int) $plan['id']; ?>">
                                        <i class="fas fa-calendar-check"></i> Daily Update
                                    </a>
                                    <?php if ($canManagePlans): ?>
                                    <form method="post" style="margin:0;" onsubmit="return confirm('Delete this Course Action Plan and all topics?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $plan['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
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

function bindBatchStartSync(batchId, startId, hintId) {
    var batchSelect = document.getElementById(batchId);
    var startInput = document.getElementById(startId);
    var hint = hintId ? document.getElementById(hintId) : null;
    if (!batchSelect || !startInput) return;

    function localToday() {
        var today = new Date();
        var y = today.getFullYear();
        var m = String(today.getMonth() + 1).padStart(2, '0');
        var d = String(today.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function sync() {
        var opt = batchSelect.options[batchSelect.selectedIndex];
        var batchVal = batchSelect.value;
        var start = opt ? (opt.getAttribute('data-start-date') || '') : '';
        startInput.readOnly = false;
        if (batchVal) {
            if (start) {
                startInput.value = start;
                if (hint) hint.textContent = 'Auto-filled from batch — change via calendar if needed';
            } else if (hint) {
                hint.textContent = 'Batch has no start date — pick a date from the calendar';
            }
        } else {
            if (!startInput.value) startInput.value = localToday();
            if (hint) hint.textContent = 'No batch — pick or type the plan start date';
        }
    }

    batchSelect.addEventListener('change', sync);
    sync();
}

bindBatchStartSync('createBatchSelect', 'createPlanStartDate', 'createPlanStartHint');
bindBatchStartSync('importBatchSelect', 'importPlanStartDate', 'importPlanStartHint');
</script>
</body>
</html>
