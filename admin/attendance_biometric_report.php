<?php
/**
 * Monthly fingerprint attendance record (IN/OUT grid) with device ID.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/institute_branding.php';
require_once __DIR__ . '/../includes/biometric_attendance_helper.php';

date_default_timezone_set('Asia/Kolkata');
if (isset($conn) && $conn instanceof mysqli) {
    @$conn->query("SET time_zone = '+05:30'");
}

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$year = (int) ($_GET['year'] ?? $istNow->format('Y'));
$month = (int) ($_GET['month'] ?? $istNow->format('n'));
if ($year < 2020 || $year > 2100) {
    $year = (int) $istNow->format('Y');
}
if ($month < 1 || $month > 12) {
    $month = (int) $istNow->format('n');
}
$courseId = (int) ($_GET['course_id'] ?? 0);
$centreId = (int) ($_GET['centre_id'] ?? 0);
$batchId = (int) ($_GET['batch_id'] ?? 0);

ensureBiometricAttendanceTables($conn);
ensureAttendanceInOutTables($conn);
$centres = attendanceListCentres($conn);
$centreLabel = attendanceCentreName($conn, $centreId);
$courses = attendanceListCoursesForCentre($conn, $centreId);
$batches = attendanceListBatchesForCourse($conn, $courseId, $centreId);
$batchLabel = attendanceBatchName($conn, $batchId);
$report = getFingerprintMonthlyRecord($conn, $year, $month, $courseId, $centreId, $batchId);
$daysInMonth = (int) $report['days'];
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];
$monthLabel = $monthNames[$month] . ' ' . $year;
$courseLabel = 'All courses';
foreach ($courses as $course) {
    if ((int) ($course['id'] ?? 0) === $courseId) {
        $courseLabel = attendanceFormatCourseLabel($course);
        break;
    }
}
$printedAt = $istNow->format('d/m/Y g:i:s A') . ' IST';
$logoUrl = app_url('assets/images/bhubaneswar_logo.png');
$colspan = 6 + $daysInMonth;
$colspanPrint = 3 + $daysInMonth;
$colspanExcel = 6 + ($daysInMonth * 2);

$dayInOutTimes = static function (array $times): array {
    $pairs = $times['pairs'] ?? [];
    if ($pairs === []) {
        $in = trim((string) ($times['in'] ?? ''));
        $out = trim((string) ($times['out'] ?? ''));
        if ($in === '' && $out === '') {
            return ['in' => [], 'out' => []];
        }
        $pairs = [['in' => $in, 'out' => $out]];
    }
    $ins = [];
    $outs = [];
    foreach ($pairs as $pair) {
        $in = trim((string) ($pair['in'] ?? ''));
        $out = trim((string) ($pair['out'] ?? ''));
        if ($in !== '') {
            $ins[] = $in;
        }
        if ($out !== '') {
            $outs[] = $out;
        }
    }
    return ['in' => $ins, 'out' => $outs];
};

$formatTimeList = static function (array $times, bool $html): string {
    if ($times === []) {
        return '';
    }
    if ($html) {
        return implode('<br>', array_map('htmlspecialchars', $times));
    }
    return implode("\n", $times);
};

$compactClock = static function (string $time): string {
    $time = trim($time);
    if ($time === '') {
        return '';
    }
    if (preg_match('/\s*am$/i', $time)) {
        return trim((string) preg_replace('/\s*am$/i', '', $time)) . 'a';
    }
    if (preg_match('/\s*pm$/i', $time)) {
        return trim((string) preg_replace('/\s*pm$/i', '', $time)) . 'p';
    }
    return $time;
};

$formatTimeListCompact = static function (array $times) use ($compactClock): string {
    $out = [];
    foreach ($times as $time) {
        $short = $compactClock((string) $time);
        if ($short !== '') {
            $out[] = htmlspecialchars($short);
        }
    }
    return implode('<br>', $out);
};

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = 'Attendance_Record_' . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
    echo '<table border="1">';
    echo '<tr><td colspan="' . $colspanExcel . '" style="font-size:18px;font-weight:bold;text-align:center;">Attendance Record</td></tr>';
    echo '<tr><td colspan="' . $colspanExcel . '">Create Time: ' . htmlspecialchars((new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('d/m/Y g:i:s A')) . ' IST</td></tr>';
    echo '<tr><td colspan="' . $colspanExcel . '">Mode Date: ' . htmlspecialchars($report['start']) . ' to ' . htmlspecialchars($report['end']) . '</td></tr>';
    echo '<tr><td colspan="' . $colspanExcel . '">Centre: ' . htmlspecialchars($centreLabel) . '</td></tr>';
    echo '<tr><td colspan="' . $colspanExcel . '">Batch: ' . htmlspecialchars($batchLabel) . '</td></tr>';
    echo '<tr><td colspan="' . $colspanExcel . '">NIELIT Bhubaneswar — Fingerprint attendance</td></tr>';
    echo '<tr></tr>';
    echo '<tr style="background:#93c5fd;font-weight:bold;text-align:center;">';
    echo '<td rowspan="2">Centre</td><td rowspan="2">Batch</td><td rowspan="2">Student ID</td><td rowspan="2">Name</td><td rowspan="2">Course / session</td><td rowspan="2">Device ID</td>';
    for ($d = 1; $d <= $daysInMonth; $d++) {
        echo '<td colspan="2">' . $d . '</td>';
    }
    echo '</tr><tr style="background:#93c5fd;font-weight:bold;text-align:center;">';
    for ($d = 1; $d <= $daysInMonth; $d++) {
        echo '<td>IN</td><td>OUT</td>';
    }
    echo '</tr>';
    if ($report['rows'] === []) {
        echo '<tr><td colspan="' . $colspanExcel . '" style="text-align:center;">No fingerprint attendance in this month.</td></tr>';
    } else {
        foreach ($report['rows'] as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars((string) ($row['centre'] ?? '—')) . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['batch'] ?? '—')) . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['student_id']) . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['name']) . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['department']) . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['device_id']) . '</td>';
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $io = $dayInOutTimes($row['days'][$d] ?? []);
                echo '<td style="text-align:center;white-space:pre-wrap;">' . $formatTimeList($io['in'], true) . '</td>';
                echo '<td style="text-align:center;white-space:pre-wrap;">' . $formatTimeList($io['out'], true) . '</td>';
            }
            echo '</tr>';
        }
    }
    echo '</table></body></html>';
    exit;
}

$active_theme = loadActiveTheme($conn);
$qs = http_build_query([
    'year' => $year,
    'month' => $month,
    'course_id' => $courseId > 0 ? $courseId : '',
    'centre_id' => $centreId > 0 ? $centreId : '',
    'batch_id' => $batchId > 0 ? $batchId : '',
    'export' => 'excel',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fingerprint Report - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .att-meta { font-size: 0.9rem; color: #334155; }
        .att-wrap { overflow-x: auto; background: #fff; }
        .att-matrix { border-collapse: collapse; width: max-content; min-width: 100%; font-size: 12px; }
        .att-matrix th, .att-matrix td { border: 1px solid #94a3b8; padding: 4px 6px; vertical-align: middle; }
        .att-matrix thead th { background: #93c5fd; color: #0f172a; text-align: center; font-weight: 700; white-space: nowrap; }
        .att-matrix tbody tr:nth-child(even) { background: #f1f5f9; }
        .att-matrix .col-centre { min-width: 140px; }
        .att-matrix .col-name { min-width: 160px; }
        .att-matrix .col-dept { min-width: 180px; }
        .att-matrix .col-dev { min-width: 120px; }
        .att-matrix .col-stack {
            min-width: 58px;
            text-align: center;
            font-size: 10px;
            line-height: 1.2;
            padding: 3px 4px;
        }
        .io-in, .io-out { display: block; }
        .io-in { color: #0f766e; font-weight: 700; }
        .io-out { color: #b45309; font-weight: 700; border-top: 1px solid #cbd5e1; margin-top: 2px; padding-top: 2px; }
        .t-short { display: none; }
        .att-title { text-align: center; font-size: 1.75rem; font-weight: 700; margin: 0 0 0.5rem; }
        .print-only { display: none; }
        .print-signs { display: none; }
        .print-hint { font-size: 0.82rem; color: #64748b; }
        @page {
            size: A4 landscape;
            margin: 8mm 6mm 10mm 6mm;
        }
        @media print {
            html, body.admin-body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 297mm !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print,
            .admin-sidebar,
            .sidebar-toggle-btn,
            .sidebar-overlay,
            .toast,
            .navbar { display: none !important; }
            .print-hide { display: none !important; }
            .admin-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: none !important;
            }
            .container-fluid { padding: 0 !important; }
            .card, .card-body { border: 0 !important; box-shadow: none !important; padding: 0 !important; }
            .att-wrap { overflow: visible !important; }
            .att-title, .att-meta { display: none !important; }
            .print-only { display: table-row !important; }
            .print-signs { display: flex !important; }
            .t-full { display: none !important; }
            .t-short { display: inline !important; }
            .att-matrix {
                table-layout: fixed !important;
                width: 277mm !important;
                min-width: 0 !important;
                max-width: 277mm !important;
                font-size: 6px;
            }
            .att-matrix th, .att-matrix td {
                padding: 1px 1px;
                border-color: #0f172a;
                word-break: break-word;
                overflow: hidden;
            }
            .att-matrix thead th {
                background: #93c5fd !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                white-space: normal;
            }
            .att-matrix thead { display: table-header-group; }
            .att-matrix tfoot { display: table-footer-group; }
            .att-matrix tbody tr { page-break-inside: avoid; }
            .att-matrix .col-id { width: 22mm; font-size: 6.5px; }
            .att-matrix .col-name { width: 28mm; min-width: 0; font-size: 6.5px; }
            .att-matrix .col-dept { width: 26mm; min-width: 0; font-size: 6px; }
            .att-matrix .col-stack {
                min-width: 0 !important;
                width: auto;
                font-size: 5.5px;
                padding: 1px 0;
                line-height: 1.15;
            }
            .io-in, .io-out { border: 0; margin: 0; padding: 0; }
            .io-out { border-top: 0.4pt solid #94a3b8; margin-top: 1px; padding-top: 1px; }
            .print-banner th {
                background: #fff !important;
                border: 0 !important;
                padding: 0 0 4px !important;
                text-align: left;
            }
            .lh-header { display: flex; align-items: center; gap: 8px; }
            .lh-header img { height: 28px; width: auto; }
            .lh-text { flex: 1; text-align: center; }
            .lh-text p { margin: 0; line-height: 1.2; }
            .lh-text .hi { font-size: 10px; font-weight: 700; }
            .lh-text .en { font-size: 9px; font-weight: 700; }
            .lh-text .tag { font-size: 7px; color: #334155; }
            .lh-rule { border: 0; border-top: 1.5px solid #0f172a; margin: 3px 0; }
            .doc-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; margin: 0; text-align: center; }
            .doc-meta { font-size: 7.5px; color: #334155; margin: 2px 0 0; text-align: center; }
            .print-foot td {
                border: 0 !important;
                padding: 4px 2px 0 !important;
                font-size: 7px;
                color: #334155;
                text-align: center;
            }
            .print-signs {
                display: flex !important;
                margin-top: 10px;
                justify-content: space-between;
                gap: 12px;
                font-size: 8px;
                text-align: center;
                page-break-inside: avoid;
            }
            .print-signs > div { flex: 1; }
            .print-signs .line {
                border-bottom: 1px solid #0f172a;
                height: 20px;
                margin: 0 auto 4px;
                width: 80%;
            }
        }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 no-print">
            <div>
                <h2 class="mb-0"><i class="fas fa-th"></i> Fingerprint Report</h2>
                <p class="text-muted mb-0">Monthly fingerprint attendance. Each day shows IN on top and OUT below so all days fit on A4 landscape.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print A4 landscape
                </button>
                <a class="btn btn-success" href="attendance_biometric_report.php?<?php echo htmlspecialchars($qs); ?>">
                    <i class="fas fa-file-excel"></i> Download Excel
                </a>
            </div>
        </div>
        <p class="print-hint no-print mb-2">Print uses A4 landscape so all <?php echo (int) $daysInMonth; ?> days fit. In the printer dialog choose <strong>A4</strong> and <strong>Landscape</strong> (not Portrait).</p>

        <div class="card mb-3 no-print">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Centre</label>
                        <select class="form-select" name="centre_id" onchange="this.form.submit()">
                            <option value="0">All centres</option>
                            <?php foreach ($centres as $centre): ?>
                                <option value="<?php echo (int) $centre['id']; ?>" <?php echo (int) $centre['id'] === $centreId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $centre['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Section</label>
                        <select class="form-select" name="batch_id">
                            <option value="0">All sections</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo (int) $batch['id']; ?>" <?php echo (int) $batch['id'] === $batchId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(attendanceFormatBatchLabel($batch)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <select class="form-select" name="month">
                            <?php foreach ($monthNames as $num => $name): ?>
                                <option value="<?php echo (int) $num; ?>" <?php echo $num === $month ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Year</label>
                        <select class="form-select" name="year">
                            <?php for ($y = (int) date('Y'); $y >= 2024; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id">
                            <option value="0">All courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo (int) $course['id']; ?>" <?php echo (int) $course['id'] === $courseId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(attendanceFormatCourseLabel($course)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit">Show</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="att-meta mb-2">
                    Create Time: <?php echo htmlspecialchars((new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('d/m/Y g:i:s A')); ?> IST<br>
                    Centre: <?php echo htmlspecialchars($centreLabel); ?><br>
                    Batch: <?php echo htmlspecialchars($batchLabel); ?><br>
                    Mode Date: <?php echo htmlspecialchars($report['start']); ?> to <?php echo htmlspecialchars($report['end']); ?>
                </div>
                <h3 class="att-title">Attendance Record</h3>
                <div class="att-wrap">
                    <table class="att-matrix">
                        <thead>
                            <tr class="print-only print-banner">
                                <th colspan="<?php echo (int) $colspanPrint; ?>">
                                    <div class="lh-header">
                                        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="NIELIT">
                                        <div class="lh-text">
                                            <p class="hi"><?php echo htmlspecialchars(INSTITUTE_NAME_HI_FORMAL, ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="en">National Institute of Electronics and Information Technology (NIELIT)</p>
                                            <p class="tag">(An Autonomous Scientific Society of <?php echo htmlspecialchars(MINISTRY_NAME_EN, ENT_QUOTES, 'UTF-8'); ?>, Govt. of India)</p>
                                        </div>
                                    </div>
                                    <hr class="lh-rule">
                                    <p class="doc-title">Fingerprint Attendance Record</p>
                                    <p class="doc-meta">
                                        <strong>Month:</strong> <?php echo htmlspecialchars($monthLabel); ?>
                                        &nbsp;|&nbsp; <strong>Centre:</strong> <?php echo htmlspecialchars($centreLabel); ?>
                                        &nbsp;|&nbsp; <strong>Section:</strong> <?php echo htmlspecialchars($batchLabel); ?>
                                        &nbsp;|&nbsp; <strong>Course:</strong> <?php echo htmlspecialchars($courseLabel); ?>
                                        &nbsp;|&nbsp; <strong>Period:</strong> <?php echo htmlspecialchars($report['start'] . ' to ' . $report['end']); ?>
                                    </p>
                                </th>
                            </tr>
                            <tr>
                                <th class="col-centre print-hide" rowspan="1">Centre</th>
                                <th class="col-centre print-hide" rowspan="1">Batch</th>
                                <th class="col-id" rowspan="1">Student ID</th>
                                <th class="col-name" rowspan="1">Name</th>
                                <th class="col-dept" rowspan="1">Course / session</th>
                                <th class="col-dev print-hide" rowspan="1">Device ID</th>
                                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                    <th class="col-stack"><?php echo $d; ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($report['rows'] === []): ?>
                            <tr>
                                <td colspan="<?php echo (int) $colspan; ?>" class="text-center text-muted py-4">
                                    No fingerprint attendance in <?php echo htmlspecialchars($monthLabel); ?>.
                                    Mark students on Fingerprint Attendance first.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($report['rows'] as $row): ?>
                                <tr>
                                    <td class="print-hide"><?php echo htmlspecialchars((string) ($row['centre'] ?? '—')); ?></td>
                                    <td class="print-hide"><?php echo htmlspecialchars((string) ($row['batch'] ?? '—')); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['name']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['department']); ?></td>
                                    <td class="print-hide"><?php echo htmlspecialchars((string) $row['device_id']); ?></td>
                                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                        <?php $io = $dayInOutTimes($row['days'][$d] ?? []); ?>
                                        <td class="col-stack">
                                            <span class="io-in">
                                                <span class="t-full"><?php echo $formatTimeList($io['in'], true) !== '' ? $formatTimeList($io['in'], true) : '&nbsp;'; ?></span>
                                                <span class="t-short"><?php echo $formatTimeListCompact($io['in']) !== '' ? $formatTimeListCompact($io['in']) : '&nbsp;'; ?></span>
                                            </span>
                                            <span class="io-out">
                                                <span class="t-full"><?php echo $formatTimeList($io['out'], true) !== '' ? $formatTimeList($io['out'], true) : '&nbsp;'; ?></span>
                                                <span class="t-short"><?php echo $formatTimeListCompact($io['out']) !== '' ? $formatTimeListCompact($io['out']) : '&nbsp;'; ?></span>
                                            </span>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="print-only print-foot">
                                <td colspan="<?php echo (int) $colspanPrint; ?>">
                                    NIELIT Bhubaneswar | Fingerprint Attendance Record | Printed: <?php echo htmlspecialchars($printedAt); ?>
                                    | Page generated from the student portal
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="print-signs">
                    <div>
                        <div class="line"></div>
                        <strong>Faculty / Coordinator</strong>
                    </div>
                    <div>
                        <div class="line"></div>
                        <strong>Centre In-charge</strong>
                    </div>
                    <div>
                        <div class="line"></div>
                        <strong>Director / Authorised Signatory</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
