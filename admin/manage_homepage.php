<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login_new.php');
    exit();
}

require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/theme_loader.php';
require_once '../includes/audit_logger.php';

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Load active theme
$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);

// ============================================================================
// AJAX REQUEST HANDLERS
// ============================================================================

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Validate CSRF token for AJAX requests
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.']);
        exit();
    }
    
    $action = $_POST['action'];
    
    // Handle reorder sections request
    if ($action === 'reorder') {
        if (!isset($_POST['order_data'])) {
            echo json_encode(['success' => false, 'message' => 'Missing order data']);
            exit();
        }
        
        $order_data_json = $_POST['order_data'];
        $order_data = json_decode($order_data_json, true);
        
        if (!is_array($order_data)) {
            echo json_encode(['success' => false, 'message' => 'Invalid order data format']);
            exit();
        }
        
        // Call reorderSections function
        $result = reorderSections($conn, $order_data);
        
        if ($result) {
            // Clear cache after successful reorder
            clearHomepageCache();
            
            // Log successful reorder
            logHomepageContentAction($conn, $_SESSION['admin'], 'reorder', null, 'multiple_sections', 'success', 
                "Reordered " . count($order_data) . " content sections");
            
            echo json_encode(['success' => true, 'message' => 'Section order updated successfully']);
        } else {
            // Log failed reorder
            logHomepageContentAction($conn, $_SESSION['admin'], 'reorder', null, 'multiple_sections', 'failure', 
                "Database error during reorder");
            
            echo json_encode(['success' => false, 'message' => 'Failed to update section order']);
        }
        exit();
    }
    
    // Handle toggle status request
    if ($action === 'toggle_status') {
        if (!isset($_POST['section_id']) || !isset($_POST['status'])) {
            echo json_encode(['success' => false, 'message' => 'Missing section ID or status']);
            exit();
        }
        
        $section_id = intval($_POST['section_id']);
        $status = intval($_POST['status']);
        
        // Get section key for logging
        $stmt = $conn->prepare("SELECT section_key FROM homepage_content WHERE id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result_data = $stmt->get_result();
        $section = $result_data->fetch_assoc();
        $section_key = $section ? $section['section_key'] : "unknown_section";
        
        // Call toggleSectionStatus function
        $result = toggleSectionStatus($conn, $section_id, $status);
        
        if ($result) {
            // Clear cache after successful status toggle
            clearHomepageCache();
            
            $action_type = $status == 1 ? 'activate' : 'deactivate';
            $action_desc = $status == 1 ? 'Activated' : 'Deactivated';
            
            // Log successful status change
            logHomepageContentAction($conn, $_SESSION['admin'], $action_type, $section_id, $section_key, 'success', 
                "{$action_desc} content section: {$section_key}");
            
            echo json_encode(['success' => true, 'message' => 'Section status updated successfully']);
        } else {
            $action_type = $status == 1 ? 'activate' : 'deactivate';
            
            // Log failed status change
            logHomepageContentAction($conn, $_SESSION['admin'], $action_type, $section_id, $section_key, 'failure', 
                "Database error during status toggle");
            
            echo json_encode(['success' => false, 'message' => 'Failed to update section status']);
        }
        exit();
    }
    
    // Handle get section data request (for editing)
    if ($action === 'get_section') {
        if (!isset($_POST['section_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing section ID']);
            exit();
        }
        
        $section_id = intval($_POST['section_id']);
        $stmt = $conn->prepare("SELECT * FROM homepage_content WHERE id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($section = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'section' => $section]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Section not found']);
        }
        exit();
    }
    
    // Unknown action
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit();
}

