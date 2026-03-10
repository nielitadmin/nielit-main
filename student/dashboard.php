<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch student details
$sql = "SELECT * FROM students WHERE student_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

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

// Fetch announcements for this student
$announcements_sql = "SELECT * FROM announcements 
                      WHERE is_active = 1 
                      AND (target_audience = 'all' 
                           OR target_audience = 'students' 
                           OR (target_audience = 'specific_course' AND course_code = ?))
                      ORDER BY created_at DESC 
                      LIMIT 5";
$stmt_announcements = $conn->prepare($announcements_sql);
$stmt_announcements->bind_param("s", $student['course']);
$stmt_announcements->execute();
$announcements_result = $stmt_announcements->get_result();

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

// Get attendance (if you have attendance table)
$attendance_percentage = 0;
$sql_attendance = "SELECT 
    COUNT(*) as total_classes,
    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count
    FROM attendance WHERE student_id = ?";
$stmt_attendance = $conn->prepare($sql_attendance);
if ($stmt_attendance) {
    $stmt_attendance->bind_param("s", $student_id);
    $stmt_attendance->execute();
    $result_attendance = $stmt_attendance->get_result();
    if ($row_attendance = $result_attendance->fetch_assoc()) {
        $total = $row_attendance['total_classes'];
        $present = $row_attendance['present_count'];
        if ($total > 0) {
            $attendance_percentage = round(($present / $total) * 100, 1);
        }
    }
}

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
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bullhorn"></i> Announcements</h5>
                </div>
                <div class="card-body">
                    <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
                        <?php while ($announcement = $announcements_result->fetch_assoc()): 
                            $alert_class = [
                                'info' => 'alert-info',
                                'success' => 'alert-success',
                                'warning' => 'alert-warning',
                                'danger' => 'alert-danger'
                            ];
                            $icon_class = [
                                'info' => 'fa-info-circle',
                                'success' => 'fa-check-circle',
                                'warning' => 'fa-exclamation-triangle',
                                'danger' => 'fa-exclamation-circle'
                            ];
                        ?>
                        <div class="alert <?php echo $alert_class[$announcement['type']]; ?> alert-dismissible fade show" role="alert">
                            <h6 class="alert-heading">
                                <i class="fas <?php echo $icon_class[$announcement['type']]; ?>"></i>
                                <?php echo htmlspecialchars($announcement['title']); ?>
                            </h6>
                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></p>
                            <hr>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> 
                                <?php echo date('M d, Y - h:i A', strtotime($announcement['created_at'])); ?>
                                <?php if ($announcement['target_audience'] == 'specific_course'): ?>
                                    | <i class="fas fa-tag"></i> <?php echo $announcement['course_code']; ?>
                                <?php endif; ?>
                            </small>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endwhile; ?>
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

<?php include 'includes/footer.php'; ?>
