<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login_new.php');
    exit();
}

require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/theme_loader.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Load active theme
$active_theme = loadActiveTheme($conn);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }
    
    $action = $_POST['action'];
    
    // Handle reorder
    if ($action === 'reorder') {
        $order_data = json_decode($_POST['order_data'], true);
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("UPDATE navigation_menu SET display_order = ? WHERE id = ?");
            foreach ($order_data as $id => $order) {
                $stmt->bind_param("ii", $order, $id);
                $stmt->execute();
            }
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to update order']);
        }
        exit();
    }
    
    // Handle toggle status
    if ($action === 'toggle_status') {
        $id = intval($_POST['menu_id']);
        $status = intval($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE navigation_menu SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        }
        exit();
    }
    
    // Handle get menu item
    if ($action === 'get_menu') {
        $id = intval($_POST['menu_id']);
        $stmt = $conn->prepare("SELECT * FROM navigation_menu WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($menu = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'menu' => $menu]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Menu item not found']);
        }
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_menu'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Invalid request";
        $_SESSION['message_type'] = "danger";
        header('Location: manage_navigation.php');
        exit();
    }
    
    $menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : null;
    $label = trim($_POST['label']);
    $url = trim($_POST['url']);
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $display_order = intval($_POST['display_order']);
    $target = $_POST['target'];
    $icon = trim($_POST['icon']);
    
    if ($menu_id) {
        // Update
        $stmt = $conn->prepare("UPDATE navigation_menu SET label=?, url=?, parent_id=?, display_order=?, target=?, icon=? WHERE id=?");
        $stmt->bind_param("ssiissi", $label, $url, $parent_id, $display_order, $target, $icon, $menu_id);
    } else {
        // Create
        $stmt = $conn->prepare("INSERT INTO navigation_menu (label, url, parent_id, display_order, target, icon) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiss", $label, $url, $parent_id, $display_order, $target, $icon);
    }
    
    if ($stmt->execute()) {
        $_SESSION['message'] = $menu_id ? 'Menu item updated successfully' : 'Menu item created successfully';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Failed to save menu item';
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: manage_navigation.php');
    exit();
}

// Handle delete
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM navigation_menu WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Menu item deleted successfully';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Failed to delete menu item';
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: manage_navigation.php');
    exit();
}

// Fetch all menu items
$menu_items = $conn->query("SELECT * FROM navigation_menu ORDER BY parent_id IS NULL DESC, display_order ASC");
$parent_items = $conn->query("SELECT id, label FROM navigation_menu WHERE parent_id IS NULL ORDER BY display_order ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Navigation Menu - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo getThemeFavicon($active_theme); ?>" type="image/x-icon">
    <style>
        .menu-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .menu-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .menu-table th {
            background: #f8fafc;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .menu-table td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
        }
        
        .menu-table tr:hover {
            background: #f8fafc;
        }
        
        .menu-child {
            padding-left: 40px !important;
            background: #f8fafc;
        }
        
        .menu-child::before {
            content: "└─ ";
            color: #94a3b8;
            margin-right: 8px;
        }
        
        .drag-handle {
            cursor: move;
            color: #94a3b8;
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
    </style>
</head>
<body class="admin-body">
    <!-- Sidebar -->
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
                <a href="manage_announcements.php" class="nav-link">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_navigation.php" class="nav-link active">
                    <i class="fas fa-bars"></i> Navigation Menu
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
                <h4><i class="fas fa-bars"></i> Manage Navigation Menu</h4>
                <small>Control menu items displayed on the public website</small>
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
            <!-- Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            <?php endif; ?>

            <!-- Menu Items Listing -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list"></i> Navigation Menu Items
                    </h5>
                    <div style="display: flex; gap: 12px;">
                        <a href="manage_homepage.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Homepage
                        </a>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Menu Item
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if ($menu_items && $menu_items->num_rows > 0): ?>
                        <div class="menu-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th style="width: 60px;">Order</th>
                                        <th>Label</th>
                                        <th>URL</th>
                                        <th style="width: 100px;">Target</th>
                                        <th style="width: 100px;">Status</th>
                                        <th style="width: 180px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="sortableMenu">
                                    <?php 
                                    $menu_items->data_seek(0);
                                    while ($item = $menu_items->fetch_assoc()): 
                                        $is_parent = $item['parent_id'] === null;
                                    ?>
                                    <tr data-id="<?php echo $item['id']; ?>" <?php echo !$is_parent ? 'class="menu-child"' : ''; ?>>
                                        <td>
                                            <i class="fas fa-grip-vertical drag-handle"></i>
                                        </td>
                                        <td><?php echo $item['display_order']; ?></td>
                                        <td>
                                            <?php if ($item['icon']): ?>
                                                <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($item['label']); ?>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($item['url']); ?></code>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $item['target'] === '_blank' ? 'bg-info' : 'bg-secondary'; ?>">
                                                <?php echo $item['target']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $item['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="editMenu(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="toggleStatus(<?php echo $item['id']; ?>, <?php echo $item['is_active'] ? 0 : 1; ?>)">
                                                <i class="fas fa-toggle-<?php echo $item['is_active'] ? 'on' : 'off'; ?>"></i>
                                            </button>
                                            <a href="?delete_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure? This will also delete child menu items.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bars"></i>
                            <h5>No Menu Items</h5>
                            <p>Click "Add Menu Item" to create your first navigation item</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Add/Edit Menu Modal -->
    <div class="modal fade" id="menuModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="menuForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="menu_id" id="menu_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Label *</label>
                            <input type="text" name="label" id="label" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">URL *</label>
                            <input type="text" name="url" id="url" class="form-control" required 
                                   placeholder="e.g., index.php or #">
                            <small class="form-text text-muted">Use # for dropdown parent items</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parent Menu</label>
                            <select name="parent_id" id="parent_id" class="form-select">
                                <option value="">None (Top Level)</option>
                                <?php 
                                $parent_items->data_seek(0);
                                while ($parent = $parent_items->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $parent['id']; ?>">
                                        <?php echo htmlspecialchars($parent['label']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" name="display_order" id="display_order" class="form-control" value="0" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Target</label>
                                <select name="target" id="target" class="form-select">
                                    <option value="_self">Same Window</option>
                                    <option value="_blank">New Window</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Icon (Optional)</label>
                            <input type="text" name="icon" id="icon" class="form-control" 
                                   placeholder="e.g., fas fa-home">
                            <small class="form-text text-muted">FontAwesome icon class</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_menu" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Menu Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
    <script>
        const menuModal = new bootstrap.Modal(document.getElementById('menuModal'));
        const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Menu Item';
            document.getElementById('menuForm').reset();
            document.getElementById('menu_id').value = '';
            menuModal.show();
        }
        
        function editMenu(id) {
            fetch('manage_navigation.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=get_menu&menu_id=${id}&csrf_token=${csrfToken}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalTitle').textContent = 'Edit Menu Item';
                    document.getElementById('menu_id').value = data.menu.id;
                    document.getElementById('label').value = data.menu.label;
                    document.getElementById('url').value = data.menu.url;
                    document.getElementById('parent_id').value = data.menu.parent_id || '';
                    document.getElementById('display_order').value = data.menu.display_order;
                    document.getElementById('target').value = data.menu.target;
                    document.getElementById('icon').value = data.menu.icon || '';
                    menuModal.show();
                }
            });
        }
        
        function toggleStatus(id, status) {
            fetch('manage_navigation.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=toggle_status&menu_id=${id}&status=${status}&csrf_token=${csrfToken}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }
    </script>
</body>
</html>
