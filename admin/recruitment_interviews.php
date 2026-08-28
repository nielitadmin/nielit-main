<?php
/**
 * Admin — interview schedules for recruitment jobs.
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];
$canEdit = recruitmentCanEdit(null, $conn);
$adminUser = (string) ($_SESSION['admin'] ?? '');

$jobId = (int) ($_GET['job_id'] ?? 0);
$showNew = isset($_GET['new']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['message'] = 'Invalid security token. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . app_url('admin/recruitment_interviews'));
        exit();
    }
    if (!$canEdit) {
        $_SESSION['message'] = 'You can view interviews but cannot create them.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . app_url('admin/recruitment_interviews'));
        exit();
    }
    $result = recruitmentSaveInterview($conn, [
        'job_id' => (int) ($_POST['job_id'] ?? 0),
        'title' => $_POST['title'] ?? '',
        'interview_date' => $_POST['interview_date'] ?? '',
        'interview_time' => $_POST['interview_time'] ?? '',
        'mode' => $_POST['mode'] ?? 'online',
        'venue' => $_POST['venue'] ?? '',
        'meeting_url' => $_POST['meeting_url'] ?? '',
        'notes' => $_POST['notes'] ?? '',
        'created_by' => $adminUser,
    ]);
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    if ($result['success'] && !empty($result['id'])) {
        header('Location: ' . app_url('admin/recruitment_interview') . '?id=' . (int) $result['id']);
        exit();
    }
    header('Location: ' . app_url('admin/recruitment_interviews') . '?new=1');
    exit();
}

$notice = (string) ($_SESSION['message'] ?? '');
$noticeType = (string) ($_SESSION['message_type'] ?? 'success');
unset($_SESSION['message'], $_SESSION['message_type']);

$jobs = recruitmentListJobs($conn);
$rows = recruitmentListInterviews($conn, $jobId);
$active_theme = loadActiveTheme($conn);
$page_title = 'Interview schedule';
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
                <h2 class="mb-0"><i class="fas fa-video"></i> Interview schedule</h2>
                <p class="text-muted mb-0">Schedule interviews and call candidates one by one on a system-controlled link.</p>
            </div>
            <?php if ($canEdit): ?>
                <a class="btn btn-primary" href="<?php echo htmlspecialchars(app_url('admin/recruitment_interviews') . '?new=1'); ?>">New interview</a>
            <?php endif; ?>
        </div>
        <?php if ($notice !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($noticeType); ?>"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <?php if ($showNew && $canEdit): ?>
        <form class="card mb-4" method="post">
            <div class="card-header fw-semibold">Schedule an interview</div>
            <div class="card-body row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <div class="col-md-6">
                    <label class="form-label">Job opening</label>
                    <select class="form-select" name="job_id" required>
                        <option value="">Select job</option>
                        <?php foreach ($jobs as $j): ?>
                            <option value="<?php echo (int) $j['id']; ?>" <?php echo $jobId === (int) $j['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $j['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Title</label>
                    <input class="form-control" name="title" required placeholder="e.g. Online interview — round 1">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input class="form-control" type="date" name="interview_date" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Time</label>
                    <input class="form-control" type="time" name="interview_time">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mode</label>
                    <select class="form-select" name="mode" id="ivMode">
                        <option value="online">Online</option>
                        <option value="offline">Offline / campus</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Venue (if offline)</label>
                    <input class="form-control" name="venue" placeholder="Room / campus">
                </div>
                <div class="col-12">
                    <label class="form-label">Online meeting link (optional)</label>
                    <input class="form-control" name="meeting_url" placeholder="Paste Google Meet / Zoom if you have one. Leave blank to use the system room.">
                    <div class="form-text">If blank, the system opens a gated Jitsi room. Candidates can join that room only after you click Call.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes for the board</label>
                    <textarea class="form-control" name="notes" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Save schedule</button>
                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/recruitment_interviews')); ?>">Cancel</a>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Interview</th>
                            <th>Job</th>
                            <th>Date / time</th>
                            <th>Mode</th>
                            <th>Candidates</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No interviews scheduled yet.</td></tr>
                    <?php else: foreach ($rows as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars((string) $row['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) ($row['job_title'] ?? '')); ?></td>
                            <td>
                                <?php echo htmlspecialchars(recruitmentFormatDate($row['interview_date'] ?? '')); ?>
                                <?php $tm = substr((string) ($row['interview_time'] ?? ''), 0, 5); echo $tm && $tm !== '00:00' ? ' · ' . htmlspecialchars($tm) : ''; ?>
                            </td>
                            <td><?php echo htmlspecialchars(ucfirst((string) $row['mode'])); ?></td>
                            <td><?php echo (int) ($row['candidate_count'] ?? 0); ?></td>
                            <td>
                                <span class="badge text-bg-<?php echo ($row['status'] ?? '') === 'live' ? 'success' : (($row['status'] ?? '') === 'completed' ? 'secondary' : 'info'); ?>">
                                    <?php echo htmlspecialchars(recruitmentInterviewStatuses()[$row['status'] ?? ''] ?? (string) $row['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars(app_url('admin/recruitment_interview') . '?id=' . (int) $row['id']); ?>">Open desk</a>
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
