<?php
// Start session and include the database connection
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';

// Enable error reporting for debugging (remove in production)
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// Check if the admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");
    exit();
}

// Load active theme
$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);

// Role flags
$admin_role = $_SESSION['admin_role'] ?? '';
$is_course_coordinator = ($admin_role === 'course_coordinator');
$is_front_office = ($admin_role === 'front_office_desk');

// ─── HANDLE: Delete student ───────────────────────────────────────────────────
if (isset($_GET['delete_id'])) {
    if ($is_front_office) {
        $_SESSION['message'] = "Access denied. Front Office Desk cannot delete students.";
        $_SESSION['message_type'] = "danger";
        header("Location: students.php");
        exit();
    }
    $delete_id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Student deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting student: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    header("Location: students.php");
    exit();
}

// ─── HANDLE: Approve student ──────────────────────────────────────────────────
if (isset($_GET['approve_id'])) {
    $approve_id = $_GET['approve_id'];
    $stmt = $conn->prepare("UPDATE students SET status = 'active' WHERE student_id = ?");
    $stmt->bind_param("s", $approve_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Student approved successfully! Status changed to Active.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error approving student: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    header("Location: students.php");
    exit();
}

// ─── HANDLE: Reject student (POST with reason) ────────────────────────────────
if (isset($_POST['reject_student'])) {
    $reject_id        = $_POST['reject_id'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    $rejection_note   = trim($_POST['rejection_note']   ?? '');

    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(255) DEFAULT NULL");
    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS rejection_note TEXT DEFAULT NULL");

    $stmt = $conn->prepare("UPDATE students SET status = 'rejected', rejection_reason = ?, rejection_note = ? WHERE student_id = ?");
    if ($stmt) {
        $stmt->bind_param("sss", $rejection_reason, $rejection_note, $reject_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Student registration rejected. Reason: " . htmlspecialchars($rejection_reason);
            $_SESSION['message_type'] = "warning";
        } else {
            $_SESSION['message'] = "Error rejecting student: " . $conn->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    } else {
        $stmt2 = $conn->prepare("UPDATE students SET status = 'rejected' WHERE student_id = ?");
        $stmt2->bind_param("s", $reject_id);
        $stmt2->execute();
        $stmt2->close();
        $_SESSION['message'] = "Student registration rejected.";
        $_SESSION['message_type'] = "warning";
    }

    $redirect = "students.php";
    if (!empty($_POST['filter_course'])) {
        $redirect .= "?filter_course=" . urlencode($_POST['filter_course']);
    }
    header("Location: $redirect");
    exit();
}

// ─── HANDLE: Reject student (legacy GET) ─────────────────────────────────────
if (isset($_GET['reject_id'])) {
    $reject_id = $_GET['reject_id'];
    $stmt = $conn->prepare("UPDATE students SET status = 'rejected' WHERE student_id = ?");
    $stmt->bind_param("s", $reject_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Student registration rejected.";
        $_SESSION['message_type'] = "warning";
    }
    $stmt->close();
    header("Location: students.php");
    exit();
}

// ─── HANDLE: Update student ───────────────────────────────────────────────────
if (isset($_POST['update_student'])) {
    $student_id  = $_POST['student_id'];
    $name        = $_POST['name'];
    $father_name = $_POST['father_name'];
    $mother_name = $_POST['mother_name'];
    $dob         = $_POST['dob'];
    $mobile      = $_POST['mobile'];
    $email       = $_POST['email'];
    $course      = $_POST['course'];
    $status      = $_POST['status'];
    $address     = $_POST['address'];
    $city        = $_POST['city'];
    $state       = $_POST['state'];
    $pincode     = $_POST['pincode'];

    // FIX: student_id is a string → use 's' not 'i' for the last bind param
    $stmt = $conn->prepare("UPDATE students SET 
        name=?, father_name=?, mother_name=?, dob=?, mobile=?, email=?,
        course=?, status=?, address=?, city=?, state=?, pincode=?
        WHERE student_id=?");
    $stmt->bind_param("sssssssssssss",
        $name, $father_name, $mother_name, $dob, $mobile, $email,
        $course, $status, $address, $city, $state, $pincode, $student_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Student updated successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating student: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    header("Location: students.php");
    exit();
}

// ─── HANDLE: Assign batch (single) ───────────────────────────────────────────
if (isset($_POST['assign_batch'])) {
    $student_id = $_POST['student_id'];
    $batch_id   = $_POST['batch_id'];

    if (!empty($batch_id)) {
        $stmt = $conn->prepare("UPDATE students SET batch_id = ? WHERE student_id = ?");
        $stmt->bind_param("is", $batch_id, $student_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Student assigned to batch successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error assigning student to batch: " . $conn->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }

    $redirect_params = [];
    if (!empty($_POST['filter_course'])) $redirect_params[] = 'filter_course=' . urlencode($_POST['filter_course']);
    if (!empty($_POST['start_date']))    $redirect_params[] = 'start_date='    . urlencode($_POST['start_date']);
    if (!empty($_POST['end_date']))      $redirect_params[] = 'end_date='      . urlencode($_POST['end_date']);
    header("Location: students.php" . (!empty($redirect_params) ? '?' . implode('&', $redirect_params) : ''));
    exit();
}

// ─── HANDLE: Bulk assign batch ────────────────────────────────────────────────
if (isset($_POST['bulk_assign_batch'])) {
    $student_ids = $_POST['student_ids'] ?? [];
    $batch_id    = $_POST['batch_id']    ?? '';

    if (!empty($batch_id) && !empty($student_ids)) {
        $success_count = 0;
        $error_count   = 0;
        $stmt = $conn->prepare("UPDATE students SET batch_id = ? WHERE student_id = ?");
        foreach ($student_ids as $sid) {
            $stmt->bind_param("is", $batch_id, $sid);
            $stmt->execute() ? $success_count++ : $error_count++;
        }
        $stmt->close();
        $_SESSION['message']      = "$success_count student(s) assigned to batch successfully!";
        $_SESSION['message_type'] = $error_count > 0 ? "warning" : "success";
        if ($error_count > 0) {
            $_SESSION['message'] .= " $error_count student(s) failed to assign.";
        }
    } else {
        $_SESSION['message']      = "Please select students and a batch.";
        $_SESSION['message_type'] = "warning";
    }

    $redirect_params = [];
    if (!empty($_POST['filter_course'])) $redirect_params[] = 'filter_course=' . urlencode($_POST['filter_course']);
    if (!empty($_POST['start_date']))    $redirect_params[] = 'start_date='    . urlencode($_POST['start_date']);
    if (!empty($_POST['end_date']))      $redirect_params[] = 'end_date='      . urlencode($_POST['end_date']);
    header("Location: students.php" . (!empty($redirect_params) ? '?' . implode('&', $redirect_params) : ''));
    exit();
}

// ─── HANDLE: Remove student from batch ───────────────────────────────────────
if (isset($_GET['remove_batch'])) {
    $student_id = $_GET['remove_batch'];
    $stmt = $conn->prepare("UPDATE students SET batch_id = NULL WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Student removed from batch successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error removing student from batch: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();

    $redirect_params = [];
    if (!empty($_GET['filter_course']))  $redirect_params[] = 'filter_course='  . urlencode($_GET['filter_course']);
    if (!empty($_GET['filter_gender']) && $_GET['filter_gender'] !== 'All')
                                         $redirect_params[] = 'filter_gender='  . urlencode($_GET['filter_gender']);
    if (!empty($_GET['start_date']))     $redirect_params[] = 'start_date='     . urlencode($_GET['start_date']);
    if (!empty($_GET['end_date']))       $redirect_params[] = 'end_date='       . urlencode($_GET['end_date']);
    header("Location: students.php" . (!empty($redirect_params) ? '?' . implode('&', $redirect_params) : ''));
    exit();
}

// ─── COORDINATOR: resolve admin_id & assigned courses ────────────────────────
$admin_courses    = [];
$admin_course_ids = [];
$admin_id         = $_SESSION['admin_id'] ?? null;

if ($is_course_coordinator) {
    if (!$admin_id && isset($_SESSION['admin'])) {
        $admin_username = $_SESSION['admin'];
        $tmp = $conn->prepare("SELECT id FROM admin WHERE username = ?");
        $tmp->bind_param("s", $admin_username);
        $tmp->execute();
        $tmp_result = $tmp->get_result();
        if ($row = $tmp_result->fetch_assoc()) {
            $admin_id = $row['id'];
            $_SESSION['admin_id'] = $admin_id;
        }
        $tmp->close();
    }

    if ($admin_id) {
        $tmp = $conn->prepare("SELECT c.id, c.course_name
                               FROM admin_course_assignments aca
                               JOIN courses c ON aca.course_id = c.id
                               WHERE aca.admin_id = ? AND aca.is_active = 1");
        $tmp->bind_param("i", $admin_id);
        $tmp->execute();
        $tmp_result = $tmp->get_result();
        while ($r = $tmp_result->fetch_assoc()) {
            $admin_courses[]    = $r['course_name'];
            $admin_course_ids[] = $r['id'];
        }
        $tmp->close();
    }
}

// ─── STAT CARDS ───────────────────────────────────────────────────────────────
// Helper: run a COUNT query safely
function runCount($conn, $sql, $types = '', $values = []) {
    if (strpos($sql, '1=0') !== false) return 0; // short-circuit no-results
    $stmt = $conn->prepare($sql);
    if ($stmt === false) return 0;
    if (!empty($values)) {
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return (int)($row[array_key_first($row)] ?? 0);
}

if ($is_course_coordinator) {
    if (!empty($admin_course_ids)) {
        $ph = implode(',', array_fill(0, count($admin_course_ids), '?'));
        $types = str_repeat('i', count($admin_course_ids));

        $total_students_count  = runCount($conn,
            "SELECT COUNT(*) FROM students WHERE course_id IN ($ph) AND batch_id IS NULL AND status != 'rejected'",
            $types, $admin_course_ids);

        $pending_students_count = runCount($conn,
            "SELECT COUNT(*) FROM students WHERE status='pending' AND course_id IN ($ph)",
            $types, $admin_course_ids);

        $active_students_count  = runCount($conn,
            "SELECT COUNT(*) FROM students WHERE status='active' AND batch_id IS NULL AND course_id IN ($ph)",
            $types, $admin_course_ids);
    } else {
        $total_students_count = $pending_students_count = $active_students_count = 0;
    }
} else {
    $total_students_count   = runCount($conn, "SELECT COUNT(*) FROM students");
    $pending_students_count = runCount($conn, "SELECT COUNT(*) FROM students WHERE status='pending'");
    $active_students_count  = runCount($conn, "SELECT COUNT(*) FROM students WHERE status='active'");
}

// ─── COURSES DROPDOWN ─────────────────────────────────────────────────────────
if ($is_course_coordinator && !empty($admin_course_ids)) {
    $course_ids_safe = implode(',', array_map('intval', $admin_course_ids));
    $sql_courses = "SELECT id, course_name, course_description FROM courses WHERE id IN ($course_ids_safe) ORDER BY course_name";
} else {
    $sql_courses = "SELECT id, course_name, course_description FROM courses ORDER BY course_name";
}
$courses_result = $conn->query($sql_courses);

// ─── CHECK batches.created_by column ─────────────────────────────────────────
$col_check = $conn->query("SHOW COLUMNS FROM batches LIKE 'created_by'");
$has_created_by_column = ($col_check && $col_check->num_rows > 0);

// ─── LOAD BATCHES (for assignment modals) ────────────────────────────────────
function loadBatches($conn, $is_course_coordinator, $admin_id, $has_created_by_column) {
    if ($is_course_coordinator && $admin_id && $has_created_by_column) {
        $stmt = $conn->prepare("SELECT b.*, c.course_name
                                FROM batches b
                                LEFT JOIN courses c ON b.course_id = c.id
                                WHERE b.status = 'Active' AND b.created_by = ?
                                ORDER BY b.batch_name");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    return $conn->query("SELECT b.*, c.course_name
                         FROM batches b
                         LEFT JOIN courses c ON b.course_id = c.id
                         WHERE b.status = 'Active'
                         ORDER BY b.batch_name");
}
$batches_result  = loadBatches($conn, $is_course_coordinator, $admin_id, $has_created_by_column);
$batches_result2 = loadBatches($conn, $is_course_coordinator, $admin_id, $has_created_by_column);

// ─── FILTERS ──────────────────────────────────────────────────────────────────
$selected_course  = $_GET['filter_course']  ?? 'All';
$selected_gender  = $_GET['filter_gender']  ?? 'All';
$start_date       = $_GET['start_date']     ?? '';
$end_date         = $_GET['end_date']       ?? '';

// ─── MAIN STUDENTS QUERY ─────────────────────────────────────────────────────
$query = "SELECT s.*, b.batch_name, b.batch_code, c.course_name
          FROM students s
          LEFT JOIN batches b ON s.batch_id = b.id
          LEFT JOIN courses c ON s.course_id = c.id
          WHERE 1=1";

$bind_types  = '';
$bind_values = [];

if ($is_course_coordinator) {
    if (!empty($admin_course_ids)) {
        $ph = implode(',', array_fill(0, count($admin_course_ids), '?'));
        $query      .= " AND s.course_id IN ($ph) AND s.batch_id IS NULL AND s.status != 'rejected'";
        $bind_types  .= str_repeat('i', count($admin_course_ids));
        $bind_values  = array_merge($bind_values, $admin_course_ids);
    } else {
        $query .= " AND 1=0";
    }
}

if ($selected_course !== 'All') {
    $query        .= " AND s.course_id = ?";
    $bind_types   .= 'i';
    $bind_values[] = (int)$selected_course;
}

if ($selected_gender !== 'All') {
    $query        .= " AND s.gender = ?";
    $bind_types   .= 's';
    $bind_values[] = $selected_gender;
}

if (!empty($start_date) && !empty($end_date)) {
    $query        .= " AND s.created_at BETWEEN ? AND ?";
    $bind_types   .= 'ss';
    $bind_values[] = $start_date;
    $bind_values[] = $end_date;
}

$query .= " ORDER BY s.created_at DESC";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Database Error preparing main query: " . $conn->error);
}
if (!empty($bind_values)) {
    $stmt->bind_param($bind_types, ...$bind_values);
}
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$students_result = $stmt->get_result();
if ($students_result === false) {
    die("get_result() failed: " . $stmt->error);
}
$students_result_count = $students_result->num_rows;
$stmt->close();

// ─── FILTER STATS (separate query — FIX: was overwriting $result) ───────────
$stats = ['total' => 0, 'active' => 0, 'male' => 0, 'female' => 0];

$stats_where_parts  = [];
$stats_bind_types   = '';
$stats_bind_values  = [];

if ($is_course_coordinator) {
    if (!empty($admin_course_ids)) {
        $ph = implode(',', array_map('intval', $admin_course_ids)); // safe int list
        $stats_where_parts[] = "course_id IN ($ph)";
        $stats_where_parts[] = "batch_id IS NULL";
        $stats_where_parts[] = "status != 'rejected'";
    } else {
        $stats_where_parts[] = "1=0";
    }
}

if ($selected_course !== 'All') {
    $stats_where_parts[]  = "course_id = " . (int)$selected_course;
}
if ($selected_gender !== 'All') {
    $stats_where_parts[]  = "gender = '" . $conn->real_escape_string($selected_gender) . "'";
}
if (!empty($start_date) && !empty($end_date)) {
    $stats_where_parts[]  = "created_at BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "'";
} elseif (!empty($start_date)) {
    $stats_where_parts[]  = "created_at >= '" . $conn->real_escape_string($start_date) . "'";
} elseif (!empty($end_date)) {
    $stats_where_parts[]  = "created_at <= '" . $conn->real_escape_string($end_date) . "'";
}

$stats_where = count($stats_where_parts) ? implode(' AND ', $stats_where_parts) : '1=1';
$stats_sql   = "SELECT COUNT(*) AS total,
                       SUM(status='active')  AS active,
                       SUM(gender='Male')    AS male,
                       SUM(gender='Female')  AS female
                FROM students
                WHERE $stats_where";

$stats_res = $conn->query($stats_sql);
if ($stats_res) {
    $stats_row = $stats_res->fetch_assoc();
    $stats = [
        'total'  => (int)($stats_row['total']  ?? 0),
        'active' => (int)($stats_row['active'] ?? 0),
        'male'   => (int)($stats_row['male']   ?? 0),
        'female' => (int)($stats_row['female'] ?? 0),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo getThemeFavicon($active_theme); ?>" type="image/x-icon">
    <style>
        .batch-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .batch-modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .batch-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        .batch-modal-header h3 { margin: 0; color: #1e293b; }
        .close-modal {
            font-size: 28px; font-weight: bold; color: #64748b;
            cursor: pointer; background: none; border: none;
            padding: 0; width: 30px; height: 30px;
            display: flex; align-items: center; justify-content: center;
        }
        .close-modal:hover { color: #e74c3c; }
        .batch-info { background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .batch-info p { margin: 5px 0; color: #475569; }
        .batch-info strong { color: #1e293b; }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-users"></i> Manage Students</h4>
                <small>View and manage all registered students</small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role">
                            <?php
                            if ($admin_role === 'master_admin')       echo 'Master Administrator';
                            elseif ($admin_role === 'front_office_desk')  echo 'Front Office Desk';
                            elseif ($admin_role === 'nsqf_course_manager') echo 'NSQF Course Manager';
                            else echo 'Course Coordinator';
                            ?>
                        </span>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="admin-main">

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <h3 class="stat-value"><?php echo $total_students_count; ?></h3>
                    <p class="stat-label"><?php echo $is_course_coordinator ? 'Students Available' : 'Total Students'; ?></p>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <h3 class="stat-value"><?php echo $pending_students_count; ?></h3>
                    <p class="stat-label">Pending Approval</p>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <h3 class="stat-value"><?php echo $active_students_count; ?></h3>
                    <p class="stat-label"><?php echo $is_course_coordinator ? 'Ready for Assignment' : 'Active Students'; ?></p>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-filter"></i> Filter Students</h5>
                </div>

                <form method="GET" action="students.php">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label class="form-label">Filter by Course</label>
                            <select name="filter_course" class="form-select">
                                <?php if ($is_course_coordinator && empty($admin_course_ids)): ?>
                                    <option value="All">No courses assigned</option>
                                <?php else: ?>
                                    <option value="All" <?php if ($selected_course == 'All') echo 'selected'; ?>>
                                        <?php echo $is_course_coordinator ? 'All My Courses' : 'All Courses'; ?>
                                    </option>
                                    <?php
                                    if ($courses_result && $courses_result->num_rows > 0) {
                                        $courses_result->data_seek(0);
                                        while ($course = $courses_result->fetch_assoc()) {
                                            $cid  = (int)$course['id'];
                                            $cname = htmlspecialchars($course['course_name']);
                                            $cdesc = htmlspecialchars($course['course_description'] ?? '');
                                            $display = $cname . ($cdesc ? " ($cdesc)" : '');
                                            $sel = ($selected_course !== 'All' && (int)$selected_course === $cid) ? 'selected' : '';
                                            echo "<option value=\"$cid\" $sel>$display</option>";
                                        }
                                    }
                                    ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Filter by Gender</label>
                            <select name="filter_gender" class="form-select">
                                <option value="All"    <?php if ($selected_gender == 'All')    echo 'selected'; ?>>All Genders</option>
                                <option value="Male"   <?php if ($selected_gender == 'Male')   echo 'selected'; ?>>Male</option>
                                <option value="Female" <?php if ($selected_gender == 'Female') echo 'selected'; ?>>Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>

                <!-- Filter Stats (FIX: uses $stats_* variables, NOT $result) -->
                <div class="course-stats" style="margin-top:1.5rem;">
                    <div class="stats-grid">
                        <div class="stat-card primary">
                            <div style="display:flex;align-items:center;gap:1rem;">
                                <div class="stat-icon"><i class="fas fa-users"></i></div>
                                <div>
                                    <p class="stat-value"><?php echo number_format($stats['total']); ?></p>
                                    <p class="stat-label">Total Registered</p>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card success">
                            <div style="display:flex;align-items:center;gap:1rem;">
                                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                                <div>
                                    <p class="stat-value"><?php echo number_format($stats['active']); ?></p>
                                    <p class="stat-label">Active Students</p>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card warning">
                            <div style="display:flex;align-items:center;gap:1rem;">
                                <div class="stat-icon"><i class="fas fa-mars"></i></div>
                                <div>
                                    <p class="stat-value"><?php echo number_format($stats['male']); ?></p>
                                    <p class="stat-label">Male Students</p>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card danger">
                            <div style="display:flex;align-items:center;gap:1rem;">
                                <div class="stat-icon"><i class="fas fa-venus"></i></div>
                                <div>
                                    <p class="stat-value"><?php echo number_format($stats['female']); ?></p>
                                    <p class="stat-label">Female Students</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Students Table -->
            <?php if ($is_course_coordinator && empty($admin_courses)): ?>
            <div class="content-card">
                <div class="card-body text-center" style="padding:3rem;">
                    <div style="color:#64748b;margin-bottom:1.5rem;">
                        <i class="fas fa-user-tie" style="font-size:4rem;opacity:0.3;"></i>
                    </div>
                    <h4 style="color:#374151;margin-bottom:1rem;">No Course Assignments</h4>
                    <p style="color:#6b7280;margin-bottom:1.5rem;">
                        You haven't been assigned to any courses yet. Please contact the Master Admin.
                    </p>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            <?php else: ?>

            <?php if ($is_course_coordinator): ?>
            <div class="content-card" style="margin-bottom:1.5rem;">
                <div class="card-body" style="background:linear-gradient(135deg,#e3f2fd 0%,#f3e5f5 100%);border-left:4px solid #2196f3;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="color:#1976d2;font-size:1.5rem;"><i class="fas fa-info-circle"></i></div>
                        <div>
                            <h6 style="margin:0;color:#1565c0;font-weight:600;">Course Coordinator View</h6>
                            <p style="margin:4px 0 0 0;color:#424242;font-size:14px;">
                                Showing students from your assigned courses who are <strong>not yet assigned to batches</strong> and <strong>not rejected</strong>.
                                <?php if ($has_created_by_column): ?>
                                    You can only assign students to <strong>batches you created</strong>.
                                <?php else: ?>
                                    <br><strong>Note:</strong> Run the batch ownership migration to enable batch filtering by creator.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-users"></i> All Students
                        <?php if ($is_course_coordinator && !empty($admin_courses)): ?>
                            <small style="color:#64748b;font-weight:normal;">
                                (Showing: <?php echo implode(', ', array_map('htmlspecialchars', $admin_courses)); ?>)
                            </small>
                        <?php endif; ?>
                    </h5>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <span id="selected-count" style="color:#64748b;font-size:14px;display:none;">
                            <i class="fas fa-check-square"></i> <span id="count-number">0</span> selected
                        </span>
                        <button type="button" id="bulk-assign-btn" class="btn btn-primary" style="display:none;">
                            <i class="fas fa-layer-group"></i> Bulk Assign to Batch
                        </button>
                        <a href="export_students_excel.php<?php
                            $ep = [];
                            if ($selected_course !== 'All') $ep[] = 'filter_course=' . urlencode($selected_course);
                            if (!empty($start_date))         $ep[] = 'start_date='    . urlencode($start_date);
                            if (!empty($end_date))           $ep[] = 'end_date='      . urlencode($end_date);
                            echo !empty($ep) ? '?' . implode('&', $ep) : '';
                        ?>" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th style="width:40px;"><input type="checkbox" id="select-all" title="Select All"></th>
                                <th>Sl. No.</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Course</th>
                                <th>Batch</th>
                                <th>Status</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $sl_no = 1;
                        if ($students_result && $students_result_count > 0):
                            while ($row = $students_result->fetch_assoc()):
                                $status     = strtolower($row['status']);
                                $badge_map  = ['active'=>'badge-success','pending'=>'badge-warning','rejected'=>'badge-danger','inactive'=>'badge-secondary'];
                                $badge_cls  = $badge_map[$status] ?? 'badge-secondary';
                                $course_display = htmlspecialchars(!empty($row['course_name']) ? $row['course_name'] : $row['course']);
                        ?>
                        <tr>
                            <td>
                                <?php if (empty($row['batch_name'])): ?>
                                    <input type="checkbox" class="student-checkbox"
                                           value="<?php echo htmlspecialchars($row['student_id']); ?>"
                                           data-course="<?php echo $course_display; ?>">
                                <?php else: ?>
                                    <span style="color:#cbd5e1;" title="Already in a batch"><i class="fas fa-check-circle"></i></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $sl_no++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['student_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                            <td><span class="badge badge-primary"><?php echo $course_display; ?></span></td>
                            <td>
                                <?php if (!empty($row['batch_name'])): ?>
                                    <span class="badge badge-success" title="<?php echo htmlspecialchars($row['batch_code']); ?>">
                                        <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($row['batch_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><i class="fas fa-minus-circle"></i> Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_cls; ?>"><?php echo ucfirst($row['status']); ?></span>
                                <?php if ($status === 'rejected' && !empty($row['rejection_reason'])): ?>
                                    <br><small style="color:#dc2626;font-size:11px;" title="<?php echo htmlspecialchars($row['rejection_note'] ?? ''); ?>">
                                        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($row['rejection_reason']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <?php
                                // Build filter param suffix for action URLs
                                $fp = [];
                                if ($selected_course !== 'All') $fp[] = 'filter_course=' . urlencode($selected_course);
                                if (!empty($start_date))         $fp[] = 'start_date='    . urlencode($start_date);
                                if (!empty($end_date))           $fp[] = 'end_date='      . urlencode($end_date);
                                $filter_suffix = !empty($fp) ? '&' . implode('&', $fp) : '';
                                ?>

                                <?php if (!$is_front_office): ?>
                                    <?php if ($status === 'pending'): ?>
                                        <a href="javascript:void(0);"
                                           class="btn btn-success btn-sm approve-student-btn"
                                           title="Approve"
                                           data-student-id="<?php echo htmlspecialchars($row['student_id']); ?>"
                                           data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                           data-url="students.php?approve_id=<?php echo urlencode($row['student_id']); ?><?php echo $filter_suffix; ?>">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                        <a href="javascript:void(0);"
                                           class="btn btn-danger btn-sm reject-student-btn"
                                           title="Reject"
                                           data-student-id="<?php echo htmlspecialchars($row['student_id']); ?>"
                                           data-student-name="<?php echo htmlspecialchars($row['name']); ?>">
                                            <i class="fas fa-times"></i> Reject
                                        </a>
                                    <?php else: ?>
                                        <a href="edit_student.php?id=<?php echo urlencode($row['student_id']); ?><?php echo $filter_suffix; ?>"
                                           class="btn btn-warning btn-sm" title="Edit Student">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($row['batch_name'])): ?>
                                        <a href="javascript:void(0);"
                                           class="btn btn-secondary btn-sm remove-batch-btn"
                                           title="Remove from Batch"
                                           data-student-id="<?php echo htmlspecialchars($row['student_id']); ?>"
                                           data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                           data-batch-name="<?php echo htmlspecialchars($row['batch_name']); ?>"
                                           data-url="students.php?remove_batch=<?php echo urlencode($row['student_id']); ?><?php echo $filter_suffix; ?>">
                                            <i class="fas fa-unlink"></i>
                                        </a>
                                    <?php else: ?>
                                        <button type="button"
                                                class="btn btn-info btn-sm assign-batch-btn"
                                                title="Assign to Batch"
                                                data-student-id="<?php echo htmlspecialchars($row['student_id']); ?>"
                                                data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                data-course="<?php echo $course_display; ?>">
                                            <i class="fas fa-plus-circle"></i> Assign Batch
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Front Office: edit only -->
                                    <a href="edit_student.php?id=<?php echo urlencode($row['student_id']); ?>"
                                       class="btn btn-warning btn-sm" title="Edit Student">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="view_student_documents.php?id=<?php echo urlencode($row['student_id']); ?><?php echo $filter_suffix; ?>"
                                   class="btn btn-info btn-sm" title="View Documents">
                                    <i class="fas fa-folder-open"></i>
                                </a>
                                <a href="download_student_form.php?id=<?php echo urlencode($row['student_id']); ?>"
                                   class="btn btn-success btn-sm" title="Download Form" target="_blank">
                                    <i class="fas fa-download"></i>
                                </a>

                                <?php if (!$is_front_office): ?>
                                <a href="javascript:void(0);"
                                   class="btn btn-danger btn-sm delete-student-btn"
                                   title="Delete Student"
                                   data-student-id="<?php echo htmlspecialchars($row['student_id']); ?>"
                                   data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                   data-url="students.php?delete_id=<?php echo urlencode($row['student_id']); ?><?php echo $filter_suffix; ?>">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="11" style="padding:2.5rem;text-align:center;color:var(--text-muted);">
                                <strong>No students found for the selected filters.</strong>
                            </td>
                        </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Batch Assignment Modal (single) -->
<div id="batchModal" class="batch-modal">
    <div class="batch-modal-content">
        <div class="batch-modal-header">
            <h3><i class="fas fa-layer-group"></i> Assign Student to Batch</h3>
            <button class="close-modal" onclick="closeBatchModal()">&times;</button>
        </div>
        <div class="batch-info">
            <p><strong>Student:</strong> <span id="modal-student-name"></span></p>
            <p><strong>Course:</strong> <span id="modal-course"></span></p>
        </div>
        <form method="POST" action="students.php">
            <input type="hidden" name="student_id" id="modal-student-id">
            <input type="hidden" name="filter_course" value="<?php echo htmlspecialchars($selected_course); ?>">
            <input type="hidden" name="start_date"    value="<?php echo htmlspecialchars($start_date); ?>">
            <input type="hidden" name="end_date"      value="<?php echo htmlspecialchars($end_date); ?>">
            <div class="form-group">
                <label class="form-label">Select Batch</label>
                <select name="batch_id" id="modal-batch-select" class="form-control" required>
                    <option value="">-- Select a Batch --</option>
                    <?php if ($batches_result && $batches_result->num_rows > 0): ?>
                        <?php while ($batch = $batches_result->fetch_assoc()): ?>
                        <option value="<?php echo $batch['id']; ?>"
                                data-course="<?php echo htmlspecialchars($batch['course_name']); ?>">
                            <?php echo htmlspecialchars($batch['batch_name']); ?>
                            (<?php echo htmlspecialchars($batch['batch_code']); ?>)
                        </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" name="assign_batch" class="btn btn-primary" style="flex:1;">
                    <i class="fas fa-check"></i> Assign to Batch
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeBatchModal()" style="flex:1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Batch Assignment Modal -->
<div id="bulkBatchModal" class="batch-modal">
    <div class="batch-modal-content">
        <div class="batch-modal-header">
            <h3><i class="fas fa-layer-group"></i> Bulk Assign Students to Batch</h3>
            <button class="close-modal" onclick="closeBulkBatchModal()">&times;</button>
        </div>
        <div class="batch-info">
            <p><strong>Selected Students:</strong> <span id="bulk-modal-count">0</span></p>
            <p style="font-size:12px;color:#64748b;margin-top:5px;">
                <i class="fas fa-info-circle"></i> All selected students will be assigned to the same batch
            </p>
        </div>
        <form method="POST" action="students.php" id="bulk-assign-form">
            <input type="hidden" name="filter_course" value="<?php echo htmlspecialchars($selected_course); ?>">
            <input type="hidden" name="start_date"    value="<?php echo htmlspecialchars($start_date); ?>">
            <input type="hidden" name="end_date"      value="<?php echo htmlspecialchars($end_date); ?>">
            <div id="bulk-student-ids"></div>
            <div class="form-group">
                <label class="form-label">Select Batch</label>
                <select name="batch_id" id="bulk-modal-batch-select" class="form-control" required>
                    <option value="">-- Select a Batch --</option>
                    <?php if ($batches_result2 && $batches_result2->num_rows > 0): ?>
                        <?php while ($batch = $batches_result2->fetch_assoc()): ?>
                        <option value="<?php echo $batch['id']; ?>"
                                data-course="<?php echo htmlspecialchars($batch['course_name']); ?>">
                            <?php echo htmlspecialchars($batch['batch_name']); ?>
                            (<?php echo htmlspecialchars($batch['batch_code']); ?>) -
                            <?php echo htmlspecialchars($batch['course_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
                <small style="color:#64748b;margin-top:5px;display:block;">
                    <i class="fas fa-lightbulb"></i> Batches are filtered by selected students' courses
                </small>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" name="bulk_assign_batch" class="btn btn-primary" style="flex:1;">
                    <i class="fas fa-check"></i> Assign All to Batch
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeBulkBatchModal()" style="flex:1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Rejection Reason Modal -->
<div id="rejectModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;justify-content:center;align-items:center;">
    <div style="background:white;border-radius:12px;padding:32px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,0.2);">
        <div style="text-align:center;margin-bottom:20px;">
            <div style="width:64px;height:64px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i class="fas fa-times-circle" style="font-size:32px;color:#dc2626;"></i>
            </div>
            <h3 style="margin:0 0 6px;font-size:20px;color:#1e293b;">Reject Student</h3>
            <p style="margin:0;color:#64748b;font-size:14px;">Rejecting: <strong id="rejectStudentName"></strong></p>
        </div>
        <form method="POST" action="students.php" id="rejectForm">
            <input type="hidden" name="reject_student" value="1">
            <input type="hidden" name="reject_id" id="rejectStudentId">
            <input type="hidden" name="filter_course" value="<?php echo htmlspecialchars($selected_course ?? ''); ?>">
            <div style="margin-bottom:16px;">
                <label style="display:block;font-weight:600;margin-bottom:8px;color:#374151;">Reason for Rejection *</label>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php
                    $reasons = [
                        'Not Eligible'                    => 'Not eligible for the course',
                        'Incomplete Documents'            => 'Incomplete or missing documents',
                        'Invalid Documents'               => 'Invalid or forged documents',
                        'Age Criteria Not Met'            => 'Age criteria not met',
                        'Educational Qualification Not Met' => 'Educational qualification not met',
                        'Duplicate Registration'          => 'Duplicate registration',
                        'Other'                           => 'Other reason',
                    ];
                    foreach ($reasons as $value => $label):
                    ?>
                    <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer;" class="reason-label">
                        <input type="radio" name="rejection_reason" value="<?php echo htmlspecialchars($value); ?>" required onchange="highlightReason(this);">
                        <span style="font-size:14px;color:#374151;"><?php echo htmlspecialchars($label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div id="otherNoteDiv" style="display:none;margin-bottom:16px;">
                <label style="display:block;font-weight:600;margin-bottom:6px;color:#374151;">Additional Note</label>
                <textarea name="rejection_note" rows="2" placeholder="Specify the reason..."
                          style="width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px;resize:vertical;box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                <button type="button" onclick="closeRejectModal()"
                        style="padding:10px 24px;border:none;border-radius:8px;background:#6b7280;color:white;font-size:14px;cursor:pointer;">
                    Cancel
                </button>
                <button type="submit"
                        style="padding:10px 24px;border:none;border-radius:8px;background:#dc2626;color:white;font-size:14px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-times"></i> Confirm Reject
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
// ── Toast on page load (from session) ────────────────────────────────────────
<?php if (isset($_SESSION['message'])): ?>
document.addEventListener('DOMContentLoaded', function () {
    const type = '<?php echo addslashes($_SESSION['message_type'] ?? 'success'); ?>';
    const msg  = '<?php echo addslashes($_SESSION['message']); ?>';
    const toastType = type === 'danger' ? 'error' : type;
    toast[toastType](msg);
});
<?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>

// ── Batch modal (single) ──────────────────────────────────────────────────────
function openBatchModal(studentId, studentName, course) {
    document.getElementById('modal-student-id').value   = studentId;
    document.getElementById('modal-student-name').textContent = studentName;
    document.getElementById('modal-course').textContent       = course;

    const norm    = course.trim().toLowerCase();
    const select  = document.getElementById('modal-batch-select');
    let   matched = 0;

    select.querySelectorAll('option').forEach(opt => {
        if (!opt.value) { opt.style.display = ''; return; }
        const optCourse = (opt.dataset.course || '').trim().toLowerCase();
        opt.style.display = (optCourse === norm) ? '' : 'none';
        if (optCourse === norm) matched++;
    });

    select.value = '';
    document.getElementById('batchModal').style.display = 'block';
}
function closeBatchModal() {
    document.getElementById('batchModal').style.display = 'none';
}

// ── Bulk batch modal ──────────────────────────────────────────────────────────
function openBulkBatchModal() {
    const checked = document.querySelectorAll('.student-checkbox:checked');
    if (!checked.length) { toast.warning('Please select at least one student'); return; }

    document.getElementById('bulk-modal-count').textContent = checked.length;

    const container = document.getElementById('bulk-student-ids');
    container.innerHTML = '';
    const courses = new Set();

    checked.forEach(cb => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'student_ids[]'; inp.value = cb.value;
        container.appendChild(inp);
        const c = (cb.dataset.course || '').trim().toLowerCase();
        if (c) courses.add(c);
    });

    document.getElementById('bulk-modal-batch-select').querySelectorAll('option').forEach(opt => {
        if (!opt.value) { opt.style.display = ''; return; }
        const oc = (opt.dataset.course || '').trim().toLowerCase();
        opt.style.display = courses.has(oc) ? '' : 'none';
    });

    document.getElementById('bulk-modal-batch-select').value = '';
    document.getElementById('bulkBatchModal').style.display = 'block';
}
function closeBulkBatchModal() {
    document.getElementById('bulkBatchModal').style.display = 'none';
}

// ── Rejection modal ───────────────────────────────────────────────────────────
function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}
function highlightReason(radio) {
    document.querySelectorAll('#rejectModal .reason-label').forEach(l => {
        l.style.borderColor = '#e5e7eb'; l.style.background = 'white';
    });
    radio.parentElement.style.borderColor = '#dc2626';
    radio.parentElement.style.background  = '#fff5f5';
    document.getElementById('otherNoteDiv').style.display = radio.value === 'Other' ? 'block' : 'none';
}

// ── Selection UI update ───────────────────────────────────────────────────────
function updateSelectionUI() {
    const count = document.querySelectorAll('.student-checkbox:checked').length;
    document.getElementById('selected-count').style.display = count ? 'inline' : 'none';
    document.getElementById('bulk-assign-btn').style.display = count ? 'inline-block' : 'none';
    document.getElementById('count-number').textContent = count;
}

// ── DOMContentLoaded wiring ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    // Assign batch buttons
    document.querySelectorAll('.assign-batch-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            openBatchModal(
                this.dataset.studentId,
                this.dataset.studentName,
                this.dataset.course
            );
        });
    });

    // Remove batch buttons
    document.querySelectorAll('.remove-batch-btn').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const confirmed = await showConfirm({
                title: 'Remove from Batch',
                message: `Remove <strong>${this.dataset.studentName}</strong> from <strong>${this.dataset.batchName}</strong>?`,
                confirmText: 'Remove', cancelText: 'Cancel', type: 'warning'
            });
            if (confirmed) { toast.loading('Removing…'); window.location.href = this.dataset.url; }
        });
    });

    // Delete buttons
    document.querySelectorAll('.delete-student-btn').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const confirmed = await showConfirm({
                title: 'Delete Student',
                message: `Delete <strong>${this.dataset.studentName}</strong> (${this.dataset.studentId})? This cannot be undone.`,
                confirmText: 'Delete', cancelText: 'Cancel', type: 'danger'
            });
            if (confirmed) { toast.loading('Deleting…'); window.location.href = this.dataset.url; }
        });
    });

    // Approve buttons
    document.querySelectorAll('.approve-student-btn').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const confirmed = await showConfirm({
                title: 'Approve Student',
                message: `Approve <strong>${this.dataset.studentName}</strong> (${this.dataset.studentId})?`,
                confirmText: 'Approve', cancelText: 'Cancel', type: 'warning'
            });
            if (confirmed) { toast.loading('Approving…'); window.location.href = this.dataset.url; }
        });
    });

    // Reject buttons → open reason modal
    document.querySelectorAll('.reject-student-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('rejectStudentName').textContent = this.dataset.studentName;
            document.getElementById('rejectStudentId').value         = this.dataset.studentId;
            document.querySelectorAll('#rejectModal input[type="radio"]').forEach(r => r.checked = false);
            document.querySelectorAll('#rejectModal .reason-label').forEach(l => {
                l.style.borderColor = '#e5e7eb'; l.style.background = 'white';
            });
            document.getElementById('otherNoteDiv').style.display = 'none';
            document.getElementById('rejectModal').style.display  = 'flex';
        });
    });

    // Reject modal backdrop
    document.getElementById('rejectModal').addEventListener('click', function (e) {
        if (e.target === this) closeRejectModal();
    });

    // Select-all checkbox
    const selectAll = document.getElementById('select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked);
            updateSelectionUI();
        });
    }

    // Individual checkboxes
    document.querySelectorAll('.student-checkbox').forEach(cb => {
        cb.addEventListener('change', function () {
            updateSelectionUI();
            const all     = document.querySelectorAll('.student-checkbox');
            const checked = document.querySelectorAll('.student-checkbox:checked');
            if (selectAll) selectAll.checked = all.length > 0 && all.length === checked.length;
        });
    });

    // Bulk assign button
    const bulkBtn = document.getElementById('bulk-assign-btn');
    if (bulkBtn) bulkBtn.addEventListener('click', openBulkBatchModal);

    // Close modals on outside click
    window.addEventListener('click', function (e) {
        if (e.target === document.getElementById('batchModal'))     closeBatchModal();
        if (e.target === document.getElementById('bulkBatchModal')) closeBulkBatchModal();
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>