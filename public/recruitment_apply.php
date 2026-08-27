<?php
require_once __DIR__ . '/../includes/maintenance_check.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/navigation_helper.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/public_theme_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

ensureRecruitmentTables($conn);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];

$jobId = (int) ($_GET['job'] ?? ($_POST['job_id'] ?? 0));
$job = recruitmentGetJob($conn, $jobId);
$error = '';
$successNo = '';

if (!$job || !recruitmentJobIsAccepting($job)) {
    $job = $job ?: null;
    $accepting = false;
} else {
    $accepting = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accepting) {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif (empty($_POST['declaration'])) {
        $error = 'Please confirm the declaration before submitting.';
    } else {
        $resumePath = '';
        $photoPath = '';
        if (!empty($_FILES['resume']['name'])) {
            $up = recruitmentStoreUpload($_FILES['resume'], 'applications', ['pdf'], MAX_FILE_SIZE);
            if (!$up['ok']) {
                $error = $up['message'] ?: 'Please upload a PDF resume.';
            } else {
                $resumePath = $up['path'];
            }
        } else {
            $error = 'Please upload your resume (PDF).';
        }
        if ($error === '' && !empty($_FILES['photo']['name'])) {
            $up = recruitmentStoreUpload($_FILES['photo'], 'applications', ['jpg', 'jpeg', 'png'], MAX_FILE_SIZE);
            if (!$up['ok']) {
                $error = $up['message'] ?: 'Photo must be JPG or PNG.';
            } else {
                $photoPath = $up['path'];
            }
        }
        if ($error === '') {
            $result = recruitmentSubmitApplication($conn, $jobId, [
                'name' => $_POST['name'] ?? '',
                'father_name' => $_POST['father_name'] ?? '',
                'mother_name' => $_POST['mother_name'] ?? '',
                'dob' => $_POST['dob'] ?? '',
                'gender' => $_POST['gender'] ?? '',
                'category' => $_POST['category'] ?? '',
                'aadhar' => $_POST['aadhar'] ?? '',
                'email' => $_POST['email'] ?? '',
                'mobile' => $_POST['mobile'] ?? '',
                'address' => $_POST['address'] ?? '',
                'city' => $_POST['city'] ?? '',
                'state' => $_POST['state'] ?? '',
                'pincode' => $_POST['pincode'] ?? '',
                'qualification' => $_POST['qualification'] ?? '',
                'experience_years' => $_POST['experience_years'] ?? '',
                'experience_details' => $_POST['experience_details'] ?? '',
                'photo_path' => $photoPath,
                'resume_path' => $resumePath,
            ]);
            if (!empty($result['success'])) {
                $successNo = (string) ($result['application_no'] ?? '');
            } else {
                $error = $result['message'] ?? 'Could not submit the application.';
            }
        }
    }
}

