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

recruitmentRequireAccess($conn);
ensureRecruitmentTables($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];
$canEdit = recruitmentCanEdit(null, $conn);

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
            trim((string) ($_POST['admin_remarks'] ?? '')),
            !empty($_POST['notify_email'])
        );
        $notice = $result['message'];
        $noticeType = $result['success'] ? 'success' : 'danger';
        $app = recruitmentGetApplication($conn, $id) ?: $app;
    }
}

$education = recruitmentDecodeJsonList($app['education_json'] ?? '');
$experienceRows = recruitmentDecodeJsonList($app['experience_json'] ?? '');
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
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary" target="_blank" href="<?php echo htmlspecialchars(app_url('admin/recruitment_form') . '?id=' . (int) $app['id']); ?>">Print / PDF form</a>
                <?php if (!empty($app['resume_path'])): ?>
                    <a class="btn btn-outline-primary" target="_blank" href="<?php echo htmlspecialchars(recruitmentFileUrl((string) $app['resume_path'])); ?>">Resume</a>
                <?php endif; ?>
                <?php if (!empty($app['photo_path'])): ?>
                    <a class="btn btn-outline-secondary" target="_blank" href="<?php echo htmlspecialchars(recruitmentFileUrl((string) $app['photo_path'])); ?>">Photo</a>
                <?php endif; ?>
                <?php if (!empty($app['signature_path'])): ?>
                    <a class="btn btn-outline-secondary" target="_blank" href="<?php echo htmlspecialchars(recruitmentFileUrl((string) $app['signature_path'])); ?>">Signature</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="req-card">
                    <h5>Personal details</h5>
                    <dl class="req-dl">
                        <dt>Name</dt><dd><?php echo recVal($app, 'name'); ?></dd>
                        <dt>First / Middle / Last</dt>
                        <dd><?php
                            $nmParts = trim((string) preg_replace('/\s+/', ' ', trim((string) ($app['name_first'] ?? '') . ' ' . (string) ($app['name_middle'] ?? '') . ' ' . (string) ($app['name_last'] ?? ''))));
                            echo htmlspecialchars($nmParts !== '' ? $nmParts : '—');
                        ?></dd>
                        <dt>Father's / Husband's name</dt><dd><?php echo recVal($app, 'father_name'); ?></dd>
                        <dt>Mother's name</dt><dd><?php echo recVal($app, 'mother_name'); ?></dd>
                        <dt>Date of birth</dt><dd><?php echo htmlspecialchars(recruitmentFormatDate($app['dob'] ?? '')); ?></dd>
                        <dt>Gender</dt><dd><?php echo recVal($app, 'gender'); ?></dd>
                        <dt>Category</dt><dd><?php echo recVal($app, 'category'); ?></dd>
                        <dt>Aadhaar</dt><dd><?php echo recVal($app, 'aadhar'); ?></dd>
                        <dt>Marital status</dt><dd><?php echo recVal($app, 'marital_status'); ?></dd>
                        <dt>Nationality</dt><dd><?php echo recVal($app, 'nationality'); ?></dd>
                        <dt>PwD</dt><dd><?php
                            echo recVal($app, 'pwd_status');
                            $pwdBits = [];
                            if (trim((string) ($app['pwd_type'] ?? '')) !== '') {
                                $pwdBits[] = (string) $app['pwd_type'];
                            }
                            if (trim((string) ($app['pwd_percent'] ?? '')) !== '') {
                                $pwdBits[] = (string) $app['pwd_percent'] . '%';
                            }
                            if ($pwdBits !== []) {
                                echo ' (' . htmlspecialchars(implode(', ', $pwdBits)) . ')';
                            }
                        ?></dd>
                        <dt>Age (as on last date)</dt>
                        <dd><?php
                            $ay = (int) ($app['age_years'] ?? 0);
                            $am = (int) ($app['age_months'] ?? 0);
                            $ad = (int) ($app['age_days'] ?? 0);
                            echo $ay || $am || $ad ? ($ay . ' years ' . $am . ' months ' . $ad . ' days') : '—';
                        ?></dd>
                    </dl>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="req-card">
                    <h5>Contact</h5>
                    <dl class="req-dl">
                        <dt>Mobile</dt><dd><?php echo recVal($app, 'mobile'); ?></dd>
                        <dt>Alternate mobile</dt><dd><?php echo recVal($app, 'alt_mobile'); ?></dd>
                        <dt>Email</dt><dd><?php echo recVal($app, 'email'); ?></dd>
                        <dt>Address</dt><dd><?php echo recVal($app, 'address'); ?></dd>
                        <dt>City</dt><dd><?php echo recVal($app, 'city'); ?></dd>
                        <dt>State</dt><dd><?php echo recVal($app, 'state'); ?></dd>
                        <dt>Pincode</dt><dd><?php echo recVal($app, 'pincode'); ?></dd>
                        <dt>Permanent address</dt><dd><?php echo recVal($app, 'permanent_address'); ?></dd>
                        <dt>Permanent PIN</dt><dd><?php echo recVal($app, 'permanent_pincode'); ?></dd>
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

        <div class="req-card mb-3">
            <h5>13. Examinations passed</h5>
            <?php if ($education === []): ?>
                <p class="text-muted mb-0">No education rows saved.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Examination / Degree</th><th>University / Board</th><th>Year</th><th>% / CGPA</th><th>Subjects</th></tr></thead>
                        <tbody>
                        <?php foreach ($education as $ed): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(recruitmentDisplay($ed['exam'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(recruitmentDisplay($ed['board'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(recruitmentDisplay($ed['year'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(recruitmentDisplay($ed['percent'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(recruitmentDisplay($ed['subjects'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="req-card mb-3">
            <h5>14. Experience</h5>
            <p class="small text-muted">Total: <?php echo htmlspecialchars(recruitmentDisplay($app['experience_years'] ?? '')); ?></p>
            <?php if ($experienceRows === []): ?>
                <p class="text-muted mb-0"><?php echo htmlspecialchars(recruitmentDisplay($app['experience_details'] ?? '')); ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Organisation</th><th>Post</th><th>From</th><th>To</th><th>Duration</th><th>Nature / pay</th></tr></thead>
                        <tbody>
                        <?php foreach ($experienceRows as $er): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(recruitmentDisplay($er['org'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(recruitmentDisplay($er['post'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(recruitmentDisplay($er['from'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(recruitmentDisplay($er['to'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(recruitmentDisplay($er['duration'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(recruitmentDisplay($er['nature'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="req-card mb-3">
            <h5>15. Computer knowledge / other information</h5>
            <dl class="req-dl">
                <dt>Computer knowledge</dt><dd style="white-space:pre-wrap;"><?php echo recVal($app, 'computer_knowledge'); ?></dd>
                <dt>Any other information</dt><dd style="white-space:pre-wrap;"><?php echo recVal($app, 'additional_info'); ?></dd>
                <dt>Place of application</dt><dd><?php echo recVal($app, 'application_place'); ?></dd>
            </dl>
        </div>

        <div class="req-card mb-3">
            <h5>16. Documents attached</h5>
            <div class="row g-2">
                <?php foreach (recruitmentOfficialDocuments() as $doc): ?>
                    <?php $path = trim((string) ($app[$doc['column']] ?? '')); ?>
                    <div class="col-md-6">
                        <?php if ($path !== ''): ?>
                            <a target="_blank" href="<?php echo htmlspecialchars(recruitmentFileUrl($path)); ?>"><?php echo ($doc['item'] !== '' ? htmlspecialchars($doc['item']) . ') ' : '') . htmlspecialchars($doc['label']); ?></a>
                        <?php else: ?>
                            <span class="text-muted"><?php echo ($doc['item'] !== '' ? htmlspecialchars($doc['item']) . ') ' : '') . htmlspecialchars($doc['label']); ?> — not uploaded</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($canEdit): ?>
        <form class="req-card" method="post" id="statusForm">
            <h5>Recruitment process</h5>
            <p class="text-muted small">Change the status to move this candidate through the process. Shortlist, select, and reject send an email to the candidate with the filled application form attached as a PDF. For rejection you must enter the basis — that text is included in the email.</p>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" id="appStatus">
                        <?php foreach (recruitmentApplicationStatuses() as $val => $label): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $app['status'] === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label" id="remarksLabel">Remarks / basis of rejection</label>
                    <textarea class="form-control" name="admin_remarks" id="appRemarks" rows="3" placeholder="For rejection, write the reason (eligibility, documents, experience, etc.)."><?php echo htmlspecialchars((string) ($app['admin_remarks'] ?? '')); ?></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="notify_email" id="notifyEmail" value="1" checked>
                        <label class="form-check-label" for="notifyEmail">Send email to the candidate with the filled application form (PDF). Thank-you is sent on apply; this sends shortlisted / selected / rejected.</label>
                    </div>
                </div>
            </div>
            <button class="btn btn-primary mt-3" type="submit">Save status</button>
        </form>
        <script>
        (function () {
            var status = document.getElementById('appStatus');
            var remarks = document.getElementById('appRemarks');
            var form = document.getElementById('statusForm');
            if (!form || !status || !remarks) return;
            form.addEventListener('submit', function (ev) {
                if (status.value === 'rejected' && remarks.value.trim() === '') {
                    ev.preventDefault();
                    remarks.focus();
                    alert('Please enter the basis of rejection. This is emailed to the candidate.');
                }
            });
        })();
        </script>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
