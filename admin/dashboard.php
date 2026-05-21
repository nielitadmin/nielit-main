<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/session_manager.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");
    exit();
}

// Initialize session if role is missing (for backward compatibility)
if (!isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id'])) {
    if (!init_admin_session($_SESSION['admin'])) {
        // Session initialization failed, redirect to login
        session_unset();
        session_destroy();
        header("Location: login_new.php");
        exit();
    }
}

// Always refresh role from DB to pick up any role changes made by master admin
refresh_session_permissions();

// Front Office Desk should go directly to students page - no dashboard access needed
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'front_office_desk') {
    header("Location: students.php");
    exit();
}

// Load active theme
$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);

// Get admin's assigned courses for filtering (used throughout the page)
$admin_courses = [];
$admin_course_ids = [];
$is_course_coordinator = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'course_coordinator';

if ($is_course_coordinator) {
    // Get admin_id from session or fetch from database
    $admin_id = $_SESSION['admin_id'] ?? null;
    
    // If admin_id not in session, fetch it from database using username
    if (!$admin_id && isset($_SESSION['admin'])) {
        $admin_username = $_SESSION['admin'];
        $admin_query = "SELECT id FROM admin WHERE username = ?";
        $admin_stmt = $conn->prepare($admin_query);
        $admin_stmt->bind_param("s", $admin_username);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        if ($admin_row = $admin_result->fetch_assoc()) {
            $admin_id = $admin_row['id'];
            $_SESSION['admin_id'] = $admin_id; // Store for future use
        }
    }
    
    // Get assigned courses for this coordinator
    if ($admin_id) {
        $course_query = "SELECT c.id, c.course_name 
                        FROM admin_course_assignments aca
                        JOIN courses c ON aca.course_id = c.id
                        WHERE aca.admin_id = ? AND aca.is_active = 1";
        $course_stmt = $conn->prepare($course_query);
        $course_stmt->bind_param("i", $admin_id);
        $course_stmt->execute();
        $course_result = $course_stmt->get_result();
        while ($course_row = $course_result->fetch_assoc()) {
            $admin_courses[] = $course_row['course_name'];
            $admin_course_ids[] = (int)$course_row['id'];
        }
    }
}

// Get filter parameter
$filter_category = $_GET['category'] ?? 'all';
// Get courses tab (all, ongoing, upcoming, past)
$courses_tab = $_GET['courses_tab'] ?? 'all';
$valid_tabs = ['all','ongoing','upcoming','past'];
if (!in_array($courses_tab, $valid_tabs)) $courses_tab = 'all';

// Build query with filter and student count
$sql = "SELECT courses.*, 
    (SELECT COUNT(*) FROM students WHERE students.course_id = courses.id) as student_count 
        FROM courses WHERE 1=1";

// Add course coordinator filtering
if ($is_course_coordinator) {
    if (!empty($admin_course_ids)) {
        // Coordinator has assigned courses - show only those courses
        $placeholders = str_repeat('?,', count($admin_course_ids) - 1) . '?';
        $sql .= " AND courses.id IN ($placeholders)";
    } else {
        // Coordinator has no assigned courses - show no courses
        $sql .= " AND 1=0"; // This makes the query return no results
    }
} elseif (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'nsqf_course_manager') {
# NSQF Course Manager sees only NSQF courses
    $sql .= " AND courses.is_nsqf = 1";
}

// Add category filter
if ($filter_category !== 'all') {
    $sql .= " AND category = ?";
}

// Add courses tab filter (date based)
if ($courses_tab !== 'all') {
    if ($courses_tab === 'ongoing') {
        $sql .= " AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)";
    } elseif ($courses_tab === 'upcoming') {
        $sql .= " AND start_date > ?";
    } elseif ($courses_tab === 'past') {
        $sql .= " AND end_date < ?";
    }
}

$sql .= " ORDER BY id DESC";

// Execute query with filters
$bind_types = '';
$bind_values = [];

// Add admin courses if coordinator (only if they have assigned courses)
if ($is_course_coordinator && !empty($admin_course_ids)) {
    $bind_types .= str_repeat('i', count($admin_course_ids));
    $bind_values = array_merge($bind_values, $admin_course_ids);
}

// Add category filter
if ($filter_category !== 'all') {
    $bind_types .= 's';
    $bind_values[] = $filter_category;
}

// Add courses tab bind values (date based)
if ($courses_tab !== 'all') {
    $today = date('Y-m-d');
    if ($courses_tab === 'ongoing') {
        $bind_types .= 'ss';
        $bind_values[] = $today;
        $bind_values[] = $today;
    } elseif ($courses_tab === 'upcoming') {
        $bind_types .= 's';
        $bind_values[] = $today;
    } elseif ($courses_tab === 'past') {
        $bind_types .= 's';
        $bind_values[] = $today;
    }
}

// Bind parameters if any
if (!empty($bind_values)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($bind_types, ...$bind_values);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Check if query failed
if (!$result) {
    die("Database query failed: " . $conn->error);
}

// Delete course
if (isset($_GET['delete_id'])) {
    // Prevent NSQF managers from deleting courses
    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'nsqf_course_manager') {
        $_SESSION['message'] = "Access denied. NSQF Course Managers cannot delete courses.";
        $_SESSION['message_type'] = "danger";
        header("Location: dashboard.php");
        exit();
    }
    
    $delete_id = $_GET['delete_id'];
    $delete_sql = "DELETE FROM courses WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Course deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting course: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    header("Location: dashboard.php");
    exit();
}

// Add course
if (isset($_POST['add_course'])) {
    // Prevent NSQF managers from adding courses
    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'nsqf_course_manager') {
        $_SESSION['message'] = "Access denied. NSQF Course Managers cannot add courses directly. Please use Course Templates.";
        $_SESSION['message_type'] = "danger";
        header("Location: dashboard.php");
        exit();
    }
    
    $course_name = $_POST['course_name'];
    $course_code = strtoupper($_POST['course_code'] ?? '');
    $course_abbreviation = strtoupper($_POST['course_abbreviation'] ?? '');
    $eligibility = $_POST['eligibility'];
    $duration = $_POST['duration'];
    $training_fees = $_POST['training_fees'];
    $category = $_POST['category'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $description_url = $_POST['description_url'];
    $course_description = trim($_POST['course_description'] ?? '');
    $apply_link = $_POST['apply_link'];
    $course_coordinator = $_POST['course_coordinator'];
    $training_center = $_POST['training_center'] ?? (!empty($centres) ? $centres[0]['name'] : 'NIELIT BHUBANESWAR');
    $link_published = isset($_POST['link_published']) ? 1 : 0;
    $nsqf_type = $_POST['nsqf_type'] ?? 'NON-NSQF Course';
    $is_nsqf = ($nsqf_type === 'NSQF Course') ? 1 : 0;
    $description_pdf = '';

    if (isset($_FILES['description_pdf']) && $_FILES['description_pdf']['error'] == 0) {
        $pdf_file = $_FILES['description_pdf'];
        if ($pdf_file['type'] == 'application/pdf') {
            $pdf_path = '../course_pdf/' . uniqid('course_', true) . '.pdf';
            if (move_uploaded_file($pdf_file['tmp_name'], $pdf_path)) {
                $description_pdf = $pdf_path;
            }
        }
    }

    // Auto-add course_description column if missing
    $conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS course_description TEXT DEFAULT NULL");

    $insert_sql = "INSERT INTO courses (
        course_name, course_code, course_abbreviation, eligibility, duration, training_fees, category,
        start_date, end_date, description_url, description_pdf, apply_link, course_coordinator,
        training_center, is_nsqf, link_published, course_description
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_sql);
    // 17 variables: 14 strings, then i (is_nsqf), i (link_published), s (course_description)
    $stmt->bind_param("ssssssssssssssiis", 
        $course_name, $course_code, $course_abbreviation, $eligibility, $duration, $training_fees, $category,
        $start_date, $end_date, $description_url, $description_pdf, $apply_link, $course_coordinator,
        $training_center, $is_nsqf, $link_published, $course_description
    );

    if ($stmt->execute()) {
        $course_id = $conn->insert_id;
        
        // Auto-assign course to course coordinator who created it
        if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'course_coordinator' && isset($_SESSION['admin_id'])) {
            $admin_id = $_SESSION['admin_id'];
            $assigned_by = $_SESSION['admin_id']; // Self-assigned
            
            $assign_stmt = $conn->prepare("INSERT INTO admin_course_assignments (admin_id, course_id, is_active, assigned_by, assignment_type) VALUES (?, ?, 1, ?, 'Auto-Assigned')");
            $assign_stmt->bind_param("iii", $admin_id, $course_id, $assigned_by);
            $assign_stmt->execute();
            $assign_stmt->close();
        }
        
        // Handle scheme associations for new course
        if (isset($_POST['schemes']) && !empty($_POST['schemes'])) {
            $insert_scheme_sql = "INSERT INTO course_schemes (course_id, scheme_id) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($insert_scheme_sql);
            
            foreach ($_POST['schemes'] as $scheme_id) {
                $stmt_insert->bind_param("ii", $course_id, $scheme_id);
                $stmt_insert->execute();
            }
        }
        
        // Auto-generate QR code if registration link exists
        if (!empty($apply_link)) {
            require_once __DIR__ . '/../includes/qr_helper.php';
            $qr_result = generateCourseQRCode($course_id, $course_code);
            
            if ($qr_result['success']) {
                // Update course with QR path
                $stmt_update = $conn->prepare("UPDATE courses SET qr_code_path = ?, qr_generated_at = NOW() WHERE id = ?");
                $stmt_update->bind_param("si", $qr_result['path'], $course_id);
                $stmt_update->execute();
                
                $_SESSION['message'] = "Course added successfully! Registration link and QR code generated. Course automatically assigned to you.";
            } else {
                $_SESSION['message'] = "Course added successfully! But QR code generation failed. Course automatically assigned to you.";
            }
        } else {
            $_SESSION['message'] = "Course added successfully! Generate registration link to create QR code. Course automatically assigned to you.";
        }
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error adding course: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }

    header("Location: dashboard.php");
    exit();
}