$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);
$page_title = ($job ? $job['title'] . ' — ' : '') . 'Apply - NIELIT Bhubaneswar';
injectThemeCSS($active_theme);
emitPublicThemeHead($conn);
$old = static function (string $key) {
    return htmlspecialchars((string) ($_POST[$key] ?? ''), ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/public-theme.css">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
<?php require_once __DIR__ . '/../includes/public_skeleton_helper.php'; public_skeleton_render_head(); ?>
</head>
<body class="public-page-loading public-site">
<?php public_skeleton_render_loader('generic'); ?>
    <div class="top-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8 d-flex align-items-center justify-content-md-start justify-content-center text-header-group">
                    <img src="<?php echo APP_URL . '/' . $theme_logo; ?>" alt="NIELIT Logo" class="me-3" style="height: 50px;">
                    <div>
                        <div class="fw-bold text-primary d-none d-sm-block top-bar-title-hi"><?php echo htmlspecialchars(INSTITUTE_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="fw-bold text-dark top-bar-title-en"><?php echo htmlspecialchars(INSTITUTE_NAME_EN); ?></div>
                    </div>
                </div>
                <div class="col-md-4 d-flex justify-content-md-end justify-content-center gov-logos">
                    <div class="text-end me-3 d-none d-lg-block">
                        <small class="d-block text-secondary d-none d-md-block top-bar-ministry-hi"><?php echo htmlspecialchars(MINISTRY_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></small>
                        <small class="d-block fw-bold top-bar-ministry-en"><?php echo htmlspecialchars(MINISTRY_NAME_EN, ENT_QUOTES, 'UTF-8'); ?></small>
                    </div>
                    <img src="<?php echo APP_URL; ?>/assets/images/National-Emblem.png" alt="Gov India" class="national-emblem" style="height: 50px;">
                </div>
            </div>
        </div>
    </div>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo app_url('index'); ?>"><i class="fas fa-university me-2"></i> NIELIT</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php echo getPublicSiteNavigationHtml($conn, 'recruitment.php'); ?>
                </ul>
            </div>
        </div>
    </nav>
    <section class="page-header">
        <div class="container text-center">
            <h1 class="mb-0"><?php echo $job ? htmlspecialchars((string) $job['title']) : 'Apply'; ?></h1>
            <p class="lead mb-0 mt-2">Recruitment application form</p>
        </div>
    </section>
    <section class="py-5">
        <div class="container">
            <a class="btn btn-outline-primary btn-sm mb-4" href="<?php echo htmlspecialchars(app_url('public/recruitment')); ?>">&larr; All openings</a>

            <?php if ($successNo !== ''): ?>
                <div class="alert alert-success">
                    <h5 class="alert-heading">Application submitted</h5>
                    <p class="mb-0">Your application number is <strong><?php echo htmlspecialchars($successNo); ?></strong>. Please save it for future reference.</p>
                </div>
            <?php elseif (!$job): ?>
                <div class="alert alert-warning">This job opening was not found. Please choose an open job from the recruitment page.</div>
            <?php else: ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge bg-success">Open</span>
                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars((string) $job['post_type']); ?></span>
                            <?php if (!empty($job['advt_no'])): ?><span class="badge bg-light text-dark"><?php echo htmlspecialchars((string) $job['advt_no']); ?></span><?php endif; ?>
                        </div>
                        <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars(recruitmentDisplay($job['location'] ?? '')); ?></p>
                        <p class="mb-1"><strong>Vacancies:</strong> <?php echo (int) $job['vacancies']; ?></p>
                        <p class="mb-1"><strong>Last date:</strong> <?php echo htmlspecialchars(recruitmentFormatDate($job['last_date'] ?? '')); ?></p>
                        <?php if (!empty($job['pay_scale'])): ?><p class="mb-1"><strong>Pay / stipend:</strong> <?php echo htmlspecialchars((string) $job['pay_scale']); ?></p><?php endif; ?>
                        <?php if (!empty($job['eligibility'])): ?>
                            <h6 class="mt-3">Eligibility</h6>
                            <p style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) $job['eligibility']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($job['experience'])): ?>
                            <h6>Experience</h6>
                            <p style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) $job['experience']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($job['description'])): ?>
                            <h6>Job description</h6>
                            <p style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) $job['description']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($job['instructions'])): ?>
                            <div class="alert alert-warning mb-0">
                                <strong>What you should do</strong>
                                <div class="mt-1" style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) $job['instructions']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($job['attachment_path'])): ?>
                            <a class="btn btn-outline-secondary btn-sm mt-3" target="_blank" href="<?php echo htmlspecialchars(recruitmentFileUrl((string) $job['attachment_path'])); ?>">Download advertisement</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$accepting): ?>
                    <div class="alert alert-danger">This job is not accepting applications.</div>
                <?php else: ?>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <form class="card shadow-sm" method="post" enctype="multipart/form-data">
                        <div class="card-header fw-semibold">Application form</div>
                        <div class="card-body row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                            <input type="hidden" name="job_id" value="<?php echo (int) $jobId; ?>">
                            <div class="col-md-6">
                                <label class="form-label">Full name *</label>
                                <input class="form-control" name="name" required value="<?php echo $old('name'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Father's name *</label>
                                <input class="form-control" name="father_name" required value="<?php echo $old('father_name'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mother's name</label>
                                <input class="form-control" name="mother_name" value="<?php echo $old('mother_name'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date of birth *</label>
                                <input class="form-control" type="date" name="dob" required value="<?php echo $old('dob'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Gender *</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select</option>
                                    <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                                        <option value="<?php echo $g; ?>" <?php echo (($old('gender')) === $g) ? 'selected' : ''; ?>><?php echo $g; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Category *</label>
                                <select class="form-select" name="category" required>
                                    <option value="">Select</option>
                                    <?php foreach (['General', 'OBC', 'SC', 'ST', 'EWS'] as $c): ?>
                                        <option value="<?php echo $c; ?>" <?php echo (($old('category')) === $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Aadhaar (12 digits)</label>
                                <input class="form-control" name="aadhar" maxlength="12" value="<?php echo $old('aadhar'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mobile *</label>
                                <input class="form-control" name="mobile" required maxlength="10" value="<?php echo $old('mobile'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input class="form-control" type="email" name="email" required value="<?php echo $old('email'); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <input class="form-control" name="address" value="<?php echo $old('address'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City</label>
                                <input class="form-control" name="city" value="<?php echo $old('city'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">State</label>
                                <input class="form-control" name="state" value="<?php echo $old('state'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Pincode</label>
                                <input class="form-control" name="pincode" value="<?php echo $old('pincode'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Highest qualification *</label>
                                <input class="form-control" name="qualification" required value="<?php echo $old('qualification'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Experience (years)</label>
                                <input class="form-control" name="experience_years" value="<?php echo $old('experience_years'); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Experience details</label>
                                <textarea class="form-control" name="experience_details" rows="3"><?php echo $old('experience_details'); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Resume (PDF) *</label>
                                <input class="form-control" type="file" name="resume" accept=".pdf,application/pdf" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Passport photo (JPG/PNG)</label>
                                <input class="form-control" type="file" name="photo" accept=".jpg,.jpeg,.png">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="declaration" id="declaration" value="1" required>
                                    <label class="form-check-label" for="declaration">I declare that the information given above is true and complete.</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-primary" type="submit">Submit application</button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
    <footer class="footer-section pt-5">
        <div class="copyright-bar text-center text-muted small">
            <div class="container py-3">© <?php echo date('Y'); ?> NIELIT Bhubaneswar. All Rights Reserved.</div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php public_skeleton_render_script(); ?>
</body>
</html>
