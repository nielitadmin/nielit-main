<?php
/**
 * Helper Script: Assign Courses to Course Coordinator
 * Date: 2026-03-10
 * Description: Assigns courses to course coordinator for testing the filtering feature
 * 
 * HOW TO RUN:
 * 1. Access this file in your browser: http://yourdomain.com/migrations/assign_test_courses.php
 * 2. Select courses to assign to the coordinator
 * 3. Click "Assign Courses" button
 */

// Include database configuration
require_once __DIR__ . '/../config/config.php';

// Set content type to HTML
header('Content-Type: text/html; charset=utf-8');

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_courses'])) {
    $admin_id = intval($_POST['admin_id']);
    $course_ids = isset($_POST['course_ids']) ? $_POST['course_ids'] : [];
    $assigned_by = intval($_POST['assigned_by']);
    
    if (empty($course_ids)) {
        $message = "Please select at least one course to assign.";
        $message_type = "warning";
    } else {
        $success_count = 0;
        $error_count = 0;
        
        foreach ($course_ids as $course_id) {
            $course_id = intval($course_id);
            
            // Check if assignment already exists
            $check_sql = "SELECT id FROM admin_course_assignments 
                         WHERE admin_id = ? AND course_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $admin_id, $course_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update existing assignment to active
                $update_sql = "UPDATE admin_course_assignments 
                              SET is_active = 1 
                              WHERE admin_id = ? AND course_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $admin_id, $course_id);
                
                if ($update_stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                // Insert new assignment
                $insert_sql = "INSERT INTO admin_course_assignments 
                              (admin_id, course_id, is_active, assigned_by) 
                              VALUES (?, ?, 1, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iii", $admin_id, $course_id, $assigned_by);
                
                if ($insert_stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            $message = "Successfully assigned $success_count course(s) to the coordinator!";
            $message_type = "success";
        }
        if ($error_count > 0) {
            $message .= " Failed to assign $error_count course(s).";
            $message_type = "warning";
        }
    }
}

// Get all course coordinators
$coordinators_query = "SELECT id, username, email FROM admin WHERE role = 'course_coordinator' AND is_active = 1";
$coordinators_result = $conn->query($coordinators_query);

// Get all courses
$courses_query = "SELECT id, course_name, course_code FROM courses ORDER BY course_name";
$courses_result = $conn->query($courses_query);

// Get all master admins (for assigned_by)
$master_admins_query = "SELECT id, username FROM admin WHERE role = 'master_admin' AND is_active = 1";
$master_admins_result = $conn->query($master_admins_query);

// Get current assignments
$assignments_query = "SELECT aca.*, a.username as admin_name, c.course_name, c.course_code,
                      ma.username as assigned_by_name
                      FROM admin_course_assignments aca
                      JOIN admin a ON aca.admin_id = a.id
                      JOIN courses c ON aca.course_id = c.id
                      LEFT JOIN admin ma ON aca.assigned_by = ma.id
                      ORDER BY a.username, c.course_name";
$assignments_result = $conn->query($assignments_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Courses to Coordinator</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f7fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #34495e;
            margin-top: 30px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #28a745;
            margin: 15px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
            margin: 15px 0;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
            margin: 15px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #17a2b8;
            margin: 15px 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        select, input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .checkbox-group {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            background: #f8f9fa;
        }
        .checkbox-item {
            padding: 8px;
            margin-bottom: 5px;
            background: white;
            border-radius: 3px;
        }
        .checkbox-item:hover {
            background: #e9ecef;
        }
        .checkbox-item input {
            width: auto;
            margin-right: 10px;
        }
        .checkbox-item label {
            display: inline;
            font-weight: normal;
            margin: 0;
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #2980b9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #3498db;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success {
            background: #28a745;
            color: white;
        }
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        .select-all {
            margin-bottom: 10px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎓 Assign Courses to Course Coordinator</h1>
        
        <?php if ($message): ?>
            <div class="<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="info">
            <strong>ℹ️ About This Tool:</strong><br>
            Use this tool to assign courses to course coordinators. Once assigned, coordinators will only see students enrolled in their assigned courses when they access the Students page.
        </div>
        
        <h2>Assign New Courses</h2>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="admin_id">Select Course Coordinator:</label>
                <select name="admin_id" id="admin_id" required>
                    <option value="">-- Select Coordinator --</option>
                    <?php while ($coordinator = $coordinators_result->fetch_assoc()): ?>
                        <option value="<?php echo $coordinator['id']; ?>">
                            <?php echo htmlspecialchars($coordinator['username']); ?> 
                            (<?php echo htmlspecialchars($coordinator['email']); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Select Courses to Assign:</label>
                <div class="select-all">
                    <input type="checkbox" id="select_all" onclick="toggleAll(this)">
                    <label for="select_all" style="display: inline; margin-left: 5px;">Select All Courses</label>
                </div>
                <div class="checkbox-group">
                    <?php 
                    $courses_result->data_seek(0);
                    while ($course = $courses_result->fetch_assoc()): 
                    ?>
                        <div class="checkbox-item">
                            <input type="checkbox" 
                                   name="course_ids[]" 
                                   value="<?php echo $course['id']; ?>" 
                                   id="course_<?php echo $course['id']; ?>"
                                   class="course-checkbox">
                            <label for="course_<?php echo $course['id']; ?>">
                                <strong><?php echo htmlspecialchars($course['course_name']); ?></strong>
                                <?php if ($course['course_code']): ?>
                                    (<?php echo htmlspecialchars($course['course_code']); ?>)
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label for="assigned_by">Assigned By (Master Admin):</label>
                <select name="assigned_by" id="assigned_by" required>
                    <option value="">-- Select Master Admin --</option>
                    <?php while ($master_admin = $master_admins_result->fetch_assoc()): ?>
                        <option value="<?php echo $master_admin['id']; ?>">
                            <?php echo htmlspecialchars($master_admin['username']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <button type="submit" name="assign_courses" class="btn">
                ✅ Assign Courses
            </button>
        </form>
        
        <h2>📋 Current Course Assignments</h2>
        
        <?php if ($assignments_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Coordinator</th>
                        <th>Course</th>
                        <th>Course Code</th>
                        <th>Status</th>
                        <th>Assigned By</th>
                        <th>Assigned Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($assignment = $assignments_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($assignment['admin_name']); ?></td>
                            <td><?php echo htmlspecialchars($assignment['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($assignment['course_code']); ?></td>
                            <td>
                                <?php if ($assignment['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($assignment['assigned_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="warning">
                ⚠️ No course assignments found. Use the form above to assign courses to coordinators.
            </div>
        <?php endif; ?>
        
        <div class="info" style="margin-top: 30px;">
            <strong>🧪 Testing Instructions:</strong>
            <ol>
                <li>Assign one or more courses to the coordinator (adminbbsr)</li>
                <li>Logout from current admin session</li>
                <li>Login as the course coordinator (username: adminbbsr)</li>
                <li>Navigate to the Students page</li>
                <li>Verify that only students from assigned courses are visible</li>
                <li>Check that statistics (Total, Pending, Active) show filtered counts</li>
            </ol>
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
<?php
$conn->close();
?>
