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

// Function to validate centre input
function validateCentreInput($data) {
    $errors = [];
    
    if (empty($data['name'])) {
        $errors['name'] = "Centre name is required";
    }
    
    if (empty($data['code'])) {
        $errors['code'] = "Centre code is required";
    } elseif (!preg_match('/^[A-Z0-9]{2,10}$/', $data['code'])) {
        $errors['code'] = "Centre code must be 2-10 uppercase alphanumeric characters";
    }
    
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }
    
    if (!empty($data['phone']) && !preg_match('/^[0-9\-\+\(\) ]{10,20}$/', $data['phone'])) {
        $errors['phone'] = "Invalid phone format (10-20 characters)";
    }
    
    if (!empty($data['pincode']) && !preg_match('/^[0-9]{6}$/', $data['pincode'])) {
        $errors['pincode'] = "Pincode must be 6 digits";
    }
    
    return $errors;
}

// Function to sanitize centre input (strip HTML tags from text fields)
function sanitizeCentreInput($data) {
    return [
        'name' => strip_tags(trim($data['name'] ?? '')),
        'code' => strip_tags(trim(strtoupper($data['code'] ?? ''))),
        'address' => strip_tags(trim($data['address'] ?? '')),
        'city' => strip_tags(trim($data['city'] ?? '')),
        'state' => strip_tags(trim($data['state'] ?? '')),
        'pincode' => strip_tags(trim($data['pincode'] ?? '')),
        'phone' => strip_tags(trim($data['phone'] ?? '')),
        'email' => strip_tags(trim($data['email'] ?? ''))
    ];
}