// Get statistics
// Total courses (filtered for coordinators and NSQF managers)
if ($is_course_coordinator) {
    if (!empty($admin_course_ids)) {
        $placeholders = str_repeat('?,', count($admin_course_ids) - 1) . '?';
        $stats_sql = "SELECT COUNT(*) as count FROM courses WHERE id IN ($placeholders)";
        $stats_stmt = $conn->prepare($stats_sql);
        $stats_stmt->bind_param(str_repeat('i', count($admin_course_ids)), ...$admin_course_ids);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $total_courses = $stats_result ? $stats_result->fetch_assoc()['count'] : 0;
    } else {
        // Coordinator has no assigned courses - return 0
        $total_courses = 0;
    }
} elseif (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'nsqf_course_manager') {
    // NSQF Course Manager sees only NSQF courses count
    $stats_query = $conn->query("SELECT COUNT(*) as count FROM courses WHERE is_nsqf = 1");
    $total_courses = $stats_query ? $stats_query->fetch_assoc()['count'] : 0;
} else {
    $stats_query = $conn->query("SELECT COUNT(*) as count FROM courses");
    $total_courses = $stats_query ? $stats_query->fetch_assoc()['count'] : 0;
}

// Compute course counts per tab (ongoing / upcoming / past)
$count_all = $total_courses;
$count_ongoing = $count_upcoming = $count_past = 0;
$today = date('Y-m-d');

if ($is_course_coordinator) {
    if (!empty($admin_course_ids)) {
        $placeholders = implode(',', array_fill(0, count($admin_course_ids), '?'));
        // Ongoing
        $sql_o = "SELECT COUNT(*) as c FROM courses WHERE id IN ($placeholders) AND start_date <= ? AND (end_date IS NULL OR end_date >= ? )";
        $stmt_o = $conn->prepare($sql_o);
        $types = str_repeat('i', count($admin_course_ids)) . 'ss';
        $stmt_o->bind_param($types, ...array_merge($admin_course_ids, [$today, $today]));
        $stmt_o->execute(); $res_o = $stmt_o->get_result(); $count_ongoing = $res_o ? (int)$res_o->fetch_assoc()['c'] : 0; $stmt_o->close();

        // Upcoming
        $sql_u = "SELECT COUNT(*) as c FROM courses WHERE id IN ($placeholders) AND start_date > ?";
        $stmt_u = $conn->prepare($sql_u);
        $types = str_repeat('i', count($admin_course_ids)) . 's';
        $stmt_u->bind_param($types, ...array_merge($admin_course_ids, [$today]));
        $stmt_u->execute(); $res_u = $stmt_u->get_result(); $count_upcoming = $res_u ? (int)$res_u->fetch_assoc()['c'] : 0; $stmt_u->close();

        // Past
        $sql_p = "SELECT COUNT(*) as c FROM courses WHERE id IN ($placeholders) AND end_date < ?";
        $stmt_p = $conn->prepare($sql_p);
        $types = str_repeat('i', count($admin_course_ids)) . 's';
        $stmt_p->bind_param($types, ...array_merge($admin_course_ids, [$today]));
        $stmt_p->execute(); $res_p = $stmt_p->get_result(); $count_past = $res_p ? (int)$res_p->fetch_assoc()['c'] : 0; $stmt_p->close();
    }
} elseif (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'nsqf_course_manager') {
    // NSQF manager - include NSQF filter
    $base_where = " WHERE is_nsqf = 1";
    $r = $conn->query("SELECT COUNT(*) as c FROM courses $base_where"); $count_all = $r ? (int)$r->fetch_assoc()['c'] : 0;
    $r = $conn->prepare("SELECT COUNT(*) as c FROM courses $base_where AND start_date <= ? AND (end_date IS NULL OR end_date >= ?) "); $r->bind_param('ss',$today,$today); $r->execute(); $res=$r->get_result(); $count_ongoing = $res ? (int)$res->fetch_assoc()['c'] : 0; $r->close();
    $r = $conn->prepare("SELECT COUNT(*) as c FROM courses $base_where AND start_date > ?"); $r->bind_param('s',$today); $r->execute(); $res=$r->get_result(); $count_upcoming = $res ? (int)$res->fetch_assoc()['c'] : 0; $r->close();
    $r = $conn->prepare("SELECT COUNT(*) as c FROM courses $base_where AND end_date < ?"); $r->bind_param('s',$today); $r->execute(); $res=$r->get_result(); $count_past = $res ? (int)$res->fetch_assoc()['c'] : 0; $r->close();
} else {
    // Public / master admin
    $r = $conn->query("SELECT COUNT(*) as c FROM courses"); $count_all = $r ? (int)$r->fetch_assoc()['c'] : 0;
    $r = $conn->prepare("SELECT COUNT(*) as c FROM courses WHERE start_date <= ? AND (end_date IS NULL OR end_date >= ?) "); $r->bind_param('ss',$today,$today); $r->execute(); $res=$r->get_result(); $count_ongoing = $res ? (int)$res->fetch_assoc()['c'] : 0; $r->close();
    $r = $conn->prepare("SELECT COUNT(*) as c FROM courses WHERE start_date > ?"); $r->bind_param('s',$today); $r->execute(); $res=$r->get_result(); $count_upcoming = $res ? (int)$res->fetch_assoc()['c'] : 0; $r->close();
    $r = $conn->prepare("SELECT COUNT(*) as c FROM courses WHERE end_date < ?"); $r->bind_param('s',$today); $r->execute(); $res=$r->get_result(); $count_past = $res ? (int)$res->fetch_assoc()['c'] : 0; $r->close();
}

// Total students (filtered for coordinators)
if ($is_course_coordinator) {
    if (!empty($admin_course_ids)) {
        $placeholders = str_repeat('?,', count($admin_course_ids) - 1) . '?';
        $stats_sql = "SELECT COUNT(*) as count FROM students WHERE course_id IN ($placeholders)";
        $stats_stmt = $conn->prepare($stats_sql);
        $stats_stmt->bind_param(str_repeat('i', count($admin_course_ids)), ...$admin_course_ids);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $total_students = $stats_result ? $stats_result->fetch_assoc()['count'] : 0;
    } else {
        // Coordinator has no assigned courses - return 0
        $total_students = 0;
    }
} else {
    $stats_query = $conn->query("SELECT COUNT(*) as count FROM students");
    $total_students = $stats_query ? $stats_query->fetch_assoc()['count'] : 0;
}

// System Enhancement Module statistics
$stats_query = $conn->query("SELECT COUNT(*) as count FROM centres WHERE is_active = 1");
$total_centres = $stats_query ? $stats_query->fetch_assoc()['count'] : 0;

// Fetch active centres for dropdown
$centres_query = "SELECT id, name, code FROM centres WHERE is_active = 1 ORDER BY name ASC";
$centres_result = $conn->query($centres_query);
$centres = [];
if ($centres_result) {
    while ($centre = $centres_result->fetch_assoc()) {
        $centres[] = $centre;
    }
}

$stats_query = $conn->query("SELECT theme_name FROM themes WHERE is_active = 1 LIMIT 1");
$active_theme_name = $stats_query && $stats_query->num_rows > 0 ? $stats_query->fetch_assoc()['theme_name'] : 'Default Theme';

$stats_query = $conn->query("SELECT COUNT(*) as count FROM homepage_content WHERE is_active = 1");
$total_homepage_sections = $stats_query ? $stats_query->fetch_assoc()['count'] : 0;

$notification_count = 0;
$notification_query = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE is_active = 1");
if ($notification_query) {
    $notification_count = (int) $notification_query->fetch_assoc()['count'];
}

$course_distribution_rows = [];
$course_distribution_query = $conn->query("SELECT COALESCE(category, 'Uncategorized') AS label, COUNT(*) AS total FROM courses GROUP BY COALESCE(category, 'Uncategorized') ORDER BY total DESC LIMIT 8");
if ($course_distribution_query) {
    while ($row = $course_distribution_query->fetch_assoc()) {
        $course_distribution_rows[] = $row;
    }
}

$student_growth_rows = [];
$student_growth_query = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total FROM students WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01') GROUP BY month_key ORDER BY month_key ASC");
if ($student_growth_query) {
    while ($row = $student_growth_query->fetch_assoc()) {
        $student_growth_rows[] = $row;
    }
}

$batch_performance_rows = [];
$batch_performance_query = $conn->query("SELECT b.batch_name, b.seats_total, b.seats_filled, COALESCE(ROUND(AVG(COALESCE(bs.attendance_percentage, 0)), 1), 0) AS avg_attendance FROM batches b LEFT JOIN batch_students bs ON bs.batch_id = b.id GROUP BY b.id ORDER BY b.updated_at DESC LIMIT 5");
if ($batch_performance_query) {
    while ($row = $batch_performance_query->fetch_assoc()) {
        $batch_performance_rows[] = $row;
    }
}

$recent_courses_rows = [];
$recent_courses_query = $conn->query("SELECT c.id, c.course_name, c.category, c.duration, c.start_date, c.course_code, c.course_abbreviation, COALESCE((SELECT COUNT(*) FROM students s WHERE s.course_id = c.id), 0) AS student_count FROM courses c ORDER BY c.created_at DESC LIMIT 5");
if ($recent_courses_query) {
    while ($row = $recent_courses_query->fetch_assoc()) {
        $recent_courses_rows[] = $row;
    }
}

$recent_students_rows = [];
$recent_students_query = $conn->query("SELECT s.name, s.student_id, s.status, s.created_at, c.course_name FROM students s INNER JOIN courses c ON c.id = s.course_id ORDER BY s.created_at DESC LIMIT 5");
if ($recent_students_query) {
    while ($row = $recent_students_query->fetch_assoc()) {
        $recent_students_rows[] = $row;
    }
}

$recent_batches_rows = [];
$recent_batches_query = $conn->query("SELECT b.batch_name, b.batch_code, b.status, b.created_at, c.course_name FROM batches b INNER JOIN courses c ON c.id = b.course_id ORDER BY b.created_at DESC LIMIT 5");
if ($recent_batches_query) {
    while ($row = $recent_batches_query->fetch_assoc()) {
        $recent_batches_rows[] = $row;
    }
}