// Handle form submissions (add/edit section)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_section'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Invalid request. Please try again.";
        $_SESSION['message_type'] = "danger";
        header('Location: manage_homepage.php');
        exit();
    }
    
    $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : null;
    
    // Collect and sanitize form data
    $data = [
        'section_key' => strip_tags(trim($_POST['section_key'] ?? '')),
        'section_title' => strip_tags(trim($_POST['section_title'] ?? '')),
        'section_content' => $_POST['section_content'] ?? '', // Will be sanitized by sanitizeContent()
        'section_type' => strip_tags(trim($_POST['section_type'] ?? '')),
        'display_order' => intval($_POST['display_order'] ?? 0)
    ];
    
    // Validate input
    $errors = validateContentInput($data);
    
    if (empty($errors)) {
        if ($section_id) {
            // Update existing section
            $result = updateContentSection($conn, $section_id, $data);
            if ($result) {
                // Clear cache after successful update
                clearHomepageCache();
                
                // Log successful update
                logHomepageContentAction($conn, $_SESSION['admin'], 'update', $section_id, $data['section_key'], 'success', 
                    "Updated content section: {$data['section_key']} - {$data['section_title']}");
                
                $_SESSION['message'] = 'Content section updated successfully';
                $_SESSION['message_type'] = 'success';
            } else {
                // Log failed update
                logHomepageContentAction($conn, $_SESSION['admin'], 'update', $section_id, $data['section_key'], 'failure', 
                    "Database error: " . $conn->error);
                
                $_SESSION['message'] = 'Failed to update content section';
                $_SESSION['message_type'] = 'error';
            }
        } else {
            // Create new section
            $result = createContentSection($conn, $data);
            if ($result) {
                // Get the newly created section ID
                $section_id = $conn->insert_id;
                
                // Clear cache after successful creation
                clearHomepageCache();
                
                // Log successful creation
                logHomepageContentAction($conn, $_SESSION['admin'], 'create', $section_id, $data['section_key'], 'success', 
                    "Created content section: {$data['section_key']} - {$data['section_title']}");
                
                $_SESSION['message'] = 'Content section created successfully';
                $_SESSION['message_type'] = 'success';
            } else {
                // Check for duplicate key error
                if ($conn->errno === 1062) {
                    $_SESSION['message'] = 'Section key already exists. Please use a different key.';
                    $error_detail = "Duplicate key: {$data['section_key']}";
                } else {
                    $_SESSION['message'] = 'Failed to create content section';
                    $error_detail = "Database error: " . $conn->error;
                }
                
                // Log failed creation
                logHomepageContentAction($conn, $_SESSION['admin'], 'create', null, $data['section_key'], 'failure', $error_detail);
                
                $_SESSION['message_type'] = 'error';
            }
        }
    } else {
        $_SESSION['message'] = 'Validation errors: ' . implode(', ', $errors);
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: manage_homepage.php');
    exit();
}


// ============================================================================
// CACHE MANAGEMENT
// ============================================================================

/**
 * Clear homepage content cache
 * This function clears the cached homepage content stored in the session
 * to ensure users see updated content after any CRUD operations
 * @return void
 */
function clearHomepageCache() {
    // Unset cache data
    if (isset($_SESSION['homepage_content_cache'])) {
        unset($_SESSION['homepage_content_cache']);
    }
    
    // Unset cache timestamp
    if (isset($_SESSION['homepage_content_cache_time'])) {
        unset($_SESSION['homepage_content_cache_time']);
    }
}

// ============================================================================
// CONTENT SECTION VALIDATION
// ============================================================================

/**
 * Validate content section input
 * @param array $data Content section data to validate
 * @return array Associative array of validation errors (empty if valid)
 */
function validateContentInput($data) {
    $errors = [];
    
    // Validate section key
    if (empty($data['section_key'])) {
        $errors['section_key'] = "Section key is required";
    } elseif (!preg_match('/^[a-z0-9_]{3,50}$/', $data['section_key'])) {
        $errors['section_key'] = "Section key must be 3-50 lowercase alphanumeric characters with underscores";
    }
    
    // Validate section title
    if (empty($data['section_title'])) {
        $errors['section_title'] = "Section title is required";
    } elseif (strlen($data['section_title']) > 255) {
        $errors['section_title'] = "Section title must not exceed 255 characters";
    }
    
    // Validate section type
    $allowed_types = ['banner', 'announcement', 'featured_course', 'text_block', 'image_block'];
    if (empty($data['section_type'])) {
        $errors['section_type'] = "Section type is required";
    } elseif (!in_array($data['section_type'], $allowed_types)) {
        $errors['section_type'] = "Invalid section type. Allowed: " . implode(", ", $allowed_types);
    }
    
    // Validate display order
    if (!isset($data['display_order'])) {
        $errors['display_order'] = "Display order is required";
    } elseif (!is_numeric($data['display_order'])) {
        $errors['display_order'] = "Display order must be a number";
    } elseif ($data['display_order'] < 0) {
        $errors['display_order'] = "Display order must be a non-negative number";
    }
    
    return $errors;
}

