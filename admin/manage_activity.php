<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/activity_logger.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $_SESSION['message'] = 'Access denied. Activity log is available to Master Admin only.';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit();
}

$active_theme = loadActiveTheme($conn);
ensureActivityLogsTable($conn);

$filters = [
    'actor_type' => trim((string) ($_GET['actor_type'] ?? '')),
    'action' => trim((string) ($_GET['action'] ?? '')),
    'entity_type' => trim((string) ($_GET['entity_type'] ?? '')),
    'q' => trim((string) ($_GET['q'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$result = fetchActivityLogs($conn, array_filter($filters), $perPage, $offset);
$rows = $result['rows'];
$total = $result['total'];
$totalPages = max(1, (int) ceil($total / $perPage));
$actionLabels = activityActionLabels();

$entityTypes = [];
$etRes = $conn->query("SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL AND entity_type != '' ORDER BY entity_type ASC LIMIT 100");
if ($etRes) {
    while ($r = $etRes->fetch_assoc()) {
        $entityTypes[] = $r['entity_type'];
    }
}

function activityBadgeClass(string $actorType): string
{
    if ($actorType === 'admin') {
        return 'badge-admin';
    }
    if ($actorType === 'student') {
        return 'badge-student';
    }
    return 'badge-system';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
    <style>
        .activity-filters {
            background: #fff;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            margin-bottom: 1.25rem;
        }
        .activity-table-wrap {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }
        .activity-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
        }
        .activity-table th,
        .activity-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
            font-size: 0.92rem;
        }
        .activity-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-actor {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-admin { background: #dbeafe; color: #1d4ed8; }
        .badge-student { background: #d1fae5; color: #047857; }
        .badge-system { background: #e2e8f0; color: #475569; }
        .activity-desc { color: #0f172a; font-weight: 500; }
        .activity-meta { color: #64748b; font-size: 0.8rem; margin-top: 0.25rem; }
        .activity-kpi {
            background: #fff;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            margin-bottom: 1.25rem;
        }
        .activity-kpi strong { font-size: 1.4rem; color: #0f172a; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h2 class="mb-1"><i class="fas fa-stream"></i> Activity Log</h2>
                <p class="text-muted mb-0">Track admin and student actions across the system.</p>
            </div>
        </div>

        <div class="activity-kpi">
            Showing <strong><?php echo number_format(count($rows)); ?></strong>
            of <strong><?php echo number_format($total); ?></strong> activities
            <?php if ($totalPages > 1): ?>
                · Page <?php echo $page; ?> / <?php echo $totalPages; ?>
            <?php endif; ?>
        </div>

        <form method="get" class="activity-filters">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Actor</label>
                    <select name="actor_type" class="form-select">
                        <option value="">All</option>
                        <option value="admin" <?php echo $filters['actor_type'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="student" <?php echo $filters['actor_type'] === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="system" <?php echo $filters['actor_type'] === 'system' ? 'selected' : ''; ?>>System</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Action</label>
                    <select name="action" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($actionLabels as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $filters['action'] === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Entity</label>
                    <select name="entity_type" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($entityTypes as $et): ?>
                            <option value="<?php echo htmlspecialchars($et); ?>" <?php echo $filters['entity_type'] === $et ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($et)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" name="q" class="form-control" placeholder="Name, ID, text…" value="<?php echo htmlspecialchars($filters['q']); ?>">
                </div>
                <div class="col-12 d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                    <a href="manage_activity.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="activity-table-wrap">
            <div class="table-responsive">
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Who</th>
                            <th>Action</th>
                            <th>What happened</th>
                            <th>Entity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">No activity recorded yet. New actions will appear here automatically.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $label = $actionLabels[$row['action']] ?? ucwords(str_replace('_', ' ', (string) $row['action']));
                                $who = trim(($row['actor_name'] ?? '') . ($row['actor_role'] ? ' (' . $row['actor_role'] . ')' : ''));
                                if ($who === '') {
                                    $who = $row['actor_id'] ?: 'System';
                                }
                                ?>
                                <tr>
                                    <td style="white-space:nowrap;">
                                        <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($row['created_at']))); ?>
                                        <?php if (!empty($row['ip_address'])): ?>
                                            <div class="activity-meta"><?php echo htmlspecialchars($row['ip_address']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-actor <?php echo activityBadgeClass($row['actor_type']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($row['actor_type'])); ?>
                                        </span>
                                        <div class="activity-meta mt-1"><?php echo htmlspecialchars($who); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($label); ?></td>
                                    <td>
                                        <div class="activity-desc"><?php echo htmlspecialchars($row['description']); ?></div>
                                        <?php if (!empty($row['details'])): ?>
                                            <div class="activity-meta"><?php echo htmlspecialchars(mb_strimwidth((string) $row['details'], 0, 180, '…')); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['entity_type'])): ?>
                                            <strong><?php echo htmlspecialchars(ucfirst($row['entity_type'])); ?></strong>
                                            <?php if (!empty($row['entity_name'])): ?>
                                                <div class="activity-meta"><?php echo htmlspecialchars($row['entity_name']); ?></div>
                                            <?php elseif (!empty($row['entity_id'])): ?>
                                                <div class="activity-meta">#<?php echo htmlspecialchars($row['entity_id']); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination">
                    <?php
                    $queryBase = $_GET;
                    for ($p = 1; $p <= $totalPages; $p++):
                        $queryBase['page'] = $p;
                        $href = 'manage_activity.php?' . http_build_query($queryBase);
                    ?>
                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars($href); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
