<?php
/**
 * Monthly fingerprint attendance record (IN/OUT grid) with Mantra device ID.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/biometric_attendance_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$year = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? date('n'));
if ($year < 2020 || $year > 2100) {
    $year = (int) date('Y');
}
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
$courseId = (int) ($_GET['course_id'] ?? 0);

ensureBiometricAttendanceTables($conn);
ensureAttendanceInOutTables($conn);
$report = getFingerprintMonthlyRecord($conn, $year, $month, $courseId);
$daysInMonth = (int) $report['days'];
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];
$monthLabel = $monthNames[$month] . ' ' . $year;
$colspan = 4 + $daysInMonth;

$courses = [];
$coursesResult = $conn->query('SELECT id, course_name FROM courses ORDER BY course_name');
if ($coursesResult) {
    $courses = $coursesResult->fetch_all(MYSQLI_ASSOC);
}

$formatDayCell = static function (array $times, bool $html): string {
    $in = trim((string) ($times['in'] ?? ''));
    $out = trim((string) ($times['out'] ?? ''));
    if ($in === '' && $out === '') {
        return '';
    }
    if ($in !== '' && $out !== '') {
        return $html ? (htmlspecialchars($in) . '<br>' . htmlspecialchars($out)) : ($in . "\n" . $out);
    }
    return $html ? htmlspecialchars($in !== '' ? $in : $out) : ($in !== '' ? $in : $out);
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
    echo '<tr><td colspan="' . $colspan . '" style="font-size:18px;font-weight:bold;text-align:center;">Attendance Record</td></tr>';
    echo '<tr><td colspan="' . $colspan . '">Create Time: ' . date('Y/m/d H:i:s') . '</td></tr>';
    echo '<tr><td colspan="' . $colspan . '">Mode Date: ' . htmlspecialchars($report['start']) . ' to ' . htmlspecialchars($report['end']) . '</td></tr>';
    echo '<tr><td colspan="' . $colspan . '">NIELIT Bhubaneswar — Fingerprint (Mantra)</td></tr>';
    echo '<tr></tr>';
    echo '<tr style="background:#93c5fd;font-weight:bold;text-align:center;">';
    echo '<td>Student ID</td><td>Name</td><td>Course / session</td><td>Mantra device ID</td>';
    for ($d = 1; $d <= $daysInMonth; $d++) {
        echo '<td>' . $d . '</td>';
    }
    echo '</tr>';
    if ($report['rows'] === []) {
        echo '<tr><td colspan="' . $colspan . '" style="text-align:center;">No fingerprint attendance in this month.</td></tr>';
    } else {
        foreach ($report['rows'] as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars((string) $row['student_id']) . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['name']) . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['department']) . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['device_id']) . '</td>';
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $cell = $formatDayCell($row['days'][$d] ?? [], true);
                echo '<td style="text-align:center;white-space:pre-wrap;">' . $cell . '</td>';
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
        .att-matrix .col-id { min-width: 130px; }
        .att-matrix .col-name { min-width: 160px; }
        .att-matrix .col-dept { min-width: 180px; }
        .att-matrix .col-dev { min-width: 140px; }
        .att-matrix .col-day { min-width: 44px; text-align: center; line-height: 1.25; }
        .att-title { text-align: center; font-size: 1.75rem; font-weight: 700; margin: 0 0 0.5rem; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="mb-0"><i class="fas fa-th"></i> Fingerprint Report</h2>
                <p class="text-muted mb-0">Monthly IN/OUT record from Mantra fingerprint attendance, with device ID.</p>
            </div>
            <a class="btn btn-success" href="attendance_biometric_report.php?<?php echo htmlspecialchars($qs); ?>">
                <i class="fas fa-file-excel"></i> Download Excel
            </a>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
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
                    <div class="col-md-5">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id">
                            <option value="0">All courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo (int) $course['id']; ?>" <?php echo (int) $course['id'] === $courseId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $course['course_name']); ?>
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
                    Create Time: <?php echo date('Y/m/d H:i:s'); ?><br>
                    Mode Date: <?php echo htmlspecialchars($report['start']); ?> to <?php echo htmlspecialchars($report['end']); ?>
                </div>
                <h3 class="att-title">Attendance Record</h3>
                <div class="att-wrap">
                    <table class="att-matrix">
                        <thead>
                            <tr>
                                <th class="col-id">Student ID</th>
                                <th class="col-name">Name</th>
                                <th class="col-dept">Course / session</th>
                                <th class="col-dev">Mantra device ID</th>
                                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                    <th class="col-day"><?php echo $d; ?></th>
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
                                    <td><?php echo htmlspecialchars((string) $row['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['name']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['department']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['device_id']); ?></td>
                                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                        <td class="col-day"><?php echo $formatDayCell($row['days'][$d] ?? [], true); ?></td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
