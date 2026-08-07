<?php
/**
 * Print Class Timetable — logo + official header.
 * Supports weekly (1 sheet) and month-wise (one sheet per week with dates).
 */
require_once __DIR__ . '/../includes/url_helper.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/institute_branding.php';
require_once __DIR__ . '/../includes/class_timetable_helper.php';

$role = $_SESSION['admin_role'] ?? '';
$blocked = in_array($role, ['nsqf_manager', 'front_office', 'placement_coordinator'], true);
if ($blocked) {
    $_SESSION['message'] = 'Access denied.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . relative_url('dashboard.php'));
    exit();
}

ensureClassTimetableTable($conn);

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
$autoPrint = !isset($_GET['noprint']);
$isMonth = ($viewMode === 'month');

$slots = listClassTimetableAdmin($conn, null, $filterCentre > 0 ? $filterCentre : null);
$built = classTimetableBuildGrid($slots);
$days = $built['days'];
$periods = $built['periods'];
$grid = $built['grid'];
$facultyDisplayMap = classTimetableFacultyDisplayMap($slots);
$legends = classTimetableBuildLegends($slots);

$centreName = 'All Centres';
if ($filterCentre > 0) {
    $cStmt = $conn->prepare('SELECT name FROM centres WHERE id = ? LIMIT 1');
    if ($cStmt) {
        $cStmt->bind_param('i', $filterCentre);
        $cStmt->execute();
        $cRow = $cStmt->get_result()->fetch_assoc();
        $cStmt->close();
        if ($cRow && !empty($cRow['name'])) {
            $centreName = $cRow['name'];
        }
    }
}

$courseNameMap = [];
$courseRes = $conn->query('SELECT course_name, course_code FROM courses ORDER BY course_name ASC');
if ($courseRes) {
    while ($c = $courseRes->fetch_assoc()) {
        $short = strtoupper(preg_replace('/[-_].+$/', '', $c['course_code'] ?? ''));
        if ($short === '') {
            $short = strtoupper((string) ($c['course_code'] ?? ''));
        }
        if ($short !== '') {
            $courseNameMap[$short] = (string) ($c['course_name'] ?? '');
        }
    }
}

$monthLabel = date('F Y', mktime(0, 0, 0, $ctMonthMonth, 1, $ctMonthYear));

// Build weeks for month view
$weeks = [];
if ($isMonth) {
    $daysInMonth = (int) date('t', mktime(0, 0, 0, $ctMonthMonth, 1, $ctMonthYear));
    $currentWeek = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $ts = mktime(0, 0, 0, $ctMonthMonth, $d, $ctMonthYear);
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
}

$logoUrl = APP_URL . '/assets/images/bhubaneswar_logo.png';
$backQs = array_filter([
    'centre_id' => $filterCentre ?: null,
    'view' => $isMonth ? 'month' : null,
    'year' => $isMonth ? $ctMonthYear : null,
    'month' => $isMonth ? $ctMonthMonth : null,
]);
$backUrl = 'manage_class_timetable.php' . (!empty($backQs) ? ('?' . http_build_query($backQs)) : '');

$renderCell = static function (array $cellSlots) use ($facultyDisplayMap): string {
    if (empty($cellSlots)) {
        return '<td class="ct-empty">—</td>';
    }
    $html = '<td class="ct-filled">';
    foreach ($cellSlots as $slot) {
        $subject = trim((string) ($slot['subject'] ?? ''));
        $facCode = $facultyDisplayMap[trim((string) ($slot['faculty_name'] ?? ''))] ?? '';
        $label = $facCode !== '' ? ($subject . ' (' . $facCode . ')') : $subject;
        $html .= '<span class="ct-entry">' . htmlspecialchars($label) . '</span>';
        if (!empty($slot['batch_name'])) {
            $html .= '<span class="ct-meta">' . htmlspecialchars($slot['batch_name']) . '</span>';
        }
        if (!empty($slot['room'])) {
            $html .= '<span class="ct-meta">' . htmlspecialchars($slot['room']) . '</span>';
        }
    }
    return $html . '</td>';
};

