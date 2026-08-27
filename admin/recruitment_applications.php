<?php
/**
 * Admin — recruitment applications inbox.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

recruitmentRequireAccess();
ensureRecruitmentTables($conn);

$jobId = (int) ($_GET['job_id'] ?? 0);
$status = (string) ($_GET['status'] ?? 'all');
$q = trim((string) ($_GET['q'] ?? ''));
$rows = recruitmentListApplications($conn, ['job_id' => $jobId, 'status' => $status, 'q' => $q]);
$jobs = recruitmentListJobs($conn);
$job = $jobId > 0 ? recruitmentGetJob($conn, $jobId) : null;
$active_theme = loadActiveTheme($conn);
$page_title = 'Recruitment applications';
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
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="mb-0"><i class="fas fa-users"></i> Applications</h2>
                <p class="text-muted mb-0">
                    <?php echo $job ? htmlspecialchars('For: ' . $job['title']) : 'All recruitment applications'; ?>
                </p>
            </div>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/recruitment')); ?>">Job openings</a>
        </div>

        <form class="card mb-3" method="get">
            <div class="card-body row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Name, email, mobile, application no.">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Job</label>
                    <select class="form-select" name="job_id">
                        <option value="0">All jobs</option>
                        <?php foreach ($jobs as $j): ?>
                            <option value="<?php echo (int) $j['id']; ?>" <?php echo $jobId === (int) $j['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $j['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="all">All</option>
                        <?php foreach (recruitmentApplicationStatuses() as $val => $label): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $status === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary" type="submit">Filter</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Application no.</th>
                            <th>Candidate</th>
                            <th>Job</th>
                            <th>Mobile / Email</th>
                            <th>Applied</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No applications found.</td></tr>
                    <?php else: foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $row['application_no']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string) $row['name']); ?></strong>
                                <div class="small text-muted"><?php echo htmlspecialchars(recruitmentDisplay($row['qualification'] ?? '')); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($row['job_title'] ?? '')); ?></td>
                            <td>
                                <?php echo htmlspecialchars((string) $row['mobile']); ?><br>
                                <span class="small text-muted"><?php echo htmlspecialchars((string) $row['email']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars(recruitmentFormatDate($row['created_at'] ?? '', 'd M Y, h:i A')); ?></td>
                            <td>
                                <span class="badge text-bg-<?php echo htmlspecialchars(recruitmentStatusBadge((string) $row['status'])); ?>">
                                    <?php echo htmlspecialchars(recruitmentApplicationStatuses()[$row['status']] ?? $row['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars(app_url('admin/recruitment_application') . '?id=' . (int) $row['id']); ?>">View details</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
