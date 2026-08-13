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
require_once __DIR__ . '/../includes/teaching_access.php';

admin_require_teaching_tools();

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

$slots = listClassTimetableAdmin(
    $conn,
    null,
    $filterCentre > 0 ? $filterCentre : null,
    !$isMonth
);
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
        .print-fit-wrap {
            max-width: 1100px;
            margin: 0 auto 16px;
        }
        .sheet {
            max-width: 1100px;
            margin: 0 auto 16px;
            background: #fff;
            padding: 14px 16px 18px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.12);
        }
        .print-fit-wrap .sheet { margin: 0; max-width: none; }
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
        .week-block { margin-bottom: 8px; }
        .week-block:last-of-type { margin-bottom: 0; }
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
        @page {
            size: 297mm 210mm; /* A4 landscape */
            margin: 4mm;
        }
        @media print {
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                height: auto !important;
                overflow: hidden !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .toolbar { display: none !important; }
            .print-fit-wrap {
                max-width: none !important;
                margin: 0 !important;
                overflow: hidden !important;
            }
            .sheet {
                max-width: none !important;
                width: 100% !important;
                box-shadow: none !important;
                padding: 2mm 3mm !important;
                margin: 0 !important;
                page-break-after: auto !important;
                break-after: auto !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .lh-header { margin-bottom: 2px; gap: 6px; }
            .lh-header img { height: 26px; }
            .lh-text .hi { font-size: 10px; }
            .lh-text .en { font-size: 9px; }
            .lh-text .centre { font-size: 8px; }
            .lh-text .tag { font-size: 6.5px; }
            .lh-rule { margin: 2px 0 3px; }
            .doc-title { font-size: 10px; margin: 0 0 1px; }
            .doc-meta { font-size: 7px; margin: 0 0 3px; }
            .week-block { margin-bottom: 2.5mm; page-break-inside: avoid; }
            .week-title { font-size: 8px; margin: 0 0 2px; padding: 1px 5px; }
            .ct-sheet { font-size: 6.5px; }
            .ct-sheet th, .ct-sheet td { padding: 1px; }
            .ct-sheet thead th { font-size: 6px; padding: 2px 1px; }
            .ct-day-col { width: 58px; font-size: 7px; }
            .ct-day-date { font-size: 6px; }
            .ct-entry { font-size: 6.5px; }
            .ct-meta { font-size: 5.5px; }
            .legend { font-size: 6.5px; margin-top: 2px; }
            .footer-note { font-size: 6px; margin-top: 2px; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <a class="btn btn-back" href="<?php echo htmlspecialchars($backUrl); ?>">← Back</a>
        <button type="button" class="btn btn-print" id="btnPrintTimetable">🖨 Print A4 Landscape (1 page)</button>
    </div>

    <div class="print-fit-wrap">
    <?php if ($isMonth && !empty($weeks)): ?>
        <div class="sheet print-page" id="print-sheet">
            <?php $renderHeader(); ?>
            <h1 class="doc-title">Month-wise Class Timetable — <?php echo htmlspecialchars($monthLabel); ?></h1>
            <p class="doc-meta">
                <strong>Centre:</strong> <?php echo htmlspecialchars($centreName); ?>
                &nbsp;|&nbsp; <strong>Printed:</strong> <?php echo date('d M Y, h:i A'); ?>
                &nbsp;|&nbsp; <strong>Slots:</strong> <?php echo count($slots); ?>
            </p>
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
                ?>
                <div class="week-block">
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
                </div>
            <?php endforeach; ?>
            <?php $renderLegends(); ?>
            <p class="footer-note">Monday–Friday · Saturday &amp; Sunday are holidays · <?php echo htmlspecialchars($monthLabel); ?> · Print on A4 Landscape (1 page)</p>
        </div>
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
            <p class="footer-note">Monday–Friday schedule · Saturday &amp; Sunday are holidays · Print on A4 Landscape (1 page)</p>
        </div>
    <?php endif; ?>
    </div>

    <script>
        function resetPrintFit() {
            var wrap = document.querySelector('.print-fit-wrap');
            var sheet = document.querySelector('.print-page');
            if (wrap) {
                wrap.style.height = '';
                wrap.style.width = '';
                wrap.style.overflow = '';
            }
            if (sheet) {
                sheet.style.transform = '';
                sheet.style.transformOrigin = '';
                sheet.style.width = '';
                sheet.style.zoom = '';
            }
            document.documentElement.style.zoom = '';
        }

        function fitPagesForPrint() {
            resetPrintFit();
            var wrap = document.querySelector('.print-fit-wrap');
            var sheet = document.querySelector('.print-page');
            if (!wrap || !sheet) {
                return;
            }

            // A4 landscape printable area (~297×210mm minus 4mm margins) at 96dpi
            var maxW = 1090;
            var maxH = 740;
            var w = Math.max(sheet.scrollWidth, sheet.offsetWidth, 1);
            var h = Math.max(sheet.scrollHeight, sheet.offsetHeight, 1);
            var scale = Math.min(1, maxW / w, maxH / h) * 0.98;
            if (scale > 0.995) {
                return;
            }
            scale = Math.max(0.4, scale);

            var scaledH = Math.ceil(h * scale);
            var scaledW = Math.ceil(w * scale);
            wrap.style.overflow = 'hidden';
            wrap.style.height = scaledH + 'px';
            wrap.style.width = '100%';

            var zoomSupported = ('zoom' in sheet.style);
            if (zoomSupported) {
                sheet.style.zoom = String(scale);
            } else {
                sheet.style.transformOrigin = 'top left';
                sheet.style.transform = 'scale(' + scale.toFixed(3) + ')';
                wrap.style.width = scaledW + 'px';
            }
        }

        function printTimetable() {
            fitPagesForPrint();
            setTimeout(function () { window.print(); }, 180);
        }

        window.addEventListener('beforeprint', fitPagesForPrint);
        window.addEventListener('afterprint', resetPrintFit);

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