$renderHeader = static function () use ($logoUrl): void {
    ?>
    <div class="lh-header">
        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="NIELIT Logo">
        <div class="lh-text">
            <p class="hi"><?php echo htmlspecialchars(INSTITUTE_NAME_HI_FORMAL, ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="en">National Institute of Electronics and Information Technology (NIELIT)</p>
            <p class="centre">NIELIT Bhubaneswar | Raipur | Baleshwar</p>
            <p class="tag">(An Autonomous Scientific Society of Ministry of Electronics and Information Technology (MeitY), Govt. of India)</p>
        </div>
    </div>
    <hr class="lh-rule">
    <?php
};

$renderLegends = static function () use ($facultyDisplayMap, $legends, $courseNameMap): void {
    if (!empty($facultyDisplayMap)) {
        $parts = [];
        foreach ($facultyDisplayMap as $fullName => $code) {
            $parts[] = htmlspecialchars($code) . ' — ' . htmlspecialchars($fullName);
        }
        echo '<div class="legend"><strong>Faculty:</strong> ' . implode(', ', $parts) . '</div>';
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
        echo '<div class="legend"><strong>Courses:</strong> ' . implode(', ', $courseParts) . '</div>';
    }
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isMonth ? 'Month-wise' : 'Weekly'; ?> Class Timetable — Print</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px;
            font-family: Arial, Helvetica, sans-serif;
            color: #0f172a;
            background: #e2e8f0;
        }
        .toolbar {
            max-width: 1100px;
            margin: 0 auto 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .toolbar .btn {
            border: 0;
            border-radius: 6px;
            padding: 8px 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .btn-print { background: #0f172a; color: #fff; }
        .btn-back { background: #64748b; color: #fff; }
        .sheet {
            max-width: 1100px;
            margin: 0 auto 16px;
            background: #fff;
            padding: 14px 16px 18px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.12);
        }
        .lh-header { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
        .lh-header img { height: 44px; width: auto; display: block; }
        .lh-text { flex: 1; text-align: center; }
        .lh-text .hi { font-size: 13px; font-weight: 700; margin: 0; line-height: 1.2; }
        .lh-text .en { font-size: 12px; font-weight: 700; margin: 0; line-height: 1.2; }
        .lh-text .centre { font-size: 11px; font-weight: 600; margin: 0; line-height: 1.2; }
        .lh-text .tag { font-size: 9px; color: #334155; margin: 0; line-height: 1.2; }
        .lh-rule { border: 0; border-top: 1.5px solid #0f172a; margin: 4px 0 6px; }
        .doc-title {
            text-align: center;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            margin: 0 0 2px;
        }
        .doc-meta { text-align: center; font-size: 10px; color: #334155; margin: 0 0 6px; }
        .week-title {
            font-size: 11px;
            font-weight: 700;
            margin: 0 0 4px;
            padding: 4px 8px;
            background: #f1f5f9;
            border-left: 3px solid #f59e0b;
        }
        .ct-sheet {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8.5px;
        }
        .ct-sheet th, .ct-sheet td {
            border: 1px solid #0f172a;
            text-align: center;
            vertical-align: middle;
            padding: 2px 2px;
            line-height: 1.2;
        }
        .ct-sheet thead th {
            background: #f1f5f9;
            font-weight: 700;
            font-size: 8px;
            padding: 3px 2px;
        }
        .ct-day-col {
            width: 78px;
            background: #e2e8f0;
            font-weight: 700;
            text-align: left;
            padding-left: 4px !important;
            font-size: 9px;
        }
        .ct-day-date { display: block; font-size: 7.5px; font-weight: 500; color: #64748b; }
        .ct-filled { background: #fffbeb; font-weight: 600; }
        .ct-empty { color: #94a3b8; }
        .ct-entry { display: block; font-weight: 700; font-size: 8.5px; }
        .ct-meta { display: block; font-size: 7.5px; font-weight: 500; color: #475569; }
        .legend { margin-top: 5px; font-size: 9px; line-height: 1.3; color: #334155; }
        .footer-note { margin-top: 4px; font-size: 8px; color: #64748b; text-align: center; }
        @media print {
            html, body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
            .toolbar { display: none !important; }
            .sheet {
                max-width: none !important;
                width: 100% !important;
                box-shadow: none !important;
                padding: 3mm 4mm !important;
                margin: 0 !important;
                page-break-after: always;
                break-after: page;
                page-break-inside: avoid;
            }
            .sheet:last-of-type { page-break-after: auto; break-after: auto; }
            .lh-header img { height: 34px; }
            .lh-text .hi { font-size: 11px; }
            .lh-text .en { font-size: 10px; }
            .lh-text .centre { font-size: 9px; }
            .lh-text .tag { font-size: 7.5px; }
            .doc-title { font-size: 11px; }
            .doc-meta { font-size: 8px; margin: 0 0 4px; }
            .week-title { font-size: 9px; margin: 0 0 3px; padding: 2px 6px; }
            .ct-sheet { font-size: 7.5px; }
            .ct-sheet th, .ct-sheet td { padding: 1px; }
            .ct-sheet thead th { font-size: 7px; }
            .ct-day-col { width: 70px; font-size: 8px; }
            .ct-entry { font-size: 7.5px; }
            .ct-meta { font-size: 6.5px; }
            .legend { font-size: 7.5px; margin-top: 3px; }
            .footer-note { font-size: 7px; }
            @page { size: A4 landscape; margin: 5mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <a class="btn btn-back" href="<?php echo htmlspecialchars($backUrl); ?>">← Back</a>
        <button type="button" class="btn btn-print" id="btnPrintTimetable">🖨 Print Timetable</button>
    </div>

    <?php if ($isMonth && !empty($weeks)): ?>
        <?php foreach ($weeks as $wi => $weekDays): ?>
            <?php
            $weekNum = $wi + 1;
            $dateNums = array_map(static function ($info) {
                return (int) $info['day'];
            }, $weekDays);
            $rangeText = '';
            if (!empty($dateNums)) {
                $firstTs = mktime(0, 0, 0, $ctMonthMonth, min($dateNums), $ctMonthYear);
                $lastTs = mktime(0, 0, 0, $ctMonthMonth, max($dateNums), $ctMonthYear);
                $rangeText = date('j M', $firstTs) . ' – ' . date('j M Y', $lastTs);
            }
            $isLast = ($wi === count($weeks) - 1);
            ?>
            <div class="sheet print-page" id="print-sheet-<?php echo $weekNum; ?>">
                <?php $renderHeader(); ?>
                <h1 class="doc-title">Month-wise Class Timetable — <?php echo htmlspecialchars($monthLabel); ?></h1>
                <p class="doc-meta">
                    <strong>Centre:</strong> <?php echo htmlspecialchars($centreName); ?>
                    &nbsp;|&nbsp; <strong>Printed:</strong> <?php echo date('d M Y, h:i A'); ?>
                    &nbsp;|&nbsp; <strong>Slots:</strong> <?php echo count($slots); ?>
                </p>
                <div class="week-title">Week <?php echo $weekNum; ?><?php echo $rangeText !== '' ? ' — ' . htmlspecialchars($rangeText) : ''; ?></div>
                <table class="ct-sheet">
                    <thead>
                        <tr>
                            <th class="ct-day-col">Day / Date</th>
                            <?php foreach ($periods as $period): ?>
                                <th><?php echo htmlspecialchars($period['short']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($dow = 1; $dow <= 5; $dow++): ?>
                            <?php
                            $dayInfo = $weekDays[$dow] ?? null;
                            if ($dayInfo === null) {
                                continue;
                            }
                            $dayName = $days[$dow] ?? date('l', strtotime($dayInfo['date']));
                            ?>
                            <tr>
                                <th class="ct-day-col" scope="row">
                                    <?php echo htmlspecialchars($dayName); ?>
                                    <span class="ct-day-date"><?php echo htmlspecialchars(date('j M Y', strtotime($dayInfo['date']))); ?></span>
                                </th>
                                <?php foreach ($periods as $period): ?>
                                    <?php
                                    $printCellSlots = $grid[$dow][$period['key']] ?? [];
                                    if (!empty($dayInfo['date'])) {
                                        $printCellSlots = classTimetableFilterSlotsForDate($printCellSlots, (string) $dayInfo['date']);
                                    }
                                    echo $renderCell($printCellSlots);
                                    ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                <?php if ($isLast): ?>
                    <?php $renderLegends(); ?>
                    <p class="footer-note">Monday–Friday · Saturday &amp; Sunday are holidays · <?php echo htmlspecialchars($monthLabel); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="sheet print-page" id="print-sheet">
            <?php $renderHeader(); ?>
            <h1 class="doc-title">Weekly Class Timetable</h1>
            <p class="doc-meta">
                <strong>Centre:</strong> <?php echo htmlspecialchars($centreName); ?>
                &nbsp;|&nbsp; <strong>Printed:</strong> <?php echo date('d M Y, h:i A'); ?>
                &nbsp;|&nbsp; <strong>Slots:</strong> <?php echo count($slots); ?>
            </p>
            <table class="ct-sheet">
                <thead>
                    <tr>
                        <th class="ct-day-col">Day</th>
                        <?php foreach ($periods as $period): ?>
                            <th><?php echo htmlspecialchars($period['short']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($days as $dayNum => $dayName): ?>
                        <tr>
                            <th class="ct-day-col" scope="row"><?php echo htmlspecialchars($dayName); ?></th>
                            <?php foreach ($periods as $period): ?>
                                <?php echo $renderCell($grid[$dayNum][$period['key']] ?? []); ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php $renderLegends(); ?>
            <p class="footer-note">Monday–Friday schedule · Saturday &amp; Sunday are holidays</p>
        </div>
    <?php endif; ?>

    <script>
        function fitPagesForPrint() {
            var pages = document.querySelectorAll('.print-page');
            var maxW = 1080;
            var maxH = 720;
            pages.forEach(function (sheet) {
                sheet.style.transform = '';
                sheet.style.transformOrigin = 'top left';
                sheet.style.width = '100%';
                var w = sheet.scrollWidth;
                var h = sheet.scrollHeight;
                var scale = Math.min(1, maxW / w, maxH / h);
                if (scale < 0.99) {
                    sheet.style.transform = 'scale(' + scale.toFixed(3) + ')';
                    sheet.style.width = ((100 / scale).toFixed(2)) + '%';
                }
            });
        }

        function printTimetable() {
            fitPagesForPrint();
            setTimeout(function () { window.print(); }, 120);
        }

        window.addEventListener('beforeprint', fitPagesForPrint);
        window.addEventListener('afterprint', function () {
            document.querySelectorAll('.print-page').forEach(function (sheet) {
                sheet.style.transform = '';
                sheet.style.width = '100%';
            });
        });

        var btn = document.getElementById('btnPrintTimetable');
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                printTimetable();
            });
        }

        <?php if ($autoPrint): ?>
        window.addEventListener('load', function () {
            setTimeout(printTimetable, 400);
        });
        <?php endif; ?>
    </script>
</body>
</html>
