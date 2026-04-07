<?php
/**
 * Monthly Attendance Reports
 * NIELIT Bhubaneswar - Advanced Attendance Analytics
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/attendance_in_out_helper.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin'];
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';

// Get filter parameters
$selected_year = $_GET['year'] ?? date('Y');
$selected_month = $_GET['month'] ?? date('n');
$selected_student = $_GET['student_id'] ?? '';

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Get monthly report data for export
    $monthly_report = getMonthlyAttendanceReport($selected_student, $selected_year, $selected_month, $conn);
    
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
    // Create Excel filename
    $filename = "monthly_attendance_report_" . $months[$selected_month] . "_" . $selected_year . ".xls";
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Start Excel HTML content
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Report header
    echo '<tr><td colspan="9" style="font-weight:bold; font-size:16px; text-align:center;">NIELIT Bhubaneswar - Monthly Attendance Report</td></tr>';
    echo '<tr><td colspan="9"></td></tr>'; // Empty row
    echo '<tr><td style="font-weight:bold;">Month:</td><td colspan="8">' . $months[$selected_month] . ' ' . $selected_year . '</td></tr>';
    if ($selected_student) {
        echo '<tr><td style="font-weight:bold;">Student Filter:</td><td colspan="8">' . htmlspecialchars($selected_student) . '</td></tr>';
    }
    echo '<tr><td style="font-weight:bold;">Generated:</td><td colspan="8">' . date('d M Y h:i A') . '</td></tr>';
    echo '<tr><td colspan="9"></td></tr>'; // Empty row

    // Column headers
    echo '<tr style="background-color:#f0f0f0; font-weight:bold;">';
    echo '<td>Student Name</td>';
    echo '<td>Student ID</td>';
    echo '<td>Total Days</td>';
    echo '<td>Present</td>';
    echo '<td>Partial</td>';
    echo '<td>Absent</td>';
    echo '<td>Total Hours</td>';
    echo '<td>Attendance %</td>';
    echo '<td>Grade</td>';
    echo '</tr>';

    // Report data
    if (!empty($monthly_report)) {
        foreach ($monthly_report as $record) {
            $percentage = $record['attendance_percentage'];
            $grade = 'F';
            
            if ($percentage >= 90) {
                $grade = 'A+';
            } elseif ($percentage >= 80) {
                $grade = 'A';
            } elseif ($percentage >= 70) {
                $grade = 'B';
            } elseif ($percentage >= 60) {
                $grade = 'C';
            } elseif ($percentage >= 50) {
                $grade = 'D';
            }

            echo '<tr>';
            echo '<td>' . htmlspecialchars($record['student_name']) . '</td>';
            echo '<td>' . htmlspecialchars($record['student_id']) . '</td>';
            echo '<td>' . $record['total_days'] . '</td>';
            echo '<td>' . $record['present_days'] . '</td>';
            echo '<td>' . $record['partial_days'] . '</td>';
            echo '<td>' . $record['absent_days'] . '</td>';
            echo '<td>' . round($record['total_hours'], 2) . '</td>';
            echo '<td>' . round($percentage, 1) . '%</td>';
            echo '<td>' . $grade . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="9" style="text-align:center;">No attendance records found</td></tr>';
    }

    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

// Get monthly report data
$monthly_report = getMonthlyAttendanceReport($selected_student, $selected_year, $selected_month, $conn);

// Get available years and months
$years_query = "SELECT DISTINCT YEAR(date) as year FROM attendance_summary ORDER BY year DESC";
$years_result = $conn->query($years_query);
$available_years = $years_result ? $years_result->fetch_all(MYSQLI_ASSOC) : [];

// Get students for filter
$students_query = "SELECT DISTINCT student_id, student_name FROM attendance_summary ORDER BY student_name";
$students_result = $conn->query($students_query);
$students = $students_result ? $students_result->fetch_all(MYSQLI_ASSOC) : [];

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - NIELIT Bhubaneswar</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Admin Theme CSS -->
    <link href="../assets/css/admin-theme.css" rel="stylesheet">
    
    <style>
        .report-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .attendance-percentage {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .percentage-excellent { color: #28a745; }
        .percentage-good { color: #17a2b8; }
        .percentage-average { color: #ffc107; }
        .percentage-poor { color: #dc3545; }
        
        .filter-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .summary-stats {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap"></i> NIELIT Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="attendance_scanner.php">
                    <i class="fas fa-qrcode"></i> QR Scanner
                </a>
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-chart-bar"></i> Monthly Attendance Reports</h2>
                <p class="text-muted">Comprehensive attendance analytics and insights</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card filter-card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Year</label>
                                <select name="year" class="form-select">
                                    <?php foreach ($available_years as $year): ?>
                                        <option value="<?php echo $year['year']; ?>" 
                                                <?php echo $year['year'] == $selected_year ? 'selected' : ''; ?>>
                                            <?php echo $year['year']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Month</label>
                                <select name="month" class="form-select">
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" 
                                                <?php echo $num == $selected_month ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Student (Optional)</label>
                                <select name="student_id" class="form-select">
                                    <option value="">All Students</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['student_id']; ?>"
                                                <?php echo $student['student_id'] == $selected_student ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['student_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-light w-100">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Statistics -->
        <?php if (!empty($monthly_report)): ?>
            <?php
            $total_students = count($monthly_report);
            $avg_attendance = array_sum(array_column($monthly_report, 'attendance_percentage')) / $total_students;
            $total_hours = array_sum(array_column($monthly_report, 'total_hours'));
            $excellent_count = count(array_filter($monthly_report, function($r) { return $r['attendance_percentage'] >= 90; }));
            ?>
            <div class="summary-stats">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h3><?php echo $total_students; ?></h3>
                        <p class="mb-0">Total Students</p>
                    </div>
                    <div class="col-md-3">
                        <h3><?php echo round($avg_attendance, 1); ?>%</h3>
                        <p class="mb-0">Average Attendance</p>
                    </div>
                    <div class="col-md-3">
                        <h3><?php echo round($total_hours, 1); ?>h</h3>
                        <p class="mb-0">Total Hours</p>
                    </div>
                    <div class="col-md-3">
                        <h3><?php echo $excellent_count; ?></h3>
                        <p class="mb-0">Excellent (90%+)</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Attendance Report Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-table"></i> 
                            <?php echo $months[$selected_month] . ' ' . $selected_year; ?> Attendance Report
                        </h5>
                        <div>
                            <button class="btn btn-success btn-sm" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="printReport()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($monthly_report)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="reportTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Student ID</th>
                                            <th>Total Days</th>
                                            <th>Present</th>
                                            <th>Partial</th>
                                            <th>Absent</th>
                                            <th>Total Hours</th>
                                            <th>Attendance %</th>
                                            <th>Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monthly_report as $record): ?>
                                            <?php
                                            $percentage = $record['attendance_percentage'];
                                            $grade = 'F';
                                            $grade_class = 'percentage-poor';
                                            
                                            if ($percentage >= 90) {
                                                $grade = 'A+';
                                                $grade_class = 'percentage-excellent';
                                            } elseif ($percentage >= 80) {
                                                $grade = 'A';
                                                $grade_class = 'percentage-good';
                                            } elseif ($percentage >= 70) {
                                                $grade = 'B';
                                                $grade_class = 'percentage-average';
                                            } elseif ($percentage >= 60) {
                                                $grade = 'C';
                                                $grade_class = 'percentage-average';
                                            }
                                            ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($record['student_name']); ?></strong></td>
                                                <td><code><?php echo htmlspecialchars($record['student_id']); ?></code></td>
                                                <td><?php echo $record['total_days']; ?></td>
                                                <td><span class="badge bg-success"><?php echo $record['present_days']; ?></span></td>
                                                <td><span class="badge bg-warning"><?php echo $record['partial_days']; ?></span></td>
                                                <td><span class="badge bg-danger"><?php echo $record['absent_days']; ?></span></td>
                                                <td><?php echo $record['total_hours']; ?>h</td>
                                                <td>
                                                    <span class="attendance-percentage <?php echo $grade_class; ?>">
                                                        <?php echo $percentage; ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $grade; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No attendance data found</h5>
                                <p class="text-muted">
                                    No attendance records found for 
                                    <?php echo $months[$selected_month] . ' ' . $selected_year; ?>
                                    <?php if ($selected_student): ?>
                                        for the selected student
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function exportToExcel() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.open('?' + params.toString(), '_blank');
        }
        
        function printReport() {
            window.print();
        }
        
        // Print styles
        const printStyles = `
            @media print {
                .navbar, .btn, .filter-card { display: none !important; }
                .card { border: none !important; box-shadow: none !important; }
                .table { font-size: 12px; }
            }
        `;
        
        const styleSheet = document.createElement("style");
        styleSheet.innerText = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>