// Additional quick counts for right-side summary
$total_batches = 0;
$bq = $conn->query("SELECT COUNT(*) as count FROM batches");
if ($bq) { $total_batches = (int)$bq->fetch_assoc()['count']; }

$gender_counts = ['Male' => 0, 'Female' => 0, 'Other' => 0, 'Unknown' => 0];
$gq = $conn->query("SELECT COALESCE(NULLIF(TRIM(LOWER(gender)),''),'unknown') AS g, COUNT(*) AS total FROM students GROUP BY COALESCE(NULLIF(TRIM(LOWER(gender)),''),'unknown')");
if ($gq) {
    while ($gr = $gq->fetch_assoc()) {
        $key = ucfirst($gr['g']);
        if (!in_array($key, ['Male','Female','Other','Unknown'])) $key = 'Other';
        $gender_counts[$key] = (int)$gr['total'];
    }
}

// Detect and compute reservation category counts (SC/ST/GEN/OBC/Other)
$category_counts = ['GEN' => 0, 'SC' => 0, 'ST' => 0, 'OBC' => 0, 'OTHER' => 0, 'UNKNOWN' => 0];
$catCandidates = ['category','caste','caste_category','caste_name','reserved_category'];
$cat_col = null;
foreach ($catCandidates as $cc) {
    $check = $conn->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='".$cc."'");
    if ($check && (int)$check->fetch_assoc()['c'] > 0) { $cat_col = $cc; break; }
}
// fetch raw category buckets for debugging and mapping
$raw_category_buckets = [];
if ($cat_col) {
    $safeCol = $conn->real_escape_string($cat_col);
    $cq = $conn->query("SELECT COALESCE(NULLIF(TRIM(".$safeCol."),''),'unknown') AS cat_raw, COUNT(*) AS total FROM students GROUP BY COALESCE(NULLIF(TRIM(".$safeCol."),''),'unknown') ORDER BY COUNT(*) DESC");
    if ($cq) {
        while ($cr = $cq->fetch_assoc()) {
            $raw = $cr['cat_raw'];
            $count = (int)$cr['total'];
            $raw_category_buckets[$raw] = $count;
            $val = strtoupper($raw);
            $val_norm = preg_replace('/[^A-Z0-9]/','',$val);
            if ($val === 'UNKNOWN' || $val === '' || in_array($val, ['NA','N/A','NOT PROVIDED','NONE'])) {
                $category_counts['UNKNOWN'] += $count;
            } elseif (strpos($val_norm,'SC') !== false && strpos($val_norm,'ST') === false) {
                $category_counts['SC'] += $count;
            } elseif (strpos($val_norm,'ST') !== false && strpos($val_norm,'SC') === false) {
                $category_counts['ST'] += $count;
            } elseif (strpos($val_norm,'OBC') !== false || strpos($val_norm,'BACKWARD') !== false) {
                $category_counts['OBC'] += $count;
            } elseif (strpos($val_norm,'GEN') !== false || strpos($val_norm,'GENERAL') !== false || strpos($val_norm,'OPEN') !== false) {
                $category_counts['GEN'] += $count;
            } else {
                $category_counts['OTHER'] += $count;
            }
        }
    }
}

// Sanity: if category sums exceed total_students, adjust 'OTHER' down to match (prevents double-counting anomalies)
$sumCats = array_sum($category_counts);
if (isset($total_students) && $sumCats > $total_students) {
    $over = $sumCats - $total_students;
    $category_counts['OTHER'] = max(0, $category_counts['OTHER'] - $over);
}

// Detect and compute PWD count (various possible column names)
$pwd_count = 0;
$pwdCandidates = ['pwd','is_pwd','disability','is_disabled','pdisable','person_with_disability'];
$pwd_col = null;
foreach ($pwdCandidates as $pc) {
    $check = $conn->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='".$pc."'");
    if ($check && (int)$check->fetch_assoc()['c'] > 0) { $pwd_col = $pc; break; }
}
if ($pwd_col) {
    $pq = $conn->query("SELECT COALESCE(NULLIF(TRIM(LOWER(".$pwd_col.")),'') ,'no') AS pwd, COUNT(*) AS total FROM students GROUP BY COALESCE(NULLIF(TRIM(LOWER(".$pwd_col.")),'') ,'no')");
    if ($pq) {
        while ($pr = $pq->fetch_assoc()) {
            $pv = strtolower($pr['pwd']);
            if (in_array($pv, ['1','yes','y','true','t','si','sí','oui'])) $pwd_count += (int)$pr['total'];
        }
    }
}

$recent_activity_rows = [];
foreach ($recent_courses_rows as $course) {
    $recent_activity_rows[] = [
        'type' => 'course',
        'title' => $course['course_name'],
        'detail' => $course['category'] ?: 'Uncategorized',
        'time' => $course['start_date'] ? date('d M Y', strtotime($course['start_date'])) : 'Recently',
    ];
}
foreach ($recent_students_rows as $student) {
    $recent_activity_rows[] = [
        'type' => 'student',
        'title' => $student['name'],
        'detail' => $student['course_name'],
        'time' => $student['created_at'] ? date('d M Y', strtotime($student['created_at'])) : 'Recently',
    ];
}
foreach ($recent_batches_rows as $batch) {
    $recent_activity_rows[] = [
        'type' => 'batch',
        'title' => $batch['batch_name'],
        'detail' => $batch['course_name'],
        'time' => $batch['created_at'] ? date('d M Y', strtotime($batch['created_at'])) : 'Recently',
    ];
}

usort($recent_activity_rows, function ($left, $right) {
    return strcmp($right['time'] ?? '', $left['time'] ?? '');
});
$recent_activity_rows = array_slice($recent_activity_rows, 0, 10);

$growthMonths = [];
for ($offset = 5; $offset >= 0; $offset--) {
    $growthMonths[] = (new DateTime('first day of this month'))->modify('-' . $offset . ' months')->format('Y-m');
}

$growthLabels = [];
$growthValues = [];
foreach ($growthMonths as $monthKey) {
    $growthLabels[] = (new DateTime($monthKey . '-01'))->format('M Y');
    $match = array_values(array_filter($student_growth_rows, function ($row) use ($monthKey) {
        return $row['month_key'] === $monthKey;
    }));
    $growthValues[] = $match ? (int) $match[0]['total'] : 0;
}

$distributionLabels = [];
$distributionValues = [];
foreach ($course_distribution_rows as $row) {
    $distributionLabels[] = $row['label'];
    $distributionValues[] = (int) $row['total'];
}

$batchLabels = [];
$batchFillValues = [];
$batchAttendanceValues = [];
foreach ($batch_performance_rows as $row) {
    $batchLabels[] = $row['batch_name'];
    $batchFillValues[] = $row['seats_total'] > 0 ? round(($row['seats_filled'] / $row['seats_total']) * 100, 1) : 0;
    $batchAttendanceValues[] = (float) $row['avg_attendance'];
}