// ============================================================================
// CONTENT SANITIZATION
// ============================================================================

/**
 * Sanitize HTML content to prevent XSS attacks
 * Allows safe HTML tags only and strips dangerous tags and attributes
 * @param string $content Raw HTML content
 * @return string Sanitized HTML content
 */
function sanitizeContent($content) {
    // Define allowed HTML tags (safe formatting tags only)
    $allowed_tags = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><div><span><blockquote><code><pre>';
    
    // Strip all tags except allowed ones
    $content = strip_tags($content, $allowed_tags);
    
    // Remove dangerous attributes and event handlers
    // Pattern matches: on* attributes (onclick, onerror, etc.), javascript: protocol, data: protocol
    $dangerous_patterns = [
        '/\s*on\w+\s*=\s*["\']?[^"\']*["\']?/i',  // Remove on* event handlers (onclick, onerror, etc.)
        '/javascript\s*:/i',                        // Remove javascript: protocol
        '/data\s*:\s*text\/html/i',                 // Remove data:text/html
        '/vbscript\s*:/i',                          // Remove vbscript: protocol
        '/<script[^>]*>.*?<\/script>/is',          // Remove any remaining script tags
        '/<iframe[^>]*>.*?<\/iframe>/is',          // Remove iframe tags
        '/<object[^>]*>.*?<\/object>/is',          // Remove object tags
        '/<embed[^>]*>/i',                          // Remove embed tags
        '/<applet[^>]*>.*?<\/applet>/is',          // Remove applet tags
        '/<meta[^>]*>/i',                           // Remove meta tags
        '/<link[^>]*>/i',                           // Remove link tags
        '/<style[^>]*>.*?<\/style>/is',            // Remove style tags
        '/<base[^>]*>/i',                           // Remove base tags
        '/<form[^>]*>.*?<\/form>/is',              // Remove form tags
        '/<input[^>]*>/i',                          // Remove input tags
        '/<button[^>]*>.*?<\/button>/is',          // Remove button tags
        '/<textarea[^>]*>.*?<\/textarea>/is',      // Remove textarea tags
        '/<select[^>]*>.*?<\/select>/is',          // Remove select tags
    ];
    
    foreach ($dangerous_patterns as $pattern) {
        $content = preg_replace($pattern, '', $content);
    }
    
    // Sanitize href attributes in anchor tags to prevent javascript: and data: protocols
    $content = preg_replace_callback(
        '/<a\s+([^>]*href\s*=\s*["\']?)([^"\'>\s]+)(["\']?[^>]*)>/i',
        function($matches) {
            $href = $matches[2];
            // Only allow http, https, mailto, and relative URLs
            if (preg_match('/^(https?:\/\/|mailto:|\/|#)/i', $href)) {
                return '<a ' . $matches[1] . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . $matches[3] . '>';
            }
            // Remove the href if it's not safe
            return '<a ' . preg_replace('/href\s*=\s*["\']?[^"\'>\s]+["\']?/i', '', $matches[1] . $matches[3]) . '>';
        },
        $content
    );
    
    // Sanitize src attributes in img tags
    $content = preg_replace_callback(
        '/<img\s+([^>]*src\s*=\s*["\']?)([^"\'>\s]+)(["\']?[^>]*)>/i',
        function($matches) {
            $src = $matches[2];
            // Only allow http, https, and relative URLs
            if (preg_match('/^(https?:\/\/|\/)/i', $src)) {
                return '<img ' . $matches[1] . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . $matches[3] . '>';
            }
            // Remove the src if it's not safe
            return '<img ' . preg_replace('/src\s*=\s*["\']?[^"\'>\s]+["\']?/i', '', $matches[1] . $matches[3]) . '>';
        },
        $content
    );
    
    // Final cleanup: remove any remaining dangerous attributes
    $content = preg_replace('/\s*style\s*=\s*["\'][^"\']*expression[^"\']*["\']?/i', '', $content);
    
    return trim($content);
}

// ============================================================================
// CONTENT SECTION CRUD FUNCTIONS
// ============================================================================

