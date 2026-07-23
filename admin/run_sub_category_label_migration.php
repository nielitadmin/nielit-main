<?php
/**
 * Master Admin: run sub-category label migration from the admin panel.
 * Replaces blocked /migrations/ web access for production one-time updates.
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/course_sub_category_migration.php';

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

$run_results = [];
$run_attempted = false;
$pending = get_sub_category_label_migration_pending($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_migration') {
    $run_attempted = true;
    if (empty($_POST['confirm_run'])) {
        $_SESSION['message'] = 'Please confirm before running the migration.';
        $_SESSION['message_type'] = 'warning';
    } else {
        $run_results = run_sub_category_label_migration($conn);
        $pending = get_sub_category_label_migration_pending($conn);

        $failed = array_filter($run_results, static function ($row) {
            return empty($row['success']);
        });

        if (empty($failed)) {
            $_SESSION['message'] = 'Sub-category label migration completed successfully.';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Migration finished with errors. Review the results below.';
            $_SESSION['message_type'] = 'danger';
        }
    }
}

$page_title = 'Sub-Category Label Migration';
$active_theme = loadActiveTheme($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="container-fluid py-4">
            <div class="mb-4">
                <h2><i class="fas fa-tags"></i> <?php echo htmlspecialchars($page_title); ?></h2>
                <p class="text-muted mb-0">
                    Updates legacy labels in the database:
                    <strong>NON-NSQF Course</strong> → <strong>Non-NSQF Course</strong>,
                    <strong>GOVT/CORPORATE Training</strong> → <strong>Govt/Corporate Training</strong>.
                </p>
            </div>

            <?php if (!empty($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo htmlspecialchars($_SESSION['message_type'] ?? 'info'); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($_SESSION['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-database"></i> Pending Updates</h5>
                </div>
                <div class="card-body">
                    <?php if ($pending['is_complete']): ?>
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle"></i>
                            All sub-category labels are already up to date. No migration needed.
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Rows still using old labels:</p>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Target</th>
                                        <th class="text-end">Rows to update</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending['steps'] as $step): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($step['label']); ?></td>
                                            <td class="text-end">
                                                <span class="badge <?php echo $step['count'] > 0 ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                                    <?php echo (int) $step['count']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th class="text-end"><?php echo (int) $pending['total']; ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($run_attempted && !empty($run_results)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list-check"></i> Migration Results</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($run_results as $result): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div>
                                        <?php if ($result['success']): ?>
                                            <i class="fas fa-check-circle text-success"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle text-danger"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($result['label']); ?>
                                        <?php if (!empty($result['error'])): ?>
                                            <div class="small text-danger"><?php echo htmlspecialchars($result['error']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge bg-secondary"><?php echo (int) $result['affected']; ?> updated</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$pending['is_complete']): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-play-circle"></i> Run Migration</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Safe to run more than once — only rows with old labels are updated.
                        </p>
                        <form method="POST" onsubmit="return confirm('Run the sub-category label migration now?');">
                            <input type="hidden" name="action" value="run_migration">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="confirm_run" value="1" id="confirm_run" required>
                                <label class="form-check-label" for="confirm_run">
                                    I understand this will update course sub-category labels in the database.
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-database"></i> Run Migration Now
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
