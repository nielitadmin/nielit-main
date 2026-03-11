<?php
session_start();
header('Content-Type: application/json');

// Debug logging
error_log("AJAX Course Assignments called with action: " . ($_POST['action'] ?? 'none'));

// Check if admin is logged in (compatible with both old and new login systems)
$is_logged_in = isset($_SESSION['admin_logged_in']) || isset($_SESSION['admin']);

if (!$is_logged_in) {
    error_log("AJAX: User not logged in");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'master_admin') {
    error_log("AJAX: User not master admin, role: " . ($_SESSION['admin_role'] ?? 'none'));
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

require_once '../config/database.php';

$action = $_POST['action'] ?? '';
error_log("AJAX: Processing action: " . $action);

switch ($action) {
    case 'assign_courses':
        assignCourses();
        break;
    case 'remove_assignment':
        removeAssignment();
        break;
    case 'get_assignments':
        getAssignments();
        break;
    case 'get_stats':
        getStats();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function assignCourses() {
    global $conn;
    
    error_log("assignCourses function called");
    error_log("POST data: " . json_encode($_POST));
    
    $admin_id = intval($_POST['admin_id'] ?? 0);
    $course_ids = $_POST['course_ids'] ?? [];
    $assigned_by = $_SESSION['admin_id'] ?? null;

    error_log("assignCourses: admin_id=$admin_id, course_ids=" . json_encode($course_ids) . ", assigned_by=$assigned_by");

    if ($admin_id <= 0) {
        error_log("assignCourses: Invalid admin_id - received: " . ($_POST['admin_id'] ?? 'NULL'));
        echo json_encode(['success' => false, 'message' => 'Please select a valid course coordinator.', 'debug' => ['admin_id_received' => $_POST['admin_id'] ?? 'NULL', 'admin_id_parsed' => $admin_id]]);
        return;
    }
    
    if (empty($course_ids)) {
        error_log("assignCourses: No course_ids provided");
        echo json_encode(['success' => false, 'message' => 'Please select at least one course to assign.']);
        return;
    }
    
    if (!$assigned_by) {
        error_log("assignCourses: No assigned_by in session");
        echo json_encode(['success' => false, 'message' => 'Session error: Please log out and log back in.']);
        return;
    }

    // Check if assignment_type column exists
    $has_assignment_type = false;
    try {
        $chk = $conn->query("SHOW COLUMNS FROM admin_course_assignments LIKE 'assignment_type'");
        $has_assignment_type = ($chk->num_rows > 0);
    } catch (Exception $e) {}

    $success_count = $error_count = $duplicate_count = 0;
    $duplicate_courses = [];
    $assigned_courses = [];

    foreach ($course_ids as $cid) {
        $cid = intval($cid);
        try {
            // Get course name
            $s = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
            $s->bind_param("i", $cid);
            $s->execute();
            $result = $s->get_result();
            $course_name = $result->fetch_assoc()['course_name'] ?? "Course #$cid";

            // Check if assignment already exists
            $s2 = $conn->prepare("SELECT id, is_active FROM admin_course_assignments WHERE admin_id = ? AND course_id = ?");
            $s2->bind_param("ii", $admin_id, $cid);
            $s2->execute();
            $existing = $s2->get_result()->fetch_assoc();

            if ($existing) {
                if ($existing['is_active'] == 1) {
                    $duplicate_count++;
                    $duplicate_courses[] = $course_name;
                    continue;
                }
                // Reactivate existing assignment
                $sql = $has_assignment_type
                    ? "UPDATE admin_course_assignments SET is_active=1, assigned_at=NOW(), assigned_by=?, assignment_type='Manual' WHERE admin_id=? AND course_id=?"
                    : "UPDATE admin_course_assignments SET is_active=1, assigned_at=NOW(), assigned_by=? WHERE admin_id=? AND course_id=?";
                $st = $conn->prepare($sql);
                $st->bind_param("iii", $assigned_by, $admin_id, $cid);
            } else {
                // Create new assignment
                $sql = $has_assignment_type
                    ? "INSERT INTO admin_course_assignments (admin_id,course_id,is_active,assigned_by,assigned_at,assignment_type) VALUES (?,?,1,?,NOW(),'Manual')"
                    : "INSERT INTO admin_course_assignments (admin_id,course_id,is_active,assigned_by,assigned_at) VALUES (?,?,1,?,NOW())";
                $st = $conn->prepare($sql);
                $st->bind_param("iii", $admin_id, $cid, $assigned_by);
            }
            
            if ($st->execute()) {
                $success_count++;
                $assigned_courses[] = $course_name;
            } else {
                $error_count++;
            }
        } catch (Exception $e) {
            $error_count++;
        }
    }

    // Build response message
    $messages = [];
    $type = 'success';
    
    if ($success_count > 0) {
        $messages[] = "Successfully assigned $success_count course(s)";
    }
    if ($duplicate_count > 0) {
        $messages[] = "$duplicate_count course(s) already assigned";
        if (!$success_count) $type = 'warning';
    }
    if ($error_count > 0) {
        $messages[] = "Failed to assign $error_count course(s)";
        if (!$success_count && !$duplicate_count) $type = 'error';
    }

    echo json_encode([
        'success' => $success_count > 0,
        'message' => implode('. ', $messages),
        'type' => $type,
        'stats' => [
            'success' => $success_count,
            'duplicates' => $duplicate_count,
            'errors' => $error_count
        ],
        'assigned_courses' => $assigned_courses,
        'duplicate_courses' => $duplicate_courses
    ]);
}

function removeAssignment() {
    global $conn;
    
    $assignment_id = intval($_POST['assignment_id'] ?? 0);
    
    if ($assignment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
        return;
    }

    // Get assignment details before removing
    $s = $conn->prepare("SELECT a.username AS admin_name, c.course_name
                         FROM admin_course_assignments aca
                         JOIN admin a ON aca.admin_id = a.id
                         JOIN courses c ON aca.course_id = c.id
                         WHERE aca.id = ? AND aca.is_active = 1");
    $s->bind_param("i", $assignment_id);
    $s->execute();
    $details = $s->get_result()->fetch_assoc();

    if (!$details) {
        echo json_encode(['success' => false, 'message' => 'Assignment not found or already removed']);
        return;
    }

    // Remove assignment
    $r = $conn->prepare("UPDATE admin_course_assignments SET is_active = 0 WHERE id = ?");
    $r->bind_param("i", $assignment_id);
    
    if ($r->execute()) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully removed '{$details['course_name']}' from '{$details['admin_name']}'",
            'assignment_id' => $assignment_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove course assignment']);
    }
}

function getAssignments() {
    global $conn;
    
    $result = $conn->query("SELECT aca.*, 
                            a.username AS admin_name, 
                            a.email AS admin_email,
                            c.course_name, 
                            c.course_code, 
                            COALESCE(ma.username, 'System') AS assigned_by_name,
                            COALESCE(aca.assignment_type, 'Manual') AS assignment_type,
                            aca.assigned_at
                            FROM admin_course_assignments aca
                            JOIN admin a ON aca.admin_id = a.id
                            JOIN courses c ON aca.course_id = c.id
                            LEFT JOIN admin ma ON aca.assigned_by = ma.id
                            WHERE aca.is_active = 1
                            ORDER BY aca.assigned_at DESC, a.username, c.course_name");

    $assignments = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }
    }

    echo json_encode(['success' => true, 'assignments' => $assignments]);
}

function getStats() {
    global $conn;
    
    $stats_result = $conn->query("SELECT
        COUNT(DISTINCT aca.admin_id) AS total_coordinators_with_assignments,
        COUNT(aca.id) AS total_assignments,
        COUNT(DISTINCT aca.course_id) AS total_courses_assigned
        FROM admin_course_assignments aca WHERE aca.is_active = 1");
    
    $stats = $stats_result ? $stats_result->fetch_assoc() : [
        'total_coordinators_with_assignments' => 0,
        'total_assignments' => 0,
        'total_courses_assigned' => 0
    ];

    echo json_encode(['success' => true, 'stats' => $stats]);
}
?>