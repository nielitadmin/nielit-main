<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/homepage_loader.php';
require_once __DIR__ . '/../includes/hero_banner_helper.php';
require_once __DIR__ . '/../includes/news_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: ' . relative_url('login.php'));
    exit();
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $_SESSION['message'] = 'Access denied. Homepage management is available to Master Admin only.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . relative_url('dashboard.php'));
    exit();
}

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Load active theme
$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);

if (!ensureHomepageContentSchema($conn)) {
    $_SESSION['message'] = 'Homepage database setup failed. Please contact support.';
    $_SESSION['message_type'] = 'danger';
}

seedIndexHomepageSections($conn);
ensureHeroBannersSchema($conn);
syncHeroBannersFromFilesystem($conn);
$hero_banners = listHeroBanners($conn);
$index_section_keys = array_keys(getIndexHomepageSectionDefinitions());
$index_section_categories = getIndexHomepageSectionsForAdmin($conn);

ensureNewsTable($conn);

if (isset($_GET['delete_news'])) {
    $deleteId = (int) $_GET['delete_news'];
    $result = deleteNewsArticle($conn, $deleteId);
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    header('Location: ' . relative_url('manage_homepage.php#homepage-news'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_news']) || isset($_POST['edit_news']))) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . relative_url('manage_homepage.php#homepage-news'));
        exit();
    }

    $result = saveNewsArticle($conn, [
        'id' => isset($_POST['edit_news']) ? (int) ($_POST['id'] ?? 0) : 0,
        'title' => $_POST['title'] ?? '',
        'content' => $_POST['content'] ?? '',
        'category' => $_POST['category'] ?? '',
        'image_url' => $_POST['image_url'] ?? '',
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ], $_FILES['image_file'] ?? null, (string) $_SESSION['admin']);

    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : ($result['message'] === 'No file uploaded' ? 'warning' : 'danger');
    header('Location: ' . relative_url('manage_homepage.php#homepage-news'));
    exit();
}

$news_list = listAllNews($conn);
$edit_news = null;
if (isset($_GET['edit_news'])) {
    $edit_news = getNewsArticle($conn, (int) $_GET['edit_news']);
}

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

    if ($action === 'upload_hero_banner') {
        $altText = trim((string) ($_POST['alt_text'] ?? ''));
        $result = uploadHeroBanner($conn, $_FILES['banner_file'] ?? null, $altText);
        if ($result['success']) {
            logHomepageContentAction($conn, $_SESSION['admin'], 'create', $result['banner']['id'] ?? null, 'hero_banner', 'success', 'Uploaded hero banner');
        } else {
            logHomepageContentAction($conn, $_SESSION['admin'], 'create', null, 'hero_banner', 'failure', $result['message'] ?? 'Upload failed');
        }
        echo json_encode($result);
        exit();
    }

    if ($action === 'delete_hero_banner') {
        $bannerId = (int) ($_POST['banner_id'] ?? 0);
        $result = deleteHeroBanner($conn, $bannerId);
        if ($result['success']) {
            logHomepageContentAction($conn, $_SESSION['admin'], 'delete', $bannerId, 'hero_banner', 'success', 'Deleted hero banner');
        } else {
            logHomepageContentAction($conn, $_SESSION['admin'], 'delete', $bannerId, 'hero_banner', 'failure', $result['message'] ?? 'Delete failed');
        }
        echo json_encode($result);
        exit();
    }

    if ($action === 'reorder_hero_banners') {
        $orderData = json_decode((string) ($_POST['order_data'] ?? '[]'), true);
        if (!is_array($orderData)) {
            echo json_encode(['success' => false, 'message' => 'Invalid order data']);
            exit();
        }
        $result = reorderHeroBanners($conn, $orderData);
        if ($result) {
            logHomepageContentAction($conn, $_SESSION['admin'], 'reorder', null, 'hero_banner', 'success', 'Reordered hero banners');
            echo json_encode(['success' => true, 'message' => 'Banner order updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update banner order']);
        }
        exit();
    }

    if ($action === 'toggle_hero_banner') {
        $bannerId = (int) ($_POST['banner_id'] ?? 0);
        $status = (int) ($_POST['status'] ?? 0);
        $result = toggleHeroBannerStatus($conn, $bannerId, $status);
        if ($result) {
            logHomepageContentAction($conn, $_SESSION['admin'], $status ? 'activate' : 'deactivate', $bannerId, 'hero_banner', 'success', 'Updated hero banner status');
            echo json_encode(['success' => true, 'message' => 'Banner status updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update banner status']);
        }
        exit();
    }

    if ($action === 'update_hero_banner_alt') {
        $bannerId = (int) ($_POST['banner_id'] ?? 0);
        $altText = (string) ($_POST['alt_text'] ?? '');
        $result = updateHeroBannerAltText($conn, $bannerId, $altText);
        if ($result['success']) {
            logHomepageContentAction($conn, $_SESSION['admin'], 'update', $bannerId, 'hero_banner', 'success', 'Updated hero banner alt text');
        }
        echo json_encode($result);
        exit();
    }
    
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
            clearHomepageContentCache();
            
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
            clearHomepageContentCache();
            
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
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit();
        }
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($section = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'section' => $section]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Section not found']);
        }
        $stmt->close();
        exit();
    }

    if ($action === 'get_section_by_key') {
        if (!isset($_POST['section_key'])) {
            echo json_encode(['success' => false, 'message' => 'Missing section key']);
            exit();
        }

        $section_key = strip_tags(trim($_POST['section_key']));
        $stmt = $conn->prepare("SELECT * FROM homepage_content WHERE section_key = ? LIMIT 1");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit();
        }
        $stmt->bind_param("s", $section_key);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($section = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'section' => $section]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Section not found']);
        }
        $stmt->close();
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
        header('Location: ' . relative_url('manage_homepage.php'));
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
                clearHomepageContentCache();
                
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
                clearHomepageContentCache();
                
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
    
    header('Location: ' . relative_url('manage_homepage.php'));
    exit();
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
    $sectionKey = (string) ($data['section_key'] ?? '');
    if (homepageIsJsonSectionKey($sectionKey)) {
        $data['section_content'] = trim((string) ($data['section_content'] ?? ''));
    } else {
        $data['section_content'] = sanitizeContent($data['section_content'] ?? '');
    }

    $stmt = $conn->prepare("INSERT INTO homepage_content (section_key, section_title, section_content, section_type, display_order) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log('createContentSection prepare failed: ' . $conn->error);
        return false;
    }
    $stmt->bind_param("ssssi", $data['section_key'], $data['section_title'], $data['section_content'], $data['section_type'], $data['display_order']);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Update existing content section
 * @param mysqli $conn Database connection
 * @param int $id Section ID
 * @param array $data Updated content section data
 * @return bool Success status
 */
