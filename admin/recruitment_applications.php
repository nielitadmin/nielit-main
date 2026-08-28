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

recruitmentRequireAccess($conn);
ensureRecruitmentTables($conn);
recruitmentKickMailWorker();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];
$canEdit = recruitmentCanEdit(null, $conn);
$isMasterAdmin = recruitmentIsMasterAdmin();

$jobId = (int) ($_GET['job_id'] ?? 0);
$status = (string) ($_GET['status'] ?? 'all');
$q = trim((string) ($_GET['q'] ?? ''));

$listUrl = static function () use ($jobId, $status, $q): string {
    $params = [];
    if ($jobId > 0) {
        $params['job_id'] = (string) $jobId;
    }
    if ($status !== '' && $status !== 'all') {
        $params['status'] = $status;
    }
    if ($q !== '') {
        $params['q'] = $q;
    }
    $base = app_url('admin/recruitment_applications');
    return $params === [] ? $base : ($base . '?' . http_build_query($params));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['message'] = 'Invalid security token. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . $listUrl());
        exit();
    }
    if ((string) ($_POST['action'] ?? '') === 'delete_application') {
        $result = recruitmentDeleteApplication($conn, (int) ($_POST['id'] ?? 0));
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        header('Location: ' . $listUrl());
        exit();
    }
    if ((string) ($_POST['action'] ?? '') === 'mark_interviewed') {
        if (!recruitmentCanEdit(null, $conn)) {
            $_SESSION['message'] = 'You can view applications but cannot change status.';
            $_SESSION['message_type'] = 'danger';
        } else {
            $result = recruitmentMarkApplicationInterviewed($conn, (int) ($_POST['id'] ?? 0), false);
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
        header('Location: ' . $listUrl());
        exit();
    }
    if ((string) ($_POST['action'] ?? '') === 'undo_interview') {
        $result = recruitmentUndoApplicationInterview($conn, (int) ($_POST['id'] ?? 0));
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        header('Location: ' . $listUrl());
        exit();
    }
}

$notice = (string) ($_SESSION['message'] ?? '');
$noticeType = (string) ($_SESSION['message_type'] ?? 'success');
unset($_SESSION['message'], $_SESSION['message_type']);

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
            <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/recruitment')); ?>">Job openings</a>
            <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(app_url('admin/recruitment_interviews') . ($jobId > 0 ? ('?job_id=' . $jobId) : '')); ?>">Interviews</a>
            </div>
        </div>
        <?php if ($notice !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($noticeType); ?>"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

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
                                <div class="small text-muted"><?php echo htmlspecialchars(recruitmentDisplay(recruitmentHighestEducationLabel($row))); ?></div>
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
                                <?php if (trim((string) ($row['offer_letter_path'] ?? '')) !== ''): ?>
                                    <div class="small mt-1">
                                        <a target="_blank" href="<?php echo htmlspecialchars(recruitmentFileUrl((string) $row['offer_letter_path'])); ?>">Offer letter</a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars(app_url('admin/recruitment_application') . '?id=' . (int) $row['id']); ?>">View details</a>
                                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo htmlspecialchars(app_url('admin/recruitment_form') . '?id=' . (int) $row['id']); ?>">PDF</a>
                                <?php if ($canEdit && in_array((string) $row['status'], ['submitted', 'under_review', 'shortlisted'], true)): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                        <input type="hidden" name="action" value="mark_interviewed">
                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                        <button class="btn btn-sm btn-outline-info" type="submit">Interviewed</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canEdit && (string) $row['status'] === 'selected'): ?>
                                    <a class="btn btn-sm btn-outline-success" href="<?php echo htmlspecialchars(app_url('admin/recruitment_application') . '?id=' . (int) $row['id'] . '#offer-letter'); ?>">
                                        <?php echo trim((string) ($row['offer_letter_path'] ?? '')) !== '' ? 'Replace offer letter' : 'Upload offer letter'; ?>
                                    </a>
                                <?php elseif ($canEdit && (string) $row['status'] === 'interviewed'): ?>
                                    <a class="btn btn-sm btn-outline-success" href="<?php echo htmlspecialchars(app_url('admin/recruitment_application') . '?id=' . (int) $row['id'] . '#offer-letter'); ?>">Select / offer letter</a>
                                    <?php if ($isMasterAdmin): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Undo interview for <?php echo htmlspecialchars((string) $row['name']); ?>? They will go back to Shortlisted and can be called again.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                            <input type="hidden" name="action" value="undo_interview">
                                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                            <button class="btn btn-sm btn-outline-warning" type="submit">Undo interview</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($isMasterAdmin): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete application <?php echo htmlspecialchars((string) $row['application_no']); ?> for <?php echo htmlspecialchars((string) $row['name']); ?>? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                        <input type="hidden" name="action" value="delete_application">
                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
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
