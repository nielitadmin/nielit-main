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

// Excel export — week grid OR full month (4 weekly tables with dates)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $exportCentre = isset($_GET['centre_id']) ? (int) $_GET['centre_id'] : 0;
    $exportView = strtolower(trim((string) ($_GET['view'] ?? 'week')));
    if (!in_array($exportView, ['week', 'month'], true)) {
        $exportView = 'week';
    }
    $exportYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
    $exportMonth = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
    if ($exportMonth < 1 || $exportMonth > 12) {
        $exportMonth = (int) date('n');
    }
    if ($exportYear < 2000 || $exportYear > 2100) {
        $exportYear = (int) date('Y');
    }

    $exportSlots = listClassTimetableAdmin($conn, null, $exportCentre > 0 ? $exportCentre : null);
    $built = classTimetableBuildGrid($exportSlots);
    $periods = $built['periods'];
    $days = $built['days'];
    $grid = $built['grid'];
    $facultyDisplayMap = classTimetableFacultyDisplayMap($exportSlots);
    $legends = classTimetableBuildLegends($exportSlots);

    $courseNameMap = [];
    $courseRes = $conn->query("SELECT course_name, course_code FROM courses ORDER BY course_name ASC");
    if ($courseRes) {
        while ($c = $courseRes->fetch_assoc()) {
            $short = strtoupper(preg_replace('/[-_].+$/', '', $c['course_code'] ?? ''));
            if ($short === '') {
                $short = strtoupper((string) ($c['course_code'] ?? ''));
            }
            if ($short !== '') {
                $courseNameMap[$short] = (string) ($c['course_name'] ?? '');
            }
            $full = trim((string) ($c['course_name'] ?? ''));
            if ($full !== '') {
                $courseNameMap[strtoupper($full)] = $full;
            }
        }
    }

    $centreName = 'All Centres';
    if ($exportCentre > 0) {
        $cStmt = $conn->prepare('SELECT name FROM centres WHERE id = ? LIMIT 1');
        if ($cStmt) {
            $cStmt->bind_param('i', $exportCentre);
            $cStmt->execute();
            $cRow = $cStmt->get_result()->fetch_assoc();
            $cStmt->close();
            if ($cRow && !empty($cRow['name'])) {
                $centreName = $cRow['name'];
            }
        }
    }

    $colCount = 1 + count($periods);
    $monthLabel = date('F Y', mktime(0, 0, 0, $exportMonth, 1, $exportYear));
    $filenameBase = 'class_timetable_' . preg_replace('/[^a-zA-Z0-9]+/', '_', $centreName);
    if ($exportView === 'month') {
        $filename = $filenameBase . '_month_' . date('Ym', mktime(0, 0, 0, $exportMonth, 1, $exportYear)) . '.xls';
        $titleText = 'NIELIT Class Timetable — Month-wise (' . $monthLabel . ')';
    } else {
        $filename = $filenameBase . '_weekly_' . date('Ymd') . '.xls';
        $titleText = 'NIELIT Class Timetable — Weekly Grid';
    }

    // Helper to render one cell's HTML
    $renderCell = static function (array $cellSlots) use ($facultyDisplayMap, $courseNameMap): string {
        if (empty($cellSlots)) {
            return '<td class="empty">—</td>';
        }
        $html = '<td class="filled">';
        $parts = [];
        foreach ($cellSlots as $slot) {
            $subject = trim((string) ($slot['subject'] ?? ''));
            $facultyCode = $facultyDisplayMap[trim((string) ($slot['faculty_name'] ?? ''))] ?? '';
            $line = htmlspecialchars($subject);
            if ($facultyCode !== '') {
                $line .= ' (' . htmlspecialchars($facultyCode) . ')';
            }
            $subjKey = strtoupper($subject);
            $fullCourse = $courseNameMap[$subjKey] ?? '';
            if ($fullCourse !== '' && strcasecmp($fullCourse, $subject) !== 0) {
                $line .= '<br><span class="course-full">' . htmlspecialchars($fullCourse) . '</span>';
            } elseif (!empty($slot['course_name']) && strcasecmp((string) $slot['course_name'], $subject) !== 0) {
                $line .= '<br><span class="course-full">' . htmlspecialchars($slot['course_name']) . '</span>';
            }
            if (!empty($slot['batch_name'])) {
                $line .= '<br><span class="batch">' . htmlspecialchars($slot['batch_name']);
                if (!empty($slot['batch_code'])) {
                    $line .= ' (' . htmlspecialchars($slot['batch_code']) . ')';
                }
                $line .= '</span>';
            }
            if (!empty($slot['room'])) {
                $line .= '<br><span class="room">' . htmlspecialchars($slot['room']) . '</span>';
            }
            if (!empty($slot['centre_name'])) {
                $line .= '<br><span class="room">' . htmlspecialchars($slot['centre_name']) . '</span>';
            }
            $parts[] = $line;
        }
        $html .= implode('<hr style="margin:4px 0;border:0;border-top:1px dashed #cbd5e1;">', $parts);
        $html .= '</td>';
        return $html;
    };

    $renderPeriodHeader = static function () use ($periods): string {
        $h = '<tr><th class="day-col">Day / Date</th>';
        foreach ($periods as $period) {
            $h .= '<th>' . htmlspecialchars(str_replace("\n", ' ', $period['label'])) . '</th>';
        }
        return $h . '</tr>';
    };

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>
        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; margin-bottom: 18px; }
        th, td { border: 1px solid #0f172a; padding: 6px 5px; vertical-align: middle; text-align: center; }
        th { background: #0f172a; color: #ffffff; font-weight: bold; }
        .day-col { background: #e2e8f0; font-weight: bold; text-align: left; width: 110px; }
        .filled { background: #fffbeb; font-weight: 600; text-align: left; }
        .empty { color: #94a3b8; }
        .title { font-size: 16px; font-weight: bold; text-align: center; border: none; }
        .meta { text-align: left; border: none; font-size: 12px; }
        .week-title { font-size: 13px; font-weight: bold; text-align: left; border: none; background: #f1f5f9; padding: 8px; }
        .legend { text-align: left; border: none; font-size: 11px; }
        .batch { color: #475569; font-size: 10px; }
        .room { color: #64748b; font-size: 10px; }
        .course-full { color: #0369a1; font-size: 10px; }
        .date-sub { color: #64748b; font-size: 10px; font-weight: normal; }
    </style></head><body>';

    echo '<table>';
    echo '<tr><td class="title" colspan="' . $colCount . '">' . htmlspecialchars($titleText) . '</td></tr>';
    echo '<tr><td class="meta" colspan="' . $colCount . '"><b>Centre:</b> ' . htmlspecialchars($centreName) . ' &nbsp;|&nbsp; <b>Generated:</b> ' . date('d M Y, h:i A') . ' &nbsp;|&nbsp; <b>Slots:</b> ' . count($exportSlots) . '</td></tr>';
    echo '<tr><td colspan="' . $colCount . '" style="border:none;height:8px;"></td></tr>';
    echo '</table>';

    if ($exportView === 'month') {
        // Build Mon–Fri weeks for the selected month (same as month screen)
        $daysInMonth = (int) date('t', mktime(0, 0, 0, $exportMonth, 1, $exportYear));
        $weeks = [];
        $currentWeek = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $ts = mktime(0, 0, 0, $exportMonth, $d, $exportYear);
            $dow = (int) date('N', $ts);
            if ($dow > 5) {
                continue;
            }
            if ($dow === 1 || empty($currentWeek)) {
                if (!empty($currentWeek)) {
                    $weeks[] = $currentWeek;
                }
                $currentWeek = [];
            }
            $currentWeek[$dow] = [
                'day' => $d,
                'date' => date('Y-m-d', $ts),
            ];
        }
        if (!empty($currentWeek)) {
            $weeks[] = $currentWeek;
        }

        foreach ($weeks as $wi => $weekDays) {
            $weekNum = $wi + 1;
            $dateNums = array_map(static function ($info) {
                return (int) $info['day'];
            }, $weekDays);
            $rangeText = '';
            if (!empty($dateNums)) {
                $firstTs = mktime(0, 0, 0, $exportMonth, min($dateNums), $exportYear);
                $lastTs = mktime(0, 0, 0, $exportMonth, max($dateNums), $exportYear);
                $rangeText = date('j M', $firstTs) . ' – ' . date('j M Y', $lastTs);
            }

            echo '<table>';
            echo '<tr><td class="week-title" colspan="' . $colCount . '">Week ' . $weekNum . ($rangeText !== '' ? ' — ' . htmlspecialchars($rangeText) : '') . '</td></tr>';
            echo $renderPeriodHeader();

            for ($dow = 1; $dow <= 5; $dow++) {
                $dayInfo = $weekDays[$dow] ?? null;
                if ($dayInfo === null) {
                    continue;
                }
                $dayName = $days[$dow] ?? date('l', strtotime($dayInfo['date']));
                echo '<tr>';
                echo '<td class="day-col">' . htmlspecialchars($dayName)
                    . '<br><span class="date-sub">' . htmlspecialchars(date('j M Y', strtotime($dayInfo['date']))) . '</span></td>';
                foreach ($periods as $period) {
                    $exportCellSlots = $grid[$dow][$period['key']] ?? [];
                    if (!empty($dayInfo['date'])) {
                        $exportCellSlots = classTimetableFilterSlotsForDate($exportCellSlots, (string) $dayInfo['date']);
                    }
                    echo $renderCell($exportCellSlots);
                }
                echo '</tr>';
            }
            echo '</table>';
        }
    } else {
        // Single weekly pattern table
        echo '<table>';
        echo '<tr><th class="day-col">Day</th>';
        foreach ($periods as $period) {
            echo '<th>' . htmlspecialchars(str_replace("\n", ' ', $period['label'])) . '</th>';
        }
        echo '</tr>';
        foreach ($days as $dayNum => $dayName) {
            echo '<tr>';
            echo '<td class="day-col">' . htmlspecialchars($dayName) . '</td>';
            foreach ($periods as $period) {
                echo $renderCell($grid[$dayNum][$period['key']] ?? []);
            }
            echo '</tr>';
        }
        echo '</table>';
    }

    // Legends
    echo '<table>';
    echo '<tr><td colspan="' . $colCount . '" style="border:none;height:8px;"></td></tr>';
    if (!empty($facultyDisplayMap)) {
        $facultyParts = [];
        foreach ($facultyDisplayMap as $fullName => $code) {
            $facultyParts[] = htmlspecialchars($code) . ' = ' . htmlspecialchars($fullName);
        }
        echo '<tr><td class="legend" colspan="' . $colCount . '"><b>Faculty:</b> ' . implode(', ', $facultyParts) . '</td></tr>';
    }
    if (!empty($legends['subjects'])) {
        $courseParts = [];
        foreach ($legends['subjects'] as $subj => $_) {
            $key = strtoupper(trim($subj));
            $full = $courseNameMap[$key] ?? '';
            if ($full !== '' && strcasecmp($full, $subj) !== 0) {
                $courseParts[] = htmlspecialchars($subj) . ' — ' . htmlspecialchars($full);
            } else {
                $courseParts[] = htmlspecialchars($subj);
            }
        }
        echo '<tr><td class="legend" colspan="' . $colCount . '"><b>Courses:</b> ' . implode(', ', $courseParts) . '</td></tr>';
    }
    echo '<tr><td class="legend" colspan="' . $colCount . '"><b>Note:</b> Cells show Subject (Faculty). Full course name, batch and room appear below each entry. Saturday &amp; Sunday are holidays.</td></tr>';
    echo '</table></body></html>';
    exit();
}

$filterBatch = 0; // No batch filter on grid — always show combined timetable
$filterCentre = isset($_GET['centre_id']) ? (int) $_GET['centre_id'] : 0;
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
    $filterCentre = isset($_POST['redirect_centre_id']) ? (int) $_POST['redirect_centre_id'] : $filterCentre;
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
if ($filterCentre > 0) {
    $redirectQs['centre_id'] = $filterCentre;
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
            'centre_id' => !empty($_POST['centre_id']) ? (int) $_POST['centre_id'] : null,
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
            $redirectQs = [];
            if ($filterCentre > 0) {
                $redirectQs['centre_id'] = $filterCentre;
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
// Ensure optional batch_description column exists before selecting it
$colCheck = $conn->query("SHOW COLUMNS FROM batches LIKE 'batch_description'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query('ALTER TABLE batches ADD COLUMN batch_description TEXT NULL DEFAULT NULL AFTER batch_name');
}
$batchSql = "SELECT b.id, b.batch_name, b.batch_code, b.batch_description, b.status,
                    b.start_date, b.end_date, c.course_name, c.course_code
             FROM batches b
             LEFT JOIN courses c ON c.id = b.course_id
             ORDER BY b.status = 'Active' DESC, b.start_date DESC";
$batchRes = $conn->query($batchSql);
if ($batchRes) {
    while ($b = $batchRes->fetch_assoc()) {
        $allBatchesForSelect[] = $b;
    }
}

$slots = listClassTimetableAdmin($conn, $filterBatch > 0 ? $filterBatch : null, $filterCentre > 0 ? $filterCentre : null);
$dayLabels = classTimetableDayLabels();

$allCoursesForSelect = [];
$courseRes = $conn->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name ASC");
if ($courseRes) {
    while ($c = $courseRes->fetch_assoc()) {
        $allCoursesForSelect[] = $c;
    }
}

$allCentresForSelect = [];
$centreRes = $conn->query("SELECT id, name FROM centres WHERE is_active = 1 ORDER BY name ASC");
if ($centreRes) {
    while ($ctr = $centreRes->fetch_assoc()) {
        $allCentresForSelect[] = $ctr;
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
                               href="manage_class_timetable.php?<?php echo http_build_query(array_filter(['centre_id' => $filterCentre ?: null, 'view' => 'week'])); ?>">
                                Weekly
                            </a>
                            <a class="btn btn-sm <?php echo $viewMode === 'month' ? 'btn-primary' : 'btn-secondary'; ?>"
                               href="manage_class_timetable.php?<?php echo http_build_query(array_filter(['centre_id' => $filterCentre ?: null, 'view' => 'month', 'year' => $ctMonthYear, 'month' => $ctMonthMonth])); ?>">
                                Month-wise
                            </a>
                        </div>
                        <form method="get" style="margin:0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
                            <?php if ($viewMode === 'month'): ?>
                                <input type="hidden" name="year" value="<?php echo (int) $ctMonthYear; ?>">
                                <input type="hidden" name="month" value="<?php echo (int) $ctMonthMonth; ?>">
                            <?php endif; ?>
                            <select name="centre_id" class="form-control" style="min-width:180px;" onchange="this.form.submit()">
                                <option value="0">All centres</option>
                                <?php foreach ($allCentresForSelect as $ctr): ?>
                                    <option value="<?php echo (int) $ctr['id']; ?>" <?php echo $filterCentre === (int) $ctr['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ctr['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <a class="btn btn-success" href="manage_class_timetable.php?<?php
                            echo http_build_query(array_filter([
                                'centre_id' => $filterCentre ?: null,
                                'view' => $viewMode,
                                'year' => $viewMode === 'month' ? $ctMonthYear : null,
                                'month' => $viewMode === 'month' ? $ctMonthMonth : null,
                                'export' => 'excel',
                            ]));
                        ?>" title="<?php echo $viewMode === 'month' ? 'Download full month Excel (all weeks with dates)' : 'Download weekly Excel timetable'; ?>">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a class="btn btn-secondary" target="_blank" rel="noopener"
                           href="print_class_timetable.php?<?php
                            echo http_build_query(array_filter([
                                'centre_id' => $filterCentre ?: null,
                                'view' => $viewMode,
                                'year' => $viewMode === 'month' ? $ctMonthYear : null,
                                'month' => $viewMode === 'month' ? $ctMonthMonth : null,
                            ]));
                           ?>"
                           title="<?php echo $viewMode === 'month' ? 'Print month-wise timetable (each week with dates)' : 'Print weekly timetable with logo and header'; ?>">
                            <i class="fas fa-print"></i> Print Timetable
                        </a>
                        <button type="button" class="btn btn-primary" onclick="openSlotModal()">
                            <i class="fas fa-plus"></i> Add Slot
                        </button>
                    </div>
                </div>

                <div style="padding: 0 1rem 1rem;">
                    <?php if ($viewMode === 'month'): ?>
                        <?php
                        $ctMonthBaseUrl = 'manage_class_timetable.php';
                        $ctMonthQuery = array_filter(['centre_id' => $filterCentre ?: null]);
                        $ctMonthEditable = true;
                        $ctGridFilterBatch = 0;
                        $ctGridCsrf = (string) $_SESSION['csrf_token'];
                        $ctGridCourses = $allCoursesForSelect;
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
                        $ctGridFilterBatch = 0;
                        $ctGridFilterCentre = $filterCentre;
                        $ctGridShowLegends = true;
                        $ctGridCourses = $allCoursesForSelect;
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
                <input type="hidden" name="redirect_centre_id" value="<?php echo (int) $filterCentre; ?>">
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
                            <select class="form-control" id="ct_batch_id" name="batch_id" required onchange="showBatchDetails()">
                                <option value="">Select batch…</option>
                                <?php foreach ($allBatchesForSelect as $b):
                                    $shortCode = strtoupper(preg_replace('/[-_].+$/', '', $b['course_code'] ?? ''));
                                    if ($shortCode === '') {
                                        $shortCode = (string) ($b['course_code'] ?? '');
                                    }
                                ?>
                                    <option value="<?php echo (int) $b['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($b['batch_name'] ?? '', ENT_QUOTES); ?>"
                                            data-code="<?php echo htmlspecialchars($b['batch_code'] ?? '', ENT_QUOTES); ?>"
                                            data-description="<?php echo htmlspecialchars($b['batch_description'] ?? '', ENT_QUOTES); ?>"
                                            data-course="<?php echo htmlspecialchars($b['course_name'] ?? '', ENT_QUOTES); ?>"
                                            data-course-code="<?php echo htmlspecialchars($shortCode, ENT_QUOTES); ?>"
                                            data-start="<?php echo htmlspecialchars($b['start_date'] ?? '', ENT_QUOTES); ?>"
                                            data-end="<?php echo htmlspecialchars($b['end_date'] ?? '', ENT_QUOTES); ?>"
                                            data-status="<?php echo htmlspecialchars($b['status'] ?? '', ENT_QUOTES); ?>">
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

                    <div class="form-group" id="ct_batch_details_wrap" style="display:none;margin-bottom:1rem;">
                        <label>Batch Details</label>
                        <div id="ct_batch_details" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;font-size:0.875rem;color:#334155;line-height:1.5;"></div>
                    </div>

                    <div class="form-row" style="display:flex;gap:16px;flex-wrap:wrap;">
                        <div class="form-group" style="flex:1;min-width:250px;">
                            <label for="ct_centre_id">Centre (optional)</label>
                            <select class="form-control" id="ct_centre_id" name="centre_id">
                                <option value="">-- All Centres --</option>
                                <?php foreach ($allCentresForSelect as $ctr): ?>
                                    <option value="<?php echo (int) $ctr['id']; ?>">
                                        <?php echo htmlspecialchars($ctr['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                                <option value="" data-fullname="">-- Pick a course or type below --</option>
                                <?php foreach ($allCoursesForSelect as $c):
                                    $shortCode = strtoupper(preg_replace('/[-_].+$/', '', $c['course_code'] ?? ''));
                                    if ($shortCode === '') $shortCode = $c['course_code'] ?? '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($shortCode); ?>"
                                            data-fullname="<?php echo htmlspecialchars($c['course_name'] ?? ''); ?>">
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

function formatBatchDate(ymd) {
    if (!ymd) return '';
    var parts = String(ymd).split('-');
    if (parts.length !== 3) return ymd;
    return parts[2] + '-' + parts[1] + '-' + parts[0];
}

function showBatchDetails() {
    var sel = document.getElementById('ct_batch_id');
    var wrap = document.getElementById('ct_batch_details_wrap');
    var box = document.getElementById('ct_batch_details');
    if (!sel || !wrap || !box) return;

    if (!sel.value) {
        wrap.style.display = 'none';
        box.innerHTML = '';
        return;
    }

    var opt = sel.options[sel.selectedIndex];
    var name = opt.getAttribute('data-name') || '';
    var code = opt.getAttribute('data-code') || '';
    var desc = opt.getAttribute('data-description') || '';
    var course = opt.getAttribute('data-course') || '';
    var courseCode = opt.getAttribute('data-course-code') || '';
    var start = opt.getAttribute('data-start') || '';
    var end = opt.getAttribute('data-end') || '';
    var status = opt.getAttribute('data-status') || '';

    var lines = [];
    lines.push('<strong>' + escapeHtml(name) + '</strong> <span class="ct-muted">(' + escapeHtml(code) + ')</span>');
    if (course) {
        lines.push('<div><strong>Course:</strong> ' + escapeHtml(course) + (courseCode ? ' (' + escapeHtml(courseCode) + ')' : '') + '</div>');
    }
    if (start || end) {
        lines.push('<div><strong>Duration:</strong> ' + escapeHtml(formatBatchDate(start) || '—') + ' to ' + escapeHtml(formatBatchDate(end) || '—') + '</div>');
    }
    if (status) {
        lines.push('<div><strong>Status:</strong> ' + escapeHtml(status) + '</div>');
    }
    if (desc) {
        lines.push('<div style="margin-top:4px;white-space:pre-wrap;"><strong>Description:</strong> ' + escapeHtml(desc) + '</div>');
    } else {
        lines.push('<div class="ct-muted" style="margin-top:4px;">No batch description set.</div>');
    }
    box.innerHTML = lines.join('');
    wrap.style.display = '';

    // Auto-fill course/subject from batch when adding (not overwriting if subject already filled on edit)
    if (courseCode) {
        var courseSel = document.getElementById('ct_course_select');
        var subjectEl = document.getElementById('ct_subject');
        if (courseSel) {
            for (var i = 0; i < courseSel.options.length; i++) {
                if (courseSel.options[i].value.toUpperCase() === courseCode.toUpperCase()) {
                    courseSel.value = courseSel.options[i].value;
                    break;
                }
            }
        }
        if (subjectEl && !subjectEl.value) {
            subjectEl.value = courseCode;
        }
    }
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function syncCourseSelectFromSubject() {
    var subj = (document.getElementById('ct_subject').value || '').toUpperCase().trim();
    var sel = document.getElementById('ct_course_select');
    if (!sel) return;
    sel.value = '';
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value.toUpperCase() === subj) {
            sel.value = sel.options[i].value;
            break;
        }
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

    var isEdit = false;
    if (row) {
        if (row.id) {
            isEdit = true;
            document.getElementById('slotModalTitle').textContent = 'Edit Timetable Slot';
            document.getElementById('ct_id').value = row.id;
            document.getElementById('ct_batch_id').value = row.batch_id || '';
            document.getElementById('ct_centre_id').value = row.centre_id || '';
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
    showBatchDetails();

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