function updateContentSection($conn, $id, $data) {
    $sectionKey = (string) ($data['section_key'] ?? '');
    if (homepageIsJsonSectionKey($sectionKey)) {
        $data['section_content'] = trim((string) ($data['section_content'] ?? ''));
    } else {
        $data['section_content'] = sanitizeContent($data['section_content'] ?? '');
    }

    $stmt = $conn->prepare("UPDATE homepage_content SET section_title=?, section_content=?, section_type=?, display_order=? WHERE id=?");
    if (!$stmt) {
        error_log('updateContentSection prepare failed: ' . $conn->error);
        return false;
    }
    $stmt->bind_param("sssii", $data['section_title'], $data['section_content'], $data['section_type'], $data['display_order'], $id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
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
    if (!$stmt) {
        error_log('toggleSectionStatus prepare failed: ' . $conn->error);
        return false;
    }
    $stmt->bind_param("ii", $status, $id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
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

// Fetch dynamic content sections (exclude predefined index page keys)
$content_sections = getAllContentSections($conn);
$dynamic_sections = [];
if ($content_sections) {
    while ($row = $content_sections->fetch_assoc()) {
        if (!in_array($row['section_key'], $index_section_keys, true)) {
            $dynamic_sections[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; script-src-attr 'unsafe-inline'; object-src 'none';">
    <title>Manage Homepage Content - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
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
            background: linear-gradient(135deg, var(--primary-color, #0a1628) 0%, var(--secondary-color, #112240) 100%);
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

        .hero-banner-upload {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: end;
            padding: 20px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            margin-bottom: 24px;
        }

        .hero-banner-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 18px;
        }

        .hero-banner-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .hero-banner-card.drag-over {
            box-shadow: 0 0 0 2px var(--primary-color, #0d47a1);
        }

        .hero-banner-card[draggable="true"] {
            cursor: move;
        }

        .hero-banner-preview {
            width: 100%;
            height: 140px;
            object-fit: cover;
            background: #0f172a;
            display: block;
        }

        .hero-banner-body {
            padding: 14px;
        }

        .hero-banner-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            font-size: 12px;
            color: #64748b;
        }

        .hero-banner-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .hero-banner-empty {
            padding: 28px;
            text-align: center;
            color: #64748b;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
        }

        .index-page-map {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 24px;
            padding: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .index-page-map a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid #dbe3ef;
            color: #334155;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .index-page-map a:hover {
            border-color: var(--primary-color, #0d47a1);
            color: var(--primary-color, #0d47a1);
        }

        .index-page-map .map-order {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--primary-color, #0d47a1);
            color: #fff;
            font-size: 11px;
        }

        .index-category-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .index-category-card {
            border: 1px solid #dbe3ef;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }

        .index-category-card > summary.index-category-header {
            list-style: none;
        }

        .index-category-card > summary.index-category-header::-webkit-details-marker {
            display: none;
        }

        .index-category-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            padding: 18px 20px;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            cursor: pointer;
            user-select: none;
        }

        .index-category-header:hover {
            background: #eef2ff;
        }

        .index-category-title-wrap {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            flex: 1;
        }

        .index-category-order {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--primary-color, #0d47a1);
            color: #fff;
            font-weight: 700;
            font-size: 14px;
        }

        .index-category-title {
            margin: 0 0 6px;
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .index-category-title i {
            color: var(--primary-color, #0d47a1);
            margin-right: 8px;
        }

        .index-category-description {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .index-category-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .index-category-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #dbe3ef;
            background: #fff;
            border-radius: 999px;
            padding: 6px 12px;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
        }

        .index-category-count {
            font-size: 12px;
            font-weight: 600;
            color: inherit;
        }

        .index-category-toggle {
            color: #64748b;
            transition: transform 0.2s ease;
        }

        .index-category-card[open] .index-category-toggle {
            transform: rotate(180deg);
        }

        .index-category-body {
            padding: 0 20px 20px;
        }

        .index-category-actions {
            margin-bottom: 16px;
        }

        .index-subgroup {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px dashed #e2e8f0;
        }

        .index-subgroup:first-child {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
        }

        .index-subgroup h6 {
            margin: 0 0 12px;
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        #hero-banners,
        details.index-category-card,
        #homepage-news,
        #additional-blocks {
            scroll-margin-top: 110px;
        }

        .homepage-news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 18px;
        }

        .homepage-news-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }

        .homepage-news-card-header {
            background: linear-gradient(135deg, #1a56db 0%, #1e40af 100%);
            color: #fff;
            padding: 16px;
        }

        .homepage-news-card-body {
            padding: 16px;
        }

        .homepage-news-empty {
            padding: 32px;
            text-align: center;
            color: #64748b;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
        }

        .news-status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .news-status-active {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
        }

        .news-status-inactive {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
        }

        .index-section-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .index-section-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .index-section-item .item-meta {
            flex: 1;
            min-width: 0;
        }

        .index-section-item .item-label {
            font-weight: 600;
            color: #334155;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .index-section-item .item-preview {
            color: #64748b;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .index-section-item code {
            font-size: 11px;
            color: #475569;
        }
    </style>
</head>
<body class="admin-body">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-home"></i> Manage Homepage Content</h4>
                <small>Edit index.php sections and additional homepage blocks</small>
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

            <!-- Hero Carousel Banners -->
            <div class="content-card mb-4" id="hero-banners">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-images"></i> Hero Carousel Banners
                    </h5>
                    <a href="<?php echo relative_url('../index.php'); ?>" class="btn btn-secondary" target="_blank" rel="noopener">
                        <i class="fas fa-external-link-alt"></i> Preview Homepage
                    </a>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Upload, reorder, and manage the images shown in the homepage hero carousel. Recommended size: 1920×900 px or similar wide banner ratio. Allowed formats: JPG, PNG, WebP, GIF, AVIF (max 8MB).
                    </p>

                    <form id="heroBannerUploadForm" class="hero-banner-upload" enctype="multipart/form-data">
                        <div>
                            <label for="hero_banner_file" class="form-label">Upload Banner Image</label>
                            <input type="file" class="form-control" id="hero_banner_file" name="banner_file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" required>
                        </div>
                        <div>
                            <label for="hero_banner_alt" class="form-label">Alt Text (optional)</label>
                            <input type="text" class="form-control" id="hero_banner_alt" name="alt_text" placeholder="NIELIT Bhubaneswar campus">
                        </div>
                        <button type="submit" class="btn btn-primary" id="heroBannerUploadBtn">
                            <i class="fas fa-upload"></i> Upload Banner
                        </button>
                    </form>

                    <?php if (empty($hero_banners)): ?>
                        <div class="hero-banner-empty">
                            <i class="fas fa-image fa-2x mb-3"></i>
                            <p class="mb-0">No hero banners yet. Upload your first banner image above.</p>
                        </div>
                    <?php else: ?>
                        <div class="hero-banner-grid" id="heroBannerGrid">
                            <?php foreach ($hero_banners as $banner): ?>
                                <div class="hero-banner-card" draggable="true" data-banner-id="<?php echo (int) $banner['id']; ?>" data-order="<?php echo (int) $banner['display_order']; ?>">
                                    <img
                                        src="<?php echo htmlspecialchars(heroBannerAdminPreviewUrl((string) $banner['file_path']), ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="<?php echo htmlspecialchars((string) $banner['alt_text'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="hero-banner-preview"
                                        loading="lazy"
                                    >
                                    <div class="hero-banner-body">
                                        <div class="hero-banner-meta">
                                            <span><i class="fas fa-grip-vertical"></i> Order <?php echo (int) $banner['display_order']; ?></span>
                                            <span class="badge <?php echo !empty($banner['is_active']) ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo !empty($banner['is_active']) ? 'Active' : 'Hidden'; ?>
                                            </span>
                                        </div>
                                        <label class="form-label mb-1" for="hero-alt-<?php echo (int) $banner['id']; ?>">Alt Text</label>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm hero-banner-alt-input"
                                            id="hero-alt-<?php echo (int) $banner['id']; ?>"
                                            data-banner-id="<?php echo (int) $banner['id']; ?>"
                                            value="<?php echo htmlspecialchars((string) $banner['alt_text'], ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                        <div class="hero-banner-actions">
                                            <button type="button" class="btn btn-sm btn-outline-primary js-save-hero-alt" data-banner-id="<?php echo (int) $banner['id']; ?>">
                                                <i class="fas fa-save"></i> Save Alt
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-toggle-hero-banner" data-banner-id="<?php echo (int) $banner['id']; ?>" data-status="<?php echo !empty($banner['is_active']) ? 0 : 1; ?>">
                                                <i class="fas fa-eye<?php echo !empty($banner['is_active']) ? '-slash' : ''; ?>"></i>
                                                <?php echo !empty($banner['is_active']) ? 'Hide' : 'Show'; ?>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger js-delete-hero-banner" data-banner-id="<?php echo (int) $banner['id']; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Index Page Sections -->
            <div class="content-card mb-4">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-file-alt"></i> Index Page Sections
                    </h5>
                    <a href="<?php echo relative_url('../index.php'); ?>" class="btn btn-secondary" target="_blank" rel="noopener">
                        <i class="fas fa-external-link-alt"></i> View Homepage
                    </a>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Sections are grouped in the same top-to-bottom order as <code>index.php</code>, so you can easily find what you are editing.
                        Use <code>__JOB_FAIR__</code>, <code>__MOCK_TEST__</code>, and <code>__MAIN_WEBSITE__</code> in URL fields to reference Global Portal URLs.
                    </p>

                    <div class="index-page-map">
                        <a href="#hero-banners"><span class="map-order">↑</span> Hero Carousel Images</a>
                        <a href="#homepage-news"><span class="map-order">10+</span> News Articles</a>
                        <?php foreach ($index_section_categories as $category): ?>
                            <a href="#index-category-<?php echo htmlspecialchars($category['key'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="map-order"><?php echo (int) $category['order']; ?></span>
                                <?php echo htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="index-category-list">
                        <?php foreach ($index_section_categories as $categoryIndex => $category): ?>
                            <?php
                            $itemCount = 0;
                            foreach ($category['groups'] as $groupItems) {
                                $itemCount += count($groupItems);
                            }
                            ?>
                            <details class="index-category-card" id="index-category-<?php echo htmlspecialchars($category['key'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $categoryIndex === 0 ? ' open' : ''; ?>>
                                <summary class="index-category-header">
                                    <div class="index-category-title-wrap">
                                        <span class="index-category-order"><?php echo (int) $category['order']; ?></span>
                                        <div>
                                            <h6 class="index-category-title">
                                                <i class="fas <?php echo htmlspecialchars($category['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                                <?php echo htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8'); ?>
                                            </h6>
                                            <p class="index-category-description"><?php echo htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                    </div>
                                    <div class="index-category-meta">
                                        <span class="index-category-toggle-btn">
                                            <span class="index-category-count"><?php echo (int) $itemCount; ?> fields</span>
                                            <i class="fas fa-chevron-down index-category-toggle"></i>
                                        </span>
                                    </div>
                                </summary>
                                <div class="index-category-body">
                                    <?php if (!empty($category['manage_elsewhere'])): ?>
                                        <div class="index-category-actions">
                                            <?php
                                            $elsewhereUrl = (string) $category['manage_elsewhere']['url'];
                                            if ($elsewhereUrl !== '' && $elsewhereUrl[0] !== '#') {
                                                $elsewhereUrl = relative_url($elsewhereUrl);
                                            }
                                            ?>
                                            <a href="<?php echo htmlspecialchars($elsewhereUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-external-link-alt"></i>
                                                <?php echo htmlspecialchars($category['manage_elsewhere']['label'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php foreach ($category['groups'] as $groupName => $items): ?>
                                        <div class="index-subgroup">
                                            <?php if (count($category['groups']) > 1): ?>
                                                <h6><?php echo htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8'); ?></h6>
                                            <?php endif; ?>
                                            <?php foreach ($items as $item): ?>
                                                <?php
                                                $preview = homepageIsJsonSectionKey($item['section_key'])
                                                    ? 'JSON content'
                                                    : ($item['section_content'] !== '' ? $item['section_content'] : $item['section_title']);
                                                ?>
                                                <div class="index-section-item">
                                                    <div class="item-meta">
                                                        <div class="item-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div class="item-preview" title="<?php echo htmlspecialchars(strip_tags($preview), ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars(mb_strimwidth(strip_tags($preview), 0, 80, '...'), ENT_QUOTES, 'UTF-8'); ?>
                                                        </div>
                                                        <code><?php echo htmlspecialchars($item['section_key'], ENT_QUOTES, 'UTF-8'); ?></code>
                                                    </div>
                                                    <?php if (!empty($item['id'])): ?>
                                                        <button type="button" class="btn btn-sm btn-primary js-edit-index-section" data-section-key="<?php echo htmlspecialchars($item['section_key'], ENT_QUOTES, 'UTF-8'); ?>" data-section-label="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Latest News Articles -->
            <div class="content-card mb-4" id="homepage-news">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-newspaper"></i> Latest News Articles
                    </h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#homepageNewsModal">
                        <i class="fas fa-plus"></i> Add News Article
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Manage the news cards shown in the <strong>Latest News &amp; Updates</strong> section on the homepage.
                        Edit the section heading above in category <strong>10. Latest News &amp; Updates</strong>.
                    </p>

                    <?php if (empty($news_list)): ?>
                        <div class="homepage-news-empty">
                            <i class="fas fa-newspaper fa-2x mb-3"></i>
                            <p class="mb-0">No news articles yet. Add your first article to show the news section on the homepage.</p>
                        </div>
                    <?php else: ?>
                        <div class="homepage-news-grid">
                            <?php foreach ($news_list as $news): ?>
                                <div class="homepage-news-card">
                                    <div class="homepage-news-card-header">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($news['title'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                        <small>
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo date('M d, Y', strtotime($news['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="homepage-news-card-body">
                                        <p class="text-muted small mb-3">
                                            <?php echo htmlspecialchars(mb_strimwidth(strip_tags($news['content']), 0, 120, '...'), ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            <span class="news-status-badge <?php echo !empty($news['is_active']) ? 'news-status-active' : 'news-status-inactive'; ?>">
                                                <?php echo !empty($news['is_active']) ? 'Active' : 'Hidden'; ?>
                                            </span>
                                            <?php if (!empty($news['is_featured'])): ?>
                                                <span class="badge bg-warning text-dark">Featured</span>
                                            <?php endif; ?>
                                            <?php if (!empty($news['category'])): ?>
                                                <span class="badge bg-info text-dark"><?php echo htmlspecialchars($news['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="<?php echo htmlspecialchars(relative_url('manage_homepage.php?edit_news=' . (int) $news['id'] . '#homepage-news'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="<?php echo htmlspecialchars(relative_url('manage_homepage.php?delete_news=' . (int) $news['id']), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-danger js-delete-news">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Content Sections Listing -->
            <div class="content-card" id="additional-blocks">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list"></i> Additional Homepage Blocks
                    </h5>
                    <div style="display: flex; gap: 12px;">
                        <a href="manage_announcements.php" class="btn btn-secondary">
                            <i class="fas fa-bullhorn"></i> Manage Announcements
                        </a>
                        <a href="manage_navigation.php" class="btn btn-secondary">
                            <i class="fas fa-bars"></i> Edit Navigation Menu
                        </a>
                        <button type="button" class="btn btn-primary js-open-add-section">
                            <i class="fas fa-plus"></i> Add Content Section
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($dynamic_sections)): ?>
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
                                    <?php foreach ($dynamic_sections as $section): ?>
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
                                                    <button type="button" class="btn btn-sm btn-info js-edit-section" data-section-id="<?php echo (int) $section['id']; ?>" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-<?php echo $section['is_active'] ? 'warning' : 'success'; ?> js-toggle-section-status"
                                                            data-section-id="<?php echo (int) $section['id']; ?>"
                                                            data-status="<?php echo $section['is_active'] ? 0 : 1; ?>"
                                                            title="<?php echo $section['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas fa-<?php echo $section['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h5>No Content Sections Found</h5>
                            <p>Create your first content section to customize the homepage.</p>
                            <button type="button" class="btn btn-primary js-open-add-section">
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
                <form method="POST" action="<?php echo relative_url('manage_homepage.php'); ?>" id="sectionForm">
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
                            <small class="form-text text-muted" id="section_content_help">Plain text, HTML, or JSON depending on the field. See the hint below the box after you pick a section key.</small>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-info js-preview-section">
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

    <!-- Homepage News Modal -->
    <div class="modal fade<?php echo $edit_news ? ' show' : ''; ?>" id="homepageNewsModal" tabindex="-1"<?php echo $edit_news ? ' style="display:block;" aria-modal="true" role="dialog"' : ' aria-hidden="true"'; ?>>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-newspaper"></i>
                        <?php echo $edit_news ? 'Edit News Article' : 'Add News Article'; ?>
                    </h5>
                    <button type="button" class="btn-close js-close-homepage-news-modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars(relative_url('manage_homepage.php#homepage-news'), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($edit_news): ?>
                        <input type="hidden" name="id" value="<?php echo (int) $edit_news['id']; ?>">
                    <?php endif; ?>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Article Title *</label>
                            <input type="text" class="form-control" name="title" required value="<?php echo $edit_news ? htmlspecialchars($edit_news['title'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="">Select Category</option>
                                <?php
                                $newsCategories = ['Announcement', 'Achievement', 'Event', 'Update', 'Other'];
                                foreach ($newsCategories as $newsCategory):
                                ?>
                                    <option value="<?php echo htmlspecialchars($newsCategory, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($edit_news && ($edit_news['category'] ?? '') === $newsCategory) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($newsCategory, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">News Image</label>
                            <input type="file" class="form-control" id="homepage_news_image_file" name="image_file" accept="image/*">
                            <div class="form-text">Optional. JPG, PNG, WebP, GIF up to 5MB. Recommended 1200x600px.</div>
                            <div id="homepage-news-image-preview" class="mt-3" style="<?php echo (!$edit_news || empty($edit_news['image_url'])) ? 'display:none;' : ''; ?>">
                                <img id="homepage-news-preview-img" src="<?php echo $edit_news ? htmlspecialchars((string) ($edit_news['image_url'] ?? ''), ENT_QUOTES, 'UTF-8') : ''; ?>" alt="News preview" style="max-width: 280px; border-radius: 8px;">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Or Image URL</label>
                            <input type="url" class="form-control" name="image_url" value="<?php echo $edit_news ? htmlspecialchars((string) ($edit_news['image_url'] ?? ''), ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="https://example.com/image.jpg">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Article Content *</label>
                            <textarea class="form-control" name="content" rows="8" required><?php echo $edit_news ? htmlspecialchars($edit_news['content'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_featured" id="homepage_news_featured" <?php echo ($edit_news && !empty($edit_news['is_featured'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="homepage_news_featured">Mark as Featured</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="homepage_news_active" <?php echo (!$edit_news || !empty($edit_news['is_active'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="homepage_news_active">Active (visible on homepage)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary js-close-homepage-news-modal">Cancel</button>
                        <button type="submit" name="<?php echo $edit_news ? 'edit_news' : 'add_news'; ?>" value="1" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $edit_news ? 'Update' : 'Save'; ?> Article
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php if ($edit_news): ?>
        <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

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
                    <button type="button" class="btn btn-primary js-close-preview-save">
                        <i class="fas fa-save"></i> Save Section
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (required for modals) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
    <script>
        const HOMEPAGE_ADMIN_AJAX_URL = window.location.pathname;
        const HOMEPAGE_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const HOMEPAGE_NEWS_CLOSE_URL = <?php echo json_encode(relative_url('manage_homepage.php#homepage-news'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        function getSectionContentField() {
            return document.getElementById('section_content');
        }

        function getSectionContentValue() {
            const field = getSectionContentField();
            return field ? field.value : '';
        }

        function setSectionContentValue(value) {
            const field = getSectionContentField();
            if (field) {
                field.value = value || '';
            }
        }

        function notifyAdmin(message, type) {
            if (typeof showToast === 'function') {
                showToast(message, type || 'info');
                return;
            }
            window.alert(message);
        }

        function getAdminScrollOffset() {
            const topbar = document.querySelector('.admin-topbar');
            return (topbar ? topbar.offsetHeight : 80) + 20;
        }

        function scrollToIndexSection(targetId, updateHash) {
            const target = document.getElementById(targetId);
            if (!target) {
                return false;
            }

            if (target.matches('details.index-category-card')) {
                target.open = true;
            }

            window.requestAnimationFrame(function() {
                const top = target.getBoundingClientRect().top + window.pageYOffset - getAdminScrollOffset();
                window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });

                if (updateHash !== false) {
                    history.replaceState(null, '', '#' + targetId);
                }
            });

            return true;
        }

        function initializeIndexPageMap() {
            document.querySelectorAll('.index-page-map a[href^="#"]').forEach(function(link) {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    const targetId = (this.getAttribute('href') || '').replace(/^#/, '');
                    if (!targetId) {
                        return;
                    }
                    scrollToIndexSection(targetId, true);
                });
            });

            const initialHash = window.location.hash.replace(/^#/, '');
            if (initialHash) {
                window.setTimeout(function() {
                    scrollToIndexSection(initialHash, false);
                }, 150);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeIndexPageMap();
            initializeHeroBannerUpload();
            initializeHeroBannerDragDrop();
            initializeDragAndDrop();
            initializeHomepageNewsUi();
        });

        function initializeHomepageAdminActions() {
            if (initializeHomepageAdminActions.initialized) {
                return;
            }
            initializeHomepageAdminActions.initialized = true;

            document.addEventListener('click', function(event) {
                const editIndexBtn = event.target.closest('.js-edit-index-section');
                if (editIndexBtn) {
                    event.preventDefault();
                    editIndexSection(editIndexBtn.dataset.sectionKey, editIndexBtn.dataset.sectionLabel || '');
                    return;
                }

                const openAddBtn = event.target.closest('.js-open-add-section');
                if (openAddBtn) {
                    event.preventDefault();
                    openAddModal();
                    return;
                }

                const editSectionBtn = event.target.closest('.js-edit-section');
                if (editSectionBtn) {
                    event.preventDefault();
                    editSection(editSectionBtn.dataset.sectionId);
                    return;
                }

                const toggleStatusBtn = event.target.closest('.js-toggle-section-status');
                if (toggleStatusBtn) {
                    event.preventDefault();
                    toggleStatus(toggleStatusBtn.dataset.sectionId, parseInt(toggleStatusBtn.dataset.status, 10));
                    return;
                }

                const previewBtn = event.target.closest('.js-preview-section');
                if (previewBtn) {
                    event.preventDefault();
                    previewSection();
                    return;
                }

                const closePreviewBtn = event.target.closest('.js-close-preview-save');
                if (closePreviewBtn) {
                    event.preventDefault();
                    closePreviewAndSave();
                    return;
                }

                const saveAltBtn = event.target.closest('.js-save-hero-alt');
                if (saveAltBtn) {
                    event.preventDefault();
                    saveHeroBannerAlt(saveAltBtn.dataset.bannerId);
                    return;
                }

                const toggleHeroBtn = event.target.closest('.js-toggle-hero-banner');
                if (toggleHeroBtn) {
                    event.preventDefault();
                    toggleHeroBanner(toggleHeroBtn.dataset.bannerId, toggleHeroBtn.dataset.status);
                    return;
                }

                const deleteHeroBtn = event.target.closest('.js-delete-hero-banner');
                if (deleteHeroBtn) {
                    event.preventDefault();
                    deleteHeroBanner(deleteHeroBtn.dataset.bannerId);
                    return;
                }
            });
        }

        initializeHomepageAdminActions();

        function closeHomepageNewsModal() {
            if (window.location.search.indexOf('edit_news=') !== -1) {
                window.location.href = HOMEPAGE_NEWS_CLOSE_URL;
                return;
            }

            const newsModalEl = document.getElementById('homepageNewsModal');
            if (!newsModalEl) {
                return;
            }

            if (typeof bootstrap !== 'undefined') {
                const instance = bootstrap.Modal.getInstance(newsModalEl) || new bootstrap.Modal(newsModalEl);
                instance.hide();
                return;
            }

            newsModalEl.classList.remove('show');
            newsModalEl.style.display = 'none';
            newsModalEl.setAttribute('aria-hidden', 'true');
            newsModalEl.removeAttribute('aria-modal');
            newsModalEl.removeAttribute('role');
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
                backdrop.remove();
            });
        }

        function initializeHomepageNewsUi() {
            const newsModalEl = document.getElementById('homepageNewsModal');

            document.querySelectorAll('.js-close-homepage-news-modal').forEach(function(button) {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    closeHomepageNewsModal();
                });
            });

            if (newsModalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(newsModalEl);

                newsModalEl.addEventListener('hidden.bs.modal', function() {
                    document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
                        backdrop.remove();
                    });
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('padding-right');

                    if (window.location.search.indexOf('edit_news=') !== -1) {
                        window.history.replaceState(null, '', HOMEPAGE_NEWS_CLOSE_URL);
                    }
                });

                <?php if ($edit_news): ?>
                if (newsModalEl.classList.contains('show')) {
                    document.body.classList.add('modal-open');
                }
                <?php endif; ?>
            }

            const imageInput = document.getElementById('homepage_news_image_file');
            const previewWrap = document.getElementById('homepage-news-image-preview');
            const previewImg = document.getElementById('homepage-news-preview-img');
            if (imageInput && previewWrap && previewImg) {
                imageInput.addEventListener('change', function(event) {
                    const file = event.target.files && event.target.files[0];
                    if (!file) {
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function(loadEvent) {
                        previewImg.src = loadEvent.target.result;
                        previewWrap.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                });
            }

            document.querySelectorAll('.js-delete-news').forEach(function(link) {
                link.addEventListener('click', function(event) {
                    if (!window.confirm('Delete this news article?')) {
                        event.preventDefault();
                    }
                });
            });
        }

        // ============================================================================
        // MODAL FUNCTIONS
        // ============================================================================
        
        /**
         * Open add modal for creating new content section
         */
        const homepageJsonKeys = <?php echo json_encode(homepageJsonSectionKeys(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function setIndexSectionHelp(sectionKey) {
            const helpEl = document.getElementById('section_content_help');
            if (homepageJsonKeys.includes(sectionKey)) {
                if (sectionKey === 'hero_typing_lines') {
                    helpEl.textContent = 'Enter a JSON array, e.g. [{"line1":"Code Tomorrow.","line2":"Transform Today."}]';
                } else if (sectionKey.endsWith('_checklist')) {
                    helpEl.textContent = 'Enter a JSON array of strings, e.g. ["Item one","Item two"]';
                } else {
                    helpEl.textContent = 'Enter valid JSON. For links use objects with label, url, icon, and optional external/link fields. Portal placeholders: __JOB_FAIR__, __MOCK_TEST__, __MAIN_WEBSITE__.';
                }
                return;
            }
            if (sectionKey.startsWith('hero_btn_') || sectionKey.startsWith('jobfair_btn_') || sectionKey.startsWith('mocktest_btn_')) {
                helpEl.textContent = 'Title = button label. Content = URL path (/public/courses), full URL, or __JOB_FAIR__ / __MOCK_TEST__ / __MAIN_WEBSITE__.';
            } else if (sectionKey.startsWith('portal_')) {
                helpEl.textContent = 'Enter the full portal URL in Content (Title is for admin reference only).';
            } else if (sectionKey.startsWith('hero_stat_') || sectionKey.startsWith('jobfair_stat_') || sectionKey.startsWith('mocktest_stat_')) {
                helpEl.textContent = 'Title = number/value shown large. Content = label below it.';
            } else if (sectionKey.startsWith('mocktest_feature_')) {
                helpEl.textContent = 'Title = Font Awesome icon class (e.g. fa-user-graduate). Content = feature text.';
            } else if (sectionKey.startsWith('feature_') && sectionKey.endsWith('_icon')) {
                helpEl.textContent = 'Enter Font Awesome icon class in Content, e.g. fa-laptop-code';
            } else if (sectionKey.startsWith('feature_') || sectionKey.endsWith('_title')) {
                helpEl.textContent = 'Use Title for the heading/number and Content for the description text shown on index.php.';
            } else {
                helpEl.textContent = 'Plain text shown on index.php. HTML is stripped on the public page for these sections.';
            }
        }

        function isIndexJsonSection(sectionKey) {
            return homepageJsonKeys.includes(sectionKey);
        }

        function openAddModal() {
            document.getElementById('sectionForm').reset();
            document.getElementById('section_id').value = '';
            document.getElementById('section_key').readOnly = false;
            document.getElementById('section_content_help').textContent = 'Plain text, HTML, or JSON depending on the field.';
            setSectionContentValue('');
            document.getElementById('sectionModalLabel').textContent = 'Add Content Section';
            const modal = new bootstrap.Modal(document.getElementById('sectionModal'));
            modal.show();
        }
        
        function editIndexSection(sectionKey, sectionLabel) {
            fetch(HOMEPAGE_ADMIN_AJAX_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_section_by_key&section_key=' + encodeURIComponent(sectionKey) + '&csrf_token=' + encodeURIComponent(HOMEPAGE_CSRF_TOKEN)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const section = data.section;
                    document.getElementById('section_id').value = section.id;
                    document.getElementById('section_key').value = section.section_key;
                    document.getElementById('section_key').readOnly = true;
                    document.getElementById('section_title').value = section.section_title;
                    document.getElementById('section_type').value = section.section_type;
                    document.getElementById('display_order').value = section.display_order;

                    setIndexSectionHelp(section.section_key);
                    setSectionContentValue(section.section_content || '');

                    document.getElementById('sectionModalLabel').textContent = 'Edit: ' + sectionLabel;
                    const modal = new bootstrap.Modal(document.getElementById('sectionModal'));
                    modal.show();
                } else {
                    notifyAdmin('Failed to load section: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error loading index section:', error);
                notifyAdmin('Failed to load section. Please try again.', 'error');
            });
        }

        /**
         * Edit section - load section data and open modal
         */
        function editSection(sectionId) {
            // Fetch section data via AJAX
            fetch(HOMEPAGE_ADMIN_AJAX_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_section&section_id=' + sectionId + '&csrf_token=' + encodeURIComponent(HOMEPAGE_CSRF_TOKEN)
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
                    setSectionContentValue(section.section_content || '');
                    
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
            
            const content = getSectionContentValue();
            
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
            fetch(HOMEPAGE_ADMIN_AJAX_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=reorder&order_data=' + encodeURIComponent(JSON.stringify(orderData)) + '&csrf_token=' + encodeURIComponent(HOMEPAGE_CSRF_TOKEN)
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
            
            fetch(HOMEPAGE_ADMIN_AJAX_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=toggle_status&section_id=' + sectionId + '&status=' + newStatus + '&csrf_token=' + encodeURIComponent(HOMEPAGE_CSRF_TOKEN)
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

        // ============================================================================
        // HERO BANNER MANAGEMENT
        // ============================================================================

        function initializeHeroBannerUpload() {
            const form = document.getElementById('heroBannerUploadForm');
            if (!form) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const fileInput = document.getElementById('hero_banner_file');
                const altInput = document.getElementById('hero_banner_alt');
                const uploadBtn = document.getElementById('heroBannerUploadBtn');

                if (!fileInput.files.length) {
                    showToast('Please choose a banner image to upload', 'error');
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'upload_hero_banner');
                formData.append('csrf_token', HOMEPAGE_CSRF_TOKEN);
                formData.append('banner_file', fileInput.files[0]);
                formData.append('alt_text', altInput.value || '');

                uploadBtn.disabled = true;
                fetch(HOMEPAGE_ADMIN_AJAX_URL, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message || 'Banner uploaded successfully', 'success');
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showToast(data.message || 'Upload failed', 'error');
                    }
                })
                .catch(error => {
                    console.error('Hero banner upload failed:', error);
                    showToast('Upload failed. Please try again.', 'error');
                })
                .finally(() => {
                    uploadBtn.disabled = false;
                });
            });
        }

        function deleteHeroBanner(bannerId) {
            if (!confirm('Delete this hero banner image? This cannot be undone.')) {
                return;
            }

            fetch(HOMEPAGE_ADMIN_AJAX_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete_hero_banner&banner_id=' + encodeURIComponent(bannerId) + '&csrf_token=' + encodeURIComponent(HOMEPAGE_CSRF_TOKEN)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Banner deleted', 'success');
                    setTimeout(() => location.reload(), 700);
                } else {
                    showToast(data.message || 'Delete failed', 'error');
                }
            })
            .catch(error => {
                console.error('Hero banner delete failed:', error);
                showToast('Delete failed. Please try again.', 'error');
            });
        }

        function toggleHeroBanner(bannerId, newStatus) {
            fetch(HOMEPAGE_ADMIN_AJAX_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=toggle_hero_banner&banner_id=' + encodeURIComponent(bannerId) + '&status=' + encodeURIComponent(newStatus) + '&csrf_token=' + encodeURIComponent(HOMEPAGE_CSRF_TOKEN)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Banner status updated', 'success');
                    setTimeout(() => location.reload(), 700);
                } else {
                    showToast(data.message || 'Failed to update status', 'error');
                }
            })
            .catch(error => {
                console.error('Hero banner toggle failed:', error);
                showToast('Failed to update banner status.', 'error');
            });
        }

        function saveHeroBannerAlt(bannerId) {
            const input = document.getElementById('hero-alt-' + bannerId);
            if (!input) return;

            fetch(HOMEPAGE_ADMIN_AJAX_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=update_hero_banner_alt&banner_id=' + encodeURIComponent(bannerId) + '&alt_text=' + encodeURIComponent(input.value) + '&csrf_token=' + encodeURIComponent(HOMEPAGE_CSRF_TOKEN)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Alt text saved', 'success');
                } else {
                    showToast(data.message || 'Failed to save alt text', 'error');
                }
            })
            .catch(error => {
                console.error('Hero banner alt save failed:', error);
                showToast('Failed to save alt text.', 'error');
            });
        }

        let draggedHeroBanner = null;

        function initializeHeroBannerDragDrop() {
            const grid = document.getElementById('heroBannerGrid');
            if (!grid) return;

            const cards = grid.querySelectorAll('.hero-banner-card');
            cards.forEach(card => {
                card.addEventListener('dragstart', function(e) {
                    draggedHeroBanner = this;
                    this.style.opacity = '0.6';
                    e.dataTransfer.effectAllowed = 'move';
                });

                card.addEventListener('dragend', function() {
                    this.style.opacity = '1';
                    cards.forEach(item => item.classList.remove('drag-over'));
                });

                card.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('drag-over');
                });

                card.addEventListener('dragleave', function() {
                    this.classList.remove('drag-over');
                });

                card.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('drag-over');
                    if (!draggedHeroBanner || draggedHeroBanner === this) {
                        return;
                    }

                    const allCards = Array.from(grid.querySelectorAll('.hero-banner-card'));
                    const draggedIndex = allCards.indexOf(draggedHeroBanner);
                    const targetIndex = allCards.indexOf(this);

                    if (draggedIndex < targetIndex) {
                        grid.insertBefore(draggedHeroBanner, this.nextSibling);
                    } else {
                        grid.insertBefore(draggedHeroBanner, this);
                    }

                    updateHeroBannerOrder();
                });
            });
        }

        function updateHeroBannerOrder() {
            const grid = document.getElementById('heroBannerGrid');
            if (!grid) return;

            const orderData = [];
            grid.querySelectorAll('.hero-banner-card').forEach((card, index) => {
                orderData.push({
                    id: parseInt(card.dataset.bannerId, 10),
                    order: index + 1
                });
                card.dataset.order = index + 1;
                const meta = card.querySelector('.hero-banner-meta span');
                if (meta) {
                    meta.innerHTML = '<i class="fas fa-grip-vertical"></i> Order ' + (index + 1);
                }
            });

            fetch(HOMEPAGE_ADMIN_AJAX_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=reorder_hero_banners&order_data=' + encodeURIComponent(JSON.stringify(orderData)) + '&csrf_token=' + encodeURIComponent(HOMEPAGE_CSRF_TOKEN)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Banner order updated', 'success');
                } else {
                    showToast(data.message || 'Failed to update banner order', 'error');
                    setTimeout(() => location.reload(), 1500);
                }
            })
            .catch(error => {
                console.error('Hero banner reorder failed:', error);
                showToast('Failed to update banner order.', 'error');
                setTimeout(() => location.reload(), 1500);
            });
        }

        // Display session messages as toast notifications
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['message'])): ?>
            notifyAdmin(<?php echo json_encode((string) $_SESSION['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode((string) ($_SESSION['message_type'] ?? 'info'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            <?php 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
