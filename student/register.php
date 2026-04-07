<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';

// LINK-ONLY ACCESS: Require course_id parameter
// Support both 'course_id' and 'course' parameters for backward compatibility
$selected_course_id = $_GET['course_id'] ?? $_GET['course'] ?? '';
/*  */
// If no course_id provided, show error and redirect
if (empty($selected_course_id)) {
    $_SESSION['error'] = 'Invalid access! Registration is only available through course registration links.';
    header('Location: ' . APP_URL . '/public/courses.php');
    exit();
}

// Fetch course details - REQUIRED for link-based registration
// Support both numeric ID and course code
$course_details = null;
$stmt = false;

// Check if it's a numeric ID or course code
if (is_numeric($selected_course_id)) {
    // Numeric ID
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $selected_course_id);
    }
} else {
    // Course code (e.g., 'sas', 'WD101')
    $stmt = $conn->prepare("SELECT * FROM courses WHERE (course_code = ? OR course_abbreviation = ?)");
    if ($stmt) {
        $stmt->bind_param("ss", $selected_course_id, $selected_course_id);
    }
}

// Check if prepare was successful
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $conn->error;
    header('Location: ' . APP_URL . '/public/courses.php');
    exit();
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Invalid or inactive course. Please select a valid course from the courses page.';
    header('Location: ' . APP_URL . '/public/courses.php');
    exit();
}

$course_details = $result->fetch_assoc();

// Check if the registration link is active
if (!isset($course_details['link_published']) || $course_details['link_published'] != 1) {
    $_SESSION['error'] = 'Registration for this course is currently closed. Please contact the administration for more information.';
    header('Location: ' . APP_URL . '/public/courses.php');
    exit();
}