$dashboard_payload = [
    'studentGrowth' => ['labels' => $growthLabels, 'values' => $growthValues],
    'courseDistribution' => ['labels' => $distributionLabels, 'values' => $distributionValues],
    'batchPerformance' => ['labels' => $batchLabels, 'fillRate' => $batchFillValues, 'attendanceRate' => $batchAttendanceValues],
    'notificationCount' => $notification_count,
    'categoryDistribution' => ['labels' => array_values(array_keys($category_counts)), 'values' => array_values($category_counts)],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; object-src 'none';">
    <title>Admin Dashboard - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo getThemeFavicon($active_theme); ?>" type="image/x-icon">
    <style>
        /* Modern Professional Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            animation: modalBackdropFadeIn 0.3s ease-out;
        }
        
        .modal.show {
            display: flex !important;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-dialog {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.05);
            max-width: 1000px;
            width: 100%;
            max-height: 95vh;
            overflow: hidden;
            animation: modalSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
        }
        
        @keyframes modalBackdropFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 2rem 2.5rem 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .modal-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(245,158,11,0.2) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .modal-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 2;
        }
        
        .modal-title i {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        
        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.2s;
            z-index: 3;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        
        .modal-body {
            padding: 2.5rem;
            max-height: calc(95vh - 200px);
            overflow-y: auto;
        }
        
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .modal-body::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        .modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        .modal-footer {
            padding: 1.5rem 2.5rem 2rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        /* Modern Form Styles */
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .form-section-title i {
            width: 24px;
            height: 24px;
            background: rgba(10, 22, 40, 0.1);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: var(--primary);
        }
        
        .form-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .form-grid-2 {
            grid-template-columns: 1fr 1fr;
        }
        
        .form-grid-3 {
            grid-template-columns: 2fr 1fr 1fr;
        }
        
        .form-group {
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .form-label .required {
            color: #ef4444;
            margin-left: 2px;
        }
        
        .form-control, .form-select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: #ffffff;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(10, 22, 40, 0.1);
            transform: translateY(-1px);
        }
        
        .form-control:hover, .form-select:hover {
            border-color: #d1d5db;
        }
        
        .form-help {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .form-help i {
            font-size: 0.7rem;
        }
        
        /* Enhanced Button Styles */
        .btn {
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(10, 22, 40, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(10, 22, 40, 0.4);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(107, 114, 128, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
        
        /* Special Input Styles */
        .input-group {
            display: flex;
            gap: 0.5rem;
            align-items: end;
        }
        
        .input-group .form-control {
            flex: 1;
        }
        
        .input-group .btn {
            white-space: nowrap;
            margin-bottom: 0;
        }
        
        /* Checkbox Styles */
        .checkbox-group {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            margin-top: 0.5rem;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 6px;
            transition: background 0.2s;
        }
        
        .checkbox-item:hover {
            background: rgba(10, 22, 40, 0.05);
        }
        
        .checkbox-item:last-child {
            margin-bottom: 0;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
        
        /* Info Boxes */
        .info-box {
            background: linear-gradient(135deg, #dbeafe 0%, #e0f2fe 100%);
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 1rem;
            margin: 1rem 0;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .info-box i {
            color: #1d4ed8;
            font-size: 1.1rem;
            margin-top: 0.1rem;
        }
        
        .info-box-content {
            flex: 1;
        }
        
        .info-box-title {
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 0.25rem;
        }
        
        .info-box-text {
            color: #1e40af;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        
        .warning-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-left: 4px solid #f59e0b;
        }
        
        .warning-box i {
            color: #d97706;
        }
        
        .warning-box-title {
            color: #92400e;
        }
        
        .warning-box-text {
            color: #92400e;
        }
        
        /* Premium Dashboard Skin for Existing Structure */
        :root {
            --dash-bg: #eef3f9;
            --dash-surface: rgba(255, 255, 255, 0.82);
            --dash-border: rgba(148, 163, 184, 0.18);
            --dash-text: #0f172a;
            --dash-muted: #64748b;
            --dash-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
            --dash-radius: 22px;
        }

        body {
            font-family: 'Inter', 'Poppins', 'Segoe UI', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.12), transparent 28%),
                radial-gradient(circle at top right, rgba(124, 58, 237, 0.10), transparent 24%),
                linear-gradient(180deg, #f8fbff 0%, var(--dash-bg) 100%);
            color: var(--dash-text);
            overflow-x: hidden;
        }

        body::before,
        body::after {
            content: '';
            position: fixed;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            filter: blur(12px);
            opacity: 0.18;
            z-index: 0;
            pointer-events: none;
            animation: blobFloat 18s ease-in-out infinite;
        }

        body::before {
            top: -120px;
            left: -80px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.65), transparent 68%);
        }

        body::after {
            bottom: -120px;
            right: -80px;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.58), transparent 68%);
            animation-delay: -6s;
        }

        @keyframes blobFloat {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
            50% { transform: translate3d(0, 18px, 0) scale(1.05); }
        }

        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(148, 163, 184, 0.12);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #2563eb, #7c3aed);
            border-radius: 999px;
        }

        .admin-wrapper,
        .admin-content,
        .admin-main {
            position: relative;
            z-index: 1;
        }

        .admin-content {
            background: transparent;
        }

        .admin-main {
            padding: 1rem 1.15rem 1.35rem;
        }

        .admin-topbar {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.90), rgba(248, 250, 252, 0.84));
            border: 1px solid var(--dash-border);
            border-radius: 26px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(24px) saturate(160%);
        }

        .topbar-left h4 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--dash-text);
        }

        .topbar-left h4 i {
            width: 38px;
            height: 38px;
            display: inline-grid;
            place-items: center;
            border-radius: 14px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: #fff;
            margin-right: 0.5rem;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.18);
        }

        .topbar-left small {
            color: var(--dash-muted);
            font-size: 0.9rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.35rem 0.4rem 0.35rem 0.95rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        .user-details {
            text-align: right;
            line-height: 1.15;
        }

        .user-name {
            display: block;
            color: var(--dash-text);
            font-weight: 800;
        }

        .user-role {
            display: block;
            color: var(--dash-muted);
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #fff;
            font-weight: 800;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            box-shadow: 0 16px 26px rgba(37, 99, 235, 0.24);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.9rem;
            margin-bottom: 1rem;
        }

        .stat-card,
        .content-card,
        .glass-panel {
            position: relative;
            overflow: hidden;
            background: var(--dash-surface);
            border: 1px solid var(--dash-border);
            border-radius: var(--dash-radius);
            box-shadow: var(--dash-shadow);
            backdrop-filter: blur(20px) saturate(150%);
        }

        .stat-card {
            padding: 1.15rem 1.15rem 1.05rem;
            transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
        }

        .stat-card::before,
        .content-card::before,
        .glass-panel::before {
            content: '';
            position: absolute;
            inset: auto -18% -42% auto;
            width: 8rem;
            height: 8rem;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.16) 0%, transparent 68%);
            pointer-events: none;
        }

        .stat-card:hover,
        .content-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.12);
            border-color: rgba(37, 99, 235, 0.24);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            margin-bottom: 0.8rem;
            color: #fff;
            box-shadow: 0 14px 26px rgba(15, 23, 42, 0.12);
        }

        .stat-card.primary .stat-icon { background: linear-gradient(135deg, #2563eb, #60a5fa); }
        .stat-card.success .stat-icon { background: linear-gradient(135deg, #10b981, #34d399); }
        .stat-card.info .stat-icon { background: linear-gradient(135deg, #06b6d4, #22d3ee); }
        .stat-card.warning .stat-icon { background: linear-gradient(135deg, #f59e0b, #fb923c); }
        .stat-card.secondary .stat-icon { background: linear-gradient(135deg, #64748b, #94a3b8); }

        .stat-value {
            margin: 0;
            font-size: clamp(1.65rem, 2.2vw, 2.05rem);
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--dash-text);
        }

        .stat-label {
            margin: 0.3rem 0 0;
            color: var(--dash-muted);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .dashboard-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.8fr) minmax(280px, 1fr);
            gap: 1rem;
            padding: 1.2rem;
            margin-bottom: 1rem;
        }

        .hero-copy h2 {
            margin: 0.25rem 0 0.6rem;
            font-size: clamp(1.45rem, 2.4vw, 2.05rem);
            letter-spacing: -0.05em;
            color: var(--dash-text);
        }

        .hero-copy p {
            margin: 0;
            color: var(--dash-muted);
            max-width: 62ch;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.10);
            color: #1d4ed8;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .hero-badges {
            display: grid;
            gap: 0.75rem;
        }

        .hero-badge {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.15rem 0.8rem;
            align-items: center;
            padding: 0.95rem 1rem;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(148, 163, 184, 0.16);
        }

        .hero-badge strong {
            font-size: 1.2rem;
            color: var(--dash-text);
        }

        .hero-badge small {
            grid-column: 2;
            color: var(--dash-muted);
        }

        .badge-dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            display: inline-block;
        }

        .badge-dot.success { background: linear-gradient(135deg, #10b981, #34d399); }
        .badge-dot.accent { background: linear-gradient(135deg, #2563eb, #7c3aed); }
        .badge-dot.info { background: linear-gradient(135deg, #06b6d4, #22d3ee); }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .analytics-card-large { grid-column: span 12; }
        .analytics-card-wide { grid-column: span 7; }
        .analytics-side { grid-column: span 5; }
        .analytics-card { grid-column: span 6; }

        .card-header-soft {
            align-items: flex-start !important;
        }

        .section-note {
            margin-top: 0.35rem;
            color: var(--dash-muted);
            font-size: 0.85rem;
        }

        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            background: rgba(16, 185, 129, 0.10);
            color: #047857;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .live-pill::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.35);
            animation: pulseDot 1.8s infinite;
        }

        @keyframes pulseDot {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.35); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .chart-wrap {
            min-height: 290px;
        }

        .chart-wrap-small {
            min-height: 260px;
        }

        .ring-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .ring-card {
            display: grid;
            justify-items: center;
            gap: 0.45rem;
            padding: 0.9rem;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(148, 163, 184, 0.16);
        }

        .ring {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: conic-gradient(#2563eb var(--ring-value), rgba(226, 232, 240, 0.95) 0);
            position: relative;
            box-shadow: inset 0 0 0 12px rgba(255, 255, 255, 0.95);
        }

        .ring::after {
            content: '';
            position: absolute;
            inset: 12px;
            border-radius: 50%;
            background: #fff;
        }

        .ring span {
            position: relative;
            z-index: 1;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--dash-text);
        }

        .ring-card small {
            color: var(--dash-muted);
            font-weight: 600;
        }

        .activity-table {
            overflow: auto;
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, 0.14);
        }

        /* Right-side summary */
        .summary-list { display: grid; gap: 0.8rem; }
        .summary-item { display:flex; justify-content:space-between; align-items:center; padding: 0.85rem; border-radius:12px; background: rgba(255,255,255,0.78); border:1px solid rgba(148,163,184,0.08); }
        .analytics-side .card-body { padding: 1.25rem; }
        /* Make pie chart larger and centered */
        #categoryPieChart { max-width: 220px !important; width: 40%; height: auto !important; display:block; margin-right: 0.6rem; transition: transform 220ms ease, filter 220ms ease; will-change: transform; }
        .category-chart-wrap { position:relative; display:flex; gap:0.6rem; align-items:center; padding:0.35rem 0.1rem; border-radius:16px; overflow:hidden; transition: transform 220ms ease, background-color 220ms ease, box-shadow 220ms ease; }
        .category-chart-wrap::before { content:''; position:absolute; inset:0; background: radial-gradient(circle at 20% 50%, rgba(37,99,235,0.12), transparent 42%), radial-gradient(circle at 80% 50%, rgba(124,58,237,0.10), transparent 45%); opacity:0; transition: opacity 220ms ease; pointer-events:none; }
        .category-chart-wrap:hover { transform: translateY(-2px); background: rgba(255,255,255,0.62); box-shadow: 0 18px 40px rgba(37, 99, 235, 0.12), 0 0 0 1px rgba(37, 99, 235, 0.08); }
        .category-chart-wrap:hover::before { opacity:1; }
        .category-chart-wrap:hover #categoryPieChart { transform: scale(1.06) rotate(-1deg); filter: saturate(1.08) drop-shadow(0 10px 18px rgba(37, 99, 235, 0.16)); }
        .category-chart-caption { flex:1; font-size:0.95rem; color:var(--dash-muted); transition: color 220ms ease, transform 220ms ease, opacity 220ms ease; }
        .category-chart-wrap:hover .category-chart-caption { color: var(--dash-text); transform: translateX(2px); opacity: 0.98; }
        @media (max-width: 1100px) { .analytics-card-wide { grid-column: span 12; } .analytics-side { grid-column: span 12; } #categoryPieChart { width: 100%; max-width: 320px; margin: 0 auto; } }
        .summary-item strong { font-size:1.15rem; color:var(--dash-text); }
        .gender-bar { width:100%; height:10px; background:#f1f5f9; border-radius:999px; overflow:hidden; margin-top:6px; }
        .gender-fill { height:100%; }

        .activity-table table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.72);
        }

        .activity-table th,
        .activity-table td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            text-align: left;
            color: var(--dash-text);
        }

        .activity-table th {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
            background: rgba(37, 99, 235, 0.05);
        }

        .activity-type {
            display: inline-flex;
            padding: 0.38rem 0.72rem;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .activity-type-course { background: rgba(37, 99, 235, 0.10); color: #1d4ed8; }
        .activity-type-student { background: rgba(16, 185, 129, 0.10); color: #047857; }
        .activity-type-batch { background: rgba(245, 158, 11, 0.12); color: #b45309; }

        .loading-surface {
            position: relative;
        }

        .loading-surface::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
            transform: translateX(-100%);
            animation: shimmer 2.4s infinite;
            pointer-events: none;
            opacity: 0.18;
        }

        @keyframes shimmer {
            to { transform: translateX(100%); }
        }

        .modern-table {
            margin: 0;
            color: var(--dash-text);
        }

        .modern-table thead th {
            background: linear-gradient(90deg, #0f2e66 0%, #2563eb 100%);
            border-bottom: 1px solid rgba(59, 130, 246, 0.35);
            color: rgba(255, 255, 255, 0.96) !important;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.95rem;
            font-weight: 800;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .modern-table tbody td {
            padding: 0.95rem;
            vertical-align: middle;
            border-color: rgba(148, 163, 184, 0.12);
        }

        .modern-table tbody tr:hover {
            background: rgba(37, 99, 235, 0.04);
        }

        .modern-table .badge {
            border-radius: 999px;
            padding: 0.5rem 0.8rem;
            font-weight: 700;
        }

        .btn {
            border-radius: 999px;
            font-weight: 700;
            padding-inline: 0.95rem;
            transition: transform 180ms ease, box-shadow 180ms ease, opacity 180ms ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            border: none;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.18);
        }

        .btn-outline-primary,
        .btn-outline-secondary,
        .btn-outline-success,
        .btn-secondary {
            border-width: 1px;
        }

        .form-control,
        .form-select {
            border-radius: 16px;
            border-color: rgba(148, 163, 184, 0.24);
            padding: 0.8rem 0.95rem;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.03);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: rgba(37, 99, 235, 0.42);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .modal {
            backdrop-filter: blur(10px);
        }

        .modal-dialog {
            border-radius: 24px;
        }

        .modal-content {
            border: 0;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.28);
        }

        .modal-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            border: 0;
            padding: 1.1rem 1.35rem;
        }

        .modal-body {
            padding: 1.25rem 1.35rem;
        }

        .modal-footer {
            border-top: 1px solid rgba(148, 163, 184, 0.16);
            background: #f8fafc;
            padding: 0.95rem 1.35rem 1.1rem;
        }

        .info-box,
        .warning-box {
            border-radius: 18px;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .analytics-card { grid-column: span 12; }
        }

        @media (max-width: 992px) {
            .admin-main {
                padding: 1rem;
            }

            .content-card .card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-hero {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .admin-topbar {
                padding: 1rem;
            }

            .user-details {
                display: none;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card,
            .content-card,
            .glass-panel {
                border-radius: 20px;
            }

            .dashboard-hero {
                padding: 1rem;
            }

            .hero-actions {
                flex-direction: column;
            }

            .hero-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .ring-grid {
                grid-template-columns: 1fr 1fr;
            }

            .modal-body,
            .modal-header,
            .modal-footer {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .form-grid-2, .form-grid-3 {
                grid-template-columns: 1fr;
            }

            .content-card .card-header {
                padding: 0.9rem 1rem;
            }
        }
    </style>
</head>
<body class="premium-dashboard">

<div class="admin-wrapper">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-tachometer-alt"></i> 
                    <?php 
                    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'nsqf_course_manager') {
                        echo 'NSQF Course Dashboard';
                    } else {
                        echo 'Dashboard';
                    }
                    ?>
                </h4>
                <small>Welcome back, 
                    <?php 
                    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'nsqf_course_manager') {
                        echo 'NSQF Course Manager!';
                    } else {
                        echo 'Admin!';
                    }
                    ?>
                </small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role">
                            <?php 
                            if (isset($_SESSION['admin_role'])) {
                                switch ($_SESSION['admin_role']) {
                                    case 'master_admin':
                                        echo 'Master Administrator';
                                        break;
                                    case 'nsqf_course_manager':
                                        echo 'NSQF Course Manager';
                                        break;
                                    default:
                                        echo 'Course Coordinator';
                                }
                            } else {
                                echo 'Administrator';
                            }
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
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        toast.<?php echo ($_SESSION['message_type'] ?? 'success') === 'success' ? 'success' : 'error'; ?>('<?php echo addslashes($_SESSION['message']); ?>', 5000);
                    });
                </script>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $total_courses; ?></h3>
                    <p class="stat-label">Total Courses</p>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $total_students; ?></h3>
                    <p class="stat-label">Total Students</p>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $total_centres; ?></h3>
                    <p class="stat-label">Training Centres</p>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-palette"></i>
                    </div>
                    <h3 class="stat-value" style="font-size: 1.2rem;"><?php echo htmlspecialchars($active_theme_name); ?></h3>
                    <p class="stat-label">Active Theme</p>
                </div>
                
                <div class="stat-card secondary">
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $total_homepage_sections; ?></h3>
                    <p class="stat-label">Homepage Sections</p>
                </div>
            </div>

            <!-- Compact All Courses Summary (Top) -->
            <?php if ($is_course_coordinator && empty($admin_course_ids)): ?>
                <div class="content-card" style="margin-bottom: 2rem; padding: 1.25rem; display:flex; align-items:center; gap:1rem; justify-content:space-between;">
                    <div style="display:flex; align-items:center; gap:1rem;">
                        <div style="width:56px; height:56px; background:linear-gradient(135deg,#f3f4f6,#e5e7eb); border-radius:12px; display:grid; place-items:center;">
                            <i class="fas fa-book" style="font-size:1.25rem; color:#9ca3af;"></i>
                        </div>
                        <div>
                            <div style="font-weight:800; font-size:1.05rem; color:#0f172a;">No Course Assignments</div>
                            <div style="color:#64748b; font-size:0.9rem;">You have no assigned courses. Create one or ask Master Admin to assign.</div>
                        </div>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-primary" onclick="openModal('addCourseModal')"><i class="fas fa-plus"></i> Add Course</button>
                        <a href="manage_courses.php" class="btn btn-outline-primary">Manage</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="content-card" style="margin-bottom: 2rem; padding: 1rem;">
                    <div style="display:flex; align-items:center; gap:1rem; justify-content:space-between;">
                        <div style="display:flex; align-items:center; gap:1rem;">
                            <div style="width:56px; height:56px; background:linear-gradient(135deg,#2563eb,#7c3aed); border-radius:12px; display:grid; place-items:center; color:#fff;">
                                <i class="fas fa-book" style="font-size:1.25rem;"></i>
                            </div>
                            <div>
                                <div style="font-weight:800; font-size:1.05rem; color:#0f172a;">All Courses</div>
                                <div style="color:#64748b; font-size:0.9rem;">Total: <strong><?php echo $total_courses; ?></strong></div>
                            </div>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <a href="manage_courses.php" class="btn btn-outline-primary">Manage</a>
                            <a href="#courses-section" class="btn btn-primary" onclick="document.getElementById('courses-section')?.scrollIntoView({behavior:'smooth'});"><i class="fas fa-list"></i> View All Courses</a>
                        </div>
                    </div>

                    <div style="margin-top:12px; display:flex; gap:8px; align-items:center;">
                        <?php
                            $tab = $courses_tab;
                            $tabs = [
                                'all' => ['label' => 'All', 'count' => $count_all],
                                'ongoing' => ['label' => 'Ongoing', 'count' => $count_ongoing],
                                'upcoming' => ['label' => 'Upcoming', 'count' => $count_upcoming],
                                'past' => ['label' => 'Past', 'count' => $count_past]
                            ];
                            foreach ($tabs as $key => $info) {
                                $active = $tab === $key ? 'background: linear-gradient(135deg,#2563eb,#7c3aed); color: #fff;' : 'background: #f3f4f6; color: #374151;';
                                $url = 'dashboard.php?courses_tab=' . $key;
                                echo "<a href=\"$url\" class=\"btn\" style=\"padding:8px 12px; border-radius:12px; $active\">{$info['label']} <span style='margin-left:8px; font-weight:700;'>({$info['count']})</span></a> ";
                            }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Courses Table Section (moved to top) -->
            <div class="content-card" style="margin-bottom: 20px;">
                <div class="card-header" style="border-bottom: 1px solid #e2e8f0;">
                    <h5 class="card-title" style="margin: 0;">
                        <i class="fas fa-filter"></i> Filter Courses
                    </h5>
                </div>
                <div style="padding: 20px;">
                    <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 16px; align-items: end;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label"><i class="fas fa-tag"></i> Filter by Category</label>
                            <select name="category" class="form-select" onchange="this.form.submit()" style="width: 100%;">
                                <option value="all" <?= $filter_category === 'all' ? 'selected' : '' ?>>All Categories</option>
                                <?php if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'nsqf_course_manager'): ?>
                                <option value="NSQF" <?= $filter_category === 'NSQF' ? 'selected' : '' ?>>NSQF</option>
                                <option value="NON-NSQF" <?= $filter_category === 'NON-NSQF' ? 'selected' : '' ?>>NON-NSQF</option>
                                <option value="Internship Program" <?= $filter_category === 'Internship Program' ? 'selected' : '' ?>>Internship Program</option>
                                <?php else: ?>
                                <option value="NSQF" <?= $filter_category === 'NSQF' ? 'selected' : '' ?>>NSQF</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; color: #64748b;">
                            <i class="fas fa-info-circle"></i>
                            <span>
                                <?php 
                                $total_filtered = $result->num_rows;
                                if ($filter_category !== 'all') {
                                    echo '<strong style="color: #0d47a1;">' . $total_filtered . ' results</strong> found';
                                } else {
                                    echo '<strong style="color: #64748b;">' . $total_filtered . ' total</strong> courses';
                                }
                                ?>
                            </span>
                        </div>
                        <div>
                            <?php if ($filter_category !== 'all'): ?>
                                <a href="dashboard.php" class="btn btn-secondary" style="width: 100%;">
                                    <i class="fas fa-redo"></i> Clear Filter
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary" disabled style="width: 100%; opacity: 0.5;">
                                    <i class="fas fa-filter"></i> No Filter
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($is_course_coordinator && empty($admin_courses)): ?>
                <!-- No Course Assignments Message for Coordinators -->
                <div class="content-card">
                    <div class="card-body text-center" style="padding: 3rem;">
                        <div style="color: #64748b; margin-bottom: 1.5rem;">
                            <i class="fas fa-book" style="font-size: 4rem; opacity: 0.3;"></i>
                        </div>
                        <h4 style="color: #374151; margin-bottom: 1rem;">No Course Assignments</h4>
                        <p style="color: #6b7280; margin-bottom: 1.5rem;">
                            You haven't been assigned to any courses yet. You can create a new course or contact the Master Admin to assign existing courses to your coordinator account.
                        </p>
                        <div style="background: #f3f4f6; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                            <small style="color: #6b7280;">
                                <i class="fas fa-info-circle"></i> 
                                Course coordinators can only view and manage courses they are assigned to.
                            </small>
                        </div>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <button class="btn btn-primary" onclick="openModal('addCourseModal')">
                                <i class="fas fa-plus"></i> Add New Course
                            </button>
                            <a href="manage_courses.php" class="btn btn-outline-primary">
                                <i class="fas fa-book"></i> Manage Courses
                            </a>
                            <a href="students.php" class="btn btn-secondary">
                                <i class="fas fa-users"></i> View Students
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
            <div class="content-card" id="courses-section">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-book"></i> 
                        <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'nsqf_course_manager'): ?>
                            NSQF Courses (Read Only)
                        <?php else: ?>
                            All Courses
                        <?php endif; ?>
                        <?php if ($is_course_coordinator && !empty($admin_courses)): ?>
                            <small style="color: #64748b; font-weight: normal;">
                                (Showing your assigned courses: <?php echo implode(', ', $admin_courses); ?>)
                            </small>
                        <?php endif; ?>
                    </h5>
                    <?php if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'nsqf_course_manager'): ?>
                    <button class="btn btn-primary" onclick="openModal('addCourseModal')">
                        <i class="fas fa-plus"></i> Add New Course
                    </button>
                    <?php else: ?>
                    <div class="alert alert-warning" style="margin: 0; padding: 8px 12px; font-size: 14px;">
                        <i class="fas fa-eye"></i> View Only - Use Manage NSQF Course to add NSQF courses
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Course Name</th>
                                <th>Course Code</th>
                                <th>Student ID Code</th>
                                <th>Category</th>
                                <th>Duration</th>
                                <th>Fees</th>
                                <th>Start Date</th>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['course_name']); ?></strong><br>
                                        <small style="color: #64748b;"><?php echo htmlspecialchars($row['eligibility']); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['course_code'])): ?>
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($row['course_code']); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Not Set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['course_abbreviation'])): ?>
                                            <span class="badge badge-success"><?php echo htmlspecialchars($row['course_abbreviation']); ?></span>
                                            <br><small class="text-muted">NIELIT/2026/<?php echo htmlspecialchars($row['course_abbreviation']); ?>/####</small>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Not Set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo htmlspecialchars($row['category']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['duration']); ?></td>
                                    <td>₹<?php echo is_numeric($row['training_fees']) ? number_format($row['training_fees']) : htmlspecialchars($row['training_fees']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($row['start_date'])); ?></td>
                                    <td>
                                        <?php 
                                        $student_count = $row['student_count'] ?? 0;
                                        $badge_class = $student_count > 0 ? 'badge-success' : 'badge-secondary';
                                        ?>
                                                     <a href="students.php?filter_course=<?php echo urlencode($row['id']); ?>" 
                                           class="badge <?php echo $badge_class; ?>" 
                                           style="text-decoration: none; font-size: 14px; padding: 6px 12px;">
                                            <i class="fas fa-users"></i> <?php echo $student_count; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'nsqf_course_manager'): ?>
                                        <a href="edit_course.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="dashboard.php?delete_id=<?php echo $row['id']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirmDelete(event, '<?php echo htmlspecialchars($row['course_name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php else: ?>
                                        <span class="badge badge-secondary">
                                            <i class="fas fa-eye"></i> View Only
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #64748b;">
                                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.3;"></i>
                                        <p style="margin: 0; font-size: 16px;">No courses found. Click "Add New Course" to get started.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="dashboard-hero glass-panel">
                <div class="hero-copy">
                    <div class="eyebrow">Live operations</div>
                    <h2>Premium admin analytics with quick operational insight.</h2>
                    <p>Track course growth, student activity, batch health, and system content from one clean control panel.</p>
                    <div class="hero-actions">
                        <button class="btn btn-primary" onclick="openModal('addCourseModal')">
                            <i class="fas fa-plus"></i> Add New Course
                        </button>
                        <a href="manage_courses.php" class="btn btn-outline-primary">
                            <i class="fas fa-layer-group"></i> Manage Courses
                        </a>
                        <a href="students.php" class="btn btn-outline-secondary">
                            <i class="fas fa-users"></i> View Students
                        </a>
                    </div>
                </div>
                <div class="hero-badges">
                    <div class="hero-badge">
                        <span class="badge-dot success"></span>
                        <strong><?php echo $notification_count; ?></strong>
                        <small>Active notices</small>
                    </div>
                    <div class="hero-badge">
                        <span class="badge-dot accent"></span>
                        <strong><?php echo $total_students; ?></strong>
                        <small>Live students</small>
                    </div>
                    <div class="hero-badge">
                        <span class="badge-dot info"></span>
                        <strong><?php echo count($recent_activity_rows); ?></strong>
                        <small>Recent activities</small>
                    </div>
                </div>
            </div>

            <div class="analytics-grid">
                <section class="content-card analytics-card analytics-card-large loading-surface">
                    <div class="card-header card-header-soft">
                        <div>
                            <h5 class="card-title"><i class="fas fa-chart-line"></i> Student Growth</h5>
                            <div class="section-note">Monthly registrations across the last six months.</div>
                        </div>
                        <span class="live-pill">Live counters</span>
                    </div>
                    <div class="card-body chart-wrap">
                        <canvas id="studentGrowthChart" height="120"></canvas>
                    </div>
                </section>

                <section class="content-card analytics-card loading-surface">
                    <div class="card-header card-header-soft">
                        <div>
                            <h5 class="card-title"><i class="fas fa-chart-pie"></i> Course Distribution</h5>
                            <div class="section-note">Quick view of where courses are concentrated.</div>
                        </div>
                    </div>
                    <div class="card-body chart-wrap chart-wrap-small">
                        <canvas id="courseDistributionChart" height="220"></canvas>
                    </div>
                </section>

                <section class="content-card analytics-card loading-surface">
                    <div class="card-header card-header-soft">
                        <div>
                            <h5 class="card-title"><i class="fas fa-chart-column"></i> Batch Performance</h5>
                            <div class="section-note">Seat fill and average attendance by batch.</div>
                        </div>
                    </div>
                    <div class="card-body chart-wrap chart-wrap-small">
                        <canvas id="batchPerformanceChart" height="220"></canvas>
                    </div>
                </section>

                <section class="content-card analytics-card loading-surface">
                    <div class="card-header card-header-soft">
                        <div>
                            <h5 class="card-title"><i class="fas fa-ring"></i> Progress Rings</h5>
                            <div class="section-note">Compact operational health indicators.</div>
                        </div>
                    </div>
                    <div class="card-body ring-grid">
                        <div class="ring-card">
                            <div class="ring" style="--ring-value: <?php echo min(100, round(($total_students / max($total_courses, 1)) * 12, 0)); ?>%"><span><?php echo $total_courses; ?></span></div>
                            <small>Courses</small>
                        </div>
                        <div class="ring-card">
                            <div class="ring" style="--ring-value: <?php echo min(100, $total_centres * 8); ?>%"><span><?php echo $total_centres; ?></span></div>
                            <small>Centres</small>
                        </div>
                        <div class="ring-card">
                            <div class="ring" style="--ring-value: <?php echo min(100, $total_homepage_sections * 12); ?>%"><span><?php echo $total_homepage_sections; ?></span></div>
                            <small>Sections</small>
                        </div>
                        <div class="ring-card">
                            <div class="ring" style="--ring-value: <?php echo min(100, $notification_count * 12); ?>%"><span><?php echo $notification_count; ?></span></div>
                            <small>Notices</small>
                        </div>
                    </div>
                </section>

                <section class="content-card analytics-card analytics-card-wide loading-surface">
                    <div class="card-header card-header-soft">
                        <div>
                            <h5 class="card-title"><i class="fas fa-stream"></i> Recent Activity</h5>
                            <div class="section-note">Latest courses, students, and batch updates.</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="activity-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Item</th>
                                        <th>Details</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_activity_rows)): ?>
                                        <?php foreach ($recent_activity_rows as $activity): ?>
                                            <tr>
                                                <td>
                                                    <span class="activity-type activity-type-<?php echo htmlspecialchars($activity['type']); ?>">
                                                        <?php echo ucfirst(htmlspecialchars($activity['type'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($activity['title']); ?></td>
                                                <td><?php echo htmlspecialchars($activity['detail']); ?></td>
                                                <td><?php echo htmlspecialchars($activity['time']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" style="text-align:center; color:#6b7280;">No recent activity found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <section class="content-card analytics-side">
                    <div class="card-header card-header-soft">
                        <div>
                            <h5 class="card-title"><i class="fas fa-eye"></i> Snapshot</h5>
                            <div class="section-note">Quick operational counters and gender split.</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="summary-list">
                            <div class="summary-item"><div>Students</div><strong><?php echo number_format($total_students); ?></strong></div>
                            <div class="summary-item"><div>Courses</div><strong><?php echo number_format($total_courses); ?></strong></div>
                            <div class="summary-item"><div>Batches</div><strong><?php echo number_format($total_batches); ?></strong></div>
                            <div class="summary-item"><div>Centres</div><strong><?php echo number_format($total_centres); ?></strong></div>
                            <div style="height:6px"></div>
                            <div>
                                <small style="font-weight:700; color:var(--dash-muted);">Gender distribution</small>
                                <div style="display:grid; gap:0.6rem; margin-top:0.6rem;">
                                    <?php
                                        $totalGender = array_sum($gender_counts) ?: 1;
                                        foreach ($gender_counts as $gk => $gv):
                                            $pct = round(($gv / $totalGender) * 100, 1);
                                            $color = $gk === 'Male' ? '#2563eb' : ($gk === 'Female' ? '#ec4899' : '#f59e0b');
                                    ?>
                                        <div style="display:flex; align-items:center; gap:0.6rem;">
                                            <div style="flex:1;">
                                                <div style="display:flex; justify-content:space-between; font-weight:600; color:var(--dash-muted); font-size:0.9rem;"><span><?php echo htmlspecialchars($gk); ?></span><span><?php echo $gv; ?> (<small><?php echo $pct; ?>%</small>)</span></div>
                                                <div class="gender-bar"><div class="gender-fill" style="width:<?php echo $pct; ?>%; background: linear-gradient(90deg, <?php echo $color; ?>, rgba(124,58,237,0.6));"></div></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div style="height:8px"></div>
                            <div class="category-chart-wrap" style="margin-top:0.6rem;">
                                <canvas id="categoryPieChart" width="160" height="160" style="max-width:160px; height:100px;"></canvas>
                                <div class="category-chart-caption">Category breakdown — hover the chart for details.</div>
                            </div>
                            <div style="height:8px"></div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Quick Actions for Course Coordinators -->
            <?php if ($is_course_coordinator): ?>
            <div class="content-card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-3 flex-wrap">
                        <button class="btn btn-primary" onclick="openModal('addCourseModal')">
                            <i class="fas fa-plus"></i> Add New Course
                        </button>
                        <a href="manage_courses.php" class="btn btn-outline-primary">
                            <i class="fas fa-book"></i> Manage Courses
                        </a>
                        <a href="students.php" class="btn btn-outline-secondary">
                            <i class="fas fa-users"></i> View Students
                        </a>
                        <a href="<?php echo APP_URL; ?>/batch_module/admin/approve_students.php" class="btn btn-outline-success">
                            <i class="fas fa-user-check"></i> Approve Students
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions for NSQF Managers -->
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'nsqf_course_manager'): ?>
            <div class="content-card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-graduation-cap"></i> Manage NSQF Course
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="manage_nsqf_templates.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Manage NSQF Course
                        </a>
                        <div class="alert alert-info" style="margin: 0; flex: 1;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Note:</strong> As an NSQF Course Manager, you can create and manage NSQF course entries. Course Coordinators will use these to create actual courses.
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modern Add Course Modal -->
<?php if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'nsqf_course_manager'): ?>
<div class="modal" id="addCourseModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">
                <i class="fas fa-graduation-cap"></i>
                Add New Course
            </h5>
            <button type="button" class="modal-close" onclick="closeModal('addCourseModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form action="dashboard.php" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <!-- Basic Course Information -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i>
                        Basic Course Information
                    </div>
                    
                    <div class="form-grid form-grid-3">
                        <div class="form-group">
                            <label class="form-label">Course Name <span class="required">*</span></label>
                            <input type="text" class="form-control" id="add_course_name_dash" name="course_name" required placeholder="e.g., Post Graduate Programme in Artificial Intelligence">
                            <select name="course_name_template" id="add_course_name_template_dash" class="form-control" style="display:none;">
                                <option value="">-- Select Course Template --</option>
                            </select>
                            <div class="form-help">
                                <i class="fas fa-lightbulb"></i>
                                Enter the full course name as it will appear on certificates
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Course Code <span class="required">*</span></label>
                            <input type="text" class="form-control" name="course_code" maxlength="20" required style="text-transform: uppercase;" placeholder="PPI-2026">
                            <div class="form-help">
                                <i class="fas fa-tag"></i>
                                Unique identifier (e.g., PPI-2026)
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Student ID Code <span class="required">*</span></label>
                            <input type="text" class="form-control" name="course_abbreviation" id="add_abbr_dash" maxlength="10" required style="text-transform: uppercase;" placeholder="PPI">
                            <div class="form-help">
                                <i class="fas fa-id-card"></i>
                                For ID: NIELIT/2026/<strong>PPI</strong>/0001
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Details -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-cogs"></i>
                        Course Details
                    </div>
                    
                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Category <span class="required">*</span></label>
                            <select class="form-select" name="category" id="add_category_dash" required>
                                <option value="">Select Category</option>
                                <option value="Degree / Diploma / PG">Degree / Diploma Courses / PG</option>
                                <option value="Skill Based (Long Term) >500 hrs">Skill Based (Long Term) Courses &gt; 500 hrs</option>
                                <option value="Skill Based (Short Term) 90-500 hrs">Skill Based (Short Term) Courses &gt;90 hrs to &lt;=500 hrs</option>
                                <option value="Short Term / Digital Competency <=90 hrs">Short Term Courses / Digital Competency Courses &lt;= 90 hours</option>
                                <option value="NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)">NIELIT HQ's Digital Literacy Courses (CCC / ECC / CCCP / BCC / ACC)</option>
                                <option value="Internship Program">Internship Program</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Sub-Category <span class="required">*</span></label>
                            <select class="form-select" name="nsqf_type" id="add_nsqf_type_dash" required>
                                <option value="">--Select Sub-Category--</option>
                                <option value="NSQF Course">NSQF Course</option>
                                <option value="NON-NSQF Course">NON-NSQF Course</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Eligibility <span class="required">*</span></label>
                            <input type="text" class="form-control" name="eligibility" id="add_eligibility_dash" required placeholder="Graduate in any discipline">
                            <div class="form-help">
                                <i class="fas fa-user-graduate"></i>
                                For NSQF courses, this will be filled automatically from template
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Duration <span class="required">*</span></label>
                            <input type="text" class="form-control" name="duration" placeholder="6 Months" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Training Fees <span class="required">*</span></label>
                            <input type="text" class="form-control" name="training_fees" placeholder="15000" required>
                            <div class="form-help">
                                <i class="fas fa-rupee-sign"></i>
                                Enter amount in INR (without currency symbol)
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Administrative Details -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-users-cog"></i>
                        Administrative Details
                    </div>
                    
                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Course Coordinator <span class="required">*</span></label>
                            <input type="text" class="form-control" name="course_coordinator" required placeholder="Dr. John Smith">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Training Centre <span class="required">*</span></label>
                            <select class="form-select" name="training_center" required>
                                <option value="">-- Select Training Centre --</option>
                                <?php if (!empty($centres)): ?>
                                    <?php foreach ($centres as $centre): ?>
                                        <option value="<?= htmlspecialchars($centre['name']) ?>">
                                            <?= htmlspecialchars($centre['name']) ?> (<?= htmlspecialchars($centre['code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="NIELIT BHUBANESWAR">NIELIT BHUBANESWAR (Default)</option>
                                <?php endif; ?>
                            </select>
                            <div class="form-help">
                                <i class="fas fa-building"></i>
                                <?php if (empty($centres)): ?>
                                    <span style="color: #f59e0b;">No training centres found. Using default centre.</span>
                                <?php else: ?>
                                    Select the training centre where this course will be conducted
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Start Date <span class="required">*</span></label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">End Date <span class="required">*</span></label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-file-alt"></i>
                        Additional Information
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description URL</label>
                        <input type="url" class="form-control" name="description_url" placeholder="https://nielit.gov.in/course-details">
                        <div class="form-help">
                            <i class="fas fa-link"></i>
                            Optional: Link to detailed course information
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Course Description</label>
                        <textarea class="form-control" name="course_description" rows="3" placeholder="Location: NIELIT Bhubaneswar, Ground Floor. Venue: Training Hall A. Any additional details about the course..."></textarea>
                        <div class="form-help">
                            <i class="fas fa-map-marker-alt"></i>
                            Add location, venue, or any extra information about this course
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description PDF</label>
                        <input type="file" class="form-control" name="description_pdf" accept=".pdf">
                        <div class="form-help">
                            <i class="fas fa-file-pdf"></i>
                            Upload course brochure or detailed syllabus (PDF only)
                        </div>
                    </div>
                </div>

                <!-- Registration Link Settings -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-link"></i>
                        Registration Link Settings
                    </div>
                    
                    <!-- Schemes/Projects Selection -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-project-diagram"></i> Associated Schemes/Projects
                        </label>
                        <?php
                        // Fetch all active schemes for add course form
                        $schemes_query_add = "SELECT * FROM schemes WHERE status = 'Active' ORDER BY scheme_name";
                        $schemes_result_add = $conn->query($schemes_query_add);
                        ?>
                        
                        <div class="checkbox-group">
                            <?php if ($schemes_result_add && $schemes_result_add->num_rows > 0): ?>
                                <?php while ($scheme = $schemes_result_add->fetch_assoc()): ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="schemes[]" value="<?php echo $scheme['id']; ?>">
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($scheme['scheme_name']); ?></span>
                                        <span style="color: #6c757d; font-size: 12px;">(<?php echo htmlspecialchars($scheme['scheme_code']); ?>)</span>
                                    </label>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 1rem; color: #6c757d;">
                                    <i class="fas fa-info-circle"></i> No schemes available. 
                                    <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php" target="_blank" style="color: #007bff;">Create schemes</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-help">
                            <i class="fas fa-check-square"></i>
                            Select one or more schemes/projects for this course
                        </div>
                    </div>
                    
                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Registration Link</label>
                            <div class="input-group">
                                <input type="url" class="form-control" name="apply_link" id="add_apply_link_dash" placeholder="Will be auto-generated" readonly>
                                <button type="button" class="btn btn-success" onclick="generateApplyLinkDash()">
                                    <i class="fas fa-magic"></i> Generate
                                </button>
                            </div>
                            <div class="form-help">
                                <i class="fas fa-wand-magic-sparkles"></i>
                                Click "Generate" to create registration URL automatically
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Publish Status</label>
                            <div style="padding-top: 0.875rem;">
                                <label class="checkbox-item" style="margin-bottom: 0;">
                                    <input type="checkbox" name="link_published" id="add_link_published_dash" value="1">
                                    <span id="add_publish_status_dash">Unpublished</span>
                                </label>
                            </div>
                            <div class="form-help">
                                <i class="fas fa-eye"></i>
                                Toggle to show/hide on website
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <div class="info-box-content">
                            <div class="info-box-title">Registration Link Preview</div>
                            <div class="info-box-text" id="link_preview_dash">Enter course name and click "Generate Link"</div>
                        </div>
                    </div>
                    
                    <div class="info-box warning-box">
                        <i class="fas fa-qrcode"></i>
                        <div class="info-box-content">
                            <div class="warning-box-title">QR Code Generation</div>
                            <div class="warning-box-text">QR code will be generated automatically when you save the course with a registration link.</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCourseModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="add_course" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Course
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
const dashboardPayload = <?php echo json_encode($dashboard_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}

// Modern confirm delete
async function confirmDelete(event, courseName) {
    event.preventDefault();
    const confirmed = await showConfirm({
        title: 'Delete Course?',
        message: `Are you sure you want to delete "${courseName}"? This action cannot be undone.`,
        confirmText: 'Delete',
        cancelText: 'Cancel',
        type: 'danger'
    });
    
    if (confirmed) {
        window.location.href = event.target.closest('a').href;
    }
    return false;
}

// Generate Apply Link for Dashboard (Simple - no AJAX for new courses)
function generateApplyLinkDash() {
    const courseNameInput = document.getElementById('add_course_name_dash');
    const courseCodeInput = document.querySelector('input[name="course_code"]');
    const linkInput = document.getElementById('add_apply_link_dash');
    const previewSpan = document.getElementById('link_preview_dash');
    
    const courseName = courseNameInput.value.trim();
    const courseCode = courseCodeInput.value.trim();
    
    if (!courseName) {
        toast.warning('Please enter course name first!');
        courseNameInput.focus();
        return;
    }
    
    if (!courseCode) {
        toast.warning('Please enter course code first!');
        courseCodeInput.focus();
        return;
    }
    
    // Generate link based on course CODE (not course name)
    const baseUrl = window.location.origin + window.location.pathname.replace('dashboard.php', '');
    const registrationLink = baseUrl + '../student/register.php?course=' + encodeURIComponent(courseCode);
    
    linkInput.value = registrationLink;
    previewSpan.textContent = registrationLink;
    
    // Show success message
    toast.success('Registration link generated! QR code will be created automatically when you save the course.');
}

// Toggle publish status label
document.addEventListener('DOMContentLoaded', function() {
    const publishCheckbox = document.getElementById('add_link_published_dash');
    if (publishCheckbox) {
        publishCheckbox.addEventListener('change', function() {
            const statusSpan = document.getElementById('add_publish_status_dash');
            statusSpan.textContent = this.checked ? 'Published' : 'Unpublished';
            statusSpan.style.color = this.checked ? '#28a745' : '';
            statusSpan.style.fontWeight = this.checked ? 'bold' : '';
        });
    }
    
    // NSQF Template Integration
    const isNSQFManager = <?php echo json_encode(isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'nsqf_course_manager'); ?>;
    const isCourseCoordinator = <?php echo json_encode(isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'course_coordinator'); ?>;
    
    const nsqfTypeSelect = document.getElementById('add_nsqf_type_dash');
    if (nsqfTypeSelect) {
        // Add change event for template integration based on sub-category
        nsqfTypeSelect.addEventListener('change', function() {
            handleNsqfTypeChangeDash(this.value);
        });
    }
});

// Handle sub-category (nsqf_type) change for template integration
function handleNsqfTypeChangeDash(nsqfType) {
    const courseNameInput = document.getElementById('add_course_name_dash');
    const courseNameTemplate = document.getElementById('add_course_name_template_dash');
    const eligibilityField = document.getElementById('add_eligibility_dash');
    
    const isCourseCoordinator = <?php echo json_encode(isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'course_coordinator'); ?>;
    const isNSQFManager = <?php echo json_encode(isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'nsqf_course_manager'); ?>;
    
    if (nsqfType === 'NSQF Course') {
        // Show template dropdown for Course Coordinators
        if (isCourseCoordinator) {
            courseNameInput.style.display = 'none';
            courseNameTemplate.style.display = 'block';
            courseNameTemplate.required = true;
            courseNameInput.required = false;
            
            // Fetch NSQF templates
            fetchNSQFTemplatesDash(category);
        } else if (isNSQFManager) {
            // NSQF managers can create new courses directly
            courseNameInput.style.display = 'block';
            courseNameTemplate.style.display = 'none';
            courseNameInput.required = true;
            courseNameTemplate.required = false;
        }
        
        // Make eligibility read-only for coordinators
        if (isCourseCoordinator && eligibilityField) {
            eligibilityField.readOnly = true;
            eligibilityField.placeholder = 'Will be filled from selected template';
        }
    } else {
        // Non-NSQF courses - show regular input
        courseNameInput.style.display = 'block';
        courseNameTemplate.style.display = 'none';
        courseNameInput.required = true;
        courseNameTemplate.required = false;
        
        if (eligibilityField) {
            eligibilityField.readOnly = false;
            eligibilityField.placeholder = 'Enter eligibility criteria';
        }
    }
}

// Fetch NSQF templates for dashboard
function fetchNSQFTemplatesDash(category) {
    fetch('get_nsqf_templates.php?category=' + encodeURIComponent(category))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateTemplateDropdownDash(data.templates);
            } else {
                console.error('Error fetching templates:', data.message);
                toast.error('Error loading course templates. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toast.error('Error loading course templates. Please check your connection.');
        });
}

// Populate template dropdown for dashboard
function populateTemplateDropdownDash(templates) {
    const templateSelect = document.getElementById('add_course_name_template_dash');
    
    // Clear existing options except first
    templateSelect.innerHTML = '<option value="">-- Select Course Template --</option>';
    
    // Add template options
    templates.forEach(template => {
        const option = document.createElement('option');
        option.value = template.id;
        option.textContent = template.course_name;
        option.dataset.eligibility = template.eligibility;
        templateSelect.appendChild(option);
    });
    
    // Add change event to populate eligibility
    templateSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const eligibilityField = document.getElementById('add_eligibility_dash');
        const courseNameInput = document.getElementById('add_course_name_dash');
        
        if (selectedOption.dataset.eligibility && eligibilityField) {
            eligibilityField.value = selectedOption.dataset.eligibility;
        }
        
        // Set the actual course name for form submission
        if (courseNameInput) {
            courseNameInput.value = selectedOption.textContent;
        }
    });
}

function initDashboardCharts() {
    if (typeof Chart === 'undefined') {
        return;
    }

    const growthCanvas = document.getElementById('studentGrowthChart');
    const distributionCanvas = document.getElementById('courseDistributionChart');
    const batchCanvas = document.getElementById('batchPerformanceChart');

    if (growthCanvas) {
        new Chart(growthCanvas, {
            type: 'line',
            data: {
                labels: dashboardPayload.studentGrowth.labels,
                datasets: [{
                    label: 'Student Growth',
                    data: dashboardPayload.studentGrowth.values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.14)',
                    tension: 0.42,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#ffffff',
                    pointBorderWidth: 3,
                    pointBorderColor: '#2563eb'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.96)',
                        padding: 12,
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0',
                        cornerRadius: 14,
                        displayColors: false
                    }
                },
                scales: {
                    x: { grid: { color: 'rgba(148, 163, 184, 0.12)' }, ticks: { color: '#64748b' } },
                    y: { grid: { color: 'rgba(148, 163, 184, 0.12)' }, ticks: { color: '#64748b', precision: 0 } }
                }
            }
        });
    }

    if (distributionCanvas) {
        new Chart(distributionCanvas, {
            type: 'doughnut',
            data: {
                labels: dashboardPayload.courseDistribution.labels,
                datasets: [{
                    data: dashboardPayload.courseDistribution.values,
                    backgroundColor: ['#2563eb', '#7c3aed', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#14b8a6', '#64748b'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#475569', usePointStyle: true, pointStyle: 'circle' }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.96)',
                        padding: 12,
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0',
                        cornerRadius: 14
                    }
                }
            }
        });
    }

    if (batchCanvas) {
        new Chart(batchCanvas, {
            type: 'bar',
            data: {
                labels: dashboardPayload.batchPerformance.labels,
                datasets: [{
                    label: 'Seat Fill %',
                    data: dashboardPayload.batchPerformance.fillRate,
                    backgroundColor: 'rgba(37, 99, 235, 0.8)',
                    borderRadius: 12
                }, {
                    label: 'Attendance %',
                    data: dashboardPayload.batchPerformance.attendanceRate,
                    backgroundColor: 'rgba(16, 185, 129, 0.78)',
                    borderRadius: 12
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#475569', usePointStyle: true, pointStyle: 'circle' } },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.96)',
                        padding: 12,
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0',
                        cornerRadius: 14
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748b' } },
                    y: { beginAtZero: true, max: 100, grid: { color: 'rgba(148, 163, 184, 0.12)' }, ticks: { color: '#64748b', callback: value => value + '%' } }
                }
            }
        });
    }

    const categoryCanvas = document.getElementById('categoryPieChart');
    if (categoryCanvas && dashboardPayload.categoryDistribution) {
        const catLabels = dashboardPayload.categoryDistribution.labels;
        const catValues = dashboardPayload.categoryDistribution.values;
        new Chart(categoryCanvas, {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catValues,
                    backgroundColor: ['#10b981','#2563eb','#f59e0b','#ef4444','#7c3aed','#94a3b8'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.96)', titleColor: '#fff', bodyColor: '#e2e8f0' }
                }
            }
        });
    }

    document.querySelectorAll('.loading-surface').forEach(surface => {
        surface.classList.remove('loading-surface');
    });
}

document.addEventListener('DOMContentLoaded', initDashboardCharts);
</script>

</body>
</html>
<?php $conn->close(); ?>