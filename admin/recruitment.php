<?php
/**
 * Admin — recruitment jobs (create, open/close, manage).
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];
$adminUser = (string) ($_SESSION['admin'] ?? '');
$canEdit = recruitmentCanEdit();

$notice = (string) ($_SESSION['message'] ?? '');
$noticeType = (string) ($_SESSION['message_type'] ?? 'success');
unset($_SESSION['message'], $_SESSION['message_type']);

$editId = (int) ($_GET['edit'] ?? 0);
$showForm = isset($_GET['new']) || $editId > 0;
$job = $editId > 0 ? recruitmentGetJob($conn, $editId) : null;
if ($editId > 0 && !$job) {
    $notice = 'Job opening not found.';
    $noticeType = 'danger';
    $showForm = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['message'] = 'Invalid security token. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . app_url('admin/recruitment'));
        exit();
    }
    if (!$canEdit) {
        $_SESSION['message'] = 'You can view recruitment but cannot change job openings.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . app_url('admin/recruitment'));
        exit();
    }
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'delete') {
        $result = recruitmentDeleteJob($conn, (int) ($_POST['id'] ?? 0));
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        header('Location: ' . app_url('admin/recruitment'));
        exit();
    }

    $attachment = trim((string) ($_POST['existing_attachment'] ?? ''));
    if (!empty($_FILES['attachment']['name'])) {
        $up = recruitmentStoreUpload($_FILES['attachment'], 'jobs', ['pdf', 'jpg', 'jpeg', 'png'], MAX_FILE_SIZE);
        if (!$up['ok']) {
            $_SESSION['message'] = $up['message'] ?: 'Could not upload the advertisement file.';
            $_SESSION['message_type'] = 'danger';
            header('Location: ' . app_url('admin/recruitment') . ((int) ($_POST['id'] ?? 0) > 0 ? ('?edit=' . (int) $_POST['id']) : '?new=1'));
            exit();
        }
        $attachment = $up['path'];
    }

    $result = recruitmentSaveJob($conn, [
        'id' => (int) ($_POST['id'] ?? 0),
        'title' => $_POST['title'] ?? '',
        'advt_no' => $_POST['advt_no'] ?? '',
        'post_type' => $_POST['post_type'] ?? 'Other',
        'vacancies' => $_POST['vacancies'] ?? 1,
        'location' => $_POST['location'] ?? '',
        'pay_scale' => $_POST['pay_scale'] ?? '',
        'eligibility' => $_POST['eligibility'] ?? '',
        'experience' => $_POST['experience'] ?? '',
        'age_limit' => $_POST['age_limit'] ?? '',
        'description' => $_POST['description'] ?? '',
        'instructions' => $_POST['instructions'] ?? '',
        'last_date' => $_POST['last_date'] ?? '',
        'open_from' => $_POST['open_from'] ?? '',
        'status' => $_POST['status'] ?? 'draft',
        'attachment_path' => $attachment,
    ], $adminUser);
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    header('Location: ' . app_url('admin/recruitment'));
    exit();
}

$filterStatus = (string) ($_GET['status'] ?? 'all');
$jobs = recruitmentListJobs($conn, ['status' => $filterStatus, 'q' => (string) ($_GET['q'] ?? '')]);
$stats = recruitmentStats($conn);
$active_theme = loadActiveTheme($conn);
$page_title = 'Recruitment';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .req-stat { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.1rem; }
        .req-stat b { display:block; font-size:1.6rem; color:#0f172a; }
        .req-stat span { color:#64748b; font-size:0.85rem; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="mb-0"><i class="fas fa-briefcase"></i> Recruitment</h2>
                <p class="text-muted mb-0">Create job openings, set what candidates must do, and manage applications.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/recruitment_applications')); ?>">Applications</a>
                <a class="btn btn-outline-primary" target="_blank" href="<?php echo htmlspecialchars(app_url('public/recruitment')); ?>">Public portal</a>
                <?php if ($canEdit && !$showForm): ?>
                    <a class="btn btn-primary" href="<?php echo htmlspecialchars(app_url('admin/recruitment') . '?new=1'); ?>"><i class="fas fa-plus"></i> New job opening</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($notice !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($noticeType); ?>"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="req-stat"><span>Job openings</span><b><?php echo (int) $stats['jobs']; ?></b></div></div>
            <div class="col-md-3"><div class="req-stat"><span>Currently open</span><b><?php echo (int) $stats['open']; ?></b></div></div>
            <div class="col-md-3"><div class="req-stat"><span>Applications</span><b><?php echo (int) $stats['applications']; ?></b></div></div>
            <div class="col-md-3"><div class="req-stat"><span>Shortlisted</span><b><?php echo (int) $stats['shortlisted']; ?></b></div></div>
        </div>

        <?php if ($showForm && $canEdit): ?>
            <?php $j = $job ?: []; ?>
            <form class="card mb-4" method="post" enctype="multipart/form-data">
                <div class="card-header fw-semibold"><?php echo $job ? 'Edit job opening' : 'New job opening'; ?></div>
                <div class="card-body row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="id" value="<?php echo (int) ($j['id'] ?? 0); ?>">
                    <input type="hidden" name="existing_attachment" value="<?php echo htmlspecialchars((string) ($j['attachment_path'] ?? '')); ?>">
                    <div class="col-md-8">
                        <label class="form-label">Post / job title *</label>
                        <input class="form-control" name="title" required value="<?php echo htmlspecialchars((string) ($j['title'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Advertisement no.</label>
                        <input class="form-control" name="advt_no" value="<?php echo htmlspecialchars((string) ($j['advt_no'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Post type</label>
                        <select class="form-select" name="post_type">
                            <?php foreach (recruitmentPostTypes() as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo (($j['post_type'] ?? '') === $val) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Vacancies</label>
                        <input class="form-control" type="number" min="1" name="vacancies" value="<?php echo (int) ($j['vacancies'] ?? 1); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Location</label>
                        <input class="form-control" name="location" value="<?php echo htmlspecialchars((string) ($j['location'] ?? 'Bhubaneswar')); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pay / stipend</label>
                        <input class="form-control" name="pay_scale" value="<?php echo htmlspecialchars((string) ($j['pay_scale'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Open from</label>
                        <input class="form-control" type="date" name="open_from" value="<?php echo htmlspecialchars((string) ($j['open_from'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last date to apply</label>
                        <input class="form-control" type="date" name="last_date" value="<?php echo htmlspecialchars((string) ($j['last_date'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <?php foreach (recruitmentJobStatuses() as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo (($j['status'] ?? 'draft') === $val) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Set to <strong>Open</strong> to show this job on the public apply form.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Eligibility</label>
                        <textarea class="form-control" name="eligibility" rows="3"><?php echo htmlspecialchars((string) ($j['eligibility'] ?? '')); ?></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Experience required</label>
                        <textarea class="form-control" name="experience" rows="3"><?php echo htmlspecialchars((string) ($j['experience'] ?? '')); ?></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Age limit</label>
                        <input class="form-control" name="age_limit" value="<?php echo htmlspecialchars((string) ($j['age_limit'] ?? '')); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Job description / duties</label>
                        <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars((string) ($j['description'] ?? '')); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">What candidates should do</label>
                        <textarea class="form-control" name="instructions" rows="4" placeholder="e.g. Fill the online form, upload resume and photo, keep Aadhaar ready, no fee."><?php echo htmlspecialchars((string) ($j['instructions'] ?? '')); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Advertisement (PDF / image)</label>
                        <input class="form-control" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                        <?php if (!empty($j['attachment_path'])): ?>
                            <div class="form-text">Current file: <a target="_blank" href="<?php echo htmlspecialchars(recruitmentFileUrl((string) $j['attachment_path'])); ?>">View</a></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save job opening</button>
                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/recruitment')); ?>">Cancel</a>
                </div>
            </form>
        <?php endif; ?>

        <form class="card mb-3" method="get">
            <div class="card-body row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input class="form-control" name="q" value="<?php echo htmlspecialchars((string) ($_GET['q'] ?? '')); ?>" placeholder="Title, advertisement no., location">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="all">All</option>
                        <?php foreach (recruitmentJobStatuses() as $val => $label): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $filterStatus === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/recruitment')); ?>">Reset</a>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Job</th>
                            <th>Type</th>
                            <th>Last date</th>
                            <th>Status</th>
                            <th>Applications</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($jobs)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No job openings yet. Create one to start receiving applications.</td></tr>
                    <?php else: foreach ($jobs as $row): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars((string) $row['title']); ?></strong>
                                <div class="small text-muted"><?php echo htmlspecialchars(recruitmentDisplay($row['advt_no'] ?? '')); ?> · <?php echo htmlspecialchars(recruitmentDisplay($row['location'] ?? '')); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($row['post_type'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars(recruitmentFormatDate($row['last_date'] ?? '')); ?></td>
                            <td>
                                <span class="badge text-bg-<?php echo htmlspecialchars(recruitmentStatusBadge((string) ($row['status'] ?? ''))); ?>">
                                    <?php echo htmlspecialchars(recruitmentJobStatuses()[$row['status'] ?? ''] ?? ucfirst((string) $row['status'])); ?>
                                </span>
                                <?php if (($row['status'] ?? '') === 'open' && !recruitmentJobIsAccepting($row)): ?>
                                    <div class="small text-danger">Past last date</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) ($row['application_count'] ?? 0); ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(app_url('admin/recruitment_applications') . '?job_id=' . (int) $row['id']); ?>">Applications</a>
                                <?php if ($canEdit): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/recruitment') . '?edit=' . (int) $row['id']); ?>">Edit</a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this job and all its applications?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                        <input type="hidden" name="action" value="delete">
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
