<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch student name and course
$sql = "SELECT name, course FROM students WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Calculate attendance statistics
$sql_stats = "SELECT 
    COUNT(*) as total_classes,
    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
    FROM attendance WHERE student_id = ?";
$stmt_stats = $conn->prepare($sql_stats);

$total_classes = 0;
$present_count = 0;
$absent_count = 0;
$late_count = 0;
$attendance_percentage = 0;

if ($stmt_stats) {
    $stmt_stats->bind_param("s", $student_id);
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
    if ($row_stats = $result_stats->fetch_assoc()) {
        $total_classes = $row_stats['total_classes'];
        $present_count = $row_stats['present_count'];
        $absent_count = $row_stats['absent_count'];
        $late_count = $row_stats['late_count'];
        if ($total_classes > 0) {
            $attendance_percentage = round(($present_count / $total_classes) * 100, 1);
        }
    }
}

// Fetch attendance records
$sql_attendance = "SELECT * FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 50";
$stmt_attendance = $conn->prepare($sql_attendance);
$attendance_records = [];
if ($stmt_attendance) {
    $stmt_attendance->bind_param("s", $student_id);
    $stmt_attendance->execute();
    $result_attendance = $stmt_attendance->get_result();
    while ($row = $result_attendance->fetch_assoc()) {
        $attendance_records[] = $row;
    }
}

$page_title = "Attendance";
include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-calendar-check"></i> Attendance Record</h2>
            <p class="text-muted">Track your class attendance and maintain good academic standing</p>
        </div>
    </div>

    <!-- Attendance Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-primary-gradient">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_classes; ?></h3>
                    <p>Total Classes</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stat-card bg-success-gradient">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $present_count; ?></h3>
                    <p>Present</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stat-card bg-danger" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $absent_count; ?></h3>
                    <p>Absent</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stat-card bg-warning-gradient">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $late_count; ?></h3>
                    <p>Late</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Percentage -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-4">
                    <h3 class="mb-3">Overall Attendance</h3>
                    <div class="attendance-circle mx-auto mb-3">
                        <svg width="200" height="200">
                            <circle cx="100" cy="100" r="80" fill="none" stroke="#e9ecef" stroke-width="20"/>
                            <circle cx="100" cy="100" r="80" fill="none" 
                                    stroke="<?php echo $attendance_percentage >= 75 ? '#28a745' : ($attendance_percentage >= 60 ? '#ffc107' : '#dc3545'); ?>" 
                                    stroke-width="20"
                                    stroke-dasharray="<?php echo ($attendance_percentage / 100) * 502.4; ?> 502.4"
                                    transform="rotate(-90 100 100)"/>
                            <text x="100" y="100" text-anchor="middle" dy="10" 
                                  font-size="36" font-weight="bold" 
                                  fill="<?php echo $attendance_percentage >= 75 ? '#28a745' : ($attendance_percentage >= 60 ? '#ffc107' : '#dc3545'); ?>">
                                <?php echo $attendance_percentage; ?>%
                            </text>
                        </svg>
                    </div>
                    <?php if ($attendance_percentage >= 75): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Excellent! You're maintaining good attendance.
                        </div>
                    <?php elseif ($attendance_percentage >= 60): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Your attendance is below recommended level. Try to improve!
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle"></i> Critical! Your attendance is very low. Please attend classes regularly.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Records -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Attendance History</h5>
                </div>
                <div class="card-body">
                    <?php if (count($attendance_records) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Subject/Class</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                                        <td><?php echo date('l', strtotime($record['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['subject'] ?? 'General'); ?></td>
                                        <td><?php echo htmlspecialchars($record['time'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php
                                            $status = strtolower($record['status']);
                                            $badge_class = 'badge-secondary';
                                            $icon = 'fa-question';
                                            
                                            if ($status == 'present') {
                                                $badge_class = 'badge-success';
                                                $icon = 'fa-check';
                                            } elseif ($status == 'absent') {
                                                $badge_class = 'badge-danger';
                                                $icon = 'fa-times';
                                            } elseif ($status == 'late') {
                                                $badge_class = 'badge-warning';
                                                $icon = 'fa-clock';
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <i class="fas <?php echo $icon; ?>"></i>
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['remarks'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No attendance records found</h5>
                            <p class="text-muted">Attendance records will appear here once classes begin.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.attendance-circle {
    width: 200px;
    height: 200px;
}
</style>

<?php include 'includes/footer.php'; ?>
