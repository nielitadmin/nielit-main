<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login_new.php');
    exit();
}

// Check if user is master admin
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'master_admin') {
    header('Location: dashboard.php');
    exit();
}

require_once '../config/database.php';

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_courses'])) {
    $admin_id = intval($_POST['admin_id']);
    $course_ids = isset($_POST['course_ids']) ? $_POST['course_ids'] : [];
    $assigned_by = $_SESSION['admin_id'] ?? null;
    
    if ($admin_id <= 0) {
        $message = "Please select a valid course coordinator.";
        $message_type = "error";
    } else if (empty($course_ids)) {
        $message = "Please select at least one course to assign.";
        $message_type = "error";
    } else if (!$assigned_by) {
        $message = "Session error: Unable to identify the assigning admin.";
        $message_type = "error";
    } else {
        $success_count = 0;
        $error_count = 0;
        
        foreach ($course_ids as $course_id) {
            $course_id = intval($course_id);
            
            try {
                // Check if assignment already exists
                $check_sql = "SELECT id FROM admin_course_assignments WHERE admin_id = ? AND course_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ii", $admin_id, $course_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Update existing assignment
                    $update_sql = "UPDATE admin_course_assignments 
                                  SET is_active = 1, assigned_at = NOW(), assigned_by = ?, assignment_type = 'Manual' 
                                  WHERE admin_id = ? AND course_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("iii", $assigned_by, $admin_id, $course_id);
                    
                    if ($update_stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                } else {
                    // Insert new assignment
                    $insert_sql = "INSERT INTO admin_course_assignments 
                                  (admin_id, course_id, is_active, assigned_by, assigned_at, assignment_type) 
                                  VALUES (?, ?, 1, ?, NOW(), 'Manual')";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("iii", $admin_id, $course_id, $assigned_by);
                    
                    if ($insert_stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                }
            } catch (Exception $e) {
                $error_count++;
            }
        }
        
        if ($success_count > 0) {
            $message = "Successfully assigned $success_count course(s)!";
            $message_type = "success";
        }
        if ($error_count > 0) {
            $message .= " Failed to assign $error_count course(s).";
            $message_type = ($success_count > 0) ? "warning" : "error";
        }
    }
}

// Get coordinators and courses
$coordinators = $conn->query("SELECT id, username, email FROM admin WHERE role = 'course_coordinator' AND is_active = 1 ORDER BY username");
$courses = $conn->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Course Assignment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 800px; margin-top: 50px; }
        .card { box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .course-item { 
            background: white; 
            border: 1px solid #dee2e6; 
            border-radius: 8px; 
            padding: 15px; 
            margin-bottom: 10px; 
        }
        .course-item:hover { background-color: #f8f9ff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-user-plus"></i> Simple Course Assignment</h4>
            </div>
            <div class="card-body">
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : ($message_type === 'warning' ? 'warning' : 'danger'); ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Course Coordinator *</label>
                        <select name="admin_id" class="form-select" required>
                            <option value="">-- Select Coordinator --</option>
                            <?php while ($coordinator = $coordinators->fetch_assoc()): ?>
                                <option value="<?php echo $coordinator['id']; ?>">
                                    <?php echo htmlspecialchars($coordinator['username']); ?> 
                                    (<?php echo htmlspecialchars($coordinator['email']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Courses to Assign *</label>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleAll(this)">
                            <label class="form-check-label fw-bold" for="selectAll">
                                <i class="fas fa-check-double text-primary"></i> Select All Courses
                            </label>
                        </div>
                        
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php while ($course = $courses->fetch_assoc()): ?>
                                <div class="course-item">
                                    <div class="form-check">
                                        <input class="form-check-input course-checkbox" type="checkbox" 
                                               name="course_ids[]" 
                                               value="<?php echo $course['id']; ?>" 
                                               id="course_<?php echo $course['id']; ?>">
                                        <label class="form-check-label" for="course_<?php echo $course['id']; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($course['course_name']); ?></strong>
                                                    <?php if ($course['course_code']): ?>
                                                        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <i class="fas fa-graduation-cap text-muted"></i>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" name="assign_courses" class="btn btn-primary">
                            <i class="fas fa-check"></i> Assign Courses
                        </button>
                        <a href="manage_course_assignments.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Main Page
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('.course-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }
    </script>
</body>
</html>