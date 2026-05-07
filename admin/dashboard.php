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
    $sql .= " AND (courses.category IN ('Long Term NSQF', 'Short Term NSQF') OR courses.course_type IN ('Long Term NSQF', 'Short Term NSQF'))";
}

// Add category filter
if ($filter_category !== 'all') {
    $sql .= " AND category = ?";
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
        training_center, link_published, course_description
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_sql);
    // FIX: 16 variables = 15 's' + 1 'i' (link_published) + 1 's' (course_description) = "ssssssssssssssis"
    $stmt->bind_param("ssssssssssssssis", 
        $course_name, $course_code, $course_abbreviation, $eligibility, $duration, $training_fees, $category,
        $start_date, $end_date, $description_url, $description_pdf, $apply_link, $course_coordinator,
        $training_center, $link_published, $course_description
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
    $stats_query = $conn->query("SELECT COUNT(*) as count FROM courses WHERE category IN ('Long Term NSQF', 'Short Term NSQF') OR course_type IN ('Long Term NSQF', 'Short Term NSQF')");
    $total_courses = $stats_query ? $stats_query->fetch_assoc()['count'] : 0;
} else {
    $stats_query = $conn->query("SELECT COUNT(*) as count FROM courses");
    $total_courses = $stats_query ? $stats_query->fetch_assoc()['count'] : 0;
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
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 10px;
                max-width: calc(100% - 20px);
            }
            
            .modal-header {
                padding: 1.5rem 1.5rem 1rem;
            }
            
            .modal-body {
                padding: 1.5rem;
            }
            
            .modal-footer {
                padding: 1rem 1.5rem 1.5rem;
                flex-direction: column;
            }
            
            .form-grid-2, .form-grid-3 {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

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
                        <i class="fas fa-graduation-cap"></i> NSQF Template Management
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="manage_nsqf_templates.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Manage Course Templates
                        </a>
                        <div class="alert alert-info" style="margin: 0; flex: 1;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Note:</strong> As an NSQF Course Manager, you can create and manage course templates. Course Coordinators will use your templates to create actual courses.
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Courses Table -->
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
                                <option value="Long Term NSQF" <?= $filter_category === 'Long Term NSQF' ? 'selected' : '' ?>>Long Term NSQF</option>
                                <option value="Short Term NSQF" <?= $filter_category === 'Short Term NSQF' ? 'selected' : '' ?>>Short Term NSQF</option>
                                <option value="Short-Term Non-NSQF" <?= $filter_category === 'Short-Term Non-NSQF' ? 'selected' : '' ?>>Short-Term Non-NSQF</option>
                                <option value="Internship Program" <?= $filter_category === 'Internship Program' ? 'selected' : '' ?>>Internship Program</option>
                                <?php else: ?>
                                <option value="Long Term NSQF" <?= $filter_category === 'Long Term NSQF' ? 'selected' : '' ?>>Long Term NSQF</option>
                                <option value="Short Term NSQF" <?= $filter_category === 'Short Term NSQF' ? 'selected' : '' ?>>Short Term NSQF</option>
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
            <div class="content-card">
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
                        <i class="fas fa-eye"></i> View Only - Use Course Templates to create templates for coordinators
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
                                                     <a href="students.php?filter_course_id=<?php echo urlencode($row['id']); ?>" 
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
                                <option value="Long Term NSQF">Long Term NSQF</option>
                                <option value="Short Term NSQF">Short Term NSQF</option>
                                <option value="Short-Term Non-NSQF">Short-Term Non-NSQF</option>
                                <option value="Internship Program">Internship Program</option>
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

<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
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
    
    const categorySelect = document.getElementById('add_category_dash');
    if (categorySelect) {
        // Restrict categories for NSQF managers
        if (isNSQFManager) {
            const options = categorySelect.querySelectorAll('option');
            options.forEach(option => {
                if (option.value && !['Long Term NSQF', 'Short Term NSQF'].includes(option.value)) {
                    option.style.display = 'none';
                }
            });
        }
        
        // Add change event for template integration
        categorySelect.addEventListener('change', function() {
            handleCategoryChangeDash(this.value);
        });
    }
});

// Handle category change for template integration
function handleCategoryChangeDash(category) {
    const courseNameInput = document.getElementById('add_course_name_dash');
    const courseNameTemplate = document.getElementById('add_course_name_template_dash');
    const eligibilityField = document.getElementById('add_eligibility_dash');
    
    const isCourseCoordinator = <?php echo json_encode(isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'course_coordinator'); ?>;
    const isNSQFManager = <?php echo json_encode(isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'nsqf_course_manager'); ?>;
    
    if (['Long Term NSQF', 'Short Term NSQF'].includes(category)) {
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
</script>

</body>
</html>
<?php $conn->close(); ?>