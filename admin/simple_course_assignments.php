<?php
// Simple version of course assignments page for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if admin is logged in (compatible with both old and new login systems)
$is_logged_in = isset($_SESSION['admin_logged_in']) || isset($_SESSION['admin']);

if (!$is_logged_in) {
    header('Location: login_new.php');
    exit();
}

// Check if user is master admin
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'master_admin') {
    header('Location: dashboard.php');
    exit();
}

require_once '../config/database.php';

// Simple message handling
$message = '';
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}

// Get basic data
try {
    $coordinators_query = "SELECT id, username, email FROM admin WHERE role = 'course_coordinator' AND is_active = 1 ORDER BY username";
    $coordinators_result = $conn->query($coordinators_query);

    $courses_query = "SELECT id, course_name, course_code FROM courses ORDER BY course_name";
    $courses_result = $conn->query($courses_query);

    // Check if admin_course_assignments table exists
    $table_exists = false;
    $check_table = $conn->query("SHOW TABLES LIKE 'admin_course_assignments'");
    if ($check_table && $check_table->num_rows > 0) {
        $table_exists = true;
        $assignments_query = "SELECT aca.*, a.username as admin_name, c.course_name 
                             FROM admin_course_assignments aca
                             JOIN admin a ON aca.admin_id = a.id
                             JOIN courses c ON aca.course_id = c.id
                             WHERE aca.is_active = 1
                             ORDER BY a.username, c.course_name";
        $assignments_result = $conn->query($assignments_query);
    }
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Course Assignments - NIELIT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-user-tie"></i> Simple Course Assignments</h2>
                    <div>
                        <a href="debug_manage_course_assignments.php" class="btn btn-warning btn-sm">
                            <i class="fas fa-bug"></i> Debug
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Debug Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>System Status</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Session Admin:</strong> <?php echo $_SESSION['admin'] ?? 'NOT SET'; ?></p>
                        <p><strong>Session Role:</strong> <?php echo $_SESSION['admin_role'] ?? 'NOT SET'; ?></p>
                        <p><strong>Coordinators Found:</strong> <?php echo isset($coordinators_result) ? $coordinators_result->num_rows : 'ERROR'; ?></p>
                        <p><strong>Courses Found:</strong> <?php echo isset($courses_result) ? $courses_result->num_rows : 'ERROR'; ?></p>
                        <p><strong>Assignments Table:</strong> <?php echo $table_exists ? 'EXISTS' : 'MISSING'; ?></p>
                    </div>
                </div>

                <!-- Coordinators List -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Course Coordinators</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($coordinators_result) && $coordinators_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($coordinator = $coordinators_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $coordinator['id']; ?></td>
                                                <td><?php echo htmlspecialchars($coordinator['username']); ?></td>
                                                <td><?php echo htmlspecialchars($coordinator['email']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No course coordinators found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Courses List -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Available Courses</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($courses_result) && $courses_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Course Name</th>
                                            <th>Course Code</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $courses_result->data_seek(0);
                                        while ($course = $courses_result->fetch_assoc()): 
                                        ?>
                                            <tr>
                                                <td><?php echo $course['id']; ?></td>
                                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                                <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No courses found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Current Assignments -->
                <?php if ($table_exists): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Current Assignments</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($assignments_result) && $assignments_result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Coordinator</th>
                                                <th>Course</th>
                                                <th>Assigned At</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($assignment = $assignments_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $assignment['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($assignment['admin_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($assignment['course_name']); ?></td>
                                                    <td><?php echo $assignment['assigned_at']; ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No assignments found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <h5>Missing Table</h5>
                        <p>The admin_course_assignments table doesn't exist. Please run the debug script to create it.</p>
                        <a href="debug_manage_course_assignments.php" class="btn btn-warning">
                            <i class="fas fa-tools"></i> Run Debug & Create Table
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-header">
                        <h5>Actions</h5>
                    </div>
                    <div class="card-body">
                        <a href="debug_manage_course_assignments.php" class="btn btn-warning me-2">
                            <i class="fas fa-bug"></i> Debug System
                        </a>
                        <a href="manage_course_assignments.php" class="btn btn-primary me-2">
                            <i class="fas fa-cogs"></i> Try Full Version
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>