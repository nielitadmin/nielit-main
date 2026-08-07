<?php
/**
 * Print Weekly Class Timetable — logo + official header, print-friendly.
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
$autoPrint = !isset($_GET['noprint']);

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

$logoUrl = APP_URL . '/assets/images/bhubaneswar_logo.png';
$backUrl = 'manage_class_timetable.php' . ($filterCentre > 0 ? ('?centre_id=' . $filterCentre) : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Class Timetable — Print</title>
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
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print { background: #0f172a; color: #fff; }
        .btn-back { background: #64748b; color: #fff; }
        .sheet {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            padding: 14px 16px 18px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.12);
        }
        .lh-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
        }
        .lh-header img {
            height: 44px;
            width: auto;
            display: block;
        }
        .lh-text { flex: 1; text-align: center; }
        .lh-text .hi {
            font-size: 13px;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }
        .lh-text .en {
            font-size: 12px;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }
        .lh-text .centre {
            font-size: 11px;
            font-weight: 600;
            margin: 0;
            line-height: 1.2;
        }
        .lh-text .tag {
            font-size: 9px;
            color: #334155;
            margin: 0;
            line-height: 1.2;
        }
        .lh-rule {
            border: 0;
            border-top: 1.5px solid #0f172a;
            margin: 4px 0 6px;
        }
        .doc-title {
            text-align: center;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            margin: 0 0 2px;
        }
        .doc-meta {
            text-align: center;
            font-size: 10px;
            color: #334155;
            margin: 0 0 6px;
        }
        .ct-sheet {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8.5px;
        }
        .ct-sheet th,
        .ct-sheet td {
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
            white-space: pre-line;
            line-height: 1.15;
            padding: 3px 2px;
        }
        .ct-day-col {
            width: 62px;
            background: #e2e8f0;
            font-weight: 700;
            text-align: left;
            padding-left: 4px !important;
            font-size: 9px;
        }
        .ct-filled { background: #fffbeb; font-weight: 600; }
        .ct-empty { color: #94a3b8; }
        .ct-entry { display: block; font-weight: 700; font-size: 8.5px; }
        .ct-meta { display: block; font-size: 7.5px; font-weight: 500; color: #475569; }
        .ct-course { display: none; } /* full name only in legend to save space */
        .legend {
            margin-top: 5px;
            font-size: 9px;
            line-height: 1.3;
            color: #334155;
        }
        .legend strong { color: #0f172a; }
        .footer-note {
            margin-top: 4px;
            font-size: 8px;
            color: #64748b;
            text-align: center;
        }
        @media print {
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                height: auto !important;
            }
            .toolbar { display: none !important; }
            .sheet {
                max-width: none !important;
                width: 100% !important;
                box-shadow: none !important;
                padding: 3mm 4mm !important;
                margin: 0 !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .lh-header { gap: 8px; margin-bottom: 2px; }
            .lh-header img { height: 34px; }
            .lh-text .hi { font-size: 11px; }
            .lh-text .en { font-size: 10px; }
            .lh-text .centre { font-size: 9px; }
            .lh-text .tag { font-size: 7.5px; }
            .lh-rule { margin: 2px 0 4px; }
            .doc-title { font-size: 11px; margin: 0 0 1px; }
            .doc-meta { font-size: 8px; margin: 0 0 4px; }
            .ct-sheet { font-size: 7.5px; }
            .ct-sheet th, .ct-sheet td { padding: 1px 1px; }
            .ct-sheet thead th { font-size: 7px; padding: 2px 1px; }
            .ct-day-col { width: 52px; font-size: 8px; }
            .ct-entry { font-size: 7.5px; }
            .ct-meta { font-size: 6.5px; }
            .legend { margin-top: 3px; font-size: 7.5px; line-height: 1.25; }
            .footer-note { margin-top: 2px; font-size: 7px; }
            @page {
                size: A4 landscape;
                margin: 5mm;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <a class="btn btn-back" href="<?php echo htmlspecialchars($backUrl); ?>">← Back</a>
        <button type="button" class="btn btn-print" id="btnPrintTimetable">🖨 Print Timetable</button>
    </div>

    <div class="sheet" id="print-sheet">
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

        <h1 class="doc-title">Weekly Class Timetable</h1>
        <p class="doc-meta">
            <strong>Centre:</strong> <?php echo htmlspecialchars($centreName); ?>
            &nbsp;|&nbsp;
            <strong>Printed:</strong> <?php echo date('d M Y, h:i A'); ?>
            &nbsp;|&nbsp;
            <strong>Slots:</strong> <?php echo count($slots); ?>
        </p>

        <table class="ct-sheet" aria-label="Weekly class timetable">
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
                            <?php
                            $cellSlots = $grid[$dayNum][$period['key']] ?? [];
                            $filled = !empty($cellSlots);
                            ?>
                            <td class="<?php echo $filled ? 'ct-filled' : 'ct-empty'; ?>">
                                <?php if ($filled): ?>
                                    <?php foreach ($cellSlots as $slot): ?>
                                        <?php
                                        $subject = trim((string) ($slot['subject'] ?? ''));
                                        $facCode = $facultyDisplayMap[trim((string) ($slot['faculty_name'] ?? ''))] ?? '';
                                        $label = $facCode !== '' ? ($subject . ' (' . $facCode . ')') : $subject;
                                        $subjKey = strtoupper($subject);
                                        $fullCourse = $courseNameMap[$subjKey] ?? '';
                                        ?>
                                        <span class="ct-entry"><?php echo htmlspecialchars($label); ?></span>
                                        <?php if ($fullCourse !== '' && strcasecmp($fullCourse, $subject) !== 0): ?>
                                            <span class="ct-course"><?php echo htmlspecialchars($fullCourse); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($slot['batch_name'])): ?>
                                            <span class="ct-meta"><?php echo htmlspecialchars($slot['batch_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($slot['room'])): ?>
                                            <span class="ct-meta"><?php echo htmlspecialchars($slot['room']); ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($facultyDisplayMap)): ?>
            <div class="legend">
                <strong>Faculty:</strong>
                <?php
                $parts = [];
                foreach ($facultyDisplayMap as $fullName => $code) {
                    $parts[] = htmlspecialchars($code) . ' — ' . htmlspecialchars($fullName);
                }
                echo implode(', ', $parts);
                ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($legends['subjects'])): ?>
            <div class="legend">
                <strong>Courses:</strong>
                <?php
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
                echo implode(', ', $courseParts);
                ?>
            </div>
        <?php endif; ?>

        <p class="footer-note">Monday–Friday schedule · Saturday &amp; Sunday are holidays</p>
    </div>

    <script>
        function fitSheetForPrint() {
            var sheet = document.getElementById('print-sheet');
            if (!sheet) return;
            sheet.style.transform = '';
            sheet.style.transformOrigin = 'top left';
            sheet.style.width = '100%';

            // A4 landscape printable area ~ 287mm × 200mm at ~96dpi ≈ 1084 × 756 px
            var maxW = 1080;
            var maxH = 720;
            var w = sheet.scrollWidth;
            var h = sheet.scrollHeight;
            var scale = Math.min(1, maxW / w, maxH / h);
            if (scale < 0.99) {
                sheet.style.transform = 'scale(' + scale.toFixed(3) + ')';
                sheet.style.width = ((100 / scale).toFixed(2)) + '%';
            }
        }

        function printTimetable() {
            fitSheetForPrint();
            setTimeout(function () { window.print(); }, 100);
        }

        window.addEventListener('beforeprint', fitSheetForPrint);
        window.addEventListener('afterprint', function () {
            var sheet = document.getElementById('print-sheet');
            if (!sheet) return;
            sheet.style.transform = '';
            sheet.style.width = '100%';
        });

        document.getElementById('btnPrintTimetable') && document.getElementById('btnPrintTimetable').addEventListener('click', function (e) {
            e.preventDefault();
            printTimetable();
        });

        <?php if ($autoPrint): ?>
        window.addEventListener('load', function () {
            setTimeout(printTimetable, 400);
        });
        <?php endif; ?>
    </script>
</body>
</html>
