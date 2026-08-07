<?php
/**
 * Admin: Manage Class Timetable
 * Weekly per-batch schedule slots (Mon–Sat).
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
require_once __DIR__ . '/../includes/class_timetable_helper.php';

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
ensureClassTimetableTable($conn);

// Excel/CSV export — grid format (days × time periods) matching the on-screen timetable
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $exportBatch = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : 0;
    $exportSlots = listClassTimetableAdmin($conn, $exportBatch > 0 ? $exportBatch : null);
    $built = classTimetableBuildGrid($exportSlots);
    $periods = $built['periods'];
    $days = $built['days'];
    $grid = $built['grid'];

    $filename = 'class_timetable' . ($exportBatch > 0 ? '_batch_' . $exportBatch : '_all') . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row: Day | period columns
    $header = ['Day'];
    foreach ($periods as $period) {
        $header[] = $period['short'];
    }
    fputcsv($out, $header);

    // One row per day
    foreach ($days as $dayNum => $dayName) {
        $row = [$dayName];
        foreach ($periods as $period) {
            $cellSlots = $grid[$dayNum][$period['key']] ?? [];
            if (empty($cellSlots)) {
                $row[] = '-';
            } else {
                $labels = [];
                foreach ($cellSlots as $slot) {
                    $label = classTimetableCellLabel($slot);
                    if (!empty($slot['room'])) {
                        $label .= ' [' . $slot['room'] . ']';
                    }
                    $labels[] = $label;
                }
                $row[] = implode(' / ', $labels);
            }
        }
        fputcsv($out, $row);
    }

    // Faculty legend
    $legends = classTimetableBuildLegends($exportSlots);
    if (!empty($legends['faculty'])) {
        fputcsv($out, []);
        $facultyParts = [];
        foreach ($legends['faculty'] as $ini => $full) {
            $facultyParts[] = $ini . ' = ' . $full;
        }
        fputcsv($out, ['Faculty: ' . implode(', ', $facultyParts)]);
    }

    fclose($out);
    exit();
}

$filterBatch = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : 0;
$viewMode = strtolower(trim((string) ($_GET['view'] ?? 'week')));
if (!in_array($viewMode, ['week', 'month'], true)) {
    $viewMode = 'week';
}
$ctMonthYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$ctMonthMonth = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
if ($ctMonthMonth < 1 || $ctMonthMonth > 12) {
    $ctMonthMonth = (int) date('n');
}
if ($ctMonthYear < 2000 || $ctMonthYear > 2100) {
    $ctMonthYear = (int) date('Y');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filterBatch = isset($_POST['redirect_batch_id']) ? (int) $_POST['redirect_batch_id'] : $filterBatch;
    $postView = strtolower(trim((string) ($_POST['redirect_view'] ?? '')));
    if (in_array($postView, ['week', 'month'], true)) {
        $viewMode = $postView;
    }
    if (isset($_POST['redirect_year'])) {
        $ctMonthYear = (int) $_POST['redirect_year'];
    }
    if (isset($_POST['redirect_month'])) {
        $ctMonthMonth = (int) $_POST['redirect_month'];
    }
}

$redirectQs = [];
if ($filterBatch > 0) {
    $redirectQs['batch_id'] = $filterBatch;
}
if ($viewMode === 'month') {
    $redirectQs['view'] = 'month';
    $redirectQs['year'] = $ctMonthYear;
    $redirectQs['month'] = $ctMonthMonth;
}
$redirectUrl = 'manage_class_timetable.php' . (!empty($redirectQs) ? ('?' . http_build_query($redirectQs)) : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $_SESSION['message'] = 'Invalid security token. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . $redirectUrl);
        exit();
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $editId = (int) ($_POST['id'] ?? 0);

        // Multi-day: if days[] checkboxes are submitted, create one slot per day
        $daysArray = isset($_POST['days']) && is_array($_POST['days']) ? array_map('intval', $_POST['days']) : [];
        $singleDay = (int) ($_POST['day_of_week'] ?? 0);
        if (empty($daysArray) && $singleDay > 0) {
            $daysArray = [$singleDay];
        }
        if ($editId > 0) {
            $daysArray = [$singleDay]; // editing always targets one slot
        }

        $basePayload = [
            'batch_id' => (int) ($_POST['batch_id'] ?? 0),
            'start_time' => $_POST['start_time'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'subject' => $_POST['subject'] ?? '',
            'faculty_name' => $_POST['faculty_name'] ?? '',
            'room' => $_POST['room'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'created_by' => (string) ($_SESSION['admin'] ?? 'admin'),
        ];

        $successCount = 0;
        $lastResult = ['success' => false, 'message' => 'No days selected.'];
        foreach ($daysArray as $dayVal) {
            $payload = $basePayload;
            $payload['day_of_week'] = $dayVal;
            $lastResult = saveClassTimetableSlot($conn, $payload, $editId > 0 ? $editId : null);
            if ($lastResult['success']) {
                $successCount++;
            }
        }

        if ($successCount > 1) {
            $result = ['success' => true, 'message' => "Added slot for $successCount days."];
        } else {
            $result = $lastResult;
        }
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $savedBatch = (int) ($payload['batch_id'] ?? 0);
            if ($savedBatch > 0) {
                $filterBatch = $savedBatch;
            }
            $redirectQs = [];
            if ($filterBatch > 0) {
                $redirectQs['batch_id'] = $filterBatch;
            }
            if ($viewMode === 'month') {
                $redirectQs['view'] = 'month';
                $redirectQs['year'] = $ctMonthYear;
                $redirectQs['month'] = $ctMonthMonth;
            }
            $redirectUrl = 'manage_class_timetable.php' . (!empty($redirectQs) ? ('?' . http_build_query($redirectQs)) : '');
        }
        header('Location: ' . $redirectUrl);
        exit();
    }

    if ($action === 'delete') {
        $deleteId = (int) ($_POST['id'] ?? 0);
        $result = deleteClassTimetableSlot($conn, $deleteId);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        header('Location: ' . $redirectUrl);
        exit();
    }
}

$allBatchesForSelect = [];
$batchSql = "SELECT b.id, b.batch_name, b.batch_code, b.status, c.course_name
             FROM batches b
             LEFT JOIN courses c ON c.id = b.course_id
             ORDER BY b.status = 'Active' DESC, b.start_date DESC";
$batchRes = $conn->query($batchSql);
if ($batchRes) {
    while ($b = $batchRes->fetch_assoc()) {
        $allBatchesForSelect[] = $b;
    }
}

$slots = listClassTimetableAdmin($conn, $filterBatch > 0 ? $filterBatch : null);
$dayLabels = classTimetableDayLabels();

$allCoursesForSelect = [];
$courseRes = $conn->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name ASC");
if ($courseRes) {
    while ($c = $courseRes->fetch_assoc()) {
        $allCoursesForSelect[] = $c;
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
    <title>Class Timetable - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .ct-muted { color: #64748b; font-size: 0.875rem; }
        .ct-modal .form-group { margin-bottom: 1rem; }
        .ct-modal label { font-weight: 500; color: #334155; margin-bottom: 6px; display: block; }
        .ct-help { font-size: 0.8rem; color: #64748b; margin-top: 4px; }
        #ct_day_single_wrap { display: none; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-calendar-alt"></i> Class Timetable</h4>
                <small>Weekly grid schedule (Monday–Friday periods) — like the institute spreadsheet</small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role">Administrator</span>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr((string) $_SESSION['admin'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-main">
            <!-- Toast notifications shown via JS -->

            <div class="content-card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <h5 class="card-title" style="margin:0;">
                        <i class="fas fa-<?php echo $viewMode === 'month' ? 'calendar' : 'th'; ?>"></i>
                        <?php echo $viewMode === 'month' ? 'Month-wise Timetable' : 'Weekly Timetable Grid'; ?>
                        <span class="ct-muted">(<?php echo count($slots); ?> slots)</span>
                    </h5>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <div class="btn-group" role="group" aria-label="View mode">
                            <a class="btn btn-sm <?php echo $viewMode === 'week' ? 'btn-primary' : 'btn-secondary'; ?>"
                               href="manage_class_timetable.php?<?php echo http_build_query(array_filter(['batch_id' => $filterBatch ?: null, 'view' => 'week'])); ?>">
                                Weekly
                            </a>
                            <a class="btn btn-sm <?php echo $viewMode === 'month' ? 'btn-primary' : 'btn-secondary'; ?>"
                               href="manage_class_timetable.php?<?php echo http_build_query(array_filter(['batch_id' => $filterBatch ?: null, 'view' => 'month', 'year' => $ctMonthYear, 'month' => $ctMonthMonth])); ?>">
                                Month-wise
                            </a>
                        </div>
                        <form method="get" style="margin:0;display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
                            <?php if ($viewMode === 'month'): ?>
                                <input type="hidden" name="year" value="<?php echo (int) $ctMonthYear; ?>">
                                <input type="hidden" name="month" value="<?php echo (int) $ctMonthMonth; ?>">
                            <?php endif; ?>
                            <select name="batch_id" class="form-control" style="min-width:220px;" onchange="this.form.submit()">
                                <option value="0">All batches (combined grid)</option>
                                <?php foreach ($allBatchesForSelect as $b): ?>
                                    <option value="<?php echo (int) $b['id']; ?>" <?php echo $filterBatch === (int) $b['id'] ? 'selected' : ''; ?>>
                                        <?php
                                        echo htmlspecialchars(($b['batch_name'] ?? '') . ' (' . ($b['batch_code'] ?? '') . ')');
                                        if (!empty($b['course_name'])) {
                                            echo ' — ' . htmlspecialchars($b['course_name']);
                                        }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <a class="btn btn-success" href="manage_class_timetable.php?<?php echo http_build_query(array_filter(['batch_id' => $filterBatch ?: null, 'export' => 'excel'])); ?>" title="Download as Excel/CSV">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <button type="button" class="btn btn-primary" onclick="openSlotModal()">
                            <i class="fas fa-plus"></i> Add Slot
                        </button>
                    </div>
                </div>

                <div style="padding: 0 1rem 1rem;">
                    <?php if ($viewMode === 'month'): ?>
                        <p class="ct-muted" style="margin: 0 0 12px;">
                            Month calendar filled from your weekly recurring slots (same class every matching weekday).
                        </p>
                        <?php
                        $ctMonthBaseUrl = 'manage_class_timetable.php';
                        $ctMonthQuery = array_filter(['batch_id' => $filterBatch ?: null]);
                        $ctMonthEditable = true;
                        $ctGridFilterBatch = $filterBatch;
                        $ctGridCsrf = (string) $_SESSION['csrf_token'];
                        include __DIR__ . '/../includes/class_timetable_month.php';
                        ?>
                    <?php else: ?>
                        <p class="ct-muted" style="margin: 0 0 12px;">
                            Columns are time periods; rows are days. Cell text shows <strong>Subject (Faculty initials)</strong>.
                            Click <strong>+</strong> in an empty cell to add a class for that period.
                        </p>
                        <?php
                        $ctGridEditable = true;
                        $ctGridCsrf = (string) $_SESSION['csrf_token'];
                        $ctGridFilterBatch = $filterBatch;
                        $ctGridShowLegends = true;
                        include __DIR__ . '/../includes/class_timetable_grid.php';
                        ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade ct-modal" id="slotModal" tabindex="-1" role="dialog" aria-labelledby="slotModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" id="slotForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="ct_id" value="0">
                <input type="hidden" name="redirect_batch_id" value="<?php echo (int) $filterBatch; ?>">
                <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($viewMode); ?>">
                <input type="hidden" name="redirect_year" value="<?php echo (int) $ctMonthYear; ?>">
                <input type="hidden" name="redirect_month" value="<?php echo (int) $ctMonthMonth; ?>">

                <div class="modal-header">
                    <h5 class="modal-title" id="slotModalTitle">Add Timetable Slot</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="closeSlotModal()"></button>
                </div>
                <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                    <div class="form-row" style="display:flex;gap:16px;flex-wrap:wrap;">
                        <div class="form-group" style="flex:1;min-width:220px;">
                            <label for="ct_batch_id">Batch <span class="text-danger">*</span></label>
                            <select class="form-control" id="ct_batch_id" name="batch_id" required>
                                <option value="">Select batch…</option>
                                <?php foreach ($allBatchesForSelect as $b): ?>
                                    <option value="<?php echo (int) $b['id']; ?>">
                                        <?php
                                        echo htmlspecialchars(($b['batch_name'] ?? '') . ' (' . ($b['batch_code'] ?? '') . ')');
                                        if (!empty($b['course_name'])) {
                                            echo ' — ' . htmlspecialchars($b['course_name']);
                                        }
                                        if (($b['status'] ?? '') !== 'Active') {
                                            echo ' [' . htmlspecialchars($b['status'] ?? '') . ']';
                                        }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1;min-width:180px;" id="ct_day_single_wrap">
                            <label for="ct_day">Day <span class="text-danger">*</span></label>
                            <select class="form-control" id="ct_day" name="day_of_week">
                                <option value="">Select day…</option>
                                <?php foreach ($dayLabels as $dayNum => $dayName): ?>
                                    <option value="<?php echo (int) $dayNum; ?>"><?php echo htmlspecialchars($dayName); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex:2;min-width:280px;" id="ct_days_multi_wrap">
                            <label>Days <span class="text-danger">*</span></label>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;padding:6px 0;">
                                <label style="display:flex;align-items:center;gap:4px;font-weight:normal;cursor:pointer;">
                                    <input type="checkbox" id="ct_days_all" onchange="toggleAllDays(this)"> <strong>Mon–Fri</strong>
                                </label>
                                <?php foreach ($dayLabels as $dayNum => $dayName):
                                    if ($dayNum >= 6) continue; // Skip Saturday & Sunday (holidays)
                                ?>
                                    <label style="display:flex;align-items:center;gap:4px;font-weight:normal;cursor:pointer;">
                                        <input type="checkbox" name="days[]" value="<?php echo (int) $dayNum; ?>" class="ct-day-check">
                                        <?php echo htmlspecialchars(substr($dayName, 0, 3)); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row" style="display:flex;gap:16px;flex-wrap:wrap;">
                        <div class="form-group" style="flex:1;min-width:200px;">
                            <label for="ct_period">Period (standard grid)</label>
                            <select class="form-control" id="ct_period" onchange="applyPeriodTimes()">
                                <option value="">Custom / pick below…</option>
                                <?php foreach (classTimetablePeriods() as $period): ?>
                                    <option value="<?php echo htmlspecialchars($period['key']); ?>"
                                            data-start="<?php echo htmlspecialchars(substr($period['start'], 0, 5)); ?>"
                                            data-end="<?php echo htmlspecialchars(substr($period['end'], 0, 5)); ?>">
                                        <?php echo htmlspecialchars($period['short']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="ct-help">Pick a period to fill start/end times like the spreadsheet.</div>
                        </div>
                        <div class="form-group" style="flex:1;min-width:140px;">
                            <label for="ct_start">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="ct_start" name="start_time" required>
                        </div>
                        <div class="form-group" style="flex:1;min-width:140px;">
                            <label for="ct_end">End Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="ct_end" name="end_time" required>
                        </div>
                        <div class="form-group" style="flex:2;min-width:200px;">
                            <label for="ct_course_select">Course</label>
                            <select class="form-control" id="ct_course_select" onchange="applyCourseToSubject()">
                                <option value="">-- Pick a course or type below --</option>
                                <?php foreach ($allCoursesForSelect as $c): ?>
                                    <option value="<?php echo htmlspecialchars(($c['course_name'] ?? '') . ' (' . ($c['course_code'] ?? '') . ')'); ?>">
                                        <?php echo htmlspecialchars(($c['course_name'] ?? '') . ' (' . ($c['course_code'] ?? '') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" style="display:flex;gap:16px;flex-wrap:wrap;">
                        <div class="form-group" style="flex:2;min-width:200px;">
                            <label for="ct_subject">Subject / Course Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ct_subject" name="subject" required maxlength="255" placeholder="e.g. CCC / Course on Computer Concepts (CCC-01)">
                            <div class="ct-help">Auto-filled from course above, or type manually.</div>
                        </div>
                        <div class="form-group" style="flex:1;min-width:180px;">
                            <label for="ct_faculty">Faculty (optional)</label>
                            <input type="text" class="form-control" id="ct_faculty" name="faculty_name" maxlength="255" placeholder="Faculty name">
                        </div>
                        <div class="form-group" style="flex:1;min-width:140px;">
                            <label for="ct_room">Room (optional)</label>
                            <input type="text" class="form-control" id="ct_room" name="room" maxlength="100" placeholder="e.g. Lab-2">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ct_notes">Notes (optional)</label>
                        <textarea class="form-control" id="ct_notes" name="notes" rows="2" placeholder="Extra instructions…"></textarea>
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:500;display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" id="ct_is_active" name="is_active" value="1" checked>
                            Visible to students
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeSlotModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Slot</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
<?php if ($message !== ''): ?>
showToast(<?php echo json_encode($message); ?>, <?php echo json_encode($message_type === 'danger' ? 'error' : $message_type); ?>);
<?php endif; ?>

function confirmDeleteSlot(e, form) {
    e.preventDefault();
    showConfirm({
        title: 'Delete Timetable Slot',
        message: 'Are you sure you want to delete this timetable slot? This cannot be undone.',
        type: 'danger',
        confirmText: 'Delete',
        cancelText: 'Cancel'
    }).then(function(confirmed) {
        if (confirmed) {
            form.submit();
        }
    });
    return false;
}
</script>
<script>
function toTimeInput(mysqlTime) {
    if (!mysqlTime) return '';
    var s = String(mysqlTime);
    if (s.length >= 5) return s.substring(0, 5);
    return s;
}

function getSlotModalInstance() {
    var el = document.getElementById('slotModal');
    if (!el) return null;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        return bootstrap.Modal.getOrCreateInstance(el);
    }
    return null;
}

function closeSlotModal() {
    var instance = getSlotModalInstance();
    if (instance) {
        instance.hide();
        return;
    }
    var el = document.getElementById('slotModal');
    if (!el) return;
    el.classList.remove('show');
    el.style.display = 'none';
    el.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(function (b) { b.remove(); });
}

function applyPeriodTimes() {
    var sel = document.getElementById('ct_period');
    if (!sel || !sel.value) return;
    var opt = sel.options[sel.selectedIndex];
    document.getElementById('ct_start').value = opt.getAttribute('data-start') || '';
    document.getElementById('ct_end').value = opt.getAttribute('data-end') || '';
}

function applyCourseToSubject() {
    var sel = document.getElementById('ct_course_select');
    if (sel && sel.value) {
        document.getElementById('ct_subject').value = sel.value;
    }
}

function syncPeriodSelectFromTimes() {
    var start = document.getElementById('ct_start').value || '';
    var sel = document.getElementById('ct_period');
    if (!sel) return;
    sel.value = '';
    for (var i = 0; i < sel.options.length; i++) {
        if ((sel.options[i].getAttribute('data-start') || '') === start) {
            sel.value = sel.options[i].value;
            break;
        }
    }
}

function toggleAllDays(cb) {
    var checks = document.querySelectorAll('.ct-day-check');
    // Mon-Fri = values 1-5
    checks.forEach(function(c) {
        if (parseInt(c.value) <= 5) {
            c.checked = cb.checked;
        }
    });
}

function setDayMode(isEdit) {
    var singleWrap = document.getElementById('ct_day_single_wrap');
    var multiWrap = document.getElementById('ct_days_multi_wrap');
    if (isEdit) {
        singleWrap.style.display = '';
        multiWrap.style.display = 'none';
        document.getElementById('ct_day').required = true;
    } else {
        singleWrap.style.display = 'none';
        multiWrap.style.display = '';
        document.getElementById('ct_day').required = false;
    }
}

function openSlotModalForPeriod(dayOfWeek, startTime, endTime) {
    openSlotModal({
        day_of_week: dayOfWeek,
        start_time: startTime,
        end_time: endTime
    });
}

function openSlotModal(row) {
    var form = document.getElementById('slotForm');
    form.reset();
    document.getElementById('ct_id').value = '0';
    document.getElementById('ct_is_active').checked = true;
    document.getElementById('slotModalTitle').textContent = 'Add Timetable Slot';
    document.getElementById('ct_period').value = '';
    document.querySelectorAll('.ct-day-check').forEach(function(c) { c.checked = false; });
    document.getElementById('ct_days_all').checked = false;

    var filterBatch = <?php echo (int) $filterBatch; ?>;
    if (filterBatch > 0) {
        document.getElementById('ct_batch_id').value = String(filterBatch);
    }

    var isEdit = false;
    if (row) {
        if (row.id) {
            isEdit = true;
            document.getElementById('slotModalTitle').textContent = 'Edit Timetable Slot';
            document.getElementById('ct_id').value = row.id;
            document.getElementById('ct_batch_id').value = row.batch_id || '';
            document.getElementById('ct_subject').value = row.subject || '';
            document.getElementById('ct_faculty').value = row.faculty_name || '';
            document.getElementById('ct_room').value = row.room || '';
            document.getElementById('ct_notes').value = row.notes || '';
            document.getElementById('ct_is_active').checked = String(row.is_active) === '1' || row.is_active === true || row.is_active === 1;
        }
        if (row.day_of_week) {
            document.getElementById('ct_day').value = row.day_of_week || '';
            // Also check the corresponding multi-day checkbox
            document.querySelectorAll('.ct-day-check').forEach(function(c) {
                if (c.value === String(row.day_of_week)) c.checked = true;
            });
        }
        if (row.start_time) {
            document.getElementById('ct_start').value = toTimeInput(row.start_time);
        }
        if (row.end_time) {
            document.getElementById('ct_end').value = toTimeInput(row.end_time);
        }
        syncPeriodSelectFromTimes();
    }

    setDayMode(isEdit);

    var instance = getSlotModalInstance();
    if (instance) {
        instance.show();
        return;
    }
    var el = document.getElementById('slotModal');
    el.style.display = 'block';
    el.classList.add('show');
    el.setAttribute('aria-hidden', 'false');
}

// Form validation: ensure at least one day selected in multi-day mode
document.getElementById('slotForm').addEventListener('submit', function(e) {
    var editId = parseInt(document.getElementById('ct_id').value) || 0;
    if (editId > 0) return; // editing uses single day dropdown
    var checks = document.querySelectorAll('.ct-day-check:checked');
    if (checks.length === 0) {
        e.preventDefault();
        alert('Please select at least one day.');
    }
});
</script>
</body>
</html>
