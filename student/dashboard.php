<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';
require_once __DIR__ . '/../includes/attendance_in_out_helper.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];
$selected_record_id = isset($_GET['record_id']) ? (int)$_GET['record_id'] : (int)($_SESSION['active_record_id'] ?? 0);
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : (int)($_SESSION['active_course_id'] ?? 0);

$enrollments = getEnrollmentsForStudentId($conn, $student_id);

// Fetch student details (selected enrollment row, or latest)
$sql = "SELECT s.*, sch.scheme_name, sch.scheme_code
        FROM students s
        LEFT JOIN schemes sch ON sch.id = s.scheme_id
        WHERE s.student_id = ?";
if ($selected_record_id > 0) {
    $sql .= " AND s.id = ? LIMIT 1";
} elseif ($selected_course_id > 0) {
    $sql .= " AND s.course_id = ? ORDER BY s.id DESC LIMIT 1";
} else {
    $sql .= " ORDER BY s.id DESC LIMIT 1";
}
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

if ($selected_record_id > 0) {
    $stmt->bind_param("si", $student_id, $selected_record_id);
} elseif ($selected_course_id > 0) {
    $stmt->bind_param("si", $student_id, $selected_course_id);
} else {
    $stmt->bind_param("s", $student_id);
}
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if ($student) {
    $selected_record_id = (int)$student['id'];
    $selected_course_id = (int)($student['course_id'] ?? 0);
    $_SESSION['active_record_id'] = $selected_record_id;
    $_SESSION['active_course_id'] = $selected_course_id;
}

// Try to get course details if courses table exists
$course_name = $student['course'];
$duration = 'N/A';
$fees = 0;

$sql_course = "SELECT course_name, duration, fees FROM courses WHERE course_code = ?";
$stmt_course = $conn->prepare($sql_course);
if ($stmt_course) {
    $stmt_course->bind_param("s", $student['course']);
    $stmt_course->execute();
    $result_course = $stmt_course->get_result();
    if ($row_course = $result_course->fetch_assoc()) {
        $course_name = $row_course['course_name'];
        $duration = $row_course['duration'];
        $fees = $row_course['fees'];
    }
}

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$student_status = strtolower(trim((string)($student['status'] ?? 'pending')));
$has_pending_enrollment = false;
$has_active_enrollment = false;
foreach ($enrollments as $enr) {
    $enrStatus = strtolower(trim((string)($enr['status'] ?? 'pending')));
    if ($enrStatus === 'pending') {
        $has_pending_enrollment = true;
    }
    if (in_array($enrStatus, ['active', 'approved'], true)) {
        $has_active_enrollment = true;
    }
}
$show_pending_notice = ($student_status === 'pending' || $has_pending_enrollment) && !$has_active_enrollment;

// Fetch announcements for this student (all + students + any enrolled course)
$announcements_list = [];
$course_codes = array_values(array_unique(array_filter(array_merge(
    [$student['course'] ?? ''],
    array_map(function ($enr) {
        return $enr['course_code'] ?? ($enr['course'] ?? '');
    }, $enrollments)
))));

