<?php
/**
 * NIELIT Bhubaneswar - API Key Management
 * Admin interface for managing API keys
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../config/api_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: ../admin/login.php");
    exit;
}

$admin_id = $_SESSION['admin'];
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            createApiKey();
            break;
        case 'revoke':
            revokeApiKey();
            break;
        case 'update':
            updateApiKey();
            break;
    }
}

// Get all API keys
$api_keys = getAllApiKeys();

function createApiKey() {
    global $conn, $admin_id;
    
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $permissions = $_POST['permissions'] ?? 'read';
    $rate_limit = (int)($_POST['rate_limit'] ?? API_RATE_LIMIT);
    
    if (empty($name)) {
        $_SESSION['error'] = 'API key name is required';
        return;
    }
    
    $api_key = generateApiKey();
    $api_key_hash = hashApiKey($api_key);
    
    $stmt = $conn->prepare("
        INSERT INTO api_keys (name, description, api_key_hash, permissions, rate_limit, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("ssssis", $name, $description, $api_key_hash, $permissions, $rate_limit, $admin_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'API key created successfully';
        $_SESSION['new_api_key'] = $api_key; // Show only once
    } else {
        $_SESSION['error'] = 'Failed to create API key';
    }
}

function revokeApiKey() {
    global $conn;
    
    $api_key_id = (int)($_POST['api_key_id'] ?? 0);
    
    $stmt = $conn->prepare("UPDATE api_keys SET is_active = 0, revoked_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $api_key_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'API key revoked successfully';
    } else {
        $_SESSION['error'] = 'Failed to revoke API key';
    }
}

function updateApiKey() {
    global $conn;
    
    $api_key_id = (int)($_POST['api_key_id'] ?? 0);
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $permissions = $_POST['permissions'] ?? 'read';
    $rate_limit = (int)($_POST['rate_limit'] ?? API_RATE_LIMIT);
    
    $stmt = $conn->prepare("
        UPDATE api_keys 
        SET name = ?, description = ?, permissions = ?, rate_limit = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    $stmt->bind_param("sssii", $name, $description, $permissions, $rate_limit, $api_key_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'API key updated successfully';
    } else {
        $_SESSION['error'] = 'Failed to update API key';
    }
}

function getAllApiKeys() {
    global $conn;
    
    // Check if admins table exists
    $admin_table_exists = $conn->query("SHOW TABLES LIKE 'admins'")->num_rows > 0;
    
    if ($admin_table_exists) {
        $sql = "
            SELECT 
                ak.*,
                a.name as created_by_name,
                (SELECT COUNT(*) FROM api_requests ar WHERE ar.api_key_id = ak.id) as total_requests,
                (SELECT COUNT(*) FROM api_requests ar WHERE ar.api_key_id = ak.id AND ar.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as requests_24h
            FROM api_keys ak
            LEFT JOIN admins a ON ak.created_by = a.id
            ORDER BY ak.created_at DESC
        ";
    } else {
        $sql = "
            SELECT 
                ak.*,
                'Administrator' as created_by_name,
                (SELECT COUNT(*) FROM api_requests ar WHERE ar.api_key_id = ak.id) as total_requests,
                (SELECT COUNT(*) FROM api_requests ar WHERE ar.api_key_id = ak.id AND ar.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as requests_24h
            FROM api_keys ak
            ORDER BY ak.created_at DESC
        ";
    }
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        // If prepare fails, return empty array
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        return [];
    }
    
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Key Management - NIELIT Bhubaneswar</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Admin Theme CSS -->
    <link href="../../assets/css/admin-theme.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include '../../admin/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-content">
            <div class="admin-main">
                <div class="container-fluid py-4">
                    <!-- Page Header -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h2><i class="fas fa-key"></i> API Key Management</h2>
                            <p class="text-muted">Manage API keys for external applications</p>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['new_api_key'])): ?>
                        <div class="alert alert-warning alert-dismissible fade show">
                            <h5><i class="fas fa-exclamation-triangle"></i> Important!</h5>
                            <p>Your new API key has been generated. Please copy it now as it won't be shown again:</p>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?php echo $_SESSION['new_api_key']; ?>" id="newApiKey" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyApiKey()">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['new_api_key']); ?>
                    <?php endif; ?>

                    <!-- Create API Key Button -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createApiKeyModal">
                                <i class="fas fa-plus"></i> Create New API Key
                            </button>
                        </div>
                    </div>

                    <!-- API Keys Table -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-list"></i> API Keys</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($api_keys)): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-key fa-4x text-muted mb-3"></i>
                                            <h5 class="text-muted">No API Keys Found</h5>
                                            <p class="text-muted">Create your first API key to get started.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Description</th>
                                                        <th>Permissions</th>
                                                        <th>Rate Limit</th>
                                                        <th>Status</th>
                                                        <th>Usage</th>
                                                        <th>Created</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($api_keys as $key): ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($key['name']); ?></strong></td>
                                                            <td><?php echo htmlspecialchars($key['description']); ?></td>
                                                            <td>
                                                                <span class="badge bg-info"><?php echo ucfirst($key['permissions']); ?></span>
                                                            </td>
                                                            <td><?php echo $key['rate_limit']; ?>/hour</td>
                                                            <td>
                                                                <?php if ($key['is_active']): ?>
                                                                    <span class="badge bg-success">Active</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger">Revoked</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <small>
                                                                    Total: <?php echo $key['total_requests']; ?><br>
                                                                    24h: <?php echo $key['requests_24h']; ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <small>
                                                                    <?php echo date('d M Y', strtotime($key['created_at'])); ?><br>
                                                                    by <?php echo htmlspecialchars($key['created_by_name']); ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <?php if ($key['is_active']): ?>
                                                                    <button class="btn btn-sm btn-outline-primary" onclick="editApiKey(<?php echo $key['id']; ?>)">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-danger" onclick="revokeApiKey(<?php echo $key['id']; ?>)">
                                                                        <i class="fas fa-ban"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Revoked</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create API Key Modal -->
    <div class="modal fade" id="createApiKeyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New API Key</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control" name="name" required 
                                   placeholder="e.g., Mock Test Application">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Brief description of what this API key will be used for"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Permissions</label>
                            <select class="form-select" name="permissions">
                                <option value="read">Read Only</option>
                                <option value="read_write">Read & Write</option>
                                <option value="admin">Admin Access</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Rate Limit (requests per hour)</label>
                            <input type="number" class="form-control" name="rate_limit" 
                                   value="<?php echo API_RATE_LIMIT; ?>" min="1" max="10000">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create API Key</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function copyApiKey() {
            const apiKeyInput = document.getElementById('newApiKey');
            apiKeyInput.select();
            document.execCommand('copy');
            
            // Show feedback
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copied!';
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-success');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }
        
        function revokeApiKey(id) {
            if (confirm('Are you sure you want to revoke this API key? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="api_key_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function editApiKey(id) {
            // Implementation for edit functionality
            alert('Edit functionality coming soon!');
        }
    </script>
</body>
</html>