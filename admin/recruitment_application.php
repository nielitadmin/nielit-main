<?php
/**
 * Admin — one recruitment candidate application.
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
$canEdit = recruitmentCanEdit();

$id = (int) ($_GET['id'] ?? 0);
$app = recruitmentGetApplication($conn, $id);
if (!$app) {
    $_SESSION['message'] = 'Application not found.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . app_url('admin/recruitment_applications'));
    exit();
}

$notice = '';
$noticeType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $notice = 'Invalid security token.';
        $noticeType = 'danger';
    } else {
        $result = recruitmentUpdateApplicationStatus(
            $conn,
            $id,
            (string) ($_POST['status'] ?? ''),
            trim((string) ($_POST['admin_remarks'] ?? ''))
        );
        $notice = $result['message'];
        $noticeType = $result['success'] ? 'success' : 'danger';
        $app = recruitmentGetApplication($conn, $id) ?: $app;
    }
}

$active_theme = loadActiveTheme($conn);
$page_title = 'Application ' . (string) $app['application_no'];

function recVal(array $row, string $key): string
{
    return htmlspecialchars(recruitmentDisplay($row[$key] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .req-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.15rem 1.25rem; height:100%; }
        .req-dl { display:grid; grid-template-columns:150px 1fr; gap:6px 12px; margin:0; }
        .req-dl dt { color:#64748b; font-weight:500; font-size:0.85rem; }
        .req-dl dd { margin:0; }
        .req-photo { width:96px; height:96px; object-fit:cover; border-radius:12px; background:#e2e8f0; }
        @media (max-width:576px) { .req-dl { grid-template-columns:1fr; } }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="mb-3">
            <a href="<?php echo htmlspecialchars(app_url('admin/recruitment_applications') . '?job_id=' . (int) $app['job_id']); ?>">&larr; Back to applications</a>
        </div>
        <?php if ($notice !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($noticeType); ?>"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-4">
            <div class="d-flex gap-3 align-items-center">
                <?php if (!empty($app['photo_path'])): ?>
                    <img class="req-photo" src="<?php echo htmlspecialchars(recruitmentFileUrl((string) $app['photo_path'])); ?>" alt="">
                <?php endif; ?>
                <div>
                    <h2 class="mb-1"><?php echo htmlspecialchars((string) $app['name']); ?></h2>
                    <div class="text-muted"><?php echo htmlspecialchars((string) $app['application_no']); ?></div>
                    <div class="mt-1">
                        <span class="badge text-bg-<?php echo htmlspecialchars(recruitmentStatusBadge((string) $app['status'])); ?>">
                            <?php echo htmlspecialchars(recruitmentApplicationStatuses()[$app['status']] ?? $app['status']); ?>
                        </span>
                        <span class="badge text-bg-light text-dark"><?php echo htmlspecialchars((string) ($app['job_title'] ?? '')); ?></span>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <?php if (!empty($app['resume_path'])): ?>
                    <a class="btn btn-outline-primary" target="_blank" href="<?php echo htmlspecialchars(recruitmentFileUrl((string) $app['resume_path'])); ?>">Resume</a>
                <?php endif; ?>
                <?php if (!empty($app['photo_path'])): ?>
                    <a class="btn btn-outline-secondary" target="_blank" href="<?php echo htmlspecialchars(recruitmentFileUrl((string) $app['photo_path'])); ?>">Photo</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="req-card">
                    <h5>Personal details</h5>
                    <dl class="req-dl">
                        <dt>Name</dt><dd><?php echo recVal($app, 'name'); ?></dd>
                        <dt>Father's name</dt><dd><?php echo recVal($app, 'father_name'); ?></dd>
                        <dt>Mother's name</dt><dd><?php echo recVal($app, 'mother_name'); ?></dd>
                        <dt>Date of birth</dt><dd><?php echo htmlspecialchars(recruitmentFormatDate($app['dob'] ?? '')); ?></dd>
                        <dt>Gender</dt><dd><?php echo recVal($app, 'gender'); ?></dd>
                        <dt>Category</dt><dd><?php echo recVal($app, 'category'); ?></dd>
                        <dt>Aadhaar</dt><dd><?php echo recVal($app, 'aadhar'); ?></dd>
                    </dl>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="req-card">
                    <h5>Contact</h5>
                    <dl class="req-dl">
                        <dt>Mobile</dt><dd><?php echo recVal($app, 'mobile'); ?></dd>
                        <dt>Email</dt><dd><?php echo recVal($app, 'email'); ?></dd>
                        <dt>Address</dt><dd><?php echo recVal($app, 'address'); ?></dd>
                        <dt>City</dt><dd><?php echo recVal($app, 'city'); ?></dd>
                        <dt>State</dt><dd><?php echo recVal($app, 'state'); ?></dd>
                        <dt>Pincode</dt><dd><?php echo recVal($app, 'pincode'); ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="req-card">
                    <h5>Education &amp; experience</h5>
                    <dl class="req-dl">
                        <dt>Qualification</dt><dd><?php echo recVal($app, 'qualification'); ?></dd>
                        <dt>Experience</dt><dd><?php echo recVal($app, 'experience_years'); ?></dd>
                    </dl>
                    <p class="mb-0 mt-2" style="white-space:pre-wrap;"><?php echo htmlspecialchars(recruitmentDisplay($app['experience_details'] ?? '')); ?></p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="req-card">
                    <h5>Applied for</h5>
                    <dl class="req-dl">
                        <dt>Post</dt><dd><?php echo recVal($app, 'job_title'); ?></dd>
                        <dt>Advt no.</dt><dd><?php echo recVal($app, 'advt_no'); ?></dd>
                        <dt>Type</dt><dd><?php echo recVal($app, 'post_type'); ?></dd>
                        <dt>Last date</dt><dd><?php echo htmlspecialchars(recruitmentFormatDate($app['last_date'] ?? '')); ?></dd>
                        <dt>Applied on</dt><dd><?php echo htmlspecialchars(recruitmentFormatDate($app['created_at'] ?? '', 'd M Y, h:i A')); ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <?php if ($canEdit): ?>
        <form class="req-card" method="post">
            <h5>Manage application</h5>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach (recruitmentApplicationStatuses() as $val => $label): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $app['status'] === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control" name="admin_remarks" rows="2"><?php echo htmlspecialchars((string) ($app['admin_remarks'] ?? '')); ?></textarea>
                </div>
            </div>
            <button class="btn btn-primary mt-3" type="submit">Save status</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