$selected_course = $course_details['course_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - NIELIT Bhubaneswar</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #0d47a1;
            --secondary-blue: #1565c0;
            --accent-gold: #ffc107;
            --light-bg: #f8f9fa;
            --text-dark: #212529;
            --text-muted: #6c757d;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-dark);
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }

        /* ===== TOP BAR (Gov Info) - MATCHING INDEX.PHP ===== */
        .top-bar {
            background-color: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 8px 0;
            font-size: 0.85rem;
        }
        
        .gov-logos img {
            height: 45px;
            width: auto;
        }

        .ministry-text {
            font-weight: 600;
            color: var(--text-dark);
            line-height: 1.2;
        }

        /* ===== MAIN NAVBAR - MATCHING INDEX.PHP ===== */
        .navbar {
            background-color: var(--primary-blue);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            padding: 0.5rem 1rem;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: #fff !important;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--accent-gold) !important;
        }
        /* ============================================
           REGISTRATION FORM - ULTRA MODERN STYLING
           ============================================ */
        
        .registration-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Page Title */
        .page-title {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInDown 0.8s ease-out;
        }
        
        .page-title h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #0d47a1;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-title p {
            color: #64748b;
            font-size: 1.1rem;
        }
        
        /* ===== PROGRESS INDICATOR ===== */
        .progress-indicator {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 800px;
            margin: 0 auto 50px;
            padding: 0 20px;
            position: relative;
        }
        
        .progress-indicator::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 20%;
            right: 20%;
            height: 4px;
            background: #e2e8f0;
            z-index: 0;
        }
        
        .progress-line {
            position: absolute;
            top: 25px;
            left: 20%;
            height: 4px;
            background: linear-gradient(90deg, #0d47a1 0%, #1976d2 100%);
            transition: width 0.5s ease;
            z-index: 1;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .progress-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 4px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            color: #94a3b8;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .progress-step.active .progress-circle {
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            border-color: #0d47a1;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 16px rgba(13, 71, 161, 0.4);
        }
        
        .progress-step.completed .progress-circle {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-color: #10b981;
            color: white;
        }
        
        .progress-step.completed .progress-circle i {
            display: block;
        }
        
        .progress-step .progress-circle span {
            display: block;
        }
        
        .progress-step.completed .progress-circle span {
            display: none;
        }
        
        .progress-label {
            margin-top: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-align: center;
            transition: color 0.3s ease;
        }
        
        .progress-step.active .progress-label {
            color: #0d47a1;
        }
        
        .progress-step.completed .progress-label {
            color: #10b981;
        }
        
        /* ===== HIERARCHICAL LEVEL STRUCTURE ===== */
        .registration-level-section {
            margin-bottom: 48px;
            position: relative;
        }
        
        .registration-level-section:nth-child(1) {
            animation-delay: 0.1s;
        }
        
        .registration-level-section:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .registration-level-section:nth-child(3) {
            animation-delay: 0.3s;
        }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .level-header {
            text-align: center;
            margin-bottom: 32px;
            padding: 24px;
            background: linear-gradient(135deg, #f8fafc 0%, #e3f2fd 100%);
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }
        
        .level-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .level-header:hover::before {
            left: 100%;
        }
        
        .level-badge {
            display: inline-block;
            padding: 8px 24px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: fadeInDown 0.6s ease-out;
            position: relative;
        }
        
        .level-badge.level-1 {
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            color: white;
        }
        
        .level-badge.level-2 {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }
        
        .level-badge.level-3 {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
        }
        
        .level-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0d47a1;
            margin: 12px 0 8px;
        }
        
        .level-subtitle {
            color: #64748b;
            font-size: 1rem;
            margin: 0;
        }
        
        /* Level Indicator at Top */
        .level-indicator {
            animation: fadeInDown 0.6s ease-out;
        }
        
        /* Form Sections */
        .form-section {
            background: white;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 28px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            border-left: 5px solid #0d47a1;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .form-section::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(135deg, #0d47a1, #1976d2, #06b6d4);
            border-radius: 16px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }
        
        .form-section:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        
        .form-section:hover::before {
            opacity: 0.1;
        }
        
        /* Section Header */
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 18px;
            border-bottom: 2px solid #e3f2fd;
        }
        
        .section-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            box-shadow: 0 4px 12px rgba(13, 71, 161, 0.3);
            transition: transform 0.3s ease;
        }
        
        .form-section:hover .section-icon {
            transform: rotate(5deg) scale(1.05);
        }
        
        .section-icon i {
            color: white;
            font-size: 22px;
        }
        
        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: #0d47a1;
            margin: 0;
            line-height: 1.2;
        }
        
        .section-subtitle {
            font-size: 14px;
            color: #64748b;
            margin: 4px 0 0 0;
            font-weight: 400;
        }
        
        /* Form Labels */
        .form-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
            font-size: 14px;
            display: block;
            transition: color 0.3s ease;
        }
        
        .form-control:focus + .form-label,
        .form-select:focus + .form-label {
            color: #0d47a1;
        }
        
        .required-mark {
            color: #dc2626;
            margin-left: 3px;
            font-weight: 700;
        }
        
        /* Form Controls with Enhanced Styling */
        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            font-size: 14px;
            background: #ffffff;
            color: #1e293b;
            position: relative;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.1);
            outline: none;
            background: #f8fafc;
            transform: translateY(-1px);
        }
        
        .form-control:hover, .form-select:hover {
            border-color: #1976d2;
        }
        
        /* Input validation states */
        .form-control.is-valid, .form-select.is-valid {
            border-color: #10b981;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2310b981' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px 16px;
            padding-right: 40px;
        }
        
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: #ef4444;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23ef4444'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23ef4444' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px 16px;
            padding-right: 40px;
        }
        
        .invalid-feedback, .valid-feedback {
            display: none;
            font-size: 13px;
            margin-top: 6px;
        }
        
        .form-control.is-invalid ~ .invalid-feedback,
        .form-select.is-invalid ~ .invalid-feedback {
            display: block;
            color: #ef4444;
        }
        
        .form-control.is-valid ~ .valid-feedback,
        .form-select.is-valid ~ .valid-feedback {
            display: block;
            color: #10b981;
        }
        
        /* Course Info Card */
        .course-info-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 28px;
            box-shadow: 0 4px 12px rgba(13, 71, 161, 0.15);
            border: 2px solid #90caf9;
            animation: slideInDown 0.6s ease-out;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .course-info-card h5 {
            color: #0d47a1;
            font-weight: 700;
            margin-bottom: 16px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .course-info-card .row {
            align-items: center;
        }
        
        .course-info-card strong {
            color: #1e293b;
            font-weight: 600;
        }
        
        .course-info-card .badge {
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
        }
        
        /* File Upload Enhanced Styling */
        .file-upload-wrapper {
            position: relative;
        }
        
        .form-control[type="file"] {
            padding: 10px 14px;
            cursor: pointer;
            position: relative;
        }
        
        .form-control[type="file"]::-webkit-file-upload-button {
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 12px;
            transition: all 0.3s ease;
        }
        
        .form-control[type="file"]::-webkit-file-upload-button:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(13, 71, 161, 0.3);
        }
        
        .file-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px dashed #cbd5e0;
            display: none;
            align-items: center;
            gap: 10px;
        }
        
        .file-preview.show {
            display: flex;
        }
        
        .file-preview-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        
        .file-preview-info {
            flex: 1;
        }
        
        .file-preview-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 13px;
            word-break: break-word;
        }
        
        .file-preview-size {
            font-size: 12px;
            color: #64748b;
        }
        
        .file-preview-remove {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        .file-preview-remove:hover {
            background: #dc2626;
            transform: scale(1.05);
        }
        
        /* Image preview specific styles */
        .file-preview-image-container {
            width: 100%;
            text-align: center;
            margin-bottom: 10px;
            padding: 10px;
            background: white;
            border-radius: 8px;
        }
        
        .file-preview-image-container img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid #0d47a1;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            object-fit: contain;
        }
        
        /* ===== DOCUMENT CATEGORY STYLING ===== */
        .document-category {
            background: #f8fafc;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .document-category:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }
        
        .document-category.mandatory {
            border-left: 5px solid #dc2626;
            background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%);
        }
        
        .document-category.optional {
            border-left: 5px solid #3b82f6;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        }
        
        .category-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .category-title i {
            color: #0d47a1;
            font-size: 20px;
        }
        
        .required-badge {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: auto;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
        }
        
        .optional-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: auto;
            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
        }
        
        .document-category .form-group {
            margin-bottom: 20px;
        }
        
        .document-category .form-group:last-child {
            margin-bottom: 0;
        }
        
        .document-category .text-muted {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            font-size: 13px;
            color: #64748b;
        }
        
        .document-category .text-muted i {
            color: #3b82f6;
        }
        
        /* Responsive adjustments for document categories */
        @media (max-width: 768px) {
            .document-category {
                padding: 16px;
                margin-bottom: 16px;
            }
            
            .category-title {
                font-size: 16px;
                flex-wrap: wrap;
            }
            
            .required-badge,
            .optional-badge {
                margin-left: 0;
                margin-top: 8px;
            }
        }
        
        /* Education Table */
        .education-table {
            margin-top: 20px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .education-table table {
            margin-bottom: 0;
        }
        
        .education-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #e3f2fd 100%);
            font-weight: 700;
            color: #0d47a1;
            font-size: 13px;
            padding: 14px 10px;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .education-table td {
            padding: 10px;
            vertical-align: middle;
            border-color: #e2e8f0;
        }
        
        .education-table input {
            font-size: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
        }
        
        .education-table input:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 0 2px rgba(13, 71, 161, 0.1);
            outline: none;
        }
        
        .education-table select {
            font-size: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            background: white;
            cursor: pointer;
        }
        
        .education-table select:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 0 2px rgba(13, 71, 161, 0.1);
            outline: none;
        }
        
        .education-table select:hover {
            border-color: #1976d2;
        }
        
        /* Enhanced dropdown styling */
        .form-select-sm {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 12px 12px;
            padding-right: 32px;
        }
        
        .form-select-sm:focus {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%230d47a1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        }
        
        /* Custom input styling for "Other" selections */
        .education-table input.custom-other-input {
            background: linear-gradient(135deg, #fff7ed 0%, #fef3c7 100%);
            border: 2px solid #f59e0b;
            font-style: italic;
        }
        
        .education-table input.custom-other-input:focus {
            border-color: #d97706;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
            background: #ffffff;
            font-style: normal;
        }
        
        .education-table input.custom-other-input::placeholder {
            color: #92400e;
            font-style: italic;
        }
        
        /* Buttons */
        .btn-add-row {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .btn-add-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .btn-add-row:active {
            transform: translateY(0);
        }
        
        .btn-remove-row {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-remove-row:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
        }
        
        .btn-register {
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            color: white;
            padding: 16px 48px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(13, 71, 161, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-register::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }
        
        .btn-register:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(13, 71, 161, 0.4);
            color: white;
        }
        
        .btn-register:active {
            transform: translateY(-1px);
        }
        
        .btn-register:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-register:disabled:hover {
            transform: none;
            box-shadow: 0 4px 16px rgba(13, 71, 161, 0.3);
        }
        
        /* File Upload Styling */
        .form-control[type="file"] {
            padding: 10px 14px;
            cursor: pointer;
        }
        
        .form-control[type="file"]::-webkit-file-upload-button {
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 12px;
            transition: all 0.3s ease;
        }
        
        .form-control[type="file"]::-webkit-file-upload-button:hover {
            transform: scale(1.05);
        }
        
        /* Small Text */
        .text-muted {
            color: #64748b;
            font-size: 13px;
            margin-top: 6px;
            display: block;
        }
        
        /* Grid Layouts */
        .row {
            margin-left: -12px;
            margin-right: -12px;
        }
        
        .row > [class*="col-"] {
            padding-left: 12px;
            padding-right: 12px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .registration-container {
                padding: 0 15px;
                margin: 20px auto;
            }
            
            .form-section {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .section-header {
                flex-direction: column;
                text-align: center;
                align-items: center;
            }
            
            .section-icon {
                margin-right: 0;
                margin-bottom: 12px;
            }
            
            .page-title h1 {
                font-size: 1.8rem;
            }
            
            .btn-register {
                width: 100%;
                justify-content: center;
            }
            
            .education-table {
                font-size: 12px;
            }
            
            .education-table th,
            .education-table td {
                padding: 8px 4px;
            }
            
            /* Level Structure Mobile */
            .level-header {
                padding: 16px;
                margin-bottom: 24px;
            }
            
            .level-badge {
                padding: 6px 16px;
                font-size: 0.75rem;
            }
            
            .level-title {
                font-size: 1.4rem;
            }
            
            .level-subtitle {
                font-size: 0.9rem;
            }
            
            .registration-level-section {
                margin-bottom: 32px;
            }
            
            /* Progress Indicator Mobile */
            .progress-indicator {
                padding: 0 10px;
            }
            
            .progress-circle {
                width: 40px;
                height: 40px;
                font-size: 14px;
            }
            
            .progress-label {
                font-size: 11px;
            }
            
            .progress-indicator::before {
                left: 15%;
                right: 15%;
            }
            
            .progress-line {
                left: 15%;
            }
        }
        
        /* Loading State */
        .btn-register:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Focus Visible for Accessibility */
        *:focus-visible {
            outline: 2px solid #0d47a1;
            outline-offset: 2px;
        }
        
        /* Locked Fields Styling */
        .form-control[readonly] {
            background-color: #f0f9ff !important;
            cursor: not-allowed;
            border-color: #90caf9;
            color: #0d47a1;
            font-weight: 600;
        }
        
        .form-control[readonly]:focus {
            box-shadow: none;
            border-color: #90caf9;
        }
        
        /* Smooth Scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Navigation Buttons */
        .form-navigation {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-top: 40px;
            padding: 30px 0;
        }
        
        .btn-nav {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white !important;
            padding: 16px 48px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(108, 117, 125, 0.4);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .btn-nav:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(108, 117, 125, 0.5);
            color: white !important;
        }
        
        .btn-nav:active {
            transform: translateY(-1px);
        }
        
        .btn-next {
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%) !important;
            box-shadow: 0 4px 16px rgba(13, 71, 161, 0.4) !important;
        }
        
        .btn-next:hover {
            box-shadow: 0 8px 24px rgba(13, 71, 161, 0.5) !important;
            background: linear-gradient(135deg, #1565c0 0%, #1e88e5 100%) !important;
        }
        
        .btn-previous {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        
        /* Hide/Show Levels */
        .registration-level-section {
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Tooltip for help text */
        .help-tooltip {
            position: relative;
            display: inline-block;
            margin-left: 5px;
            cursor: help;
        }
        
        .help-tooltip i {
            color: #64748b;
            font-size: 14px;
        }
        
        .help-tooltip:hover i {
            color: #0d47a1;
        }
        
        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Success checkmark animation */
        @keyframes checkmark {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .checkmark-icon {
            animation: checkmark 0.5s ease-out;
        }
    </style>
</head>
<body>

<!-- TOP BAR WITH LOGOS - MATCHING INDEX.PHP -->
<div class="top-bar">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex align-items-center justify-content-md-start justify-content-center text-header-group">
                <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo" class="me-3" style="height: 50px;">
                <div>
                    <div class="fw-bold text-primary d-none d-sm-block">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर</div>
                    <div class="fw-bold text-dark">National Institute of Electronics & Information Technology, Bhubaneswar</div>
                </div>
            </div>
            <div class="col-md-4 d-flex justify-content-md-end justify-content-center gov-logos">
                <div class="text-end me-3 d-none d-lg-block">
                    <small class="d-block fw-bold text-secondary">Ministry of Electronics & IT</small>
                    <small class="d-block text-secondary">Government of India</small>
                </div>
                <img src="<?php echo APP_URL; ?>/assets/images/National-Emblem.png" alt="Gov India" style="height: 50px;">
            </div>
        </div>
    </div>
</div>

<!-- MAIN NAVBAR - MATCHING INDEX.PHP -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="<?php echo APP_URL; ?>/index.php">
            <i class="fas fa-university me-2"></i> NIELIT
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/public/courses.php">Courses</a></li>
                <li class="nav-item"><a class="nav-link active" href="#">Registration</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/student/login.php">Student Portal</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/public/contact.php">Contact</a></li>
            </ul>
        </div>
    </div>
</nav>

<?php
// Display session messages as toasts
if (isset($_SESSION['success'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', function() { toast.success('" . addslashes($_SESSION['success']) . "'); });</script>";
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', function() { toast.error('" . addslashes($_SESSION['error']) . "'); });</script>";
    unset($_SESSION['error']);
}
if (isset($_SESSION['warning'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', function() { toast.warning('" . addslashes($_SESSION['warning']) . "'); });</script>";
    unset($_SESSION['warning']);
}
if (isset($_SESSION['info'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', function() { toast.info('" . addslashes($_SESSION['info']) . "'); });</script>";
    unset($_SESSION['info']);
}
?>

<div class="registration-container">
    <div class="page-title">
        <div class="level-indicator mb-3">
            <span class="badge bg-primary px-4 py-2" style="font-size: 0.9rem; letter-spacing: 1px;">REGISTRATION PORTAL</span>
        </div>
        <h1><i class="fas fa-user-graduate"></i> Student Registration</h1>
        <p>Complete the 3-level registration process to enroll in NIELIT courses</p>
        <div style="height: 4px; width: 80px; background: linear-gradient(90deg, #ffc107 0%, #ff9800 100%); margin: 20px auto 0; border-radius: 2px;"></div>
    </div>

    <!-- Progress Indicator -->
    <div class="progress-indicator">
        <div class="progress-line" id="progressLine" style="width: 0%;"></div>
        
        <div class="progress-step active" data-step="1">
            <div class="progress-circle">
                <span>1</span>
                <i class="fas fa-check checkmark-icon" style="display: none;"></i>
            </div>
            <div class="progress-label">Course & Personal</div>
        </div>
        
        <div class="progress-step" data-step="2">
            <div class="progress-circle">
                <span>2</span>
                <i class="fas fa-check checkmark-icon" style="display: none;"></i>
            </div>
            <div class="progress-label">Contact & Address</div>
        </div>
        
        <div class="progress-step" data-step="3">
            <div class="progress-circle">
                <span>3</span>
                <i class="fas fa-check checkmark-icon" style="display: none;"></i>
            </div>
            <div class="progress-label">Academic & Documents</div>
        </div>
    </div>

    <!-- Course Info Card - Always Shown -->
    <div class="course-info-card">
        <h5><i class="fas fa-graduation-cap me-2"></i>Selected Course (Locked)</h5>
        <div class="row">
            <div class="col-md-6">
                <strong>Course Name:</strong> <?php echo htmlspecialchars($course_details['course_name']); ?>
            </div>
            <div class="col-md-3">
                <strong>Code:</strong> <span class="badge bg-primary"><?php echo htmlspecialchars($course_details['course_code']); ?></span>
            </div>
            <div class="col-md-3">
                <strong>Fees:</strong> <?php echo isset($course_details['training_fees']) && !empty($course_details['training_fees']) ? htmlspecialchars($course_details['training_fees']) : '₹0'; ?>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12">
                <strong>Training Centre:</strong> <?php echo htmlspecialchars($course_details['training_center'] ?? 'NIELIT BHUBANESWAR'); ?>
            </div>
        </div>
        <div class="alert alert-info mt-3 mb-0" style="font-size: 0.9rem;">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Note:</strong> Course and training centre are locked as you accessed this page via a registration link. You cannot change the course selection.
        </div>
    </div>

    <?php if (!empty($course_details['course_flyer']) && file_exists(__DIR__ . '/../' . $course_details['course_flyer'])): ?>
    <!-- Course Flyer Section -->
    <div class="course-flyer-section" style="margin-bottom: 40px;">
        <div class="content-card" style="background: white; border-radius: 16px; padding: 32px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;">
            <div class="section-header" style="display: flex; align-items: center; margin-bottom: 24px; padding-bottom: 18px; border-bottom: 2px solid #e3f2fd;">
                <div class="section-icon" style="width: 50px; height: 50px; background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 16px; box-shadow: 0 4px 12px rgba(13, 71, 161, 0.3);">
                    <i class="fas fa-image" style="color: white; font-size: 22px;"></i>
                </div>
                <div>
                    <h3 style="font-size: 22px; font-weight: 700; color: #0d47a1; margin: 0; line-height: 1.2;">Course Flyer</h3>
                    <p style="font-size: 14px; color: #64748b; margin: 4px 0 0 0; font-weight: 400;">View detailed course information</p>
                </div>
            </div>
            
            <div class="flyer-container" style="text-align: center;">
                <img src="<?php echo APP_URL . '/' . htmlspecialchars($course_details['course_flyer']); ?>" 
                     alt="<?php echo htmlspecialchars($course_details['course_name']); ?> Flyer" 
                     class="course-flyer-image"
                     style="max-width: 100%; height: auto; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); cursor: pointer; transition: transform 0.3s ease;"
                     onclick="openFlyerModal(this.src)">
                <div style="margin-top: 16px;">
                    <a href="<?php echo APP_URL . '/' . htmlspecialchars($course_details['course_flyer']); ?>" 
                       download 
                       class="btn btn-primary" 
                       style="background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%); color: white; padding: 12px 32px; border-radius: 8px; font-weight: 600; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(13, 71, 161, 0.3);">
                        <i class="fas fa-download"></i> Download Flyer
                    </a>
                    <button type="button" 
                            class="btn btn-secondary" 
                            onclick="openFlyerModal('<?php echo APP_URL . '/' . htmlspecialchars($course_details['course_flyer']); ?>')"
                            style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%); color: white; padding: 12px 32px; border-radius: 8px; font-weight: 600; border: none; margin-left: 12px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);">
                        <i class="fas fa-expand"></i> View Full Size
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Flyer Modal -->
    <div id="flyerModal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9);" onclick="closeFlyerModal()">
        <span style="position: absolute; top: 20px; right: 40px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 10001;" onclick="closeFlyerModal()">&times;</span>
        <img id="flyerModalImage" style="margin: auto; display: block; max-width: 90%; max-height: 90%; margin-top: 50px; border-radius: 8px; box-shadow: 0 8px 32px rgba(0,0,0,0.5);">
    </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo APP_URL; ?>/student/submit_registration.php" enctype="multipart/form-data" id="registrationForm">
        <!-- LEVEL 1: COURSE & PERSONAL INFORMATION -->
        <div class="registration-level-section" id="level1" style="display: block;">
            <div class="level-header">
                <span class="level-badge level-1">LEVEL 1</span>
                <h2 class="level-title">Course Selection & Personal Information</h2>
                <p class="level-subtitle">Choose your course and provide basic personal details</p>
            </div>
            
            <!-- Course Selection Section -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Course Selection</h3>
                        <p class="section-subtitle">Your selected course (locked via registration link)</p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Training Centre <span class="required-mark">*</span></label>
                        <!-- Locked Training Centre -->
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($course_details['training_center'] ?? 'NIELIT BHUBANESWAR'); ?>" readonly style="background-color: #f0f9ff; cursor: not-allowed;">
                        <input type="hidden" name="training_center" value="<?php echo htmlspecialchars($course_details['training_center'] ?? 'NIELIT BHUBANESWAR'); ?>">
                        <small class="text-muted"><i class="fas fa-lock"></i> Locked by registration link</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Select Course <span class="required-mark">*</span></label>
                        <!-- Locked Course -->
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($course_details['course_name']); ?> (<?php echo htmlspecialchars($course_details['course_code']); ?>)" readonly style="background-color: #f0f9ff; cursor: not-allowed;">
                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_details['id']); ?>">
                        <small class="text-muted"><i class="fas fa-lock"></i> Locked by registration link</small>
                    </div>
                </div>
            </div>

            <!-- Personal Information Section -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Personal Information</h3>
                        <p class="section-subtitle">Enter your basic personal details</p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Full Name <span class="required-mark">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="Enter your full name" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Father's Name <span class="required-mark">*</span></label>
                        <input type="text" class="form-control" name="father_name" placeholder="Enter father's name" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mother's Name <span class="required-mark">*</span></label>
                        <input type="text" class="form-control" name="mother_name" placeholder="Enter mother's name" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Date of Birth <span class="required-mark">*</span></label>
                        <input type="date" class="form-control" name="dob" id="dob" required onchange="calculateAge()">
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Age</label>
                        <input type="number" class="form-control" name="age" id="age" readonly>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Gender <span class="required-mark">*</span></label>
                        <select class="form-select" name="gender" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Marital Status <span class="required-mark">*</span></label>
                        <select class="form-select" name="marital_status" required>
                            <option value="">Select</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- LEVEL 2: CONTACT & ADDRESS INFORMATION -->
        <div class="registration-level-section" id="level2" style="display: none;">
            <div class="level-header">
                <span class="level-badge level-2">LEVEL 2</span>
                <h2 class="level-title">Contact & Address Information</h2>
                <p class="level-subtitle">Provide your contact details and residential address</p>
            </div>
            
            <!-- Contact Information Section -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Contact Information</h3>
                        <p class="section-subtitle">Provide your contact details</p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mobile Number <span class="required-mark">*</span></label>
                        <input type="tel" class="form-control" name="mobile" pattern="[0-9]{10}" placeholder="10-digit mobile number" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email Address <span class="required-mark">*</span></label>
                        <input type="email" class="form-control" name="email" placeholder="your.email@example.com" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Aadhar Number <span class="required-mark">*</span></label>
                        <input type="text" class="form-control" name="aadhar" pattern="[0-9]{12}" placeholder="12-digit Aadhar number" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nationality <span class="required-mark">*</span></label>
                        <select class="form-select" name="nationality" required>
                            <option value="Indian" selected>Indian</option>
                            <option value="Foreign">Foreign</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Additional Details Section -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Additional Details</h3>
                        <p class="section-subtitle">Category and other information</p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Religion <span class="required-mark">*</span></label>
                        <select class="form-select" name="religion" required>
                            <option value="">Select</option>
                            <option value="Hindu">Hindu</option>
                            <option value="Muslim">Muslim</option>
                            <option value="Christian">Christian</option>
                            <option value="Sikh">Sikh</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Category <span class="required-mark">*</span></label>
                        <select class="form-select" name="category" required>
                            <option value="">Select</option>
                            <option value="General">General</option>
                            <option value="OBC">OBC</option>
                            <option value="SC">SC</option>
                            <option value="ST">ST</option>
                            <option value="EWS">EWS</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Persons with Disabilities</label>
                        <select class="form-select" name="pwd_status">
                            <option value="No" selected>No</option>
                            <option value="Yes">Yes</option>
                        </select>
                        <small class="text-muted"><i class="fas fa-info-circle"></i> Optional disclosure</small>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Distinguishing Marks</label>
                        <input type="text" class="form-control" name="distinguishing_marks" 
                               placeholder="e.g., Birthmark on left arm" maxlength="255">
                        <small class="text-muted"><i class="fas fa-info-circle"></i> Optional - Any identifying marks</small>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">APAAR ID</label>
                        <input type="text" class="form-control" name="apaar_id" 
                               placeholder="Enter APAAR ID (optional)" maxlength="50">
                        <small class="text-muted"><i class="fas fa-info-circle"></i> Automated Permanent Academic Account Registry</small>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Position/Occupation <span class="required-mark">*</span></label>
                        <select class="form-select" name="position" required>
                            <option value="">Select</option>
                            <option value="Student">Student</option>
                            <option value="Researcher">Researcher</option>
                            <option value="Faculty">Faculty</option>
                            <option value="Industrial">Industrial</option>
                            <option value="Services Holder">Services Holder</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Address Section -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Address Details</h3>
                        <p class="section-subtitle">Enter your residential address</p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Address <span class="required-mark">*</span></label>
                        <textarea class="form-control" name="address" rows="2" placeholder="Enter your complete address" required></textarea>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">State <span class="required-mark">*</span></label>
                        <select class="form-select" name="state" id="state" required>
                            <option value="">Select State</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">City/District <span class="required-mark">*</span></label>
                        <select class="form-select" name="city" id="city" required>
                            <option value="">Select City</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Pincode <span class="required-mark">*</span></label>
                        <input type="text" class="form-control" name="pincode" pattern="[0-9]{6}" placeholder="6-digit pincode" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- LEVEL 3: ACADEMIC & DOCUMENTS -->
        <div class="registration-level-section" id="level3" style="display: none;">
            <div class="level-header">
                <span class="level-badge level-3">LEVEL 3</span>
                <h2 class="level-title">Academic Details & Document Upload</h2>
                <p class="level-subtitle">Educational qualifications, payment details, and required documents</p>
            </div>
            
            <!-- Academic Details Section -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Academic Details</h3>
                        <p class="section-subtitle">Educational qualifications and institution details</p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label">College/Institution Name</label>
                        <input type="text" class="form-control" name="college_name" placeholder="Enter your college/institution name">
                    </div>
                </div>
                
                <div class="table-responsive education-table">
                    <table class="table table-bordered" id="educationTable">
                        <thead>
                            <tr>
                                <th style="width: 5%;">Sl.</th>
                                <th style="width: 15%;">Exam Passed</th>
                                <th style="width: 15%;">Exam Name</th>
                                <th style="width: 12%;">Year</th>
                                <th style="width: 23%;">Institute/Board</th>
                                <th style="width: 15%;">Stream</th>
                                <th style="width: 10%;">%/CGPA</th>
                                <th style="width: 5%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>
                                    <select class="form-select form-select-sm" name="exam_passed[]" required onchange="updateExamName(this)">
                                        <option value="">Select Level</option>
                                        <option value="Primary">Primary (5th/8th)</option>
                                        <option value="Matriculation">Matriculation (10th)</option>
                                        <option value="Intermediate">Intermediate (+2/12th)</option>
                                        <option value="ITI">ITI</option>
                                        <option value="Diploma">Diploma</option>
                                        <option value="Graduation">Graduation</option>
                                        <option value="Post Graduation">Post Graduation</option>
                                        <option value="PhD">PhD/Doctorate</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" name="exam_name[]" required>
                                        <option value="">Select Exam</option>
                                        <!-- Options will be populated based on Exam Passed selection -->
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" name="year_of_passing[]" required>
                                        <option value="">Year</option>
                                        <!-- Years will be populated by JavaScript -->
                                    </select>
                                </td>
                                <td><input type="text" class="form-control form-control-sm" name="institute_name[]" placeholder="Board/University" required></td>
                                <td>
                                    <select class="form-select form-select-sm" name="stream[]" required onchange="handleStreamOther(this)">
                                        <option value="">Select Stream</option>
                                        <!-- General Streams -->
                                        <option value="Science">Science</option>
                                        <option value="Commerce">Commerce</option>
                                        <option value="Arts">Arts/Humanities</option>
                                        <option value="General">General</option>
                                        <option value="Vocational">Vocational</option>
                                        
                                        <!-- Engineering & Technology Streams -->
                                        <optgroup label="Engineering & Technology">
                                            <option value="Computer Science Engineering">Computer Science Engineering</option>
                                            <option value="Information Technology">Information Technology</option>
                                            <option value="Electronics & Communication Engineering">Electronics & Communication Engineering</option>
                                            <option value="Electrical Engineering">Electrical Engineering</option>
                                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                                            <option value="Civil Engineering">Civil Engineering</option>
                                            <option value="Chemical Engineering">Chemical Engineering</option>
                                            <option value="Aerospace Engineering">Aerospace Engineering</option>
                                            <option value="Automobile Engineering">Automobile Engineering</option>
                                            <option value="Biomedical Engineering">Biomedical Engineering</option>
                                            <option value="Biotechnology Engineering">Biotechnology Engineering</option>
                                            <option value="Environmental Engineering">Environmental Engineering</option>
                                            <option value="Industrial Engineering">Industrial Engineering</option>
                                            <option value="Instrumentation Engineering">Instrumentation Engineering</option>
                                            <option value="Marine Engineering">Marine Engineering</option>
                                            <option value="Mining Engineering">Mining Engineering</option>
                                            <option value="Petroleum Engineering">Petroleum Engineering</option>
                                            <option value="Production Engineering">Production Engineering</option>
                                            <option value="Textile Engineering">Textile Engineering</option>
                                            <option value="Agricultural Engineering">Agricultural Engineering</option>
                                            <option value="Food Technology">Food Technology</option>
                                            <option value="Metallurgical Engineering">Metallurgical Engineering</option>
                                            <option value="Materials Science Engineering">Materials Science Engineering</option>
                                            <option value="Robotics Engineering">Robotics Engineering</option>
                                            <option value="Artificial Intelligence & Machine Learning">Artificial Intelligence & Machine Learning</option>
                                            <option value="Data Science Engineering">Data Science Engineering</option>
                                            <option value="Cyber Security Engineering">Cyber Security Engineering</option>
                                            <option value="Software Engineering">Software Engineering</option>
                                            <option value="Network Engineering">Network Engineering</option>
                                            <option value="Embedded Systems">Embedded Systems</option>
                                            <option value="VLSI Design">VLSI Design</option>
                                            <option value="Nanotechnology">Nanotechnology</option>
                                            <option value="Renewable Energy Engineering">Renewable Energy Engineering</option>
                                        </optgroup>
                                        
                                        <!-- Computer Applications -->
                                        <optgroup label="Computer Applications">
                                            <option value="Computer Applications">Computer Applications</option>
                                            <option value="Information Systems">Information Systems</option>
                                            <option value="Computer Science">Computer Science</option>
                                            <option value="Software Development">Software Development</option>
                                            <option value="Web Development">Web Development</option>
                                            <option value="Mobile App Development">Mobile App Development</option>
                                            <option value="Database Management">Database Management</option>
                                            <option value="System Administration">System Administration</option>
                                        </optgroup>
                                        
                                        <!-- Management & Business -->
                                        <optgroup label="Management & Business">
                                            <option value="Management">Management</option>
                                            <option value="Business Administration">Business Administration</option>
                                            <option value="Marketing">Marketing</option>
                                            <option value="Finance">Finance</option>
                                            <option value="Human Resources">Human Resources</option>
                                            <option value="Operations Management">Operations Management</option>
                                            <option value="International Business">International Business</option>
                                            <option value="Entrepreneurship">Entrepreneurship</option>
                                        </optgroup>
                                        
                                        <!-- Pure Sciences -->
                                        <optgroup label="Pure Sciences">
                                            <option value="Physics">Physics</option>
                                            <option value="Chemistry">Chemistry</option>
                                            <option value="Mathematics">Mathematics</option>
                                            <option value="Biology">Biology</option>
                                            <option value="Biotechnology">Biotechnology</option>
                                            <option value="Microbiology">Microbiology</option>
                                            <option value="Biochemistry">Biochemistry</option>
                                            <option value="Environmental Science">Environmental Science</option>
                                            <option value="Statistics">Statistics</option>
                                            <option value="Applied Mathematics">Applied Mathematics</option>
                                        </optgroup>
                                        
                                        <!-- Other Specializations -->
                                        <option value="Other">Other</option>
                                    </select>
                                </td>
                                <td><input type="text" class="form-control form-control-sm" name="percentage[]" placeholder="85%" required></td>
                                <td><button type="button" class="btn-remove-row" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn-add-row" onclick="addEducationRow()">
                        <i class="fas fa-plus me-2"></i>Add More
                    </button>
                </div>
            </div>

            <!-- Payment Details Section (Optional) -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Payment Details <span class="badge bg-secondary ms-2" style="font-size: 0.7rem;">Optional</span></h3>
                        <p class="section-subtitle">Transaction information (if payment already made)</p>
                    </div>
                </div>
                
                <div class="alert alert-info mb-3" style="font-size: 0.9rem;">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> This section is optional. Fill only if you have already made the payment.
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">UTR/Transaction ID</label>
                        <input type="text" class="form-control" name="utr_number" placeholder="Enter UTR or Transaction ID (Optional)">
                        <small class="text-muted">Leave blank if payment not yet made</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Payment Receipt</label>
                        <input type="file" class="form-control" name="payment_receipt" accept="image/*,.pdf">
                        <small class="text-muted">Upload receipt if available</small>
                    </div>
                </div>
            </div>

            <!-- Document Upload Section -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Document Uploads</h3>
                        <p class="section-subtitle">Upload required documents (JPG, JPEG, or PDF format)</p>
                    </div>
                </div>
                
                <!-- Mandatory Documents: Identity Proof -->
                <div class="document-category mandatory">
                    <h4 class="category-title">
                        <i class="fas fa-id-card"></i> Identity Proof
                        <span class="required-badge">Required</span>
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Aadhar Card <span class="required-mark">*</span>
                        </label>
                        <input type="file" 
                               name="aadhar_card" 
                               id="aadhar_card"
                               class="form-control" 
                               accept=".jpg,.jpeg,.pdf"
                               required
                               data-category="aadhar">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Accepted formats: JPG, JPEG, PDF (Max 5MB)
                        </small>
                    </div>
                </div>
                
                <!-- Mandatory Documents: Educational Qualifications -->
                <div class="document-category mandatory">
                    <h4 class="category-title">
                        <i class="fas fa-graduation-cap"></i> Educational Qualifications
                        <span class="required-badge">Required</span>
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">
                            10th Marksheet/Certificate <span class="required-mark">*</span>
                        </label>
                        <input type="file" 
                               name="tenth_marksheet" 
                               id="tenth_marksheet"
                               class="form-control" 
                               accept=".jpg,.jpeg,.pdf"
                               required
                               data-category="tenth">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Accepted formats: JPG, JPEG, PDF (Max 5MB)
                        </small>
                    </div>
                </div>
                
                <!-- Optional Documents: 12th Marksheet -->
                <div class="document-category optional">
                    <h4 class="category-title">
                        <i class="fas fa-graduation-cap"></i> Higher Education
                        <span class="optional-badge">Optional</span>
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">12th Marksheet/Diploma Certificate</label>
                        <input type="file" 
                               name="twelfth_marksheet" 
                               id="twelfth_marksheet"
                               class="form-control" 
                               accept=".jpg,.jpeg,.pdf"
                               data-category="twelfth">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            If applicable
                        </small>
                    </div>
                </div>
                
                <!-- Optional Documents: Additional Documents -->
                <div class="document-category optional">
                    <h4 class="category-title">
                        <i class="fas fa-folder-plus"></i> Additional Documents
                        <span class="optional-badge">Optional</span>
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Caste Certificate</label>
                                <input type="file" 
                                       name="caste_certificate" 
                                       id="caste_certificate"
                                       class="form-control" 
                                       accept=".jpg,.jpeg,.pdf"
                                       data-category="caste">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    For applicable students only
                                </small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Graduation Certificate</label>
                                <input type="file" 
                                       name="graduation_certificate" 
                                       id="graduation_certificate"
                                       class="form-control" 
                                       accept=".jpg,.jpeg,.pdf"
                                       data-category="graduation">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    If applicable
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Other Supporting Documents</label>
                        <input type="file" 
                               name="other_documents" 
                               id="other_documents"
                               class="form-control" 
                               accept=".jpg,.jpeg,.pdf"
                               data-category="other">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Any additional supporting documents
                        </small>
                    </div>
                </div>
                
                <!-- Other Required Documents -->
                <div class="document-category mandatory">
                    <h4 class="category-title">
                        <i class="fas fa-images"></i> Photo & Signature
                        <span class="required-badge">Required</span>
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Passport Photo <span class="required-mark">*</span></label>
                                <input type="file" class="form-control" name="passport_photo" accept="image/*" required>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Recent passport size photo (JPG/PNG, max 5MB)
                                </small>
                                <small class="text-muted d-block mt-1" style="color: #10b981;">
                                    <i class="fas fa-check-circle"></i> 
                                    Preview will appear after selecting file
                                </small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Signature <span class="required-mark">*</span></label>
                                <input type="file" class="form-control" name="signature" accept="image/*" required>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Clear signature image (JPG/PNG, max 2MB)
                                </small>
                                <small class="text-muted d-block mt-1" style="color: #10b981;">
                                    <i class="fas fa-check-circle"></i> 
                                    Preview will appear after selecting file
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <!-- Navigation Buttons -->
        <div class="form-navigation text-center mb-5">
            <button type="button" class="btn-nav btn-previous" id="prevBtn" style="display: none;">
                <i class="fas fa-arrow-left me-2"></i>Previous
            </button>
            <button type="button" class="btn-nav btn-next" id="nextBtn">
                Next<i class="fas fa-arrow-right ms-2"></i>
            </button>
            <button type="submit" class="btn-register" id="submitBtn" style="display: none;">
                <i class="fas fa-paper-plane me-2"></i>Submit Registration
            </button>
        </div>
    </form>
</div>

<!-- FOOTER - MATCHING INDEX.PHP -->
<footer class="pt-5" style="background-color: #1a202c; color: #cbd5e0; font-size: 0.95rem;">
    <div class="container pb-4">
        <div class="row gy-4">
            <div class="col-lg-4 col-md-6">
                <h5 style="color: #fff; font-weight: 600; margin-bottom: 1.5rem; position: relative;">
                    Important Links
                </h5>
                <ul class="list-unstyled">
                    <li><a href="https://india.gov.in/" target="_blank" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>National Portal of India</a></li>
                    <li><a href="https://www.mygov.in/" target="_blank" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>MyGov</a></li>
                    <li><a href="https://rtionline.gov.in/" target="_blank" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>RTI Online</a></li>
                    <li><a href="http://meity.gov.in/" target="_blank" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>MeitY</a></li>
                    <li><a href="https://www.nielit.gov.in/" target="_blank" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>NIELIT HQ</a></li>
                </ul>
            </div>

            <div class="col-lg-4 col-md-6">
                <h5 style="color: #fff; font-weight: 600; margin-bottom: 1.5rem;">Quick Explore</h5>
                <ul class="list-unstyled">
                    <li><a href="<?php echo APP_URL; ?>/index.php" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>Home</a></li>
                    <li><a href="<?php echo APP_URL; ?>/public/courses.php" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>Courses</a></li>
                    <li><a href="<?php echo APP_URL; ?>/student/login.php" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>Student Portal</a></li>
                    <li><a href="<?php echo APP_URL; ?>/public/contact.php" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>Contact Us</a></li>
                </ul>
            </div>

            <div class="col-lg-4 col-md-12">
                <h5 style="color: #fff; font-weight: 600; margin-bottom: 1.5rem;">Contact Info</h5>
                <p class="small text-muted mb-3">National Institute of Electronics & Information Technology, Bhubaneswar</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-phone-alt me-2 text-warning"></i> 0674-2960354</li>
                    <li class="mb-2"><i class="fas fa-envelope me-2 text-warning"></i> dir-bbsr@nielit.gov.in</li>
                    <li class="mb-2"><i class="fas fa-clock me-2 text-warning"></i> Mon-Fri: 09:00 AM – 5:30 PM</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="copyright-bar text-center text-muted small" style="background-color: #111827; padding: 15px 0; border-top: 1px solid #2d3748;">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-md-start">
                    © 2025 NIELIT Bhubaneswar. All Rights Reserved.
                </div>
                <div class="col-md-6 text-md-end">
                    Designed & Developed by NIELIT Team
                </div>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== MULTI-STEP FORM DEBUG ===');
    console.log('DOM Content Loaded - Starting initialization');
    
// ===== MULTI-STEP FORM NAVIGATION =====
let currentStep = 1;
const totalSteps = 3;

function showStep(step) {
    console.log('=== showStep called with step:', step);
    
    // Check if all level elements exist
    for (let i = 1; i <= 3; i++) {
        const levelEl = document.getElementById('level' + i);
        console.log('Level ' + i + ' element:', levelEl ? 'FOUND' : 'NOT FOUND');
        if (levelEl) {
            console.log('Level ' + i + ' current display:', window.getComputedStyle(levelEl).display);
        }
    }
    
    // Hide all steps EXCEPT the one we want to show
    document.querySelectorAll('.registration-level-section').forEach((section, index) => {
        const levelNum = index + 1;
        if (levelNum !== step) {
            console.log('Hiding section:', section.id);
            section.style.display = 'none';
        }
    });
    
    // Show current step - FORCE IT
    const currentSection = document.getElementById('level' + step);
    if (currentSection) {
        console.log('Showing level' + step);
        currentSection.style.display = 'block';
        currentSection.style.visibility = 'visible';
        currentSection.style.opacity = '1';
        console.log('Level' + step + ' display after setting:', window.getComputedStyle(currentSection).display);
        
        // FORCE all form sections inside to be visible
        const formSections = currentSection.querySelectorAll('.form-section');
        console.log('Form sections found in level' + step + ':', formSections.length);
        formSections.forEach((section, index) => {
            section.style.display = 'block';
            section.style.visibility = 'visible';
            section.style.opacity = '1';
            console.log('Form section ' + index + ' display:', window.getComputedStyle(section).display);
        });
        
        // Scroll to top smoothly
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    } else {
        console.error('ERROR: Could not find level' + step + ' element!');
    }
    
    // Update progress indicator
    document.querySelectorAll('.progress-step').forEach((stepEl, index) => {
        if (index < step) {
            stepEl.classList.add('completed');
            stepEl.classList.remove('active');
        } else if (index === step - 1) {
            stepEl.classList.add('active');
            stepEl.classList.remove('completed');
        } else {
            stepEl.classList.remove('active', 'completed');
        }
    });
    
    // Update progress line
    const progressLine = document.getElementById('progressLine');
    const progressPercent = ((step - 1) / (totalSteps - 1)) * 100;
    progressLine.style.width = progressPercent + '%';
    console.log('Progress line width:', progressPercent + '%');
    
    // Update button visibility
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    
    console.log('Buttons found - Prev:', !!prevBtn, 'Next:', !!nextBtn, 'Submit:', !!submitBtn);
    
    if (step === 1) {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'inline-flex';
        submitBtn.style.display = 'none';
        console.log('Step 1: Showing Next button only');
    } else if (step === totalSteps) {
        prevBtn.style.display = 'inline-flex';
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'inline-flex';
        console.log('Step 3: Showing Prev and Submit buttons');
    } else {
        prevBtn.style.display = 'inline-flex';
        nextBtn.style.display = 'inline-flex';
        submitBtn.style.display = 'none';
        console.log('Step 2: Showing Prev and Next buttons');
    }
}

// Next button
const nextBtn = document.getElementById('nextBtn');
console.log('Next button element:', nextBtn ? 'FOUND' : 'NOT FOUND');
if (nextBtn) {
    nextBtn.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('=== Next button clicked, current step:', currentStep);
        
        if (currentStep < totalSteps) {
            // SIMPLIFIED VALIDATION - Just move forward
            console.log('Moving to step', currentStep + 1);
            currentStep++;
            showStep(currentStep);
        }
    });
    console.log('Next button click listener attached');
}

// Previous button
const prevBtn = document.getElementById('prevBtn');
console.log('Previous button element:', prevBtn ? 'FOUND' : 'NOT FOUND');
if (prevBtn) {
    prevBtn.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('=== Previous button clicked, current step:', currentStep);
        
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    });
    console.log('Previous button click listener attached');
}

// Initialize - show first step
console.log('=== Initializing multi-step form - calling showStep(1)');
showStep(1);
console.log('=== Initialization complete ===');

}); // End DOMContentLoaded

// ===== PROGRESS INDICATOR LOGIC =====
function updateProgress() {
    const sections = document.querySelectorAll('.registration-level-section');
    const steps = document.querySelectorAll('.progress-step');
    const progressLine = document.getElementById('progressLine');
    
    let completedSections = 0;
    
    sections.forEach((section, index) => {
        const inputs = section.querySelectorAll('input[required], select[required], textarea[required]');
        let filledInputs = 0;
        
        inputs.forEach(input => {
            if (input.type === 'file') {
                if (input.files.length > 0) filledInputs++;
            } else if (input.value.trim() !== '') {
                filledInputs++;
            }
        });
        
        const progress = inputs.length > 0 ? (filledInputs / inputs.length) : 0;
        
        if (progress > 0.5) {
            steps[index].classList.add('active');
            if (progress === 1) {
                steps[index].classList.add('completed');
                completedSections++;
            } else {
                steps[index].classList.remove('completed');
            }
        } else {
            steps[index].classList.remove('active', 'completed');
        }
    });
    
    // Update progress line
    const progressPercent = (completedSections / sections.length) * 60; // 60% is the width between first and last step
    progressLine.style.width = progressPercent + '%';
}

// Update progress on input change
document.querySelectorAll('input, select, textarea').forEach(element => {
    element.addEventListener('input', updateProgress);
    element.addEventListener('change', updateProgress);
});

// Initialize year dropdowns and exam name functionality
document.addEventListener('DOMContentLoaded', function() {
    // Populate year dropdowns
    const currentYear = new Date().getFullYear();
    const yearSelects = document.querySelectorAll('select[name="year_of_passing[]"]');
    yearSelects.forEach(select => {
        // Skip if already populated
        if (select.children.length > 1) return;
        
        for (let year = currentYear + 1; year >= 1990; year--) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            select.appendChild(option);
        }
    });
    
    // Add change listeners to existing exam passed selects
    const examPassedSelects = document.querySelectorAll('select[name="exam_passed[]"]');
    examPassedSelects.forEach(select => {
        select.addEventListener('change', function() {
            updateExamName(this);
        });
    });
    
    // Add change listeners to existing exam name selects
    const examNameSelects = document.querySelectorAll('select[name="exam_name[]"]');
    examNameSelects.forEach(select => {
        select.addEventListener('change', function() {
            handleExamNameOther(this);
        });
    });
    
    // Add change listeners to existing stream selects
    const streamSelects = document.querySelectorAll('select[name="stream[]"]');
    streamSelects.forEach(select => {
        select.addEventListener('change', function() {
            handleStreamOther(this);
        });
    });
});

// Initial progress check
setTimeout(updateProgress, 500);

// ===== REAL-TIME VALIDATION =====
function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const name = field.name;
    
    // Remove previous validation classes
    field.classList.remove('is-valid', 'is-invalid');
    
    // Skip if empty and not required
    if (value === '' && !field.hasAttribute('required')) {
        return;
    }
    
    let isValid = true;
    
    // Email validation
    if (type === 'email' || name === 'email') {
        isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }
    
    // Mobile validation
    if (name === 'mobile') {
        isValid = /^[0-9]{10}$/.test(value);
    }
    
    // Aadhar validation
    if (name === 'aadhar') {
        isValid = /^[0-9]{12}$/.test(value);
    }
    
    // Pincode validation
    if (name === 'pincode') {
        isValid = /^[0-9]{6}$/.test(value);
    }
    
    // Required field validation
    if (field.hasAttribute('required') && value === '') {
        isValid = false;
    }
    
    // Apply validation class
    if (value !== '') {
        field.classList.add(isValid ? 'is-valid' : 'is-invalid');
    }
    
    return isValid;
}

// Add real-time validation to all inputs
document.querySelectorAll('input:not([type="file"]), select, textarea').forEach(field => {
    field.addEventListener('blur', function() {
        validateField(this);
        updateProgress();
    });
    
    field.addEventListener('input', function() {
        if (this.classList.contains('is-invalid') || this.classList.contains('is-valid')) {
            validateField(this);
        }
    });
});

// ===== DOCUMENT UPLOAD VALIDATION =====
/**
 * Validates document upload files for type and size
 * @param {HTMLInputElement} inputElement - The file input element
 * @returns {Object} - Validation result with valid flag and message
 */
function validateDocumentUpload(inputElement) {
    const file = inputElement.files[0];
    
    // If no file selected and field is not required, it's valid
    if (!file) {
        if (!inputElement.hasAttribute('required')) {
            return { valid: true };
        }
        return { 
            valid: false, 
            message: 'Please select a file to upload' 
        };
    }
    
    // Get file extension
    const fileName = file.name.toLowerCase();
    const fileExtension = fileName.substring(fileName.lastIndexOf('.'));
    const allowedExtensions = ['.jpg', '.jpeg', '.pdf'];
    
    // Validate file extension
    if (!allowedExtensions.includes(fileExtension)) {
        return {
            valid: false,
            message: 'Invalid file type. Only JPG, JPEG, and PDF files are allowed.'
        };
    }
    
    // Validate file size based on type
    const maxSize = fileExtension === '.pdf' ? 10 * 1024 * 1024 : 5 * 1024 * 1024; // 10MB for PDF, 5MB for images
    const maxSizeMB = fileExtension === '.pdf' ? 10 : 5;
    
    if (file.size > maxSize) {
        return {
            valid: false,
            message: `File size exceeds maximum limit of ${maxSizeMB}MB. Current size: ${(file.size / (1024 * 1024)).toFixed(2)}MB`
        };
    }
    
    // All validations passed
    return { valid: true };
}

/**
 * Displays inline error message for invalid file upload
 * @param {HTMLInputElement} inputElement - The file input element
 * @param {string} message - The error message to display
 */
function displayFileError(inputElement, message) {
    // Remove any existing error message
    const existingError = inputElement.parentElement.querySelector('.file-error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // Create and display error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'file-error-message invalid-feedback d-block';
    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i>${message}`;
    errorDiv.style.color = '#ef4444';
    errorDiv.style.fontSize = '13px';
    errorDiv.style.marginTop = '6px';
    errorDiv.style.display = 'block';
    
    inputElement.parentElement.appendChild(errorDiv);
    inputElement.classList.add('is-invalid');
    inputElement.classList.remove('is-valid');
}

/**
 * Clears error message for file input
 * @param {HTMLInputElement} inputElement - The file input element
 */
function clearFileError(inputElement) {
    const existingError = inputElement.parentElement.querySelector('.file-error-message');
    if (existingError) {
        existingError.remove();
    }
    inputElement.classList.remove('is-invalid');
}

// ===== FILE UPLOAD PREVIEW WITH VALIDATION =====
document.querySelectorAll('input[type="file"]').forEach(fileInput => {
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        
        // Clear previous errors
        clearFileError(this);
        
        if (file) {
            // Validate document uploads (only for categorized document fields)
            const category = this.getAttribute('data-category');
            if (category) {
                const validation = validateDocumentUpload(this);
                
                if (!validation.valid) {
                    // Display error and clear file selection
                    displayFileError(this, validation.message);
                    this.value = ''; // Clear the invalid file
                    
                    // Show toast notification
                    if (typeof toast !== 'undefined') {
                        toast.error(validation.message);
                    }
                    return;
                }
                
                // Mark as valid
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            }
            
            // Show file name and size
            const fileName = file.name;
            const fileSize = (file.size / 1024).toFixed(2) + ' KB';
            const fieldName = this.getAttribute('name');
            
            // Create or update preview
            let preview = this.parentElement.querySelector('.file-preview');
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'file-preview';
                this.parentElement.appendChild(preview);
            }
            
            const fileIcon = fileName.endsWith('.pdf') ? 'fa-file-pdf' : 'fa-file-image';
            
            // Check if this is passport photo or signature for image preview
            const isImageField = (fieldName === 'passport_photo' || fieldName === 'signature');
            const isImageFile = file.type.startsWith('image/');
            
            if (isImageField && isImageFile) {
                // Show actual image preview for passport photo and signature
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <div class="file-preview-image-container" style="width: 100%; text-align: center; margin-bottom: 10px;">
                            <img src="${e.target.result}" alt="${fieldName === 'passport_photo' ? 'Passport Photo' : 'Signature'}" 
                                 style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 2px solid #0d47a1; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        </div>
                        <div class="file-preview-icon">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i>
                        </div>
                        <div class="file-preview-info">
                            <div class="file-preview-name">${fileName}</div>
                            <div class="file-preview-size">${fileSize}</div>
                        </div>
                        <button type="button" class="file-preview-remove" onclick="clearFileInput(this)">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    `;
                    preview.classList.add('show');
                    preview.style.flexDirection = 'column';
                    preview.style.alignItems = 'center';
                };
                reader.readAsDataURL(file);
            } else {
                // Standard file preview for other documents
                preview.innerHTML = `
                    <div class="file-preview-icon">
                        <i class="fas ${fileIcon}"></i>
                    </div>
                    <div class="file-preview-info">
                        <div class="file-preview-name">${fileName}</div>
                        <div class="file-preview-size">${fileSize}</div>
                    </div>
                    <button type="button" class="file-preview-remove" onclick="clearFileInput(this)">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                preview.classList.add('show');
                preview.style.flexDirection = 'row';
            }
            
            // Validate field
            validateField(this);
            updateProgress();
        }
    });
});

function clearFileInput(button) {
    const preview = button.closest('.file-preview');
    const fileInput = preview.previousElementSibling;
    fileInput.value = '';
    preview.classList.remove('show');
    validateField(fileInput);
    updateProgress();
}

// Calculate age from DOB
function calculateAge() {
    const dob = document.getElementById('dob').value;
    if (dob) {
        const dobDate = new Date(dob);
        const today = new Date();
        let age = today.getFullYear() - dobDate.getFullYear();
        const monthDiff = today.getMonth() - dobDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
            age--;
        }
        document.getElementById('age').value = age;
        updateProgress();
    }
}

// Add education row
function addEducationRow() {
    const table = document.getElementById('educationTable').getElementsByTagName('tbody')[0];
    const rowCount = table.rows.length + 1;
    const newRow = table.insertRow();
    
    // Generate year options
    const currentYear = new Date().getFullYear();
    let yearOptions = '<option value="">Year</option>';
    for (let year = currentYear + 1; year >= 1990; year--) {
        yearOptions += `<option value="${year}">${year}</option>`;
    }
    
    newRow.innerHTML = `
        <td>${rowCount}</td>
        <td>
            <select class="form-select form-select-sm" name="exam_passed[]" required onchange="updateExamName(this)">
                <option value="">Select Level</option>
                <option value="Primary">Primary (5th/8th)</option>
                <option value="Matriculation">Matriculation (10th)</option>
                <option value="Intermediate">Intermediate (+2/12th)</option>
                <option value="ITI">ITI</option>
                <option value="Diploma">Diploma</option>
                <option value="Graduation">Graduation</option>
                <option value="Post Graduation">Post Graduation</option>
                <option value="PhD">PhD/Doctorate</option>
                <option value="Other">Other</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="exam_name[]" required>
                <option value="">Select Exam</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="year_of_passing[]" required>
                ${yearOptions}
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm" name="institute_name[]" placeholder="Board/University" required></td>
        <td>
            <select class="form-select form-select-sm" name="stream[]" required onchange="handleStreamOther(this)">
                <option value="">Select Stream</option>
                <!-- General Streams -->
                <option value="Science">Science</option>
                <option value="Commerce">Commerce</option>
                <option value="Arts">Arts/Humanities</option>
                <option value="General">General</option>
                <option value="Vocational">Vocational</option>
                
                <!-- Engineering & Technology Streams -->
                <optgroup label="Engineering & Technology">
                    <option value="Computer Science Engineering">Computer Science Engineering</option>
                    <option value="Information Technology">Information Technology</option>
                    <option value="Electronics & Communication Engineering">Electronics & Communication Engineering</option>
                    <option value="Electrical Engineering">Electrical Engineering</option>
                    <option value="Mechanical Engineering">Mechanical Engineering</option>
                    <option value="Civil Engineering">Civil Engineering</option>
                    <option value="Chemical Engineering">Chemical Engineering</option>
                    <option value="Aerospace Engineering">Aerospace Engineering</option>
                    <option value="Automobile Engineering">Automobile Engineering</option>
                    <option value="Biomedical Engineering">Biomedical Engineering</option>
                    <option value="Biotechnology Engineering">Biotechnology Engineering</option>
                    <option value="Environmental Engineering">Environmental Engineering</option>
                    <option value="Industrial Engineering">Industrial Engineering</option>
                    <option value="Instrumentation Engineering">Instrumentation Engineering</option>
                    <option value="Marine Engineering">Marine Engineering</option>
                    <option value="Mining Engineering">Mining Engineering</option>
                    <option value="Petroleum Engineering">Petroleum Engineering</option>
                    <option value="Production Engineering">Production Engineering</option>
                    <option value="Textile Engineering">Textile Engineering</option>
                    <option value="Agricultural Engineering">Agricultural Engineering</option>
                    <option value="Food Technology">Food Technology</option>
                    <option value="Metallurgical Engineering">Metallurgical Engineering</option>
                    <option value="Materials Science Engineering">Materials Science Engineering</option>
                    <option value="Robotics Engineering">Robotics Engineering</option>
                    <option value="Artificial Intelligence & Machine Learning">Artificial Intelligence & Machine Learning</option>
                    <option value="Data Science Engineering">Data Science Engineering</option>
                    <option value="Cyber Security Engineering">Cyber Security Engineering</option>
                    <option value="Software Engineering">Software Engineering</option>
                    <option value="Network Engineering">Network Engineering</option>
                    <option value="Embedded Systems">Embedded Systems</option>
                    <option value="VLSI Design">VLSI Design</option>
                    <option value="Nanotechnology">Nanotechnology</option>
                    <option value="Renewable Energy Engineering">Renewable Energy Engineering</option>
                </optgroup>
                
                <!-- Computer Applications -->
                <optgroup label="Computer Applications">
                    <option value="Computer Applications">Computer Applications</option>
                    <option value="Information Systems">Information Systems</option>
                    <option value="Computer Science">Computer Science</option>
                    <option value="Software Development">Software Development</option>
                    <option value="Web Development">Web Development</option>
                    <option value="Mobile App Development">Mobile App Development</option>
                    <option value="Database Management">Database Management</option>
                    <option value="System Administration">System Administration</option>
                </optgroup>
                
                <!-- Management & Business -->
                <optgroup label="Management & Business">
                    <option value="Management">Management</option>
                    <option value="Business Administration">Business Administration</option>
                    <option value="Marketing">Marketing</option>
                    <option value="Finance">Finance</option>
                    <option value="Human Resources">Human Resources</option>
                    <option value="Operations Management">Operations Management</option>
                    <option value="International Business">International Business</option>
                    <option value="Entrepreneurship">Entrepreneurship</option>
                </optgroup>
                
                <!-- Pure Sciences -->
                <optgroup label="Pure Sciences">
                    <option value="Physics">Physics</option>
                    <option value="Chemistry">Chemistry</option>
                    <option value="Mathematics">Mathematics</option>
                    <option value="Biology">Biology</option>
                    <option value="Biotechnology">Biotechnology</option>
                    <option value="Microbiology">Microbiology</option>
                    <option value="Biochemistry">Biochemistry</option>
                    <option value="Environmental Science">Environmental Science</option>
                    <option value="Statistics">Statistics</option>
                    <option value="Applied Mathematics">Applied Mathematics</option>
                </optgroup>
                
                <!-- Other Specializations -->
                <option value="Other">Other</option>
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm" name="percentage[]" placeholder="85%" required></td>
        <td><button type="button" class="btn-remove-row" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
    `;
    
    // Add event listeners to new inputs and selects
    newRow.querySelectorAll('input, select').forEach(element => {
        element.addEventListener('input', updateProgress);
        element.addEventListener('change', updateProgress);
        if (element.tagName === 'INPUT') {
            element.addEventListener('blur', function() {
                validateField(this);
            });
        }
    });
}

// Update exam name options based on exam passed selection
function updateExamName(examPassedSelect) {
    const row = examPassedSelect.closest('tr');
    const examNameSelect = row.querySelector('select[name="exam_name[]"]');
    const selectedLevel = examPassedSelect.value;
    
    // Clear existing options
    examNameSelect.innerHTML = '<option value="">Select Exam</option>';
    
    // Handle "Other" selection for Exam Passed
    if (selectedLevel === 'Other') {
        // Replace dropdown with text input for custom entry
        const customInput = document.createElement('input');
        customInput.type = 'text';
        customInput.className = 'form-control form-control-sm custom-other-input';
        customInput.name = 'exam_passed[]';
        customInput.placeholder = 'Enter custom qualification level';
        customInput.required = true;
        customInput.value = '';
        
        // Replace the select with input
        examPassedSelect.parentNode.replaceChild(customInput, examPassedSelect);
        
        // Also replace exam name dropdown with text input
        const examNameInput = document.createElement('input');
        examNameInput.type = 'text';
        examNameInput.className = 'form-control form-control-sm custom-other-input';
        examNameInput.name = 'exam_name[]';
        examNameInput.placeholder = 'Enter exam/qualification name';
        examNameInput.required = true;
        
        examNameSelect.parentNode.replaceChild(examNameInput, examNameSelect);
        
        // Add event listeners
        customInput.addEventListener('input', updateProgress);
        customInput.addEventListener('blur', function() { validateField(this); });
        examNameInput.addEventListener('input', updateProgress);
        examNameInput.addEventListener('blur', function() { validateField(this); });
        
        return;
    }
    
    // Define exam name options for each level
    const examOptions = {
        'Primary': [
            'Primary School Certificate',
            '5th Standard',
            '8th Standard',
            'Elementary Education'
        ],
        'Matriculation': [
            'Secondary School Certificate (SSC)',
            'High School Certificate (HSC)',
            'Board of Secondary Education',
            'CBSE Class 10',
            'ICSE Class 10',
            'State Board 10th'
        ],
        'Intermediate': [
            'Higher Secondary Certificate',
            'Intermediate Certificate',
            'CBSE Class 12',
            'ICSE Class 12',
            'State Board 12th',
            'Pre-University Course (PUC)',
            'Higher Secondary Education'
        ],
        'ITI': [
            'Industrial Training Institute',
            'National Council for Vocational Training (NCVT)',
            'State Council for Vocational Training (SCVT)',
            'Craftsman Training Scheme (CTS)',
            'Apprenticeship Training Scheme (ATS)'
        ],
        'Diploma': [
            'Diploma in Engineering',
            'Polytechnic Diploma',
            'Technical Diploma',
            'Professional Diploma',
            'Vocational Diploma'
        ],
        'Graduation': [
            'Bachelor of Technology (B.Tech)',
            'Bachelor of Engineering (B.E.)',
            'Bachelor of Science (B.Sc)',
            'Bachelor of Arts (B.A.)',
            'Bachelor of Commerce (B.Com)',
            'Bachelor of Computer Applications (BCA)',
            'Bachelor of Business Administration (BBA)',
            'Bachelor of Fine Arts (BFA)',
            'Other Bachelor Degree'
        ],
        'Post Graduation': [
            'Master of Technology (M.Tech)',
            'Master of Engineering (M.E.)',
            'Master of Science (M.Sc)',
            'Master of Arts (M.A.)',
            'Master of Commerce (M.Com)',
            'Master of Computer Applications (MCA)',
            'Master of Business Administration (MBA)',
            'Master of Fine Arts (MFA)',
            'Other Master Degree'
        ],
        'PhD': [
            'Doctor of Philosophy (Ph.D)',
            'Doctor of Science (D.Sc)',
            'Doctor of Literature (D.Litt)',
            'Doctor of Engineering (D.Eng)',
            'Other Doctorate Degree'
        ],
        'Other': [
            'Certificate Course',
            'Professional Certification',
            'Skill Development Course',
            'Other Qualification'
        ]
    };
    
    // Populate exam name options
    if (examOptions[selectedLevel]) {
        examOptions[selectedLevel].forEach(examName => {
            const option = document.createElement('option');
            option.value = examName;
            option.textContent = examName;
            examNameSelect.appendChild(option);
        });
    }
    
    // Add "Other" option at the end for exam name
    const otherOption = document.createElement('option');
    otherOption.value = 'Other';
    otherOption.textContent = 'Other (Specify custom)';
    examNameSelect.appendChild(otherOption);
    
    // Add event listener for exam name "Other" selection
    examNameSelect.addEventListener('change', function() {
        handleExamNameOther(this);
    });
}

// Handle "Other" selection in exam name dropdown
function handleExamNameOther(examNameSelect) {
    if (examNameSelect.value === 'Other') {
        // Replace dropdown with text input for custom entry
        const customInput = document.createElement('input');
        customInput.type = 'text';
        customInput.className = 'form-control form-control-sm custom-other-input';
        customInput.name = 'exam_name[]';
        customInput.placeholder = 'Enter custom exam/qualification name';
        customInput.required = true;
        customInput.value = '';
        
        // Replace the select with input
        examNameSelect.parentNode.replaceChild(customInput, examNameSelect);
        
        // Add event listeners
        customInput.addEventListener('input', updateProgress);
        customInput.addEventListener('blur', function() { validateField(this); });
    }
}

// Handle "Other" selection in stream dropdown
function handleStreamOther(streamSelect) {
    if (streamSelect.value === 'Other') {
        // Replace dropdown with text input for custom entry
        const customInput = document.createElement('input');
        customInput.type = 'text';
        customInput.className = 'form-control form-control-sm custom-other-input';
        customInput.name = 'stream[]';
        customInput.placeholder = 'Enter custom stream/specialization';
        customInput.required = true;
        customInput.value = '';
        
        // Replace the select with input
        streamSelect.parentNode.replaceChild(customInput, streamSelect);
        
        // Add event listeners
        customInput.addEventListener('input', updateProgress);
        customInput.addEventListener('blur', function() { validateField(this); });
    }
}

// Remove education row
function removeRow(button) {
    const row = button.closest('tr');
    row.remove();
    // Renumber rows
    const table = document.getElementById('educationTable').getElementsByTagName('tbody')[0];
    Array.from(table.rows).forEach((row, index) => {
        row.cells[0].textContent = index + 1;
    });
    updateProgress();
}

// State and City API
const API_KEY = 'N3hJNDk4TEl0bTAzSnE2RVdhZzdaQXN3OElvTzRnRnlaY3VYdVhVSg==';
const BASE_URL = 'https://api.countrystatecity.in/v1';

// Load states
fetch(`${BASE_URL}/countries/IN/states`, {
    headers: {'X-CSCAPI-KEY': API_KEY}
})
.then(res => res.json())
.then(states => {
    const stateSelect = document.getElementById('state');
    states.forEach(state => {
        const option = document.createElement('option');
        option.value = state.iso2;
        option.textContent = state.name;
        stateSelect.appendChild(option);
    });
});

// Load cities when state changes
document.getElementById('state').addEventListener('change', function() {
    const stateCode = this.value;
    const citySelect = document.getElementById('city');
    citySelect.innerHTML = '<option value="">Select City</option>';
    
    if (stateCode) {
        fetch(`${BASE_URL}/countries/IN/states/${stateCode}/cities`, {
            headers: {'X-CSCAPI-KEY': API_KEY}
        })
        .then(res => res.json())
        .then(cities => {
            cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city.name;
                option.textContent = city.name;
                citySelect.appendChild(option);
            });
        });
    }
    updateProgress();
});

// Filter courses by training centre (only if not locked)
<?php if (empty($course_details)): ?>
document.getElementById('training_center').addEventListener('change', function() {
    const selectedCenter = this.value;
    const courseSelect = document.getElementById('course_id');
    const options = courseSelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
            return;
        }
        const courseCenter = option.getAttribute('data-center');
        option.style.display = (courseCenter === selectedCenter || !selectedCenter) ? 'block' : 'none';
    });
    updateProgress();
});
<?php endif; ?>

// Form validation with toast notifications
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    console.log('Form submitted with simplified validation');
    
    // Basic validation only - let HTML5 and server handle the rest
    const requiredFields = ['name', 'father_name', 'mother_name', 'dob', 'gender', 'marital_status', 'mobile', 'email', 'aadhar', 'nationality', 'religion', 'category', 'position', 'state', 'city', 'pincode', 'address'];
    
    for (let field of requiredFields) {
        const input = document.querySelector(`[name="${field}"]`);
        if (!input || !input.value.trim()) {
            alert(`Please fill in the ${field.replace('_', ' ')} field.`);
            e.preventDefault();
            if (input) input.focus();
            return false;
        }
    }
    
    // Check required files
    const requiredFiles = ['passport_photo', 'signature', 'aadhar_card', 'tenth_marksheet'];
    for (let file of requiredFiles) {
        const input = document.querySelector(`[name="${file}"]`);
        if (!input || !input.files[0]) {
            alert(`Please upload ${file.replace('_', ' ')}.`);
            e.preventDefault();
            if (input) input.focus();
            return false;
        }
    }
    
    // Basic mobile validation
    const mobile = document.querySelector('input[name="mobile"]').value;
    if (!/^[0-9]{10}$/.test(mobile)) {
        alert('Please enter a valid 10-digit mobile number.');
        e.preventDefault();
        return false;
    }
    
    // Basic email validation
    const email = document.querySelector('input[name="email"]').value;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('Please enter a valid email address.');
        e.preventDefault();
        return false;
    }
    
    console.log('All validations passed, submitting form...');
    // Form will submit normally - no preventDefault() called unless validation fails
});

// ===== FLYER MODAL FUNCTIONS =====
function openFlyerModal(imageSrc) {
    const modal = document.getElementById('flyerModal');
    const modalImg = document.getElementById('flyerModalImage');
    modal.style.display = 'block';
    modalImg.src = imageSrc;
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeFlyerModal() {
    const modal = document.getElementById('flyerModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
}

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeFlyerModal();
    }
});

// Flyer image hover effect
const flyerImage = document.querySelector('.course-flyer-image');
if (flyerImage) {
    flyerImage.addEventListener('mouseenter', function() {
        this.style.transform = 'scale(1.02)';
    });
    flyerImage.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
    });
}

// ===== SMOOTH SCROLL TO SECTION ON FOCUS =====
document.querySelectorAll('.registration-level-section').forEach((section, index) => {
    const inputs = section.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            // Highlight current level
            document.querySelectorAll('.progress-step').forEach(step => {
                step.classList.remove('active');
            });
            document.querySelectorAll('.progress-step')[index].classList.add('active');
        });
    });
});
</script>

</body>
</html>
<?php $conn->close(); ?>