/**
 * Create new content section
 * @param mysqli $conn Database connection
 * @param array $data Content section data
 * @return bool Success status
 */
function createContentSection($conn, $data) {
    // Sanitize content before saving
    $data['section_content'] = sanitizeContent($data['section_content']);
    
    $stmt = $conn->prepare("INSERT INTO homepage_content (section_key, section_title, section_content, section_type, display_order) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $data['section_key'], $data['section_title'], $data['section_content'], $data['section_type'], $data['display_order']);
    return $stmt->execute();
}

/**
 * Update existing content section
 * @param mysqli $conn Database connection
 * @param int $id Section ID
 * @param array $data Updated content section data
 * @return bool Success status
 */
function updateContentSection($conn, $id, $data) {
    // Sanitize content before saving
    $data['section_content'] = sanitizeContent($data['section_content']);
    
    $stmt = $conn->prepare("UPDATE homepage_content SET section_title=?, section_content=?, section_type=?, display_order=? WHERE id=?");
    $stmt->bind_param("sssii", $data['section_title'], $data['section_content'], $data['section_type'], $data['display_order'], $id);
    return $stmt->execute();
}

/**
 * Toggle section active status
 * @param mysqli $conn Database connection
 * @param int $id Section ID
 * @param int $status New status (0 or 1)
 * @return bool Success status
 */
function toggleSectionStatus($conn, $id, $status) {
    $stmt = $conn->prepare("UPDATE homepage_content SET is_active=? WHERE id=?");
    $stmt->bind_param("ii", $status, $id);
    return $stmt->execute();
}

/**
 * Get all content sections
 * @param mysqli $conn Database connection
 * @param bool $active_only Whether to fetch only active sections
 * @return mysqli_result|false Query result
 */
function getAllContentSections($conn, $active_only = false) {
    $sql = "SELECT * FROM homepage_content";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY display_order ASC";
    return $conn->query($sql);
}

/**
 * Get content by section key
 * @param mysqli $conn Database connection
 * @param string $section_key Unique section key
 * @return array|null Section data or null if not found
 */
function getContentByKey($conn, $section_key) {
    $stmt = $conn->prepare("SELECT * FROM homepage_content WHERE section_key = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $section_key);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Reorder content sections
 * Updates display_order for multiple sections in a single transaction
 * @param mysqli $conn Database connection
 * @param array $order_data Associative array of section_id => new_order
 * @return bool Success status
 */
function reorderSections($conn, $order_data) {
    // Start transaction for atomicity
    $conn->begin_transaction();
    
    try {
        // Prepare statement for updating display order
        $stmt = $conn->prepare("UPDATE homepage_content SET display_order = ? WHERE id = ?");
        
        // Update each section's display order
        foreach ($order_data as $id => $order) {
            // Validate that order is a non-negative integer
            if (!is_numeric($order) || $order < 0) {
                throw new Exception("Invalid display order value: $order");
            }
            
            // Validate that id is a positive integer
            if (!is_numeric($id) || $id <= 0) {
                throw new Exception("Invalid section ID: $id");
            }
            
            $stmt->bind_param("ii", $order, $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update section $id: " . $stmt->error);
            }
        }
        
        // Commit transaction if all updates succeeded
        $conn->commit();
        $stmt->close();
        return true;
        
    } catch (Exception $e) {
        // Rollback transaction on any error
        $conn->rollback();
        error_log("Reorder sections failed: " . $e->getMessage());
        return false;
    }
}

// Fetch all content sections for display
$content_sections = getAllContentSections($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; object-src 'none';">
    <title>Manage Homepage Content - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo getThemeFavicon($active_theme); ?>" type="image/x-icon">
    <style>
        /* Content Sections Styles */
        .content-sections-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .content-sections-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .content-sections-table th {
            background: #f8fafc;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .content-sections-table td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
        }
        
        .content-sections-table tr:hover {
            background: #f8fafc;
        }
        
        .section-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .section-type-banner {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .section-type-announcement {
            background: #fef3c7;
            color: #92400e;
        }
        
        .section-type-featured_course {
            background: #d1fae5;
            color: #065f46;
        }
        
        .section-type-text_block {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .section-type-image_block {
            background: #fce7f3;
            color: #9f1239;
        }
        
        .section-content-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #64748b;
            font-size: 14px;
        }
        
        .drag-handle {
            cursor: move;
            color: #94a3b8;
            font-size: 18px;
        }
        
        .drag-handle:hover {
            color: #64748b;
        }
        
        /* Drag and drop styles */
        #sortableSections tr {
            transition: background-color 0.2s ease;
        }
        
        #sortableSections tr.drag-over {
            background-color: #e0f2fe !important;
            border-top: 2px solid #0ea5e9;
        }
        
        #sortableSections tr[draggable="true"] {
            cursor: move;
        }
        
        #sortableSections tr[draggable="true"]:active {
            cursor: grabbing;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }
        
        .empty-state h5 {
            color: #475569;
            margin-bottom: 12px;
            font-size: 20px;
        }
        
        .empty-state p {
            margin-bottom: 24px;
            font-size: 15px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .content-sections-table {
                overflow-x: auto;
            }
            
            .section-content-preview {
                max-width: 150px;
            }
        }
        
        /* Modal styles */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color, #0d47a1) 0%, var(--secondary-color, #1565c0) 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 20px 24px;
        }
        
        .modal-header .modal-title {
            font-weight: 600;
            font-size: 18px;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }
        
        .modal-header .btn-close:hover {
            opacity: 1;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 16px 24px;
            background: #f8fafc;
            border-radius: 0 0 12px 12px;
        }
        
        .form-label {
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 14px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color, #0d47a1);
            box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
        }
        
        .form-text {
            color: #64748b;
            font-size: 13px;
        }
        
        .preview-container {
            background: #f8fafc;
            padding: 30px;
            border-radius: 8px;
            min-height: 200px;
            border: 2px dashed #cbd5e1;
        }
    </style>
