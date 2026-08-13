<?php
/**
 * Admin: Daily Course Action Plan update — topic for today based on batch start + timetable week/day
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
require_once __DIR__ . '/../includes/class_timetable_helper.php';
require_once __DIR__ . '/../includes/teaching_access.php';

admin_require_teaching_tools();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$active_theme = loadActiveTheme($conn);
ensureLessonPlanTables($conn);

$planId = isset($_GET['plan_id']) ? (int) $_GET['plan_id'] : 0;
$logDate = isset($_GET['date']) ? preg_replace('/[^0-9\-]/', '', (string) $_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) {
    $logDate = date('Y-m-d');
}

$plan = $planId > 0 ? getLessonPlan($conn, $planId) : null;
if (!$plan) {
    $_SESSION['message'] = 'Select a course action plan first.';
    $_SESSION['message_type'] = 'danger';
    header('Location: manage_lesson_plans.php');
    exit();
}
admin_require_own_lesson_plan($plan);

$redirectUrl = 'lesson_plan_daily.php?plan_id=' . $planId . '&date=' . urlencode($logDate);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $_SESSION['message'] = 'Invalid security token.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . $redirectUrl);
        exit();
    }

    if (($_POST['action'] ?? '') === 'save_daily') {
        $payload = [
            'lesson_plan_id' => $planId,
            'lesson_plan_row_id' => (int) ($_POST['lesson_plan_row_id'] ?? 0) ?: null,
            'batch_id' => (int) ($plan['batch_id'] ?? 0),
            'log_date' => $_POST['log_date'] ?? $logDate,
            'week_number' => (int) ($_POST['week_number'] ?? 0) ?: null,
            'class_day' => (int) ($_POST['class_day'] ?? 0) ?: null,
            'topic_planned' => $_POST['topic_planned'] ?? '',
            'topic_covered' => $_POST['topic_covered'] ?? '',
            'status' => $_POST['status'] ?? 'completed',
            'remarks' => $_POST['remarks'] ?? '',
            'updated_by' => (string) ($_SESSION['admin'] ?? 'admin'),
        ];
        $result = saveLessonPlanDailyLog($conn, $payload);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $redirectUrl = 'lesson_plan_daily.php?plan_id=' . $planId . '&date=' . urlencode($payload['log_date']);
        header('Location: ' . $redirectUrl);
        exit();
    }
}

$topicInfo = getLessonPlanTopicForDate($conn, $planId, $logDate, lessonPlanEffectiveStartDate($plan));
$existingLog = getLessonPlanDailyLog($conn, $planId, $logDate);

// Timetable slots for this batch + weekday (for reference)
$dow = lessonPlanClassDayForDate($logDate);
$ttSlots = [];
if ($dow > 0 && !empty($plan['batch_id'])) {
    ensureClassTimetableTable($conn);
    $allSlots = listClassTimetableAdmin($conn, (int) $plan['batch_id'], null);
    foreach ($allSlots as $slot) {
        if ((int) ($slot['day_of_week'] ?? 0) === $dow) {
            $ttSlots[] = $slot;
        }
    }
}

$prevDate = date('Y-m-d', strtotime($logDate . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($logDate . ' +1 day'));
$dayName = date('l', strtotime($logDate));

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Course Action Plan Update — NIELIT Admin</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .lp-muted { color: #64748b; font-size: 0.875rem; }
        .lp-today {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }
        .lp-topic {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin: 6px 0 0;
        }
        .lp-tt {
            background: #f8fafc;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        .lp-nav { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-calendar-check"></i> Daily Course Action Plan Update</h4>
                <p class="lp-muted mb-0"><?php echo htmlspecialchars($plan['plan_title'] ?? ''); ?></p>
            </div>
            <div class="lp-nav">
                <a class="btn btn-secondary" href="edit_lesson_plan.php?id=<?php echo (int) $planId; ?>">Edit Plan</a>
                <a class="btn btn-secondary" href="print_lesson_plan.php?id=<?php echo (int) $planId; ?>" target="_blank">
                    <i class="fas fa-print"></i> Print Plan
                </a>
                <a class="btn btn-secondary" href="manage_lesson_plans.php">All Plans</a>
            </div>
        </div>

        <div class="admin-main">
            <div class="content-card" style="margin-bottom:1rem;">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <h5 class="card-title mb-0">Select Date</h5>
                    <div class="lp-nav">
                        <a class="btn btn-sm btn-secondary" href="lesson_plan_daily.php?plan_id=<?php echo (int) $planId; ?>&amp;date=<?php echo urlencode($prevDate); ?>">← Prev</a>
                        <a class="btn btn-sm btn-secondary" href="lesson_plan_daily.php?plan_id=<?php echo (int) $planId; ?>&amp;date=<?php echo urlencode(date('Y-m-d')); ?>">Today</a>
                        <a class="btn btn-sm btn-secondary" href="lesson_plan_daily.php?plan_id=<?php echo (int) $planId; ?>&amp;date=<?php echo urlencode($nextDate); ?>">Next →</a>
                        <form method="get" style="margin:0;display:flex;gap:6px;">
                            <input type="hidden" name="plan_id" value="<?php echo (int) $planId; ?>">
                            <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($logDate); ?>" onchange="this.form.submit()">
                        </form>
                    </div>
                </div>
                <div style="padding:1rem;">
                    <div class="lp-today">
                        <div class="lp-muted">
                            <?php echo htmlspecialchars($dayName . ', ' . date('d M Y', strtotime($logDate))); ?>
                            · Faculty: <strong><?php echo htmlspecialchars($plan['faculty_name'] ?: '—'); ?></strong>
                            · Centre: <?php echo htmlspecialchars($plan['centre_name'] ?: '—'); ?>
                            · Batch: <?php
                            if (!empty($plan['batch_name'])) {
                                echo htmlspecialchars(($plan['batch_name'] ?? '') . ' (' . ($plan['batch_code'] ?? '') . ')');
                            } else {
                                echo '—';
                            }
                            ?>
                        </div>
                        <?php if (!empty($topicInfo['is_holiday'])): ?>
                            <p class="lp-topic">Holiday / Off — no class day in Course Action Plan</p>
                        <?php else: ?>
                            <div class="lp-muted" style="margin-top:6px;">
                                Plan Week: <strong><?php echo htmlspecialchars(lessonPlanOrdinal((int) ($topicInfo['week_number'] ?? 1))); ?></strong>
                                · Class Day: <strong><?php echo htmlspecialchars(lessonPlanOrdinal((int) ($topicInfo['class_day'] ?? 1))); ?></strong>
                            </div>
                            <p class="lp-topic">
                                <?php echo htmlspecialchars($topicInfo['topic'] ?: 'No topic entered for this week/day yet.'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($ttSlots)): ?>
                        <div class="lp-tt">
                            <strong>Today’s timetable slots (this batch):</strong>
                            <ul class="mb-0 mt-1">
                                <?php foreach ($ttSlots as $slot): ?>
                                    <li>
                                        <?php
                                        echo htmlspecialchars(substr((string) $slot['start_time'], 0, 5) . '–' . substr((string) $slot['end_time'], 0, 5));
                                        echo ' · ' . ($slot['subject'] ?? '');
                                        if (!empty($slot['faculty_name'])) {
                                            echo ' · ' . $slot['faculty_name'];
                                        }
                                        if (!empty($slot['room'])) {
                                            echo ' · ' . $slot['room'];
                                        }
                                        ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <p class="lp-muted">No timetable slots found for this batch on <?php echo htmlspecialchars($dayName); ?>.</p>
                    <?php endif; ?>

                    <?php if (empty($topicInfo['is_holiday'])): ?>
                        <form method="post" action="lesson_plan_daily.php?plan_id=<?php echo (int) $planId; ?>&amp;date=<?php echo urlencode($logDate); ?>" id="dailyUpdateForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="save_daily">
                            <input type="hidden" name="log_date" value="<?php echo htmlspecialchars($logDate); ?>">
                            <input type="hidden" name="week_number" value="<?php echo (int) ($topicInfo['week_number'] ?? 0); ?>">
                            <input type="hidden" name="class_day" value="<?php echo (int) ($topicInfo['class_day'] ?? 0); ?>">
                            <input type="hidden" name="lesson_plan_row_id" value="<?php echo (int) ($topicInfo['row_id'] ?? 0); ?>">

                            <?php
                            $plannedValue = $existingLog['topic_planned']
                                ?? ($topicInfo['topic'] ?? '');
                            ?>
                            <div class="mb-3">
                                <label class="form-label">Topic Planned (from Course Action Plan)</label>
                                <textarea name="topic_planned" class="form-control" rows="3"
                                          placeholder="Enter or update the planned theory topic for this class day…"><?php
                                    echo htmlspecialchars((string) $plannedValue);
                                ?></textarea>
                                <small class="lp-muted">Editable — saving also updates this week/day on the master Course Action Plan.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Topic Covered Today (faculty update) <span class="text-danger">*</span></label>
                                <textarea name="topic_covered" class="form-control" rows="3" required
                                          placeholder="What was actually taught today…"><?php
                                    echo htmlspecialchars($existingLog['topic_covered'] ?? ($topicInfo['topic'] ?? ''));
                                ?></textarea>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <?php $st = $existingLog['status'] ?? 'completed'; ?>
                                    <select name="status" class="form-control">
                                        <?php foreach (['completed' => 'Completed', 'partial' => 'Partial', 'skipped' => 'Skipped', 'rescheduled' => 'Rescheduled', 'planned' => 'Planned'] as $k => $label): ?>
                                            <option value="<?php echo $k; ?>" <?php echo $st === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Remarks</label>
                                    <input type="text" name="remarks" class="form-control" autocomplete="off"
                                           value="<?php echo htmlspecialchars($existingLog['remarks'] ?? ''); ?>"
                                           placeholder="Optional notes…">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" id="btnSaveDaily">
                                <i class="fas fa-save"></i> Save Daily Update
                            </button>
                            <?php if ($existingLog): ?>
                                <span class="lp-muted ms-2">Last saved by <?php echo htmlspecialchars($existingLog['updated_by'] ?? ''); ?>
                                    at <?php echo htmlspecialchars($existingLog['updated_at'] ?? ''); ?></span>
                            <?php endif; ?>
                        </form>
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
