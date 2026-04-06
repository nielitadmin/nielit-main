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

// Function to validate theme input
function validateThemeInput($data) {
    $errors = [];
    
    if (empty($data['theme_name'])) {
        $errors['theme_name'] = "Theme name is required";
    }
    
    if (empty($data['primary_color'])) {
        $errors['primary_color'] = "Primary color is required";
    } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['primary_color'])) {
        $errors['primary_color'] = "Invalid color format. Use #RRGGBB";
    }
    
    if (empty($data['secondary_color'])) {
        $errors['secondary_color'] = "Secondary color is required";
    } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['secondary_color'])) {
        $errors['secondary_color'] = "Invalid color format. Use #RRGGBB";
    }
    
    if (empty($data['accent_color'])) {
        $errors['accent_color'] = "Accent color is required";
    } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['accent_color'])) {
        $errors['accent_color'] = "Invalid color format. Use #RRGGBB";
    }
    
    return $errors;
}

// Function to sanitize theme input (strip HTML tags from text fields)
function sanitizeThemeInput($data) {
    return [
        'theme_name' => strip_tags(trim($data['theme_name'] ?? '')),
        'primary_color' => strip_tags(trim($data['primary_color'] ?? '')),
        'secondary_color' => strip_tags(trim($data['secondary_color'] ?? '')),
        'accent_color' => strip_tags(trim($data['accent_color'] ?? ''))
    ];
}

// Function to handle logo upload
function uploadLogo($file, $upload_dir = '../uploads/themes/') {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file uploaded'];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
    }
    
    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, SVG'];
    }
    
    // Validate file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Invalid file extension. Allowed: jpg, jpeg, png, gif, svg'];
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size exceeds 2MB limit'];
    }
    
    // Generate unique filename
    $filename = uniqid('logo_') . '_' . time() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Return relative path from root
        return ['success' => true, 'path' => 'uploads/themes/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to save file. Please try again.'];
}

// Function to delete old logo file
function deleteOldLogo($logo_path) {
    if (!empty($logo_path) && file_exists('../' . $logo_path)) {
        return unlink('../' . $logo_path);
    }
    return true;
}

// Function to create new theme
function createTheme($conn, $data) {
    $stmt = $conn->prepare("INSERT INTO themes (theme_name, primary_color, secondary_color, accent_color, logo_path, favicon_path) VALUES (?, ?, ?, ?, ?, ?)");
    $logo_path = $data['logo_path'] ?? null;
    $favicon_path = $data['favicon_path'] ?? null;
    $stmt->bind_param("ssssss", $data['theme_name'], $data['primary_color'], $data['secondary_color'], $data['accent_color'], $logo_path, $favicon_path);
    return $stmt->execute();
}

// Function to update existing theme
function updateTheme($conn, $id, $data) {
    $stmt = $conn->prepare("UPDATE themes SET theme_name=?, primary_color=?, secondary_color=?, accent_color=?, logo_path=?, favicon_path=? WHERE id=?");
    $logo_path = $data['logo_path'] ?? null;
    $favicon_path = $data['favicon_path'] ?? null;
    $stmt->bind_param("ssssssi", $data['theme_name'], $data['primary_color'], $data['secondary_color'], $data['accent_color'], $logo_path, $favicon_path, $id);
    return $stmt->execute();
}

