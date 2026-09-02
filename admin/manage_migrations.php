<?php
/**
 * Master Admin: run migrations/ scripts from the admin panel.
 * Direct /migrations/ URLs are blocked by root .htaccess for security.
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/migration_runner_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $_SESSION['message'] = 'Access denied. Only Master Admins can run database migrations.';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

migration_runner_ensure_tracking_table($conn);
$migrations = migration_runner_list_files();
$latestRuns = migration_runner_get_latest_runs($conn);

$categoryFilter = $_GET['category'] ?? 'all';
if (!in_array($categoryFilter, ['all', 'install', 'schema', 'fix', 'data', 'check', 'other'], true)) {
    $categoryFilter = 'all';
}

$page_title = 'DB Migrations';
$active_theme = loadActiveTheme($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .migration-output {
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
            max-height: 420px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .file-name {
            font-family: Consolas, Monaco, monospace;
            font-size: 0.92rem;
            word-break: break-word;
        }
        #migrationTable td:first-child,
        #migrationTable th:first-child {
            min-width: 280px;
        }
        .filter-pills .btn { margin: 0 4px 8px 0; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="admin-main">
        <div class="container-fluid py-4">
            <div class="mb-4">
                <h2><i class="fas fa-database"></i> <?php echo htmlspecialchars($page_title); ?></h2>
                <p class="text-muted mb-0">
                    Run database migration scripts from the admin panel. Direct browser access to
                    <code>/migrations/</code> is blocked on production for security.
                </p>
            </div>

            <?php if (!empty($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo htmlspecialchars($_SESSION['message_type'] ?? 'info'); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($_SESSION['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Before running:</strong> back up your database. Run one migration at a time and review the output.
                Scripts marked <span class="badge bg-danger">Sample data</span> modify live data for testing only.
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="filter-pills">
                        <?php
                        $filters = [
                            'all' => 'All',
                            'install' => 'Install',
                            'schema' => 'Schema / Add',
                            'fix' => 'Fix',
                            'data' => 'Data / Sample',
                            'check' => 'Check / Verify',
                            'other' => 'Other',
                        ];
                        foreach ($filters as $key => $label):
                            $active = ($categoryFilter === $key) ? 'btn-primary' : 'btn-outline-primary';
                        ?>
                            <a href="?category=<?php echo urlencode($key); ?>" class="btn btn-sm <?php echo $active; ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="input-group mt-3">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="migrationSearch" class="form-control" placeholder="Search by file name or description...">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Migration Scripts (<?php echo count($migrations); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="migrationTable">
                        <thead class="table-light">
                            <tr>
                                <th>File</th>
                                <th>Category</th>
                                <th>Last run</th>
                                <th class="text-end" style="min-width: 220px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($migrations as $item):
                            if ($categoryFilter !== 'all' && $item['category'] !== $categoryFilter) {
                                continue;
                            }
                            $run = $latestRuns[$item['filename']] ?? null;
                            $searchText = strtolower($item['filename'] . ' ' . $item['description']);
                        ?>
                            <tr data-search="<?php echo htmlspecialchars($searchText); ?>">
                                <td>
                                    <div class="file-name"><?php echo htmlspecialchars($item['filename']); ?></div>
                                    <?php if ($item['description']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($item['sensitive']): ?>
                                        <div><span class="badge bg-danger">Sample data</span></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category_label']); ?></span>
                                </td>
                                <td>
                                    <?php if ($run): ?>
                                        <span class="badge <?php echo $run['status'] === 'success' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo htmlspecialchars($run['status']); ?>
                                        </span>
                                        <div class="small text-muted">
                                            <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($run['run_at']))); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($item['needs_command']): ?>
                                        <select class="form-select form-select-sm d-inline-block w-auto me-1 migration-command"
                                                data-file="<?php echo htmlspecialchars($item['filename']); ?>">
                                            <?php if ($item['filename'] === 'approve_olevel_it_nold_2026_students.php'): ?>
                                                <option value="install">preview</option>
                                                <option value="apply">apply</option>
                                            <?php else: ?>
                                                <option value="install">install</option>
                                                <option value="verify">verify</option>
                                                <?php if ($item['filename'] === 'install_rbac.php' || $item['filename'] === 'install_document_categories.php'): ?>
                                                    <option value="rollback">rollback</option>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </select>
                                    <?php endif; ?>
                                    <button type="button"
                                            class="btn btn-sm btn-primary run-migration-btn"
                                            data-file="<?php echo htmlspecialchars($item['filename']); ?>"
                                            data-sensitive="<?php echo $item['sensitive'] ? '1' : '0'; ?>"
                                            data-needs-command="<?php echo $item['needs_command'] ? '1' : '0'; ?>">
                                        <i class="fas fa-play"></i> Run
                                    </button>
                                    <?php if ($run && !empty($run['output'])): ?>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary view-output-btn"
                                                data-output="<?php echo htmlspecialchars($run['output'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-file="<?php echo htmlspecialchars($item['filename']); ?>">
                                            <i class="fas fa-terminal"></i>
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
        </div>
    </main>
</div>

<div class="modal fade" id="outputModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-terminal"></i> <span id="outputModalTitle">Migration output</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="outputModalBody" class="migration-output"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;
const outputModal = new bootstrap.Modal(document.getElementById('outputModal'));

document.getElementById('migrationSearch').addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('#migrationTable tbody tr').forEach(function (row) {
        const text = row.getAttribute('data-search') || '';
        row.style.display = !q || text.indexOf(q) !== -1 ? '' : 'none';
    });
});

document.querySelectorAll('.view-output-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('outputModalTitle').textContent = btn.dataset.file;
        document.getElementById('outputModalBody').textContent = btn.dataset.output || '';
        outputModal.show();
    });
});

document.querySelectorAll('.run-migration-btn').forEach(function (btn) {
    btn.addEventListener('click', async function () {
        const file = btn.dataset.file;
        const sensitive = btn.dataset.sensitive === '1';
        let command = 'install';

        if (btn.dataset.needsCommand === '1') {
            const row = btn.closest('tr');
            const select = row ? row.querySelector('.migration-command') : null;
            command = select ? select.value : 'install';
        }

        let confirmMsg = 'Run migration "' + file + '"';
        if (command !== 'install') {
            confirmMsg += ' with command "' + command + '"';
        }
        confirmMsg += '?';
        if (sensitive) {
            confirmMsg += '\n\nThis script modifies sample/live data. Continue only on a test database.';
        }
        if (command === 'rollback') {
            confirmMsg += '\n\nROLLBACK may delete data. Are you sure?';
        }
        if (command === 'apply') {
            confirmMsg += '\n\nAPPLY will approve listed students and de-approve extras. Backup first.';
        }
        if (!confirm(confirmMsg)) {
            return;
        }

        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running...';

        try {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('migration_file', file);
            formData.append('migration_command', command);

            const response = await fetch('run_migration.php', {
                method: 'POST',
                body: formData
            });
            const raw = await response.text();
            let data;
            try {
                data = JSON.parse(raw);
            } catch (parseErr) {
                const start = raw.indexOf('{');
                const end = raw.lastIndexOf('}');
                if (start < 0 || end <= start) {
                    throw parseErr;
                }
                data = JSON.parse(raw.slice(start, end + 1));
            }

            document.getElementById('outputModalTitle').textContent = file + (data.success ? ' — success' : ' — failed');
            let text = data.output || data.message || '';
            if (data.error) {
                text += (text ? '\n\n' : '') + 'Error: ' + data.error;
            }
            document.getElementById('outputModalBody').textContent = text;
            outputModal.show();

            if (data.success) {
                setTimeout(function () { window.location.reload(); }, 1500);
            }
        } catch (err) {
            alert('Request failed: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });
});
</script>
</body>
</html>