</head>
<body class="admin-body">
    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar">
        <div class="sidebar-header">
            <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo" class="sidebar-logo">
            <h3>NIELIT Admin</h3>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="students.php" class="nav-link">
                    <i class="fas fa-users"></i> Students
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_courses.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/manage_batches.php" class="nav-link">
                    <i class="fas fa-layer-group"></i> Batches
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php" class="nav-link">
                    <i class="fas fa-project-diagram"></i> Schemes/Projects
                </a>
            </div>
            
            <div class="nav-divider"></div>
            <div class="nav-section-title">System Settings</div>
            
            <div class="nav-item">
                <a href="manage_centres.php" class="nav-link">
                    <i class="fas fa-building"></i> Training Centres
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_themes.php" class="nav-link">
                    <i class="fas fa-palette"></i> Themes
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_homepage.php" class="nav-link active">
                    <i class="fas fa-home"></i> Homepage Content
                </a>
            </div>
            
            <div class="nav-divider"></div>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/approve_students.php" class="nav-link">
                    <i class="fas fa-user-check"></i> Approve Students
                </a>
            </div>
            <div class="nav-item">
                <a href="add_admin.php" class="nav-link">
                    <i class="fas fa-user-shield"></i> Add Admin
                </a>
            </div>
            <div class="nav-item">
                <a href="reset_password.php" class="nav-link">
                    <i class="fas fa-key"></i> Reset Password
                </a>
            </div>
            
            <div class="nav-divider"></div>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/index.php" class="nav-link">
                    <i class="fas fa-globe"></i> View Website
                </a>
            </div>
            <div class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-home"></i> Manage Homepage Content</h4>
                <small>Control dynamic content sections displayed on the public homepage</small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role">Administrator</span>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="admin-main">
            <!-- Toast notifications will appear here automatically -->

            <!-- Content Sections Listing -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list"></i> Homepage Content Sections
                    </h5>
                    <div style="display: flex; gap: 12px;">
                        <a href="manage_announcements.php" class="btn btn-secondary">
                            <i class="fas fa-bullhorn"></i> Manage Announcements
                        </a>
                        <a href="manage_navigation.php" class="btn btn-secondary">
                            <i class="fas fa-bars"></i> Edit Navigation Menu
                        </a>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Content Section
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if ($content_sections && $content_sections->num_rows > 0): ?>
                        <div class="content-sections-table">
                            <table id="sectionsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th style="width: 60px;">Order</th>
                                        <th>Section Key</th>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Content Preview</th>
                                        <th style="width: 100px;">Status</th>
                                        <th style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="sortableSections">
                                    <?php while ($section = $content_sections->fetch_assoc()): ?>
                                        <tr data-section-id="<?php echo $section['id']; ?>" data-order="<?php echo $section['display_order']; ?>">
                                            <td>
                                                <i class="fas fa-grip-vertical drag-handle" title="Drag to reorder"></i>
                                            </td>
                                            <td>
                                                <strong><?php echo $section['display_order']; ?></strong>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($section['section_key']); ?></code>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($section['section_title']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="section-type-badge section-type-<?php echo $section['section_type']; ?>">
                                                    <?php echo str_replace('_', ' ', $section['section_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="section-content-preview">
                                                    <?php echo htmlspecialchars(strip_tags($section['section_content'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($section['is_active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-info" onclick="editSection(<?php echo $section['id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-<?php echo $section['is_active'] ? 'warning' : 'success'; ?>" 
                                                            onclick="toggleStatus(<?php echo $section['id']; ?>, <?php echo $section['is_active'] ? 0 : 1; ?>)" 
                                                            title="<?php echo $section['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas fa-<?php echo $section['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h5>No Content Sections Found</h5>
                            <p>Create your first content section to customize the homepage.</p>
                            <button class="btn btn-primary" onclick="openAddModal()">
                                <i class="fas fa-plus"></i> Add Content Section
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Add/Edit Content Section Modal -->
    <div class="modal fade" id="sectionModal" tabindex="-1" aria-labelledby="sectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sectionModalLabel">Add Content Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="manage_homepage.php" id="sectionForm">
                    <input type="hidden" name="section_id" id="section_id">
                    <input type="hidden" name="submit_section" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="section_key" class="form-label">Section Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="section_key" name="section_key" required 
                                   pattern="[a-z0-9_]{3,50}" 
                                   placeholder="e.g., welcome_banner, latest_news"
                                   title="3-50 lowercase letters, numbers, and underscores only">
                            <small class="form-text text-muted">Unique identifier (3-50 lowercase alphanumeric with underscores)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="section_title" class="form-label">Section Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="section_title" name="section_title" required 
                                   maxlength="255" placeholder="e.g., Welcome to NIELIT Bhubaneswar">
                            <small class="form-text text-muted">Display title for the section (max 255 characters)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="section_type" class="form-label">Section Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="section_type" name="section_type" required>
                                <option value="">Select type...</option>
                                <option value="banner">Banner</option>
                                <option value="featured_course">Featured Course</option>
                                <option value="text_block">Text Block</option>
                                <option value="image_block">Image Block</option>
                            </select>
                            <small class="form-text text-muted">Type of content section (Use "Manage Announcements" button for announcements)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="display_order" class="form-label">Display Order <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="display_order" name="display_order" required 
                                   min="0" value="0" placeholder="0">
                            <small class="form-text text-muted">Order in which section appears (0 = first)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="section_content" class="form-label">Content</label>
                            <textarea class="form-control" id="section_content" name="section_content" rows="10"></textarea>
                            <small class="form-text text-muted">Rich HTML content for the section</small>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-info" onclick="previewSection()">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">
                        <i class="fas fa-eye"></i> Content Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> This is how your content will appear on the homepage
                    </div>
                    
                    <div class="preview-container" style="background: #f8fafc; padding: 30px; border-radius: 8px; min-height: 200px;">
                        <h3 id="preview_title" style="color: #1e293b; margin-bottom: 20px;"></h3>
                        <div id="preview_content" style="color: #475569; line-height: 1.6;"></div>
                    </div>
                    
                    <div class="mt-3">
                        <strong>Section Type:</strong> <span id="preview_type" class="section-type-badge"></span>
                    </div>
                    <div class="mt-2">
                        <strong>Section Key:</strong> <code id="preview_key"></code>
                    </div>
                    <div class="mt-2">
                        <strong>Display Order:</strong> <span id="preview_order"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="closePreviewAndSave()">
                        <i class="fas fa-save"></i> Save Section
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (required for modals) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- TinyMCE WYSIWYG Editor -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    
    <script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
    <script>
        // ============================================================================
        // TINYMCE INITIALIZATION
        // ============================================================================
        
        let editorInstance = null;
        
        // Initialize TinyMCE when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            initializeDragAndDrop();
            initializeTinyMCE();
        });
        
        /**
         * Initialize TinyMCE WYSIWYG editor
         */
        function initializeTinyMCE() {
            tinymce.init({
                selector: '#section_content',
                height: 400,
                menubar: false,
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'help', 'wordcount'
                ],
                toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | removeformat code | help',
                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; }',
                setup: function(editor) {
                    editorInstance = editor;
                }
            });
        }
        
        // ============================================================================
        // MODAL FUNCTIONS
        // ============================================================================
        
        /**
         * Open add modal for creating new content section
         */
        function openAddModal() {
            // Reset form
            document.getElementById('sectionForm').reset();
            document.getElementById('section_id').value = '';
            document.getElementById('section_key').readOnly = false;
            
            // Reset TinyMCE
            if (editorInstance) {
                editorInstance.setContent('');
            }
            
            // Update modal title
            document.getElementById('sectionModalLabel').textContent = 'Add Content Section';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('sectionModal'));
            modal.show();
        }
        
        /**
         * Edit section - load section data and open modal
         */
        function editSection(sectionId) {
            // Fetch section data via AJAX
            fetch('manage_homepage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_section&section_id=' + sectionId + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const section = data.section;
                    
                    // Populate form fields
                    document.getElementById('section_id').value = section.id;
                    document.getElementById('section_key').value = section.section_key;
                    document.getElementById('section_key').readOnly = true; // Don't allow changing key
                    document.getElementById('section_title').value = section.section_title;
                    document.getElementById('section_type').value = section.section_type;
                    document.getElementById('display_order').value = section.display_order;
                    
                    // Set TinyMCE content
                    if (editorInstance) {
                        editorInstance.setContent(section.section_content || '');
                    } else {
                        document.getElementById('section_content').value = section.section_content || '';
                    }
                    
                    // Update modal title
                    document.getElementById('sectionModalLabel').textContent = 'Edit Content Section';
                    
                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('sectionModal'));
                    modal.show();
                } else {
                    showToast('Failed to load section data: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error loading section data:', error);
                showToast('Failed to load section data. Please try again.', 'error');
            });
        }
        
        /**
         * Preview section content before saving
         */
        function previewSection() {
            // Get form values
            const title = document.getElementById('section_title').value;
            const type = document.getElementById('section_type').value;
            const key = document.getElementById('section_key').value;
            const order = document.getElementById('display_order').value;
            
            // Get content from TinyMCE
            let content = '';
            if (editorInstance) {
                content = editorInstance.getContent();
            } else {
                content = document.getElementById('section_content').value;
            }
            
            // Validate required fields
            if (!title || !type || !key) {
                showToast('Please fill in all required fields before previewing', 'warning');
                return;
            }
            
            // Populate preview modal
            document.getElementById('preview_title').textContent = title;
            document.getElementById('preview_content').innerHTML = content || '<em style="color: #94a3b8;">No content provided</em>';
            document.getElementById('preview_type').textContent = type.replace('_', ' ');
            document.getElementById('preview_type').className = 'section-type-badge section-type-' + type;
            document.getElementById('preview_key').textContent = key;
            document.getElementById('preview_order').textContent = order;
            
            // Hide section modal and show preview modal
            const sectionModal = bootstrap.Modal.getInstance(document.getElementById('sectionModal'));
            if (sectionModal) {
                sectionModal.hide();
            }
            
            const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
            previewModal.show();
        }
        
        /**
         * Close preview and return to edit modal
         */
        function closePreviewAndSave() {
            // Hide preview modal
            const previewModal = bootstrap.Modal.getInstance(document.getElementById('previewModal'));
            if (previewModal) {
                previewModal.hide();
            }
            
            // Show section modal
            const sectionModal = new bootstrap.Modal(document.getElementById('sectionModal'));
            sectionModal.show();
            
            // Submit the form
            document.getElementById('sectionForm').submit();
        }
        
        // ============================================================================
        // DRAG AND DROP FUNCTIONALITY
        // ============================================================================
        
        let draggedRow = null;
        let draggedOrder = null;
        
        /**
         * Initialize drag and drop functionality for section rows
         */
        function initializeDragAndDrop() {
            const tbody = document.getElementById('sortableSections');
            if (!tbody) return; // No sections to sort
            
            const rows = tbody.querySelectorAll('tr');
            
            rows.forEach(row => {
                // Make row draggable
                row.setAttribute('draggable', 'true');
                
                // Drag start event
                row.addEventListener('dragstart', function(e) {
                    draggedRow = this;
                    draggedOrder = parseInt(this.dataset.order);
                    this.style.opacity = '0.5';
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', this.innerHTML);
                });
                
                // Drag end event
                row.addEventListener('dragend', function(e) {
                    this.style.opacity = '1';
                    
                    // Remove all drag-over classes
                    rows.forEach(r => {
                        r.classList.remove('drag-over');
                    });
                });
                
                // Drag over event
                row.addEventListener('dragover', function(e) {
                    if (e.preventDefault) {
                        e.preventDefault(); // Allows drop
                    }
                    e.dataTransfer.dropEffect = 'move';
                    
                    // Add visual indicator
                    this.classList.add('drag-over');
                    
                    return false;
                });
                
                // Drag enter event
                row.addEventListener('dragenter', function(e) {
                    this.classList.add('drag-over');
                });
                
                // Drag leave event
                row.addEventListener('dragleave', function(e) {
                    this.classList.remove('drag-over');
                });
                
                // Drop event
                row.addEventListener('drop', function(e) {
                    if (e.stopPropagation) {
                        e.stopPropagation(); // Stops browser from redirecting
                    }
                    
                    // Don't do anything if dropping on itself
                    if (draggedRow !== this) {
                        // Swap the rows visually
                        const tbody = this.parentNode;
                        const allRows = Array.from(tbody.querySelectorAll('tr'));
                        const draggedIndex = allRows.indexOf(draggedRow);
                        const targetIndex = allRows.indexOf(this);
                        
                        if (draggedIndex < targetIndex) {
                            this.parentNode.insertBefore(draggedRow, this.nextSibling);
                        } else {
                            this.parentNode.insertBefore(draggedRow, this);
                        }
                        
                        // Update the order values and send to server
                        updateSectionOrder();
                    }
                    
                    this.classList.remove('drag-over');
                    return false;
                });
            });
        }
        
        /**
         * Update section order after drag and drop
         * Collects new order and sends AJAX request to server
         */
        function updateSectionOrder() {
            const tbody = document.getElementById('sortableSections');
            const rows = tbody.querySelectorAll('tr');
            const orderData = {};
            
            // Collect new order for each section
            rows.forEach((row, index) => {
                const sectionId = row.dataset.sectionId;
                const newOrder = index; // Use array index as new order
                orderData[sectionId] = newOrder;
                
                // Update the display order in the row
                row.dataset.order = newOrder;
                row.querySelector('td:nth-child(2) strong').textContent = newOrder;
            });
            
            // Send AJAX request to update order in database
            fetch('manage_homepage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=reorder&order_data=' + encodeURIComponent(JSON.stringify(orderData)) + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Section order updated successfully', 'success');
                } else {
                    showToast('Failed to update section order: ' + (data.message || 'Unknown error'), 'error');
                    // Reload page to restore correct order
                    setTimeout(() => location.reload(), 2000);
                }
            })
            .catch(error => {
                console.error('Error updating section order:', error);
                showToast('Failed to update section order. Please try again.', 'error');
                // Reload page to restore correct order
                setTimeout(() => location.reload(), 2000);
            });
        }
        
        // ============================================================================
        // STATUS TOGGLE FUNCTION
        // ============================================================================
        
        /**
         * Toggle section active status
         */
        function toggleStatus(sectionId, newStatus) {
            if (!confirm('Are you sure you want to ' + (newStatus ? 'activate' : 'deactivate') + ' this section?')) {
                return;
            }
            
            fetch('manage_homepage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=toggle_status&section_id=' + sectionId + '&status=' + newStatus + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Section status updated successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Failed to update section status: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error updating section status:', error);
                showToast('Failed to update section status. Please try again.', 'error');
            });
        }

        // Display session messages as toast notifications
        <?php if (isset($_SESSION['message'])): ?>
            showToast('<?php echo addslashes($_SESSION['message']); ?>', '<?php echo $_SESSION['message_type'] ?? 'info'; ?>');
            <?php 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>
    </script>
</body>
</html>
