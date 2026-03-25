<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin_role'] !== 'nsqf_course_manager') {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';

$active_theme = loadActiveTheme($conn);

// Handle Add/Edit/Delete Template
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $course_name = trim($_POST['course_name']);
        $category = $_POST['category'];
        $eligibility = trim($_POST['eligibility']);
        $created_by = $_SESSION['admin_id'];
        
        $stmt = $conn->prepare("INSERT INTO nsqf_course_templates (course_name, category, eligibility, created_by) VALUES (?, ?, ?, ?)");
        
        if ($stmt === false) {
            // Table might not exist - run migration
            include_once __DIR__ . '/../migrations/install_nsqf_templates.php';
            
            // Try again after migration
            $stmt = $conn->prepare("INSERT INTO nsqf_course_templates (course_name, category, eligibility, created_by) VALUES (?, ?, ?, ?)");
            if ($stmt === false) {
                $error = "Error: Could not prepare statement. " . $conn->error;
            }
        }
        
        if ($stmt !== false) {
            $stmt->bind_param("sssi", $course_name, $category, $eligibility, $created_by);
            
            if ($stmt->execute()) {
                $success = "NSQF course template added successfully!";
            } else {
                $error = "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['template_id']);
        $course_name = trim($_POST['course_name']);
        $category = $_POST['category'];
        $eligibility = trim($_POST['eligibility']);
        
        $stmt = $conn->prepare("UPDATE nsqf_course_templates SET course_name=?, category=?, eligibility=? WHERE id=? AND created_by=?");
        
        if ($stmt === false) {
            $error = "Error: Could not prepare statement. " . $conn->error;
        } else {
            $stmt->bind_param("sssii", $course_name, $category, $eligibility, $id, $_SESSION['admin_id']);
            
            if ($stmt->execute()) {
                $success = "Template updated successfully!";
            } else {
                $error = "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['template_id']);
        
        $stmt = $conn->prepare("UPDATE nsqf_course_templates SET is_active=0 WHERE id=? AND created_by=?");
        
        if ($stmt === false) {
            $error = "Error: Could not prepare statement. " . $conn->error;
        } else {
            $stmt->bind_param("ii", $id, $_SESSION['admin_id']);
            
            if ($stmt->execute()) {
                $success = "Template deactivated successfully!";
            } else {
                $error = "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch templates created by current NSQF manager
$templates_query = "SELECT * FROM nsqf_course_templates WHERE created_by = ? AND is_active = 1 ORDER BY created_at DESC";
$stmt = $conn->prepare($templates_query);

if ($stmt === false) {
    // Table might not exist - run migration
    echo "<div class='alert alert-warning'>NSQF templates table not found. Running migration...</div>";
    include_once __DIR__ . '/../migrations/install_nsqf_templates.php';
    
    // Try again after migration
    $stmt = $conn->prepare($templates_query);
    if ($stmt === false) {
        die("Error: Could not prepare statement. " . $conn->error);
    }
}

$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$templates = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage NSQF Templates - NIELIT Admin</title>
    <?php injectThemeCSS($active_theme); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin-theme.css" rel="stylesheet">
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2"><i class="fas fa-graduation-cap"></i> Manage NSQF Course Templates</h1>
                    <small class="text-muted">Create course templates that Course Coordinators can use</small>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                    <i class="fas fa-plus"></i> Add New Template
                </button>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <span><i class="fas fa-list"></i> Your NSQF Course Templates</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Course Name</th>
                                    <th>Category</th>
                                    <th>Eligibility</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($template = $templates->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $template['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($template['course_name']) ?></strong></td>
                                    <td>
                                        <span class="badge <?= $template['category'] === 'Long Term NSQF' ? 'bg-primary' : 'bg-warning' ?>">
                                            <?= htmlspecialchars($template['category']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($template['eligibility']) ?></td>
                                    <td><?= date('d M Y', strtotime($template['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="editTemplate(<?= htmlspecialchars(json_encode($template)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this template?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                
                                <?php if ($templates->num_rows == 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                                            <h5>No NSQF templates created yet</h5>
                                            <p>Create your first NSQF course template using the "Add New Template" button.</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Template Modal -->
<div class="modal fade" id="addTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add NSQF Course Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Course Name *</label>
                        <input type="text" name="course_name" class="form-control" required placeholder="e.g., Data Analytics">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Long Term NSQF">Long Term NSQF</option>
                            <option value="Short Term NSQF">Short Term NSQF</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Eligibility *</label>
                        <textarea name="eligibility" class="form-control" rows="3" required placeholder="e.g., 12th Pass with Mathematics"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Template Modal -->
<div class="modal fade" id="editTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit NSQF Course Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="template_id" id="edit_template_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Course Name *</label>
                        <input type="text" name="course_name" id="edit_course_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select name="category" id="edit_category" class="form-control" required>
                            <option value="Long Term NSQF">Long Term NSQF</option>
                            <option value="Short Term NSQF">Short Term NSQF</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Eligibility *</label>
                        <textarea name="eligibility" id="edit_eligibility" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editTemplate(template) {
    document.getElementById('edit_template_id').value = template.id;
    document.getElementById('edit_course_name').value = template.course_name;
    document.getElementById('edit_category').value = template.category;
    document.getElementById('edit_eligibility').value = template.eligibility;
    
    new bootstrap.Modal(document.getElementById('editTemplateModal')).show();
}
</script>
</body>
</html>
<?php $conn->close(); ?>