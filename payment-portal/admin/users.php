<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require_superadmin();

$message = '';
$messageType = 'success';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['form_action'] ?? '');

    if ($action === 'create') {
        $result = hub_admin_user_create(
            $hub_conn,
            (string) ($_POST['username'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['role'] ?? 'admin')
        );
        if ($result['ok']) {
            $message = 'Admin user created successfully.';
        } else {
            $message = $result['error'];
            $messageType = 'danger';
        }
    }

    if ($action === 'update') {
        $id = (int) ($_POST['user_id'] ?? 0);
        if ($id > 0) {
            $result = hub_admin_user_update(
                $hub_conn,
                $id,
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['role'] ?? 'admin'),
                isset($_POST['is_active'])
            );
            if ($result['ok']) {
                $message = 'User updated successfully.';
            } else {
                $message = $result['error'];
                $messageType = 'danger';
            }
        }
    }

    if ($action === 'reset_password') {
        $id = (int) ($_POST['user_id'] ?? 0);
        $newPass = (string) ($_POST['new_password'] ?? '');
        if ($id > 0 && $newPass !== '') {
            if (hub_admin_user_reset_password($hub_conn, $id, $newPass)) {
                $message = 'Password reset successfully.';
            } else {
                $message = 'Could not reset password.';
                $messageType = 'danger';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['user_id'] ?? 0);
        if ($id > 0) {
            if (hub_admin_user_delete($hub_conn, $id)) {
                $message = 'User deleted.';
            } else {
                $message = 'Cannot delete this user (you cannot delete yourself).';
                $messageType = 'danger';
            }
        }
    }
}

$users = hub_admin_user_list($hub_conn);

hub_admin_layout_start('Admin users', 'users', 'Manage staff accounts and roles');
?>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show py-2" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- User List -->
    <div class="col-lg-8">
        <div class="hub-card-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Staff accounts</h2>
                <span class="badge text-bg-primary"><?php echo count($users); ?> users</span>
            </div>
            <div class="hub-table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="hub-avatar" style="width:32px;height:32px;font-size:0.75rem;">
                                        <?php echo htmlspecialchars(strtoupper(substr((string) $u['username'], 0, 1))); ?>
                                    </span>
                                    <strong><?php echo htmlspecialchars((string) $u['username']); ?></strong>
                                </div>
                            </td>
                            <td class="small"><?php echo htmlspecialchars((string) ($u['email'] ?: '—')); ?></td>
                            <td>
                                <?php if ($u['role'] === 'superadmin'): ?>
                                    <span class="badge text-bg-warning"><i class="bi bi-star-fill me-1"></i>Superadmin</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Admin</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $u['is_active']): ?>
                                    <span class="badge text-bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge text-bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary"><?php echo htmlspecialchars((string) $u['created_at']); ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" type="button"
                                        data-bs-toggle="modal" data-bs-target="#editUser<?php echo (int) $u['id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ((int) $u['id'] !== (int) ($_SESSION['hub_admin_id'] ?? 0)): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete user <?php echo htmlspecialchars((string) $u['username']); ?>?');">
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editUser<?php echo (int) $u['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit — <?php echo htmlspecialchars((string) $u['username']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body vstack gap-3">
                                            <input type="hidden" name="form_action" value="update">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                            <div>
                                                <label class="form-label">Email</label>
                                                <input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars((string) ($u['email'] ?? '')); ?>" required>
                                            </div>
                                            <div>
                                                <label class="form-label">Role</label>
                                                <select class="form-select" name="role">
                                                    <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                    <option value="superadmin" <?php echo $u['role'] === 'superadmin' ? 'selected' : ''; ?>>Superadmin</option>
                                                </select>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_active" id="active<?php echo (int) $u['id']; ?>"
                                                       <?php echo (int) $u['is_active'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="active<?php echo (int) $u['id']; ?>">Active</label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save changes</button>
                                        </div>
                                    </form>
                                    <div class="modal-body border-top">
                                        <p class="small text-secondary mb-2">Reset password (for fallback login):</p>
                                        <form method="post" class="input-group">
                                            <input type="hidden" name="form_action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                            <input class="form-control form-control-sm" type="password" name="new_password" placeholder="New password" required minlength="6">
                                            <button class="btn btn-outline-warning btn-sm" type="submit">Reset</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add New User -->
    <div class="col-lg-4">
        <div class="hub-card-panel">
            <h2 class="h5 mb-3"><i class="bi bi-person-plus me-2"></i>Add admin user</h2>
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="form_action" value="create">
                <div>
                    <label class="form-label">Username</label>
                    <input class="form-control" type="text" name="username" required autocomplete="off" placeholder="e.g. john.doe">
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" required placeholder="user@nielitbhubaneswar.in">
                    <div class="form-text">Used for OTP login</div>
                </div>
                <div>
                    <label class="form-label">Password</label>
                    <input class="form-control" type="password" name="password" required minlength="6" placeholder="Fallback password">
                    <div class="form-text">For emergency password login</div>
                </div>
                <div>
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                        <option value="admin">Admin</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                    <div class="form-text">Superadmins can manage other users and SMTP settings</div>
                </div>
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-person-plus me-1"></i>Create user
                </button>
            </form>
        </div>

        <div class="hub-card-panel mt-3">
            <h3 class="h6 text-uppercase text-secondary fw-semibold">Role permissions</h3>
            <ul class="small mb-0">
                <li class="mb-1"><strong>Admin</strong> — View dashboard, manage payments, invoices</li>
                <li><strong>Superadmin</strong> — All above + manage users, SMTP, gateway settings</li>
            </ul>
        </div>
    </div>
</div>
<?php
hub_admin_layout_end();
