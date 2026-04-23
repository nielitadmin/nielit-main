<?php
session_start();
require_once '../config/database.php';
require_once '../includes/check_permission.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Check permission for faculty management
checkPermission('manage_faculty');

$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['role'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_faculty':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $designation = trim($_POST['designation']);
                $department = trim($_POST['department']);
                
                if (!empty($name)) {
                    $stmt = $conn->prepare("INSERT INTO faculty (name, email, phone, designation, department, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssi", $name, $email, $phone, $designation, $department, $admin_id);
                    if ($stmt->execute()) {
                        $success_message = "Faculty member added successfully!";
                    } else {
                        $error_message = "Error adding faculty member.";
                    }
                }
                break;
                
            case 'update_faculty':
                $faculty_id = $_POST['faculty_id'];
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $designation = trim($_POST['designation']);
                $department = trim($_POST['department']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $stmt = $conn->prepare("UPDATE faculty SET name = ?, email = ?, phone = ?, designation = ?, department = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param("sssssii", $name, $email, $phone, $designation, $department, $is_active, $faculty_id);
                if ($stmt->execute()) {
                    $success_message = "Faculty member updated successfully!";
                } else {
                    $error_message = "Error updating faculty member.";
                }
                break;
                
            case 'delete_faculty':
                $faculty_id = $_POST['faculty_id'];
                $stmt = $conn->prepare("UPDATE faculty SET is_active = 0 WHERE id = ?");
                $stmt->bind_param("i", $faculty_id);
                if ($stmt->execute()) {
                    $success_message = "Faculty member deactivated successfully!";
                } else {
                    $error_message = "Error deactivating faculty member.";
                }
                break;
        }
    }
}

// Fetch all faculty members - filter based on role
$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['role'] ?? '';

if ($admin_role === 'master_admin') {
    // Master admins can see all faculty
    $result = $conn->query("SELECT * FROM faculty ORDER BY is_active DESC, name ASC");
} else {
    // Course coordinators see only faculty they created + global faculty (created_by = 0 or NULL)
    $stmt = $conn->prepare("SELECT * FROM faculty 
                           WHERE (created_by = ? OR created_by = 0 OR created_by IS NULL)
                           ORDER BY is_active DESC, name ASC");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

$faculty_members = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $faculty_members[] = $row;
    }
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Faculty Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
                    <i class="fas fa-plus"></i> Add Faculty
                </button>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Faculty List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Faculty Members</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Designation</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faculty_members as $faculty): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($faculty['name']); ?></td>
                                    <td><?php echo htmlspecialchars($faculty['email']); ?></td>
                                    <td><?php echo htmlspecialchars($faculty['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($faculty['designation']); ?></td>
                                    <td><?php echo htmlspecialchars($faculty['department']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $faculty['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $faculty['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editFaculty(<?php echo htmlspecialchars(json_encode($faculty)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($faculty['is_active']): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deactivateFaculty(<?php echo $faculty['id']; ?>, '<?php echo htmlspecialchars($faculty['name']); ?>')">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Faculty Modal -->
<div class="modal fade" id="addFacultyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Faculty Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_faculty">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label for="designation" class="form-label">Designation</label>
                        <input type="text" class="form-control" id="designation" name="designation" placeholder="e.g., Professor, Assistant Professor">
                    </div>
                    
                    <div class="mb-3">
                        <label for="department" class="form-label">Department</label>
                        <input type="text" class="form-control" id="department" name="department" placeholder="e.g., Computer Science, IT">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Faculty Modal -->
<div class="modal fade" id="editFacultyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Faculty Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editFacultyForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_faculty">
                    <input type="hidden" name="faculty_id" id="edit_faculty_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="edit_phone" name="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_designation" class="form-label">Designation</label>
                        <input type="text" class="form-control" id="edit_designation" name="designation">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_department" class="form-label">Department</label>
                        <input type="text" class="form-control" id="edit_department" name="department">
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editFaculty(faculty) {
    document.getElementById('edit_faculty_id').value = faculty.id;
    document.getElementById('edit_name').value = faculty.name;
    document.getElementById('edit_email').value = faculty.email || '';
    document.getElementById('edit_phone').value = faculty.phone || '';
    document.getElementById('edit_designation').value = faculty.designation || '';
    document.getElementById('edit_department').value = faculty.department || '';
    document.getElementById('edit_is_active').checked = faculty.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('editFacultyModal')).show();
}

function deactivateFaculty(facultyId, facultyName) {
    if (confirm(`Are you sure you want to deactivate ${facultyName}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_faculty">
            <input type="hidden" name="faculty_id" value="${facultyId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>