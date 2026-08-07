<?php
/**
 * Admin: Edit Lesson Plan header + week × day topic grid
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

$planId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$plan = $planId > 0 ? getLessonPlan($conn, $planId) : null;
if (!$plan) {
    $_SESSION['message'] = 'Lesson plan not found.';
    $_SESSION['message_type'] = 'danger';
    header('Location: manage_lesson_plans.php');
    exit();
}

$viewQs = '';
if (isset($_GET['view']) && in_array(strtolower((string) $_GET['view']), ['month', 'week'], true)) {
    $viewQs .= '&view=' . urlencode(strtolower((string) $_GET['view']));
}
if (isset($_GET['m'], $_GET['y'])) {
    $viewQs .= '&m=' . (int) $_GET['m'] . '&y=' . (int) $_GET['y'];
}
$redirectUrl = 'edit_lesson_plan.php?id=' . $planId . $viewQs;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $postView = strtolower(trim((string) ($_POST['return_view'] ?? 'month')));
    if (!in_array($postView, ['month', 'week'], true)) {
        $postView = 'month';
    }
    $postM = (int) ($_POST['return_m'] ?? 0);
    $postY = (int) ($_POST['return_y'] ?? 0);
    $postRedirect = 'edit_lesson_plan.php?id=' . $planId . '&view=' . urlencode($postView);
    if ($postM >= 1 && $postM <= 12 && $postY >= 2000) {
        $postRedirect .= '&m=' . $postM . '&y=' . $postY;
    }

    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $_SESSION['message'] = 'Invalid security token.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . $postRedirect);
        exit();
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_header') {
        $payload = [
            'batch_id' => (int) ($_POST['batch_id'] ?? $plan['batch_id']),
            'plan_title' => $_POST['plan_title'] ?? '',
            'course_name' => $_POST['course_name'] ?? '',
            'module_code' => $_POST['module_code'] ?? '',
            'semester' => $_POST['semester'] ?? '',
            'faculty_name' => $_POST['faculty_name'] ?? '',
            'days_per_week' => (int) ($_POST['days_per_week'] ?? 5),
            'total_weeks' => (int) ($_POST['total_weeks'] ?? 16),
            'total_hours' => $_POST['total_hours'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'created_by' => (string) ($_SESSION['admin'] ?? 'admin'),
        ];
        $result = saveLessonPlanHeader($conn, $payload, $planId);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        header('Location: ' . $postRedirect);
        exit();
    }

    if ($action === 'save_topics') {
        $topics = isset($_POST['topics']) && is_array($_POST['topics']) ? $_POST['topics'] : [];
        $result = saveLessonPlanRows($conn, $planId, $topics);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        header('Location: ' . $postRedirect);
        exit();
    }
}

$plan = getLessonPlan($conn, $planId);
$rows = getLessonPlanRows($conn, $planId);
$totalWeeks = max(1, (int) ($plan['total_weeks'] ?? 16));
$daysPerWeek = max(1, min(6, (int) ($plan['days_per_week'] ?? 5)));
$viewMode = strtolower(trim((string) ($_GET['view'] ?? 'month')));
if (!in_array($viewMode, ['month', 'week'], true)) {
    $viewMode = 'month';
}
$monthCalendar = lessonPlanBuildMonthCalendar(
    $plan['batch_start_date'] ?? null,
    $totalWeeks,
    $daysPerWeek,
    $rows
);
$focusMonth = isset($_GET['m']) ? (int) $_GET['m'] : 0;
$focusYear = isset($_GET['y']) ? (int) $_GET['y'] : 0;
if ($focusMonth < 1 || $focusMonth > 12 || $focusYear < 2000) {
    // Default to first month in plan, or current month if inside plan
    $todayYm = date('Y-n');
    $focusYear = (int) ($monthCalendar[0]['year'] ?? date('Y'));
    $focusMonth = (int) ($monthCalendar[0]['month'] ?? date('n'));
    foreach ($monthCalendar as $mc) {
        if ($mc['year'] . '-' . $mc['month'] === $todayYm) {
            $focusYear = (int) $mc['year'];
            $focusMonth = (int) $mc['month'];
            break;
        }
    }
}
$activeMonthBlock = null;
$monthIndex = 0;
foreach ($monthCalendar as $idx => $mc) {
    if ((int) $mc['year'] === $focusYear && (int) $mc['month'] === $focusMonth) {
        $activeMonthBlock = $mc;
        $monthIndex = $idx;
        break;
    }
}
if ($activeMonthBlock === null && !empty($monthCalendar)) {
    $activeMonthBlock = $monthCalendar[0];
    $monthIndex = 0;
    $focusYear = (int) $activeMonthBlock['year'];
    $focusMonth = (int) $activeMonthBlock['month'];
}
$prevMonth = $monthCalendar[$monthIndex - 1] ?? null;
$nextMonth = $monthCalendar[$monthIndex + 1] ?? null;

$batches = [];
$batchRes = $conn->query(
    "SELECT b.id, b.batch_name, b.batch_code, c.course_name
     FROM batches b LEFT JOIN courses c ON c.id = b.course_id
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
    <title>Edit Lesson Plan — NIELIT Admin</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .lp-muted { color: #64748b; font-size: 0.875rem; }
        .lp-grid-wrap { overflow-x: auto; }
        .lp-grid {
            width: 100%;
            min-width: 720px;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .lp-grid th, .lp-grid td {
            border: 1px solid #cbd5e1;
            padding: 6px;
            vertical-align: top;
        }
        .lp-grid thead th {
            background: #0f172a;
            color: #fff;
            text-align: center;
            font-weight: 700;
        }
        .lp-grid .lp-week {
            background: #f1f5f9;
            font-weight: 700;
            width: 70px;
            text-align: center;
        }
        .lp-grid textarea, .lp-month textarea {
            width: 100%;
            min-height: 52px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 4px 6px;
            font-size: 0.8rem;
            resize: vertical;
        }
        .lp-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 8px;
            margin-bottom: 12px;
            font-size: 0.85rem;
        }
        .lp-info div { background: #f8fafc; border-radius: 6px; padding: 8px 10px; }
        .lp-info strong { display: block; color: #64748b; font-size: 0.75rem; }
        .lp-month-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }
        .lp-month-nav h5 { margin: 0; font-weight: 700; }
        .lp-week-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 1rem;
            overflow: hidden;
            background: #fff;
        }
        .lp-week-head {
            background: #f1f5f9;
            border-left: 4px solid #f59e0b;
            padding: 8px 12px;
            font-weight: 700;
            color: #0f172a;
        }
        .lp-week-head span { font-weight: 500; color: #64748b; }
        .lp-day-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 0;
            border-top: 1px solid #e2e8f0;
        }
        .lp-day-label {
            background: #f8fafc;
            padding: 10px 12px;
            font-weight: 600;
            font-size: 0.85rem;
            border-right: 1px solid #e2e8f0;
        }
        .lp-day-label small { display: block; color: #64748b; font-weight: 500; margin-top: 2px; }
        .lp-day-topic { padding: 8px 10px; }
        .lp-day-row.today .lp-day-label { box-shadow: inset 3px 0 0 #f59e0b; background: #fffbeb; }
        @media (max-width: 700px) {
            .lp-day-row { grid-template-columns: 1fr; }
            .lp-day-label { border-right: 0; border-bottom: 1px solid #e2e8f0; }
        }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-edit"></i> Edit Lesson Plan</h4>
                <p class="lp-muted mb-0"><?php echo htmlspecialchars($plan['plan_title'] ?? ''); ?></p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn btn-secondary" href="manage_lesson_plans.php"><i class="fas fa-arrow-left"></i> Back</a>
                <a class="btn btn-success" href="lesson_plan_daily.php?plan_id=<?php echo (int) $planId; ?>">
                    <i class="fas fa-calendar-check"></i> Daily Update
                </a>
            </div>
        </div>

        <div class="admin-main">
            <div class="content-card" style="margin-bottom:1rem;">
                <div class="card-header"><h5 class="card-title mb-0">Plan Details</h5></div>
                <div style="padding:1rem;">
                    <div class="lp-info">
                        <div><strong>Course</strong><?php echo htmlspecialchars($plan['course_name'] ?: '—'); ?></div>
                        <div><strong>Semester</strong><?php echo htmlspecialchars($plan['semester'] ?: '—'); ?></div>
                        <div><strong>Faculty</strong><?php echo htmlspecialchars($plan['faculty_name'] ?: '—'); ?></div>
                        <div><strong>Days/Week</strong><?php echo (int) $daysPerWeek; ?></div>
                        <div><strong>Weeks</strong><?php echo (int) $totalWeeks; ?></div>
                        <div><strong>Hours</strong><?php echo htmlspecialchars((string) ($plan['total_hours'] ?? '—')); ?></div>
                    </div>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="save_header">
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label">Batch *</label>
                                <select name="batch_id" class="form-control" required>
                                    <?php foreach ($batches as $b): ?>
                                        <option value="<?php echo (int) $b['id']; ?>" <?php echo (int) $plan['batch_id'] === (int) $b['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(($b['batch_name'] ?? '') . ' (' . ($b['batch_code'] ?? '') . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Plan Title *</label>
                                <input type="text" name="plan_title" class="form-control" required
                                       value="<?php echo htmlspecialchars($plan['plan_title'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label">Course Name</label>
                                <input type="text" name="course_name" class="form-control"
                                       value="<?php echo htmlspecialchars($plan['course_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Module Code</label>
                                <input type="text" name="module_code" class="form-control"
                                       value="<?php echo htmlspecialchars($plan['module_code'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Semester</label>
                                <input type="text" name="semester" class="form-control"
                                       value="<?php echo htmlspecialchars($plan['semester'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Faculty Name</label>
                                <input type="text" name="faculty_name" class="form-control"
                                       value="<?php echo htmlspecialchars($plan['faculty_name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-2">
                                <label class="form-label">Days/Week</label>
                                <input type="number" name="days_per_week" class="form-control" min="1" max="6"
                                       value="<?php echo (int) $daysPerWeek; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Weeks</label>
                                <input type="number" name="total_weeks" class="form-control" min="1" max="52"
                                       value="<?php echo (int) $totalWeeks; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Hours</label>
                                <input type="number" name="total_hours" class="form-control" step="0.5" min="0"
                                       value="<?php echo htmlspecialchars((string) ($plan['total_hours'] ?? '')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control"
                                       value="<?php echo htmlspecialchars($plan['notes'] ?? ''); ?>">
                            </div>
                        </div>
                        <label class="d-flex align-items-center gap-2 mb-3">
                            <input type="checkbox" name="is_active" value="1" <?php echo !empty($plan['is_active']) ? 'checked' : ''; ?>>
                            Active
                        </label>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Details</button>
                    </form>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                    <div>
                        <h5 class="card-title mb-0">Topics by Month</h5>
                        <span class="lp-muted">Theory topics mapped to batch class dates (Mon–Fri)</span>
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a class="btn btn-sm <?php echo $viewMode === 'month' ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                           href="edit_lesson_plan.php?id=<?php echo (int) $planId; ?>&view=month&m=<?php echo (int) $focusMonth; ?>&y=<?php echo (int) $focusYear; ?>">
                            <i class="fas fa-calendar-alt"></i> Month
                        </a>
                        <a class="btn btn-sm <?php echo $viewMode === 'week' ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                           href="edit_lesson_plan.php?id=<?php echo (int) $planId; ?>&view=week">
                            <i class="fas fa-th"></i> Week grid
                        </a>
                    </div>
                </div>
                <div style="padding:1rem;" class="lp-month">
                    <?php if (empty($plan['batch_start_date'])): ?>
                        <div class="alert alert-warning">Set the batch start date so topics can follow the monthly calendar.</div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="save_topics">
                        <input type="hidden" name="return_view" value="<?php echo htmlspecialchars($viewMode); ?>">
                        <input type="hidden" name="return_m" value="<?php echo (int) $focusMonth; ?>">
                        <input type="hidden" name="return_y" value="<?php echo (int) $focusYear; ?>">

                        <?php if ($viewMode === 'month'): ?>
                            <div class="lp-month-nav">
                                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                    <?php if ($prevMonth): ?>
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="edit_lesson_plan.php?id=<?php echo (int) $planId; ?>&view=month&m=<?php echo (int) $prevMonth['month']; ?>&y=<?php echo (int) $prevMonth['year']; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                            <?php echo htmlspecialchars($prevMonth['label']); ?>
                                        </a>
                                    <?php endif; ?>
                                    <h5><?php echo htmlspecialchars($activeMonthBlock['label'] ?? '—'); ?></h5>
                                    <?php if ($nextMonth): ?>
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="edit_lesson_plan.php?id=<?php echo (int) $planId; ?>&view=month&m=<?php echo (int) $nextMonth['month']; ?>&y=<?php echo (int) $nextMonth['year']; ?>">
                                            <?php echo htmlspecialchars($nextMonth['label']); ?>
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <?php foreach ($monthCalendar as $mc): ?>
                                        <?php
                                        $isActive = (int) $mc['year'] === $focusYear && (int) $mc['month'] === $focusMonth;
                                        ?>
                                        <a class="btn btn-sm <?php echo $isActive ? 'btn-dark' : 'btn-outline-secondary'; ?>"
                                           href="edit_lesson_plan.php?id=<?php echo (int) $planId; ?>&view=month&m=<?php echo (int) $mc['month']; ?>&y=<?php echo (int) $mc['year']; ?>">
                                            <?php echo htmlspecialchars(date('M Y', mktime(0, 0, 0, (int) $mc['month'], 1, (int) $mc['year']))); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php
                            $todayYmd = date('Y-m-d');
                            // Render all months so Save keeps full plan; show only active month
                            foreach ($monthCalendar as $mc):
                                $isVisible = (int) $mc['year'] === $focusYear && (int) $mc['month'] === $focusMonth;
                            ?>
                                <div <?php echo $isVisible ? '' : 'style="display:none"'; ?> aria-hidden="<?php echo $isVisible ? 'false' : 'true'; ?>">
                                    <?php if (empty($mc['weeks'])): ?>
                                        <p class="lp-muted">No class weeks in this month.</p>
                                    <?php else: ?>
                                        <?php foreach ($mc['weeks'] as $weekBlock): ?>
                                            <div class="lp-week-card">
                                                <div class="lp-week-head">
                                                    Week <?php echo (int) $weekBlock['week']; ?>
                                                    <span> · <?php echo htmlspecialchars($weekBlock['range']); ?></span>
                                                </div>
                                                <?php foreach ($weekBlock['days'] as $day): ?>
                                                    <?php
                                                    $w = (int) $day['week'];
                                                    $d = (int) $day['dow'];
                                                    $isToday = ($day['date'] === $todayYmd);
                                                    ?>
                                                    <div class="lp-day-row<?php echo $isToday ? ' today' : ''; ?>">
                                                        <div class="lp-day-label">
                                                            <?php echo htmlspecialchars($day['day_name']); ?>
                                                            <small><?php echo htmlspecialchars(date('j M Y', strtotime($day['date']))); ?>
                                                                · <?php echo htmlspecialchars(lessonPlanOrdinal($d)); ?> class day
                                                                <?php if ($isToday): ?> · Today<?php endif; ?>
                                                            </small>
                                                        </div>
                                                        <div class="lp-day-topic">
                                                            <textarea name="topics[<?php echo $w; ?>][<?php echo $d; ?>]"
                                                                      rows="3"
                                                                      placeholder="Topics to be covered (theory)…"><?php echo htmlspecialchars((string) ($day['topic'] ?? '')); ?></textarea>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <div class="lp-grid-wrap">
                                <table class="lp-grid">
                                    <thead>
                                        <tr>
                                            <th>Week</th>
                                            <?php for ($d = 1; $d <= $daysPerWeek; $d++): ?>
                                                <th><?php echo htmlspecialchars(lessonPlanOrdinal($d)); ?> Class Day<br><small>Topics to be Covered (Theory)</small></th>
                                            <?php endfor; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php for ($w = 1; $w <= $totalWeeks; $w++): ?>
                                            <tr>
                                                <td class="lp-week"><?php echo htmlspecialchars(lessonPlanOrdinal($w)); ?></td>
                                                <?php for ($d = 1; $d <= $daysPerWeek; $d++): ?>
                                                    <?php $val = $rows[$w][$d]['topic'] ?? ''; ?>
                                                    <td>
                                                        <textarea name="topics[<?php echo $w; ?>][<?php echo $d; ?>]"
                                                                  placeholder="Topic for week <?php echo $w; ?>, day <?php echo $d; ?>…"><?php echo htmlspecialchars($val); ?></textarea>
                                                    </td>
                                                <?php endfor; ?>
                                            </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top:12px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save All Topics</button>
                        </div>
                    </form>
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
