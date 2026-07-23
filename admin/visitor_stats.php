<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/theme_loader.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $_SESSION['message'] = 'Access denied. Visitor statistics are available to Master Admin only.';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit();
}

$active_theme = loadActiveTheme($conn);
$days = max(7, min(365, (int) ($_GET['days'] ?? 30)));
$summary = getVisitorSummary($conn);
$pageStats = getVisitorStatsByPage($conn, $days, 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Statistics - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .visitor-kpi {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            height: 100%;
            padding: 1.25rem;
            background: #fff;
        }
        .visitor-kpi .value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
        }
        .visitor-kpi .label {
            color: #64748b;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-eye"></i> Visitor Statistics</h4>
                <small>Page views and unique visitors across the website</small>
            </div>
        </div>

        <div class="admin-main">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="visitor-kpi">
                        <div class="value"><?php echo formatVisitorCount($summary['today_unique_visitors']); ?></div>
                        <div class="label">Unique Visitors Today</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="visitor-kpi">
                        <div class="value"><?php echo formatVisitorCount($summary['today_page_views']); ?></div>
                        <div class="label">Page Views Today</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="visitor-kpi">
                        <div class="value"><?php echo formatVisitorCount($summary['total_unique_visitors']); ?></div>
                        <div class="label">Total Unique Visitors</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="visitor-kpi">
                        <div class="value"><?php echo formatVisitorCount($summary['total_page_views']); ?></div>
                        <div class="label">Total Page Views</div>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0"><i class="fas fa-list"></i> Page-wise Traffic (Last <?php echo (int) $days; ?> Days)</h5>
                    <form method="get" class="d-flex align-items-center gap-2">
                        <label for="days" class="mb-0 small text-muted">Period</label>
                        <select name="days" id="days" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach ([7, 30, 90, 180, 365] as $option): ?>
                                <option value="<?php echo $option; ?>" <?php echo $days === $option ? 'selected' : ''; ?>>
                                    <?php echo $option; ?> days
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="card-body">
                    <?php if (empty($pageStats)): ?>
                        <p class="text-muted mb-0">No visitor data yet. Browse the public site to start collecting counts.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Page</th>
                                        <th class="text-end">Page Views</th>
                                        <th class="text-end">Unique Visitors</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pageStats as $index => $row): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><code><?php echo htmlspecialchars($row['page_path']); ?></code></td>
                                            <td class="text-end"><?php echo formatVisitorCount($row['page_views']); ?></td>
                                            <td class="text-end"><?php echo formatVisitorCount($row['unique_visitors']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