// Function to activate theme (deactivate others)
function activateTheme($conn, $id) {
    // Start transaction to ensure atomicity
    $conn->begin_transaction();
    
    try {
        // Deactivate all themes
        $conn->query("UPDATE themes SET is_active = 0");
        
        // Activate the selected theme
        $stmt = $conn->prepare("UPDATE themes SET is_active = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        return false;
    }
}

// Function to get all themes
function getAllThemes($conn) {
    $sql = "SELECT * FROM themes ORDER BY created_at DESC";
    return $conn->query($sql);
}

// Function to get active theme
function getActiveTheme($conn) {
    $result = $conn->query("SELECT * FROM themes WHERE is_active = 1 LIMIT 1");
    return $result ? $result->fetch_assoc() : null;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Invalid request. Please try again.";
        $_SESSION['message_type'] = "danger";
        header('Location: manage_themes.php');
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Sanitize POST data
        $sanitized_data = sanitizeThemeInput($_POST);
        
        // Validate input
        $errors = validateThemeInput($sanitized_data);
        
        if (empty($errors)) {
            $data = $sanitized_data;
            
            // Handle logo upload
            if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = uploadLogo($_FILES['logo_file']);
                if ($upload_result['success']) {
                    $data['logo_path'] = $upload_result['path'];
                } else {
                    $_SESSION['message'] = "Logo upload failed: " . $upload_result['message'];
                    $_SESSION['message_type'] = "danger";
                    header('Location: manage_themes.php');
                    exit();
                }
            }
            
            // Handle favicon upload
            if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = uploadLogo($_FILES['favicon_file']);
                if ($upload_result['success']) {
                    $data['favicon_path'] = $upload_result['path'];
                } else {
                    $_SESSION['message'] = "Favicon upload failed: " . $upload_result['message'];
                    $_SESSION['message_type'] = "danger";
                    header('Location: manage_themes.php');
                    exit();
                }
            }
            
            // Create theme
            if (createTheme($conn, $data)) {
                // Get the newly created theme ID
                $theme_id = $conn->insert_id;
                
                // Log successful creation
                logThemeAction($conn, $_SESSION['admin'], 'create', $theme_id, $data['theme_name'], 'success', 
                    "Created theme: {$data['theme_name']}");
                
                $_SESSION['message'] = "Theme added successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                // Log failed creation
                logThemeAction($conn, $_SESSION['admin'], 'create', null, $data['theme_name'], 'failure', 
                    "Database error: " . $conn->error);
                
                $_SESSION['message'] = "Failed to add theme. Please try again.";
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Validation errors: " . implode(", ", $errors);
            $_SESSION['message_type'] = "danger";
        }
        header('Location: manage_themes.php');
        exit();
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id']);
        
        // Sanitize POST data
        $sanitized_data = sanitizeThemeInput($_POST);
        
        // Validate input
        $errors = validateThemeInput($sanitized_data);
        
        if (empty($errors)) {
            $data = $sanitized_data;
            
            // Get existing theme data for old file paths
            $stmt = $conn->prepare("SELECT logo_path, favicon_path FROM themes WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing_theme = $result->fetch_assoc();
            
            // Handle logo upload
            if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = uploadLogo($_FILES['logo_file']);
                if ($upload_result['success']) {
                    // Delete old logo
                    if (!empty($existing_theme['logo_path'])) {
                        deleteOldLogo($existing_theme['logo_path']);
                    }
                    $data['logo_path'] = $upload_result['path'];
                } else {
                    $_SESSION['message'] = "Logo upload failed: " . $upload_result['message'];
                    $_SESSION['message_type'] = "danger";
                    header('Location: manage_themes.php');
                    exit();
                }
            } else {
                // Keep existing logo path if no new file uploaded
                $data['logo_path'] = $existing_theme['logo_path'];
            }
            
            // Handle favicon upload
            if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = uploadLogo($_FILES['favicon_file']);
                if ($upload_result['success']) {
                    // Delete old favicon
                    if (!empty($existing_theme['favicon_path'])) {
                        deleteOldLogo($existing_theme['favicon_path']);
                    }
                    $data['favicon_path'] = $upload_result['path'];
                } else {
                    $_SESSION['message'] = "Favicon upload failed: " . $upload_result['message'];
                    $_SESSION['message_type'] = "danger";
                    header('Location: manage_themes.php');
                    exit();
                }
            } else {
                // Keep existing favicon path if no new file uploaded
                $data['favicon_path'] = $existing_theme['favicon_path'];
            }
            
            // Update theme
            if (updateTheme($conn, $id, $data)) {
                // Clear theme cache after update
                clearThemeCache();
                
                // Log successful update
                logThemeAction($conn, $_SESSION['admin'], 'update', $id, $data['theme_name'], 'success', 
                    "Updated theme: {$data['theme_name']}");
                
                $_SESSION['message'] = "Theme updated successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                // Log failed update
                logThemeAction($conn, $_SESSION['admin'], 'update', $id, $data['theme_name'], 'failure', 
                    "Database error: " . $conn->error);
                
                $_SESSION['message'] = "Failed to update theme. Please try again!";
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Validation errors: " . implode(", ", $errors);
            $_SESSION['message_type'] = "danger";
        }
        header('Location: manage_themes.php');
        exit();
    }
    
    if ($action === 'activate') {
        $id = intval($_POST['id']);
        
        // Get theme name for logging
        $stmt = $conn->prepare("SELECT theme_name FROM themes WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $theme = $result->fetch_assoc();
        $theme_name = $theme ? $theme['theme_name'] : "Unknown Theme";
        
        if (activateTheme($conn, $id)) {
            // Clear theme cache after activation
            clearThemeCache();
            
            // Log successful activation
            logThemeAction($conn, $_SESSION['admin'], 'activate', $id, $theme_name, 'success', 
                "Activated theme: {$theme_name}");
            
            $_SESSION['message'] = "Theme activated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            // Log failed activation
            logThemeAction($conn, $_SESSION['admin'], 'activate', $id, $theme_name, 'failure', 
                "Database error: " . $conn->error);
            
            $_SESSION['message'] = "Failed to activate theme. Please try again.";
            $_SESSION['message_type'] = "danger";
        }
        header('Location: manage_themes.php');
        exit();
    }
}