$announcements_sql = "SELECT * FROM announcements
                      WHERE is_active = 1
                      AND (
                          target_audience IN ('all', 'students')";
if (!empty($course_codes)) {
    $placeholders = implode(',', array_fill(0, count($course_codes), '?'));
    $announcements_sql .= " OR (target_audience = 'specific_course' AND course_code IN ($placeholders))";
}
$announcements_sql .= ")
                      ORDER BY created_at DESC
                      LIMIT 10";

$stmt_announcements = $conn->prepare($announcements_sql);
if ($stmt_announcements) {
    if (!empty($course_codes)) {
        $types = str_repeat('s', count($course_codes));
        $stmt_announcements->bind_param($types, ...$course_codes);
    }
    $stmt_announcements->execute();
    $announcements_result = $stmt_announcements->get_result();
    while ($row = $announcements_result->fetch_assoc()) {
        $announcements_list[] = $row;
    }
    $stmt_announcements->close();
}

$announcement_alert_class = [
    'info' => 'ticker-info',
    'success' => 'ticker-success',
    'warning' => 'ticker-warning',
    'danger' => 'ticker-danger',
];
$announcement_icon_class = [
    'info' => 'fa-info-circle',
    'success' => 'fa-check-circle',
    'warning' => 'fa-exclamation-triangle',
    'danger' => 'fa-exclamation-circle',
];

// Get course progress (if you have a progress table)
$progress = 0; // Default
$sql_progress = "SELECT progress FROM student_progress WHERE student_id = ?";
$stmt_progress = $conn->prepare($sql_progress);
if ($stmt_progress) {
    $stmt_progress->bind_param("s", $student_id);
    $stmt_progress->execute();
    $result_progress = $stmt_progress->get_result();
    if ($row_progress = $result_progress->fetch_assoc()) {
        $progress = $row_progress['progress'];
    }
}

$portalAtt = getStudentPortalAttendance($conn, $student_id, 0);
$attendance_percentage = (float) ($portalAtt['attendance_percentage'] ?? 0);

$page_title = "Dashboard";
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-card">
                <h2>Welcome back, <?php echo htmlspecialchars($student['name']); ?>! 👋</h2>
                <p class="text-muted">Student ID: <?php echo htmlspecialchars($student['student_id']); ?></p>
            </div>
        </div>
    </div>

    <?php if ($show_pending_notice): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning border-0 shadow-sm mb-0" role="status">
                <div class="d-flex gap-3 align-items-start">
                    <i class="fas fa-hourglass-half fa-lg mt-1"></i>
                    <div>
                        <strong>Registration under review</strong>
                        <p class="mb-0 mt-1 small">
                            You can use this dashboard now. Your enrollment status is <strong>Pending</strong> while admin verifies your documents.
                            After approval, status will change to <strong>Active</strong> and you will be confirmed as a NIELIT student for this program.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($enrollments) > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-layer-group"></i> My Courses</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Scheme / Project</th>
                                    <th>Batch</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Form</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollments as $enr):
                                    $cid = (int)($enr['course_id'] ?? 0);
                                    $recordId = (int)($enr['student_record_id'] ?? $enr['id'] ?? 0);
                                    $cname = $enr['course_name'] ?? ($enr['course'] ?? 'N/A');
                                    $sname = $enr['scheme_name'] ?? '';
                                    $bname = $enr['batch_name'] ?? ($enr['batch_code'] ?? 'Not assigned');
                                    $estatus = ucfirst($enr['status'] ?? 'pending');
                                    $estatus_lc = strtolower($enr['status'] ?? 'pending');
                                    $status_badge = 'bg-secondary';
                                    if (in_array($estatus_lc, ['active', 'approved'], true)) {
                                        $status_badge = 'bg-success';
                                    } elseif ($estatus_lc === 'pending') {
                                        $status_badge = 'bg-warning text-dark';
                                    } elseif (in_array($estatus_lc, ['rejected', 'cancelled'], true)) {
                                        $status_badge = 'bg-danger';
                                    }
                                    $regDate = !empty($enr['registered_at']) ? $enr['registered_at'] : ($enr['registration_date'] ?? '');
                                ?>
                                <tr class="<?php echo ($recordId === $selected_record_id) ? 'table-primary' : ''; ?>">
                                    <td><?php echo htmlspecialchars($cname); ?></td>
                                    <td><?php echo $sname !== '' ? htmlspecialchars($sname) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($bname); ?></td>
                                    <td><span class="badge <?php echo $status_badge; ?>"><?php echo htmlspecialchars($estatus); ?></span></td>
                                    <td><?php echo $regDate ? date('M d, Y', strtotime($regDate)) : '—'; ?></td>
                                    <td>
                                        <?php if ($recordId > 0): ?>
                                        <a href="download_form.php?record_id=<?php echo $recordId; ?>" class="btn btn-sm btn-outline-success" title="Download form for this course">
                                            <i class="fas fa-file-pdf"></i> Form
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($recordId !== $selected_record_id && $recordId > 0): ?>
                                        <a href="dashboard.php?record_id=<?php echo $recordId; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php else: ?>
                                        <span class="text-muted small">Current</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <!-- Course Info Card -->
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-primary-gradient">
                <div class="stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo htmlspecialchars($course_name); ?></h3>
                    <p>Your Course</p>
                </div>
            </div>
        </div>

        <!-- Progress Card -->
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-success-gradient">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $progress; ?>%</h3>
                    <p>Course Progress</p>
                </div>
            </div>
        </div>

        <!-- Attendance Card -->
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-warning-gradient">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $attendance_percentage; ?>%</h3>
                    <p>Attendance</p>
                </div>
            </div>
        </div>

        <!-- Status Card -->
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-info-gradient">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo ucfirst($student['status'] ?? 'Active'); ?></h3>
                    <p>Status</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <a href="profile.php" class="action-btn">
                                <i class="fas fa-user"></i>
                                <span>View Profile</span>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="attendance.php" class="action-btn">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Attendance</span>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="download_form.php" class="action-btn">
                                <i class="fas fa-download"></i>
                                <span>Download Form</span>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="certificates.php" class="action-btn">
                                <i class="fas fa-certificate"></i>
                                <span>Certificates</span>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="placement.php" class="action-btn">
                                <i class="fas fa-briefcase"></i>
                                <span>My Placement</span>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="fees.php" class="action-btn">
                                <i class="fas fa-rupee-sign"></i>
                                <span>Fee Details</span>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="support.php" class="action-btn">
                                <i class="fas fa-headset"></i>
                                <span>Support</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Announcements -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-bullhorn"></i> Announcements</h5>
                    <?php if (!empty($announcements_list)): ?>
                    <span class="badge bg-primary"><?php echo count($announcements_list); ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body announcement-ticker-card">
                    <?php if (!empty($announcements_list)): ?>
                        <div class="announcement-ticker" aria-live="polite">
                            <div class="announcement-ticker-viewport">
                                <div class="announcement-ticker-track" style="--ticker-duration: <?php echo max(24, count($announcements_list) * 10); ?>s;">
                                    <?php foreach ($announcements_list as $announcement):
                                        $type = $announcement['type'] ?? 'info';
                                        $tickerClass = $announcement_alert_class[$type] ?? 'ticker-info';
                                        $iconClass = $announcement_icon_class[$type] ?? 'fa-info-circle';
                                    ?>
                                    <article class="announcement-ticker-item <?php echo $tickerClass; ?>">
                                        <div class="announcement-ticker-icon">
                                            <i class="fas <?php echo $iconClass; ?>"></i>
                                        </div>
                                        <div class="announcement-ticker-content">
                                            <h6><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                            <p><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></p>
                                            <small>
                                                <i class="fas fa-clock"></i>
                                                <?php echo date('M d, Y - h:i A', strtotime($announcement['created_at'])); ?>
                                                <?php if (($announcement['target_audience'] ?? '') === 'specific_course' && !empty($announcement['course_code'])): ?>
                                                    | <i class="fas fa-tag"></i> <?php echo htmlspecialchars($announcement['course_code']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <p class="announcement-ticker-hint text-muted small mb-0 mt-2">
                            <i class="fas fa-arrows-alt-v"></i> Announcements scroll automatically. Hover to pause.
                        </p>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No announcements at this time.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Profile Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-id-card"></i> Profile Summary</h5>
                </div>
                <div class="card-body text-center">
                    <img src="../<?php echo htmlspecialchars($student['passport_photo']); ?>" 
                         alt="Profile Photo" 
                         class="profile-photo mb-3">
                    <h5><?php echo htmlspecialchars($student['name']); ?></h5>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($student['student_id']); ?></p>
                    <p class="mb-3">
                        <i class="fas fa-envelope"></i> 
                        <?php echo htmlspecialchars($student['email']); ?>
                    </p>
                    <p class="mb-3">
                        <i class="fas fa-phone"></i> 
                        <?php echo htmlspecialchars($student['mobile']); ?>
                    </p>
                    <a href="profile.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                </div>
            </div>

            <!-- Course Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-book"></i> Course Details</h5>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">Course:</span>
                        <span class="info-value"><?php echo htmlspecialchars($course_name); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Duration:</span>
                        <span class="info-value"><?php echo htmlspecialchars($duration); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Training Centre:</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['training_center']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="badge badge-success"><?php echo ucfirst($student['status'] ?? 'Active'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Important Links -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-link"></i> Important Links</h5>
                </div>
                <div class="card-body">
                    <ul class="link-list">
                        <li><a href="https://www.nielit.gov.in/" target="_blank"><i class="fas fa-external-link-alt"></i> NIELIT Official</a></li>
                        <li><a href="../public/courses.php" target="_blank"><i class="fas fa-book-open"></i> Course Catalog</a></li>
                        <li><a href="study_materials.php"><i class="fas fa-file-pdf"></i> Study Materials</a></li>
                        <li><a href="timetable.php"><i class="fas fa-calendar"></i> Class Timetable</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Inline fallback so announcement ticker works even if cached CSS is old */
.announcement-ticker-viewport {
    position: relative;
    height: 320px;
    overflow: hidden;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.announcement-ticker-track {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 12px;
}
.announcement-ticker-item {
    display: flex;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #0ea5e9;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
}
.announcement-ticker-item.ticker-success { border-left-color: #22c55e; }
.announcement-ticker-item.ticker-warning { border-left-color: #f59e0b; }
.announcement-ticker-item.ticker-danger { border-left-color: #ef4444; }
.announcement-ticker-content h6 {
    margin: 0 0 6px;
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
}
.announcement-ticker-content p {
    margin: 0 0 8px;
    font-size: 0.875rem;
    color: #475569;
    line-height: 1.5;
}
.announcement-ticker-content small {
    color: #64748b;
    font-size: 0.78rem;
}
</style>

<?php include 'includes/footer.php'; ?>
