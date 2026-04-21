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
$report_type = $_GET['report_type'] ?? 'monthly'; // monthly, weekly, quarterly, yearly, custom
$selected_year = $_GET['year'] ?? date('Y');
$selected_month = $_GET['month'] ?? date('n');
$selected_week = $_GET['week'] ?? date('W');
$selected_quarter = $_GET['quarter'] ?? ceil(date('n') / 3);
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$selected_student = $_GET['student_id'] ?? '';
$selected_course = $_GET['course_id'] ?? '';

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Get report data based on type
    $report_data = [];
    $report_title = '';
    
    switch ($report_type) {
        case 'weekly':
            $report_data = getWeeklyAttendanceReport($selected_student, $selected_year, $selected_week, $selected_course, $conn);
            $report_title = "Weekly Attendance Report - Week {$selected_week}, {$selected_year}";
            break;
        case 'quarterly':
            $report_data = getQuarterlyAttendanceReport($selected_student, $selected_year, $selected_quarter, $selected_course, $conn);
            $quarters = ['Q1 (Jan-Mar)', 'Q2 (Apr-Jun)', 'Q3 (Jul-Sep)', 'Q4 (Oct-Dec)'];
            $report_title = "Quarterly Attendance Report - {$quarters[$selected_quarter-1]}, {$selected_year}";
            break;
        case 'yearly':
            $report_data = getYearlyAttendanceReport($selected_student, $selected_year, $selected_course, $conn);
            $report_title = "Yearly Attendance Report - {$selected_year}";
            break;
        case 'custom':
            if ($start_date && $end_date) {
                $report_data = getCustomRangeAttendanceReport($selected_student, $start_date, $end_date, $selected_course, $conn);
                $report_title = "Custom Attendance Report - " . date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
            }
            break;
        default: // monthly
            $report_data = getMonthlyAttendanceReport($selected_student, $selected_year, $selected_month, $selected_course, $conn);
            $months = [
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
            ];
            $report_title = "Monthly Attendance Report - {$months[$selected_month]} {$selected_year}";
    }
    
    // Create Excel filename
    $filename = strtolower(str_replace([' ', '-', '(', ')'], '_', $report_title)) . ".xls";
    
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
    echo '<tr><td colspan="10" style="font-weight:bold; font-size:16px; text-align:center;">NIELIT Bhubaneswar - ' . $report_title . '</td></tr>';
    echo '<tr><td colspan="10"></td></tr>'; // Empty row
    if ($selected_student) {
        echo '<tr><td style="font-weight:bold;">Student Filter:</td><td colspan="9">' . htmlspecialchars($selected_student) . '</td></tr>';
    }
    if ($selected_course) {
        echo '<tr><td style="font-weight:bold;">Course Filter:</td><td colspan="9">' . htmlspecialchars($selected_course) . '</td></tr>';
    }
    echo '<tr><td style="font-weight:bold;">Generated:</td><td colspan="9">' . date('d M Y h:i A') . '</td></tr>';
    echo '<tr><td colspan="10"></td></tr>'; // Empty row

    // Column headers
    echo '<tr style="background-color:#f0f0f0; font-weight:bold;">';
    echo '<td>Student Name</td>';
    echo '<td>Student ID</td>';
    echo '<td>Course</td>';
    echo '<td>Total Days</td>';
    echo '<td>Present</td>';
    echo '<td>Partial</td>';
    echo '<td>Absent</td>';
    echo '<td>Total Hours</td>';
    echo '<td>Attendance %</td>';
    echo '<td>Grade</td>';
    echo '</tr>';

    // Report data
    if (!empty($report_data)) {
        foreach ($report_data as $record) {
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
            echo '<td>' . htmlspecialchars($record['course_name'] ?? 'N/A') . '</td>';
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
        echo '<tr><td colspan="10" style="text-align:center;">No attendance records found</td></tr>';
    }

    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

// Get report data based on selected type
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'weekly':
        $report_data = getWeeklyAttendanceReport($selected_student, $selected_year, $selected_week, $selected_course, $conn);
        $report_title = "Weekly Report - Week {$selected_week}, {$selected_year}";
        break;
    case 'quarterly':
        $report_data = getQuarterlyAttendanceReport($selected_student, $selected_year, $selected_quarter, $selected_course, $conn);
        $quarters = ['Q1 (Jan-Mar)', 'Q2 (Apr-Jun)', 'Q3 (Jul-Sep)', 'Q4 (Oct-Dec)'];
        $report_title = "Quarterly Report - {$quarters[$selected_quarter-1]}, {$selected_year}";
        break;
    case 'yearly':
        $report_data = getYearlyAttendanceReport($selected_student, $selected_year, $selected_course, $conn);
        $report_title = "Yearly Report - {$selected_year}";
        break;
    case 'custom':
        if ($start_date && $end_date) {
            $report_data = getCustomRangeAttendanceReport($selected_student, $start_date, $end_date, $selected_course, $conn);
            $report_title = "Custom Report - " . date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
        }
        break;
    default: // monthly
        $report_data = getMonthlyAttendanceReport($selected_student, $selected_year, $selected_month, $selected_course, $conn);
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        $report_title = "Monthly Report - {$months[$selected_month]} {$selected_year}";
}