// Function to create new centre
function createCentre($conn, $data) {
    $stmt = $conn->prepare("INSERT INTO centres (name, code, address, city, state, pincode, phone, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $data['name'], $data['code'], $data['address'], $data['city'], $data['state'], $data['pincode'], $data['phone'], $data['email']);
    return $stmt->execute();
}

// Function to update existing centre
function updateCentre($conn, $id, $data) {
    $stmt = $conn->prepare("UPDATE centres SET name=?, code=?, address=?, city=?, state=?, pincode=?, phone=?, email=? WHERE id=?");
    $stmt->bind_param("ssssssssi", $data['name'], $data['code'], $data['address'], $data['city'], $data['state'], $data['pincode'], $data['phone'], $data['email'], $id);
    return $stmt->execute();
}

// Function to toggle centre active status
function toggleCentreStatus($conn, $id, $status) {
    $stmt = $conn->prepare("UPDATE centres SET is_active=? WHERE id=?");
    $stmt->bind_param("ii", $status, $id);
    return $stmt->execute();
}

// Function to get all centres
function getAllCentres($conn, $active_only = false) {
    $sql = "SELECT * FROM centres";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name ASC";
    return $conn->query($sql);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Invalid request. Please try again.";
        $_SESSION['message_type'] = "danger";
        header('Location: manage_centres.php');
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Sanitize POST data
        $sanitized_data = sanitizeCentreInput($_POST);
        
        // Validate input
        $errors = validateCentreInput($sanitized_data);
        
        if (empty($errors)) {
            // Create centre
            if (createCentre($conn, $sanitized_data)) {
                // Get the newly created centre ID
                $centre_id = $conn->insert_id;
                
                // Log successful creation
                logCentreAction($conn, $_SESSION['admin'], 'create', $centre_id, $sanitized_data['name'], 'success', 
                    "Created centre: {$sanitized_data['code']} - {$sanitized_data['name']}");
                
                $_SESSION['message'] = "Centre added successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                // Check for duplicate code error
                if ($conn->errno === 1062) {
                    $_SESSION['message'] = "Centre code already exists. Please use a different code.";
                    $error_detail = "Duplicate code: {$sanitized_data['code']}";
                } else {
                    $_SESSION['message'] = "Failed to add centre. Please try again.";
                    $error_detail = "Database error: " . $conn->error;
                }
                
                // Log failed creation
                logCentreAction($conn, $_SESSION['admin'], 'create', null, $sanitized_data['name'], 'failure', $error_detail);
                
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Validation errors: " . implode(", ", $errors);
            $_SESSION['message_type'] = "danger";
        }
        header('Location: manage_centres.php');
        exit();
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id']);
        
        // Sanitize POST data
        $sanitized_data = sanitizeCentreInput($_POST);
        
        // Validate input
        $errors = validateCentreInput($sanitized_data);
        
        if (empty($errors)) {
            // Update centre
            if (updateCentre($conn, $id, $sanitized_data)) {
                // Log successful update
                logCentreAction($conn, $_SESSION['admin'], 'update', $id, $sanitized_data['name'], 'success', 
                    "Updated centre: {$sanitized_data['code']} - {$sanitized_data['name']}");
                
                $_SESSION['message'] = "Centre updated successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                // Check for duplicate code error
                if ($conn->errno === 1062) {
                    $_SESSION['message'] = "Centre code already exists. Please use a different code.";
                    $error_detail = "Duplicate code: {$sanitized_data['code']}";
                } else {
                    $_SESSION['message'] = "Failed to update centre. Please try again.";
                    $error_detail = "Database error: " . $conn->error;
                }
                
                // Log failed update
                logCentreAction($conn, $_SESSION['admin'], 'update', $id, $sanitized_data['name'], 'failure', $error_detail);
                
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Validation errors: " . implode(", ", $errors);
            $_SESSION['message_type'] = "danger";
        }
        header('Location: manage_centres.php');
        exit();
    }
    
    if ($action === 'toggle_status') {
        $id = intval($_POST['id']);
        $status = intval($_POST['status']);
        
        // Get centre name for logging
        $stmt = $conn->prepare("SELECT name FROM centres WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $centre = $result->fetch_assoc();
        $centre_name = $centre ? $centre['name'] : "Unknown Centre";
        
        if (toggleCentreStatus($conn, $id, $status)) {
            $action_type = $status == 1 ? 'activate' : 'deactivate';
            $action_desc = $status == 1 ? 'Activated' : 'Deactivated';
            
            // Log successful status change
            logCentreAction($conn, $_SESSION['admin'], $action_type, $id, $centre_name, 'success', 
                "{$action_desc} centre: {$centre_name}");
            
            $_SESSION['message'] = "Centre status updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            // Log failed status change
            $action_type = $status == 1 ? 'activate' : 'deactivate';
            logCentreAction($conn, $_SESSION['admin'], $action_type, $id, $centre_name, 'failure', 
                "Database error: " . $conn->error);
            
            $_SESSION['message'] = "Failed to update centre status. Please try again.";
            $_SESSION['message_type'] = "danger";
        }
        header('Location: manage_centres.php');
        exit();
    }
}

// Fetch all centres for display
$result = getAllCentres($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; object-src 'none';">
    <title>Manage Centres - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo getThemeFavicon($active_theme); ?>" type="image/x-icon">
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
                <a href="manage_centres.php" class="nav-link active">
                    <i class="fas fa-building"></i> Training Centres
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_themes.php" class="nav-link">
                    <i class="fas fa-palette"></i> Themes
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_homepage.php" class="nav-link">
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
                <h4><i class="fas fa-building"></i> Manage Training Centres</h4>
                <small>View and manage all training centres</small>
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

            <!-- Centres Listing Table -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list"></i> Training Centres
                    </h5>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add Centre
                    </button>
                </div>
                
                <!-- Search and Filter Section -->
                <div class="card-body" style="padding: 20px; border-bottom: 1px solid #e2e8f0;">
                    <div class="form-row" style="gap: 15px; align-items: flex-end;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label for="searchInput" style="display: block; margin-bottom: 8px; font-weight: 500; color: #334155;">
                                <i class="fas fa-search"></i> Search
                            </label>
                            <input type="text" 
                                   id="searchInput" 
                                   class="form-control" 
                                   placeholder="Search by name, code, city, or state..."
                                   onkeyup="filterTable()">
                        </div>
                        <div class="form-group" style="min-width: 200px; margin-bottom: 0;">
                            <label for="statusFilter" style="display: block; margin-bottom: 8px; font-weight: 500; color: #334155;">
                                <i class="fas fa-filter"></i> Status
                            </label>
                            <select id="statusFilter" class="form-control" onchange="filterTable()">
                                <option value="">All Centres</option>
                                <option value="active">Active Only</option>
                                <option value="inactive">Inactive Only</option>
                            </select>
                        </div>
                        <button class="btn btn-secondary" onclick="clearFilters()" style="margin-bottom: 0;">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                    <div id="filterResults" style="margin-top: 12px; color: #64748b; font-size: 14px;"></div>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table" id="centresTable">
                        <thead>
                            <tr>
                                <th>Centre Code</th>
                                <th>Centre Name</th>
                                <th>City</th>
                                <th>State</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="centresTableBody">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($centre = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($centre['code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($centre['name']); ?></td>
                                        <td><?php echo htmlspecialchars($centre['city'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($centre['state'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($centre['phone'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($centre['email'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($centre['is_active']): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick='openEditModal(<?php echo json_encode($centre); ?>)' title="Edit Centre">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-<?php echo $centre['is_active'] ? 'warning' : 'success'; ?>" 
                                                    onclick="toggleStatus(<?php echo $centre['id']; ?>, <?php echo $centre['is_active'] ? 0 : 1; ?>)" 
                                                    title="<?php echo $centre['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo $centre['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-building" style="font-size: 48px; color: #cbd5e1; margin-bottom: 16px;"></i>
                                        <p style="color: #64748b; margin: 0;">No training centres found. Add your first centre to get started.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Centre Modal -->
    <div id="addCentreModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5><i class="fas fa-plus"></i> Add New Centre</h5>
                <button class="modal-close" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="manage_centres.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_name">Centre Name <span class="text-danger">*</span></label>
                            <input type="text" id="add_name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="add_code">Centre Code <span class="text-danger">*</span></label>
                            <input type="text" id="add_code" name="code" class="form-control" 
                                   pattern="[A-Z0-9]{2,10}" 
                                   title="2-10 uppercase alphanumeric characters" 
                                   required>
                            <small class="form-text">2-10 uppercase alphanumeric characters (e.g., BBSR, BAL01)</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="add_address">Address</label>
                        <textarea id="add_address" name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_city">City</label>
                            <input type="text" id="add_city" name="city" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="add_state">State</label>
                            <input type="text" id="add_state" name="state" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="add_pincode">Pincode</label>
                            <input type="text" id="add_pincode" name="pincode" class="form-control" 
                                   pattern="[0-9]{6}" 
                                   title="6 digit pincode">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_phone">Phone</label>
                            <input type="text" id="add_phone" name="phone" class="form-control" 
                                   pattern="[0-9\-\+\(\) ]{10,20}" 
                                   title="10-20 characters, numbers and symbols allowed">
                        </div>
                        <div class="form-group">
                            <label for="add_email">Email</label>
                            <input type="email" id="add_email" name="email" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Centre
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Centre Modal -->
    <div id="editCentreModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5><i class="fas fa-edit"></i> Edit Centre</h5>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="manage_centres.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id" name="id">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_name">Centre Name <span class="text-danger">*</span></label>
                            <input type="text" id="edit_name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_code">Centre Code <span class="text-danger">*</span></label>
                            <input type="text" id="edit_code" name="code" class="form-control" 
                                   pattern="[A-Z0-9]{2,10}" 
                                   title="2-10 uppercase alphanumeric characters" 
                                   required>
                            <small class="form-text">2-10 uppercase alphanumeric characters (e.g., BBSR, BAL01)</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_address">Address</label>
                        <textarea id="edit_address" name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_city">City</label>
                            <input type="text" id="edit_city" name="city" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_state">State</label>
                            <input type="text" id="edit_state" name="state" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_pincode">Pincode</label>
                            <input type="text" id="edit_pincode" name="pincode" class="form-control" 
                                   pattern="[0-9]{6}" 
                                   title="6 digit pincode">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_phone">Phone</label>
                            <input type="text" id="edit_phone" name="phone" class="form-control" 
                                   pattern="[0-9\-\+\(\) ]{10,20}" 
                                   title="10-20 characters, numbers and symbols allowed">
                        </div>
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Centre
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addCentreModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addCentreModal').style.display = 'none';
            document.querySelector('#addCentreModal form').reset();
        }

        function openEditModal(centre) {
            document.getElementById('edit_id').value = centre.id;
            document.getElementById('edit_name').value = centre.name;
            document.getElementById('edit_code').value = centre.code;
            document.getElementById('edit_address').value = centre.address || '';
            document.getElementById('edit_city').value = centre.city || '';
            document.getElementById('edit_state').value = centre.state || '';
            document.getElementById('edit_pincode').value = centre.pincode || '';
            document.getElementById('edit_phone').value = centre.phone || '';
            document.getElementById('edit_email').value = centre.email || '';
            document.getElementById('editCentreModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editCentreModal').style.display = 'none';
        }

        function toggleStatus(id, status) {
            const action = status === 1 ? 'activate' : 'deactivate';
            if (confirm(`Are you sure you want to ${action} this centre?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'manage_centres.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'toggle_status';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = status;
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?php echo $_SESSION['csrf_token']; ?>';
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                form.appendChild(statusInput);
                form.appendChild(csrfInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Search and filter functionality
        function filterTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const table = document.getElementById('centresTable');
            const tbody = document.getElementById('centresTableBody');
            const rows = tbody.getElementsByTagName('tr');
            
            let visibleCount = 0;
            let totalCount = rows.length;
            
            // Handle empty state row
            if (totalCount === 1 && rows[0].cells.length === 1) {
                return;
            }
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                
                if (cells.length === 0) continue;
                
                // Get cell values
                const code = cells[0].textContent.toLowerCase();
                const name = cells[1].textContent.toLowerCase();
                const city = cells[2].textContent.toLowerCase();
                const state = cells[3].textContent.toLowerCase();
                const statusBadge = cells[6].querySelector('.badge');
                const isActive = statusBadge && statusBadge.classList.contains('badge-success');
                
                // Apply search filter
                const matchesSearch = searchInput === '' || 
                    code.includes(searchInput) || 
                    name.includes(searchInput) || 
                    city.includes(searchInput) || 
                    state.includes(searchInput);
                
                // Apply status filter
                let matchesStatus = true;
                if (statusFilter === 'active') {
                    matchesStatus = isActive;
                } else if (statusFilter === 'inactive') {
                    matchesStatus = !isActive;
                }
                
                // Show/hide row
                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            }
            
            // Update filter results
            updateFilterResults(visibleCount, totalCount);
        }

        function updateFilterResults(visibleCount, totalCount) {
            const resultsDiv = document.getElementById('filterResults');
            if (visibleCount === totalCount) {
                resultsDiv.textContent = `Showing all ${totalCount} centre${totalCount !== 1 ? 's' : ''}`;
            } else {
                resultsDiv.textContent = `Showing ${visibleCount} of ${totalCount} centre${totalCount !== 1 ? 's' : ''}`;
            }
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            filterTable();
        }

        // Initialize filter results on page load
        window.addEventListener('DOMContentLoaded', function() {
            const tbody = document.getElementById('centresTableBody');
            const rows = tbody.getElementsByTagName('tr');
            const totalCount = rows.length;
            
            // Check if it's the empty state row
            if (totalCount === 1 && rows[0].cells.length === 1) {
                document.getElementById('filterResults').textContent = 'No centres found';
            } else {
                updateFilterResults(totalCount, totalCount);
            }
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addCentreModal');
            const editModal = document.getElementById('editCentreModal');
            if (event.target === addModal) {
                closeAddModal();
            }
            if (event.target === editModal) {
                closeEditModal();
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