// Fetch all themes for display
$result = getAllThemes($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Themes - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo getThemeFavicon($active_theme); ?>" type="image/x-icon">
    <style>
        /* Override card-body padding for this page */
        .card-body {
            padding: 24px !important;
        }
        
        /* Theme Preview Cards Styles */
        .themes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            padding: 20px 0;
            width: 100%;
        }
        
        .theme-preview-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
            border: 2px solid transparent;
        }
        
        .theme-preview-card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
        }
        
        .theme-preview-card.active-theme {
            border-color: #10b981;
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.2);
        }
        
        .theme-status-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 10;
        }
        
        .theme-card-header {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        .theme-logo {
            max-height: 80px;
            max-width: 200px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }
        
        .theme-logo-placeholder {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
        }
        
        .theme-card-body {
            padding: 20px;
        }
        
        .theme-name {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 16px 0;
            text-align: center;
        }
        
        .theme-colors {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 12px;
        }
        
        .color-swatch-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        
        .color-swatch {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .color-swatch:hover {
            transform: scale(1.1);
            border-color: #cbd5e1;
        }
        
        .color-label {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .theme-color-codes {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        
        .color-code-item code {
            font-size: 11px;
            padding: 4px 8px;
            background: #f1f5f9;
            border-radius: 4px;
            color: #475569;
            font-family: 'Courier New', monospace;
        }
        
        .theme-card-actions {
            padding: 16px 20px;
            background: #f8fafc;
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
            border-top: 1px solid #e2e8f0;
        }
        
        .theme-card-actions .btn {
            flex: 1;
            min-width: 80px;
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
            .themes-grid {
                grid-template-columns: 1fr;
            }
            
            .theme-card-actions {
                flex-direction: column;
            }
            
            .theme-card-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body class="admin-body">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-palette"></i> Manage Themes</h4>
                <small>Customize application appearance with colors and logos</small>
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

            <!-- Themes Listing -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list"></i> Application Themes
                    </h5>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add Theme
                    </button>
                </div>
                
                <div class="card-body">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <div class="themes-grid">
                            <?php while ($theme = $result->fetch_assoc()): ?>
                                <div class="theme-preview-card <?php echo $theme['is_active'] ? 'active-theme' : ''; ?>">
                                    <!-- Status Badge -->
                                    <div class="theme-status-badge">
                                        <?php if ($theme['is_active']): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Theme Header with Logo -->
                                    <div class="theme-card-header" style="background: linear-gradient(135deg, <?php echo htmlspecialchars($theme['primary_color']); ?> 0%, <?php echo htmlspecialchars($theme['secondary_color']); ?> 100%);">
                                        <?php if (!empty($theme['logo_path'])): ?>
                                            <img src="<?php echo APP_URL . '/' . htmlspecialchars($theme['logo_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($theme['theme_name']); ?> Logo" 
                                                 class="theme-logo">
                                        <?php else: ?>
                                            <div class="theme-logo-placeholder">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Theme Info -->
                                    <div class="theme-card-body">
                                        <h6 class="theme-name"><?php echo htmlspecialchars($theme['theme_name']); ?></h6>
                                        
                                        <!-- Color Swatches -->
                                        <div class="theme-colors">
                                            <div class="color-swatch-group">
                                                <div class="color-swatch" 
                                                     style="background-color: <?php echo htmlspecialchars($theme['primary_color']); ?>;"
                                                     title="Primary: <?php echo htmlspecialchars($theme['primary_color']); ?>">
                                                </div>
                                                <small class="color-label">Primary</small>
                                            </div>
                                            <div class="color-swatch-group">
                                                <div class="color-swatch" 
                                                     style="background-color: <?php echo htmlspecialchars($theme['secondary_color']); ?>;"
                                                     title="Secondary: <?php echo htmlspecialchars($theme['secondary_color']); ?>">
                                                </div>
                                                <small class="color-label">Secondary</small>
                                            </div>
                                            <div class="color-swatch-group">
                                                <div class="color-swatch" 
                                                     style="background-color: <?php echo htmlspecialchars($theme['accent_color']); ?>;"
                                                     title="Accent: <?php echo htmlspecialchars($theme['accent_color']); ?>">
                                                </div>
                                                <small class="color-label">Accent</small>
                                            </div>
                                        </div>
                                        
                                        <!-- Color Codes -->
                                        <div class="theme-color-codes">
                                            <div class="color-code-item">
                                                <code><?php echo htmlspecialchars($theme['primary_color']); ?></code>
                                            </div>
                                            <div class="color-code-item">
                                                <code><?php echo htmlspecialchars($theme['secondary_color']); ?></code>
                                            </div>
                                            <div class="color-code-item">
                                                <code><?php echo htmlspecialchars($theme['accent_color']); ?></code>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Theme Actions -->
                                    <div class="theme-card-actions">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick='openEditModal(<?php echo json_encode($theme); ?>)' 
                                                title="Edit Theme">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick='openPreviewModal(<?php echo json_encode($theme); ?>)' 
                                                title="Preview Theme">
                                            <i class="fas fa-eye"></i> Preview
                                        </button>
                                        <?php if (!$theme['is_active']): ?>
                                            <button class="btn btn-sm btn-success" 
                                                    onclick="activateTheme(<?php echo $theme['id']; ?>, '<?php echo htmlspecialchars($theme['theme_name'], ENT_QUOTES); ?>')" 
                                                    title="Activate Theme">
                                                <i class="fas fa-check"></i> Activate
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled>
                                                <i class="fas fa-check-circle"></i> Active
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-palette"></i>
                            <h5>No Themes Found</h5>
                            <p>Create your first theme to customize the application appearance.</p>
                            <button class="btn btn-primary" onclick="openAddModal()">
                                <i class="fas fa-plus"></i> Create Theme
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Theme Modal -->
    <div id="addThemeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5><i class="fas fa-plus"></i> Add New Theme</h5>
                <button class="modal-close" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="manage_themes.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="add_theme_name">Theme Name <span class="text-danger">*</span></label>
                        <input type="text" id="add_theme_name" name="theme_name" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_primary_color">Primary Color <span class="text-danger">*</span></label>
                            <input type="color" id="add_primary_color" name="primary_color" class="form-control" value="#0d47a1" required>
                            <small class="form-text">Main brand color</small>
                        </div>
                        <div class="form-group">
                            <label for="add_secondary_color">Secondary Color <span class="text-danger">*</span></label>
                            <input type="color" id="add_secondary_color" name="secondary_color" class="form-control" value="#1565c0" required>
                            <small class="form-text">Supporting color</small>
                        </div>
                        <div class="form-group">
                            <label for="add_accent_color">Accent Color <span class="text-danger">*</span></label>
                            <input type="color" id="add_accent_color" name="accent_color" class="form-control" value="#ffc107" required>
                            <small class="form-text">Highlight color</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_logo_file">Logo Upload</label>
                            <input type="file" id="add_logo_file" name="logo_file" class="form-control" accept="image/jpeg,image/png,image/gif,image/svg+xml">
                            <small class="form-text">Max 2MB. Allowed: JPG, PNG, GIF, SVG</small>
                        </div>
                        <div class="form-group">
                            <label for="add_favicon_file">Favicon Upload</label>
                            <input type="file" id="add_favicon_file" name="favicon_file" class="form-control" accept="image/jpeg,image/png,image/gif,image/svg+xml,image/x-icon">
                            <small class="form-text">Max 2MB. Allowed: JPG, PNG, GIF, SVG, ICO</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Theme
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Theme Modal -->
    <div id="editThemeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5><i class="fas fa-edit"></i> Edit Theme</h5>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="manage_themes.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id" name="id">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_theme_name">Theme Name <span class="text-danger">*</span></label>
                        <input type="text" id="edit_theme_name" name="theme_name" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_primary_color">Primary Color <span class="text-danger">*</span></label>
                            <input type="color" id="edit_primary_color" name="primary_color" class="form-control" required>
                            <small class="form-text">Main brand color</small>
                        </div>
                        <div class="form-group">
                            <label for="edit_secondary_color">Secondary Color <span class="text-danger">*</span></label>
                            <input type="color" id="edit_secondary_color" name="secondary_color" class="form-control" required>
                            <small class="form-text">Supporting color</small>
                        </div>
                        <div class="form-group">
                            <label for="edit_accent_color">Accent Color <span class="text-danger">*</span></label>
                            <input type="color" id="edit_accent_color" name="accent_color" class="form-control" required>
                            <small class="form-text">Highlight color</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_logo_file">Logo Upload</label>
                            <input type="file" id="edit_logo_file" name="logo_file" class="form-control" accept="image/jpeg,image/png,image/gif,image/svg+xml">
                            <small class="form-text">Max 2MB. Leave empty to keep current logo</small>
                            <div id="edit_current_logo" style="margin-top: 8px;"></div>
                        </div>
                        <div class="form-group">
                            <label for="edit_favicon_file">Favicon Upload</label>
                            <input type="file" id="edit_favicon_file" name="favicon_file" class="form-control" accept="image/jpeg,image/png,image/gif,image/svg+xml,image/x-icon">
                            <small class="form-text">Max 2MB. Leave empty to keep current favicon</small>
                            <div id="edit_current_favicon" style="margin-top: 8px;"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Theme
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview Theme Modal -->
    <div id="previewThemeModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h5><i class="fas fa-eye"></i> Theme Preview: <span id="preview_theme_name"></span></h5>
                <button class="modal-close" onclick="closePreviewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="themePreviewContainer" style="padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <!-- Preview Header -->
                    <div id="preview_header" style="padding: 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 16px;">
                        <img id="preview_logo" src="" alt="Logo" style="max-height: 50px; max-width: 150px; object-fit: contain; display: none;">
                        <h3 style="margin: 0; color: white;">NIELIT Bhubaneswar</h3>
                    </div>
                    
                    <!-- Preview Buttons -->
                    <div style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
                        <button id="preview_btn_primary" style="padding: 10px 20px; border: none; border-radius: 6px; color: white; font-weight: 500; cursor: pointer;">
                            Primary Button
                        </button>
                        <button id="preview_btn_secondary" style="padding: 10px 20px; border: none; border-radius: 6px; color: white; font-weight: 500; cursor: pointer;">
                            Secondary Button
                        </button>
                        <button id="preview_btn_accent" style="padding: 10px 20px; border: none; border-radius: 6px; color: #1e293b; font-weight: 500; cursor: pointer;">
                            Accent Button
                        </button>
                    </div>
                    
                    <!-- Preview Cards -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                        <div style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <h4 id="preview_card_title" style="margin: 0 0 12px 0;">Card Title</h4>
                            <p style="margin: 0; color: #64748b;">This is a preview of how cards will look with the selected theme colors.</p>
                        </div>
                        <div style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <h4 style="margin: 0 0 12px 0; color: #1e293b;">Another Card</h4>
                            <p id="preview_accent_text" style="margin: 0; font-weight: 500;">Accent colored text example</p>
                        </div>
                    </div>
                    
                    <!-- Color Swatches -->
                    <div style="margin-top: 24px; padding: 16px; background: #f8fafc; border-radius: 8px;">
                        <h5 style="margin: 0 0 12px 0; color: #1e293b;">Color Palette</h5>
                        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="text-align: center;">
                                <div id="preview_swatch_primary" style="width: 80px; height: 80px; border-radius: 8px; margin-bottom: 8px; border: 2px solid #e2e8f0;"></div>
                                <small style="color: #64748b;">Primary</small>
                            </div>
                            <div style="text-align: center;">
                                <div id="preview_swatch_secondary" style="width: 80px; height: 80px; border-radius: 8px; margin-bottom: 8px; border: 2px solid #e2e8f0;"></div>
                                <small style="color: #64748b;">Secondary</small>
                            </div>
                            <div style="text-align: center;">
                                <div id="preview_swatch_accent" style="width: 80px; height: 80px; border-radius: 8px; margin-bottom: 8px; border: 2px solid #e2e8f0;"></div>
                                <small style="color: #64748b;">Accent</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePreviewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Activate Theme Confirmation Modal -->
    <div id="activateConfirmModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h5><i class="fas fa-check-circle"></i> Activate Theme</h5>
                <button class="modal-close" onclick="closeActivateConfirmModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px 0;">
                    <div style="width: 80px; height: 80px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-palette" style="font-size: 36px; color: white;"></i>
                    </div>
                    <h4 style="margin: 0 0 12px 0; color: #1e293b;">Activate "<span id="confirm_theme_name"></span>"?</h4>
                    <p style="margin: 0; color: #64748b; line-height: 1.6;">
                        This will apply the selected theme to the entire application and deactivate the current active theme. 
                        The changes will take effect immediately.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeActivateConfirmModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-success" onclick="confirmActivateTheme()">
                    <i class="fas fa-check"></i> Activate Theme
                </button>
            </div>
        </div>
    </div>

    <script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addThemeModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addThemeModal').style.display = 'none';
            document.querySelector('#addThemeModal form').reset();
        }

        function openEditModal(theme) {
            document.getElementById('edit_id').value = theme.id;
            document.getElementById('edit_theme_name').value = theme.theme_name;
            document.getElementById('edit_primary_color').value = theme.primary_color;
            document.getElementById('edit_secondary_color').value = theme.secondary_color;
            document.getElementById('edit_accent_color').value = theme.accent_color;
            
            // Display current logo
            const logoContainer = document.getElementById('edit_current_logo');
            if (theme.logo_path) {
                logoContainer.innerHTML = '<small style="color: #64748b;">Current: <img src="<?php echo APP_URL; ?>/' + theme.logo_path + '" style="max-height: 30px; max-width: 100px; object-fit: contain; vertical-align: middle; margin-left: 8px;"></small>';
            } else {
                logoContainer.innerHTML = '<small style="color: #94a3b8;">No current logo</small>';
            }
            
            // Display current favicon
            const faviconContainer = document.getElementById('edit_current_favicon');
            if (theme.favicon_path) {
                faviconContainer.innerHTML = '<small style="color: #64748b;">Current: <img src="<?php echo APP_URL; ?>/' + theme.favicon_path + '" style="max-height: 20px; max-width: 20px; object-fit: contain; vertical-align: middle; margin-left: 8px;"></small>';
            } else {
                faviconContainer.innerHTML = '<small style="color: #94a3b8;">No current favicon</small>';
            }
            
            document.getElementById('editThemeModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editThemeModal').style.display = 'none';
        }

        function openPreviewModal(theme) {
            // Set theme name
            document.getElementById('preview_theme_name').textContent = theme.theme_name;
            
            // Apply colors to preview elements
            const header = document.getElementById('preview_header');
            header.style.backgroundColor = theme.primary_color;
            
            const btnPrimary = document.getElementById('preview_btn_primary');
            btnPrimary.style.backgroundColor = theme.primary_color;
            
            const btnSecondary = document.getElementById('preview_btn_secondary');
            btnSecondary.style.backgroundColor = theme.secondary_color;
            
            const btnAccent = document.getElementById('preview_btn_accent');
            btnAccent.style.backgroundColor = theme.accent_color;
            
            const cardTitle = document.getElementById('preview_card_title');
            cardTitle.style.color = theme.primary_color;
            
            const accentText = document.getElementById('preview_accent_text');
            accentText.style.color = theme.accent_color;
            
            // Color swatches
            document.getElementById('preview_swatch_primary').style.backgroundColor = theme.primary_color;
            document.getElementById('preview_swatch_secondary').style.backgroundColor = theme.secondary_color;
            document.getElementById('preview_swatch_accent').style.backgroundColor = theme.accent_color;
            
            // Display logo if available
            const previewLogo = document.getElementById('preview_logo');
            if (theme.logo_path) {
                previewLogo.src = '<?php echo APP_URL; ?>/' + theme.logo_path;
                previewLogo.style.display = 'block';
            } else {
                previewLogo.style.display = 'none';
            }
            
            document.getElementById('previewThemeModal').style.display = 'flex';
        }

        function closePreviewModal() {
            document.getElementById('previewThemeModal').style.display = 'none';
        }

        // Store theme ID for activation confirmation
        let themeToActivate = null;
        let themeNameToActivate = '';

        function activateTheme(id, themeName) {
            themeToActivate = id;
            themeNameToActivate = themeName || 'this theme';
            document.getElementById('confirm_theme_name').textContent = themeNameToActivate;
            document.getElementById('activateConfirmModal').style.display = 'flex';
        }

        function closeActivateConfirmModal() {
            document.getElementById('activateConfirmModal').style.display = 'none';
            themeToActivate = null;
            themeNameToActivate = '';
        }

        function confirmActivateTheme() {
            if (themeToActivate) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'manage_themes.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'activate';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = themeToActivate;
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?php echo $_SESSION['csrf_token']; ?>';
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                form.appendChild(csrfInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addThemeModal');
            const editModal = document.getElementById('editThemeModal');
            const previewModal = document.getElementById('previewThemeModal');
            const confirmModal = document.getElementById('activateConfirmModal');
            
            if (event.target === addModal) {
                closeAddModal();
            }
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === previewModal) {
                closePreviewModal();
            }
            if (event.target === confirmModal) {
                closeActivateConfirmModal();
            }
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