// Get available years and months
$years_query = "SELECT DISTINCT YEAR(date) as year FROM attendance_summary ORDER BY year DESC";
$years_result = $conn->query($years_query);
$available_years = $years_result ? $years_result->fetch_all(MYSQLI_ASSOC) : [];

// Get students for filter
$students_query = "SELECT DISTINCT student_id, student_name FROM attendance_summary ORDER BY student_name";
$students_result = $conn->query($students_query);
$students = $students_result ? $students_result->fetch_all(MYSQLI_ASSOC) : [];

// Get courses for filter
$courses_query = "SELECT DISTINCT id, course_name FROM courses WHERE status = 'active' ORDER BY course_name";
$courses_result = $conn->query($courses_query);
$courses = $courses_result ? $courses_result->fetch_all(MYSQLI_ASSOC) : [];

// If no active courses, get all courses
if (empty($courses)) {
    $courses_query = "SELECT DISTINCT id, course_name FROM courses ORDER BY course_name";
    $courses_result = $conn->query($courses_query);
    $courses = $courses_result ? $courses_result->fetch_all(MYSQLI_ASSOC) : [];
}

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$quarters = [
    1 => 'Q1 (January - March)',
    2 => 'Q2 (April - June)', 
    3 => 'Q3 (July - September)',
    4 => 'Q4 (October - December)'
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
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .btn-check:checked + .btn-outline-light {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: white;
            color: white;
        }
        
        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.5);
            color: rgba(255, 255, 255, 0.8);
        }
        
        .btn-outline-light:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: white;
            color: white;
        }
        
        .summary-stats {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .report-type-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 123, 255, 0.1);
            color: #007bff;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-content">
            <div class="admin-main">

    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-chart-bar"></i> Enhanced Attendance Reports</h2>
                <p class="text-muted">Comprehensive attendance analytics with flexible reporting options</p>
            </div>
        </div>

        <!-- Report Type Selector -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card filter-card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label text-white"><strong>Report Type</strong></label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="report_type" id="monthly" value="monthly" 
                                           <?php echo $report_type === 'monthly' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-light" for="monthly">Monthly</label>

                                    <input type="radio" class="btn-check" name="report_type" id="weekly" value="weekly"
                                           <?php echo $report_type === 'weekly' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-light" for="weekly">Weekly</label>

                                    <input type="radio" class="btn-check" name="report_type" id="quarterly" value="quarterly"
                                           <?php echo $report_type === 'quarterly' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-light" for="quarterly">Quarterly</label>

                                    <input type="radio" class="btn-check" name="report_type" id="yearly" value="yearly"
                                           <?php echo $report_type === 'yearly' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-light" for="yearly">Yearly</label>

                                    <input type="radio" class="btn-check" name="report_type" id="custom" value="custom"
                                           <?php echo $report_type === 'custom' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-light" for="custom">Custom Range</label>
                                </div>
                            </div>
                        </div>

                        <form method="GET" class="row g-3" id="filterForm">
                            <input type="hidden" name="report_type" id="selected_report_type" value="<?php echo $report_type; ?>">
                            
                            <!-- Monthly Filters -->
                            <div class="filter-group col-12" id="monthly_filters" style="<?php echo $report_type !== 'monthly' ? 'display:none;' : ''; ?>">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label text-white">Year</label>
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
                                        <label class="form-label text-white">Month</label>
                                        <select name="month" class="form-select">
                                            <?php foreach ($months as $num => $name): ?>
                                                <option value="<?php echo $num; ?>" 
                                                        <?php echo $num == $selected_month ? 'selected' : ''; ?>>
                                                    <?php echo $name; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Weekly Filters -->
                            <div class="filter-group col-12" id="weekly_filters" style="<?php echo $report_type !== 'weekly' ? 'display:none;' : ''; ?>">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label text-white">Year</label>
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
                                        <label class="form-label text-white">Week Number</label>
                                        <select name="week" class="form-select">
                                            <?php for ($w = 1; $w <= 53; $w++): ?>
                                                <option value="<?php echo $w; ?>" 
                                                        <?php echo $w == $selected_week ? 'selected' : ''; ?>>
                                                    Week <?php echo $w; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Quarterly Filters -->
                            <div class="filter-group col-12" id="quarterly_filters" style="<?php echo $report_type !== 'quarterly' ? 'display:none;' : ''; ?>">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label text-white">Year</label>
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
                                        <label class="form-label text-white">Quarter</label>
                                        <select name="quarter" class="form-select">
                                            <?php foreach ($quarters as $num => $name): ?>
                                                <option value="<?php echo $num; ?>" 
                                                        <?php echo $num == $selected_quarter ? 'selected' : ''; ?>>
                                                    <?php echo $name; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Yearly Filters -->
                            <div class="filter-group col-12" id="yearly_filters" style="<?php echo $report_type !== 'yearly' ? 'display:none;' : ''; ?>">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label text-white">Year</label>
                                        <select name="year" class="form-select">
                                            <?php foreach ($available_years as $year): ?>
                                                <option value="<?php echo $year['year']; ?>" 
                                                        <?php echo $year['year'] == $selected_year ? 'selected' : ''; ?>>
                                                    <?php echo $year['year']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Custom Range Filters -->
                            <div class="filter-group col-12" id="custom_filters" style="<?php echo $report_type !== 'custom' ? 'display:none;' : ''; ?>">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label text-white">Start Date</label>
                                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white">End Date</label>
                                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Common Filters -->
                            <div class="col-12">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label text-white">Student (Optional)</label>
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
                                    
                                    <div class="col-md-3">
                                        <label class="form-label text-white">Course (Optional)</label>
                                        <select name="course_id" class="form-select">
                                            <option value="">All Courses</option>
                                            <?php foreach ($courses as $course): ?>
                                                <option value="<?php echo $course['id']; ?>"
                                                        <?php echo $course['id'] == $selected_course ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label text-white">&nbsp;</label>
                                        <button type="submit" class="btn btn-light w-100">
                                            <i class="fas fa-filter"></i> Generate Report
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Statistics -->
        <?php if (!empty($report_data)): ?>
            <?php
            $total_students = count($report_data);
            $avg_attendance = array_sum(array_column($report_data, 'attendance_percentage')) / $total_students;
            $total_hours = array_sum(array_column($report_data, 'total_hours'));
            $excellent_count = count(array_filter($report_data, function($r) { return $r['attendance_percentage'] >= 90; }));
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
                            <?php echo $report_title; ?>
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
                        <?php if (!empty($report_data)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="reportTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Student ID</th>
                                            <th>Course</th>
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
                                        <?php foreach ($report_data as $record): ?>
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
                                                <td><?php echo htmlspecialchars($record['course_name'] ?? 'N/A'); ?></td>
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
                                    No attendance records found for the selected criteria.
                                    <?php if ($report_type === 'custom' && (!$start_date || !$end_date)): ?>
                                        <br>Please select both start and end dates for custom range reports.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle report type switching
        document.querySelectorAll('input[name="report_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const reportType = this.value;
                document.getElementById('selected_report_type').value = reportType;
                
                // Hide all filter groups
                document.querySelectorAll('.filter-group').forEach(group => {
                    if (group.id && group.id.includes('_filters')) {
                        group.style.display = 'none';
                    }
                });
                
                // Show relevant filter group
                const filterGroup = document.getElementById(reportType + '_filters');
                if (filterGroup) {
                    filterGroup.style.display = 'block';
                }
            });
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentReportType = document.getElementById('selected_report_type').value;
            
            // Hide all filter groups first
            document.querySelectorAll('.filter-group').forEach(group => {
                if (group.id && group.id.includes('_filters')) {
                    group.style.display = 'none';
                }
            });
            
            // Show current filter group
            const currentFilterGroup = document.getElementById(currentReportType + '_filters');
            if (currentFilterGroup) {
                currentFilterGroup.style.display = 'block';
            }
        });
        
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