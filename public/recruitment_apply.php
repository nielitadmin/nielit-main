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
$emailSent = false;

if (!$job || !recruitmentJobIsAccepting($job)) {
    $job = $job ?: null;
    $accepting = false;
} else {
    $accepting = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accepting) {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif (empty($_POST['declaration']) || empty($_POST['declaration_terms']) || empty($_POST['declaration_one']) || empty($_POST['declaration_docs'])) {
        $error = 'Please confirm all undertaking points before submitting.';
    } else {
        $paths = [];
        foreach (recruitmentOfficialDocuments() as $doc) {
            $file = $_FILES[$doc['key']] ?? [];
            if (!empty($file['name'])) {
                $up = recruitmentStoreUpload($file, 'applications', $doc['ext'], MAX_FILE_SIZE);
                if (!$up['ok']) {
                    $error = $doc['label'] . ': ' . ($up['message'] ?: 'Upload failed.');
                    break;
                }
                $paths[$doc['column']] = $up['path'];
            } elseif (!empty($doc['required'])) {
                $error = 'Please upload: ' . $doc['label'] . '.';
                break;
            }
        }
        $categorySel = trim((string) ($_POST['category'] ?? ''));
        if ($error === '' && $categorySel !== '' && $categorySel !== 'General' && empty($paths['category_cert_path'])) {
            $error = 'Please upload caste / category certificate.';
        }
        if ($error === '' && (($_POST['pwd_status'] ?? '') === 'Yes') && empty($paths['pwd_cert_path'])) {
            $error = 'Please upload PwD certificate.';
        }
        if ($error === '') {
            $first = strtoupper(trim((string) ($_POST['name_first'] ?? '')));
            $middle = strtoupper(trim((string) ($_POST['name_middle'] ?? '')));
            $last = strtoupper(trim((string) ($_POST['name_last'] ?? '')));
            $fullName = trim($first . ' ' . $middle . ' ' . $last);
            $result = recruitmentSubmitApplication($conn, $jobId, array_merge($paths, [
                'name' => $fullName,
                'name_first' => $first,
                'name_middle' => $middle,
                'name_last' => $last,
                'father_name' => $_POST['father_name'] ?? '',
                'mother_name' => $_POST['mother_name'] ?? '',
                'dob' => $_POST['dob'] ?? '',
                'gender' => $_POST['gender'] ?? '',
                'marital_status' => $_POST['marital_status'] ?? '',
                'nationality' => $_POST['nationality'] ?? 'Indian',
                'pwd_status' => $_POST['pwd_status'] ?? 'No',
                'category' => $_POST['category'] ?? '',
                'aadhar' => $_POST['aadhar'] ?? '',
                'email' => $_POST['email'] ?? '',
                'mobile' => $_POST['mobile'] ?? '',
                'alt_mobile' => $_POST['alt_mobile'] ?? '',
                'address' => $_POST['address'] ?? '',
                'city' => $_POST['city'] ?? '',
                'state' => $_POST['state'] ?? '',
                'pincode' => $_POST['pincode'] ?? '',
                'permanent_address' => !empty($_POST['same_permanent']) ? ($_POST['address'] ?? '') : ($_POST['permanent_address'] ?? ''),
                'permanent_pincode' => !empty($_POST['same_permanent']) ? ($_POST['pincode'] ?? '') : ($_POST['permanent_pincode'] ?? ''),
                'qualification' => $_POST['qualification'] ?? '',
                'experience_years' => $_POST['experience_years'] ?? '',
                'experience_details' => $_POST['experience_details'] ?? '',
                'pwd_type' => $_POST['pwd_type'] ?? '',
                'pwd_percent' => $_POST['pwd_percent'] ?? '',
                'computer_knowledge' => $_POST['computer_knowledge'] ?? '',
                'additional_info' => $_POST['additional_info'] ?? '',
                'application_place' => $_POST['application_place'] ?? '',
                '_post' => $_POST,
            ]));
            if (!empty($result['success'])) {
                $successNo = (string) ($result['application_no'] ?? '');
                $emailSent = !empty($result['email_sent']);
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
$oldArr = static function (string $key, int $i) {
    $arr = $_POST[$key] ?? [];
    return htmlspecialchars((string) ($arr[$i] ?? ''), ENT_QUOTES, 'UTF-8');
};
$eduDefaults = ['Class X / equivalent', 'Class XII / equivalent', 'Graduation', 'Post Graduation / other'];
$eduCount = max(4, is_array($_POST['edu_exam'] ?? null) ? count($_POST['edu_exam']) : 4);
$expCount = max(3, is_array($_POST['exp_org'] ?? null) ? count($_POST['exp_org']) : 3);
$asOnDate = ($job && !empty($job['last_date'])) ? (string) $job['last_date'] : date('Y-m-d');
$samePermanent = !empty($_POST['same_permanent']) || $_SERVER['REQUEST_METHOD'] !== 'POST';
$startStep = 1;
if ($error !== '') {
    $e = strtolower($error);
    if (str_contains($e, 'undertaking') || str_contains($e, 'confirm') || str_contains($e, 'signature')) {
        $startStep = 4;
    } elseif (str_contains($e, 'upload') || str_contains($e, 'document') || str_contains($e, 'resume') || str_contains($e, 'certificate') || str_contains($e, 'marksheet')) {
        $startStep = 3;
    } elseif (str_contains($e, 'photograph')) {
        $startStep = 1;
    } elseif (str_contains($e, 'examination') || str_contains($e, 'experience')) {
        $startStep = 2;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/public-theme.css">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
    <style>
        .rec-wrap { max-width: 1080px; }
        .rec-job {
            background:#fff; border:1px solid #e2e8f0; border-radius:18px;
            padding:1.25rem 1.4rem; box-shadow:0 10px 30px rgba(15,23,42,.06);
        }
        .rec-job h2 { font-family:Poppins,sans-serif; font-size:1.35rem; margin:0 0 .35rem; }
        .rec-chips { display:flex; flex-wrap:wrap; gap:.55rem .9rem; color:#475569; font-size:.92rem; }
        .rec-chips i { color:var(--blue,#1a56db); width:1.1rem; }
        .rec-wizard {
            background:#fff; border:1px solid #e2e8f0; border-radius:20px; overflow:hidden;
            box-shadow:0 16px 40px rgba(15,23,42,.08);
        }
        .rec-stepper { padding:1.2rem 1.4rem .4rem; background:linear-gradient(180deg,#f8fafc 0%,#fff 100%); }
        .rec-stepper-track { display:flex; gap:.4rem; margin-bottom:.85rem; }
        .rec-stepper-track span { flex:1; height:6px; border-radius:99px; background:#e2e8f0; }
        .rec-stepper-track span.done, .rec-stepper-track span.now { background:var(--blue,#1a56db); }
        .rec-stepper-track span.now { opacity:.55; }
        .rec-steps { display:grid; grid-template-columns:repeat(4,1fr); gap:.5rem; }
        .rec-step {
            border:0; background:transparent; text-align:left; padding:.35rem .2rem .7rem;
            color:#64748b; font-size:.82rem; font-weight:600;
        }
        .rec-step .num {
            width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center;
            background:#e2e8f0; color:#334155; font-size:.8rem; margin-right:.4rem;
        }
        .rec-step.is-active { color:var(--navy,#0a1628); }
        .rec-step.is-active .num { background:var(--blue,#1a56db); color:#fff; }
        .rec-step.is-done .num { background:#16a34a; color:#fff; }
        .rec-panel { display:none; padding:1.4rem 1.5rem 1.1rem; }
        .rec-panel.is-active { display:block; }
        .rec-panel h3 { font-family:Poppins,sans-serif; font-size:1.15rem; margin:0 0 .25rem; }
        .rec-help { color:#64748b; font-size:.9rem; margin-bottom:1.15rem; }
        .rec-wizard .form-label { font-weight:600; font-size:.86rem; color:#334155; margin-bottom:.35rem; }
        .rec-wizard .form-control, .rec-wizard .form-select {
            border-radius:12px; border-color:#dbe3ee; padding:.62rem .85rem; min-height:44px;
        }
        .rec-wizard .form-control:focus, .rec-wizard .form-select:focus {
            border-color:var(--blue,#1a56db); box-shadow:0 0 0 .2rem rgba(26,86,219,.12);
        }
        .rec-photo {
            width:100%; max-width:180px; margin-left:auto; border:2px dashed #94a3b8; border-radius:16px;
            padding:.85rem; background:#f8fafc; text-align:center;
        }
        .rec-photo img { width:100%; height:150px; object-fit:cover; border-radius:10px; display:none; margin-bottom:.5rem; }
        .rec-photo .hint { font-size:.78rem; color:#64748b; }
        .rec-table { border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; }
        .rec-table table { margin:0; }
        .rec-table th { font-size:.78rem; font-weight:600; color:#475569; white-space:nowrap; }
        .rec-file {
            display:flex; flex-direction:column; gap:.35rem; height:100%;
            border:1.5px dashed #cbd5e1; border-radius:14px; padding:1rem; background:#f8fafc; cursor:pointer;
        }
        .rec-file:hover { border-color:var(--blue,#1a56db); background:#eff6ff; }
        .rec-file.has-file { border-style:solid; border-color:#16a34a; background:#f0fdf4; }
        .rec-file input { font-size:.8rem; }
        .rec-file .ttl { font-weight:600; font-size:.86rem; color:#0f172a; }
        .rec-file .meta { font-size:.75rem; color:#64748b; }
        .rec-nav {
            display:flex; justify-content:space-between; gap:.75rem; align-items:center;
            padding:1rem 1.5rem 1.25rem; background:#f8fafc; border-top:1px solid #e8eef5;
            position:sticky; bottom:0;
        }
        .rec-success {
            background:#fff; border-radius:20px; padding:2.4rem 1.5rem; text-align:center;
            box-shadow:0 16px 40px rgba(15,23,42,.08); border:1px solid #e2e8f0;
        }
        .rec-success .ok {
            width:64px; height:64px; border-radius:50%; background:#dcfce7; color:#16a34a;
            display:inline-flex; align-items:center; justify-content:center; font-size:1.6rem; margin-bottom:1rem;
        }
        .rec-check { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:.85rem 1rem; margin-bottom:.65rem; }
        @media (max-width:767px) {
            .rec-steps { grid-template-columns:repeat(4,1fr); }
            .rec-step { font-size:0; padding:.2rem; text-align:center; }
            .rec-step .num { margin:0; }
            .rec-photo { margin:0 auto 1rem; }
        }
        html[data-mode="night"] body.public-site .rec-job,
        html[data-mode="night"] body.public-site .rec-wizard,
        html[data-mode="night"] body.public-site .rec-success { background:var(--navy-mid,#112240); border-color:#1e3a5f; }
        html[data-mode="night"] body.public-site .rec-stepper,
        html[data-mode="night"] body.public-site .rec-nav { background:#0b1a33; }
    </style>
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
            <p class="lead mb-0 mt-2">Complete the application in 4 short steps</p>
        </div>
    </section>
    <section class="pb-5">
        <div class="container rec-wrap">
            <a class="btn btn-outline-primary btn-sm mb-4" href="<?php echo htmlspecialchars(app_url('public/recruitment')); ?>">&larr; All openings</a>

            <?php if ($successNo !== ''): ?>
                <div class="rec-success">
                    <div class="ok"><i class="fas fa-check"></i></div>
                    <h3>Application submitted</h3>
                    <p class="text-muted mb-2">Please save your application number for future reference.</p>
                    <div class="fs-4 fw-semibold mb-3"><?php echo htmlspecialchars($successNo); ?></div>
                    <p class="text-muted"><?php echo $emailSent
                        ? 'A thank-you email has been sent to the address you entered.'
                        : 'Please save this number. If you do not receive a confirmation email, check spam or contact the centre.'; ?></p>
                    <a class="btn btn-primary" href="<?php echo htmlspecialchars(app_url('public/recruitment')); ?>">Back to openings</a>
                </div>
            <?php elseif (!$job): ?>
                <div class="alert alert-warning">This job opening was not found. Please choose an open job from the recruitment page.</div>
            <?php else: ?>
                <div class="rec-job mb-4">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge bg-success">Open</span>
                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars((string) $job['post_type']); ?></span>
                        <?php if (!empty($job['advt_no'])): ?><span class="badge bg-light text-dark"><?php echo htmlspecialchars((string) $job['advt_no']); ?></span><?php endif; ?>
                    </div>
                    <h2><?php echo htmlspecialchars((string) $job['title']); ?></h2>
                    <div class="rec-chips mb-2">
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(recruitmentDisplay($job['location'] ?? '')); ?></span>
                        <span><i class="fas fa-users"></i> <?php echo (int) $job['vacancies']; ?> vacancy(ies)</span>
                        <span><i class="fas fa-calendar"></i> Last date: <?php echo htmlspecialchars(recruitmentFormatDate($job['last_date'] ?? '')); ?></span>
                        <?php if (!empty($job['pay_scale'])): ?><span><i class="fas fa-indian-rupee-sign"></i> <?php echo htmlspecialchars((string) $job['pay_scale']); ?></span><?php endif; ?>
                    </div>
                    <button class="btn btn-link btn-sm px-0" type="button" data-bs-toggle="collapse" data-bs-target="#jobMore">View eligibility, instructions &amp; advertisement</button>
                    <div class="collapse mt-2" id="jobMore">
                        <?php if (!empty($job['eligibility'])): ?>
                            <h6 class="mt-2">Eligibility</h6>
                            <p class="mb-2" style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) $job['eligibility']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($job['experience'])): ?>
                            <h6>Experience</h6>
                            <p class="mb-2" style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) $job['experience']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($job['description'])): ?>
                            <h6>Job description</h6>
                            <p class="mb-2" style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) $job['description']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($job['instructions'])): ?>
                            <div class="alert alert-warning mb-2">
                                <strong>What you should do</strong>
                                <div class="mt-1" style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) $job['instructions']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($job['attachment_path'])): ?>
                            <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?php echo htmlspecialchars(recruitmentFileUrl((string) $job['attachment_path'])); ?>">Download advertisement</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$accepting): ?>
                    <div class="alert alert-danger">This job is not accepting applications.</div>
                <?php else: ?>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <form class="rec-wizard" id="applyForm" method="post" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="job_id" value="<?php echo (int) $jobId; ?>">
                        <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                        <div class="rec-stepper">
                            <div class="rec-stepper-track" id="stepTrack">
                                <span class="now"></span><span></span><span></span><span></span>
                            </div>
                            <div class="rec-steps" id="stepTabs">
                                <button type="button" class="rec-step is-active" data-step-btn="1"><span class="num">1</span><span class="d-none d-md-inline">Personal</span></button>
                                <button type="button" class="rec-step" data-step-btn="2"><span class="num">2</span><span class="d-none d-md-inline">Education</span></button>
                                <button type="button" class="rec-step" data-step-btn="3"><span class="num">3</span><span class="d-none d-md-inline">Documents</span></button>
                                <button type="button" class="rec-step" data-step-btn="4"><span class="num">4</span><span class="d-none d-md-inline">Submit</span></button>
                            </div>
                        </div>

                        <div class="rec-panel is-active" data-panel="1">
                            <h3>Personal &amp; contact details</h3>
                            <p class="rec-help">Fill in CAPITAL letters. Name and Date of Birth must match Class X / Aadhaar.</p>
                            <div class="row g-3">
                                <div class="col-lg-9">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label class="form-label">Post applied for</label>
                                            <input class="form-control" value="<?php echo htmlspecialchars((string) $job['title']); ?>" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Advertisement no.</label>
                                            <input class="form-control" value="<?php echo htmlspecialchars((string) ($job['advt_no'] ?? '')); ?>" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">First name *</label>
                                            <input class="form-control text-uppercase" name="name_first" required value="<?php echo $old('name_first'); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Middle name</label>
                                            <input class="form-control text-uppercase" name="name_middle" value="<?php echo $old('name_middle'); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Last name *</label>
                                            <input class="form-control text-uppercase" name="name_last" required value="<?php echo $old('name_last'); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Father's / Husband's name *</label>
                                            <input class="form-control text-uppercase" name="father_name" required value="<?php echo $old('father_name'); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Mother's name</label>
                                            <input class="form-control text-uppercase" name="mother_name" value="<?php echo $old('mother_name'); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label d-block">Photograph *</label>
                                    <div class="rec-photo">
                                        <img id="photoPreview" alt="Photograph preview">
                                        <div class="hint fw-semibold mb-1">Passport size photo</div>
                                        <input class="form-control form-control-sm" type="file" name="photo" id="photoFile" accept=".jpg,.jpeg,.png" required>
                                        <div class="hint mt-1">JPG / PNG, max 5 MB</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Date of Birth *</label>
                                    <input class="form-control" type="date" name="dob" id="dobField" required value="<?php echo $old('dob'); ?>">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Age as on last date (<?php echo htmlspecialchars(recruitmentFormatDate($asOnDate)); ?>)</label>
                                    <div class="row g-2">
                                        <div class="col-4"><input class="form-control" id="ageYears" readonly placeholder="Years"><div class="form-text">Years</div></div>
                                        <div class="col-4"><input class="form-control" id="ageMonths" readonly placeholder="Months"><div class="form-text">Months</div></div>
                                        <div class="col-4"><input class="form-control" id="ageDays" readonly placeholder="Days"><div class="form-text">Days</div></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Gender *</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="">Select</option>
                                        <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                                            <option value="<?php echo $g; ?>" <?php echo $old('gender') === $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Marital status *</label>
                                    <select class="form-select" name="marital_status" required>
                                        <option value="">Select</option>
                                        <?php foreach (['Unmarried', 'Married', 'Divorcee', 'Other'] as $m): ?>
                                            <option value="<?php echo $m; ?>" <?php echo $old('marital_status') === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nationality *</label>
                                    <input class="form-control" name="nationality" required value="<?php echo $old('nationality') !== '' ? $old('nationality') : 'Indian'; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Category *</label>
                                    <select class="form-select" name="category" id="categoryField" required>
                                        <option value="">Select</option>
                                        <?php foreach (['General', 'OBC', 'SC', 'ST', 'EWS'] as $c): ?>
                                            <option value="<?php echo $c; ?>" <?php echo $old('category') === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Whether PwD</label>
                                    <select class="form-select" name="pwd_status" id="pwdField">
                                        <option value="No" <?php echo $old('pwd_status') !== 'Yes' ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?php echo $old('pwd_status') === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>
                                <div class="col-md-2" id="pwdTypeWrap">
                                    <label class="form-label">PwD type</label>
                                    <select class="form-select" name="pwd_type">
                                        <option value="">Select</option>
                                        <?php foreach (['VH', 'HH', 'OH', 'Other'] as $pt): ?>
                                            <option value="<?php echo $pt; ?>" <?php echo $old('pwd_type') === $pt ? 'selected' : ''; ?>><?php echo $pt; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2" id="pwdPctWrap">
                                    <label class="form-label">% of disability</label>
                                    <input class="form-control" name="pwd_percent" value="<?php echo $old('pwd_percent'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Aadhaar number *</label>
                                    <input class="form-control" name="aadhar" required maxlength="12" inputmode="numeric" value="<?php echo $old('aadhar'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Mobile *</label>
                                    <input class="form-control" name="mobile" required maxlength="10" inputmode="numeric" value="<?php echo $old('mobile'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Alternate mobile</label>
                                    <input class="form-control" name="alt_mobile" maxlength="10" inputmode="numeric" value="<?php echo $old('alt_mobile'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Email *</label>
                                    <input class="form-control" type="email" name="email" required value="<?php echo $old('email'); ?>">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Address for correspondence *</label>
                                    <input class="form-control" name="address" required value="<?php echo $old('address'); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">City</label>
                                    <input class="form-control" name="city" value="<?php echo $old('city'); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">PIN code *</label>
                                    <input class="form-control" name="pincode" required value="<?php echo $old('pincode'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">State</label>
                                    <input class="form-control" name="state" value="<?php echo $old('state'); ?>">
                                </div>
                                <div class="col-md-8 d-flex align-items-end">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="same_permanent" id="samePermanent" value="1" <?php echo $samePermanent ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="samePermanent">Permanent address same as correspondence</label>
                                    </div>
                                </div>
                                <div class="col-md-8 perm-fields">
                                    <label class="form-label">Permanent address</label>
                                    <input class="form-control" name="permanent_address" value="<?php echo $old('permanent_address'); ?>">
                                </div>
                                <div class="col-md-4 perm-fields">
                                    <label class="form-label">Permanent PIN</label>
                                    <input class="form-control" name="permanent_pincode" value="<?php echo $old('permanent_pincode'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="rec-panel" data-panel="2">
                            <h3>Education &amp; experience</h3>
                            <p class="rec-help">Start from Class X. Add extra rows if you need more qualifications or jobs.</p>
                            <h6 class="mb-2">Examinations passed / degrees</h6>
                            <div class="rec-table table-responsive mb-2">
                                <table class="table table-sm align-middle edu-table mb-0" id="eduTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Examination / Degree</th>
                                            <th>University / Board</th>
                                            <th>Year of passing</th>
                                            <th>% / CGPA</th>
                                            <th>Subjects</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php for ($i = 0; $i < $eduCount; $i++): ?>
                                        <tr>
                                            <td><input class="form-control form-control-sm" name="edu_exam[]" value="<?php echo $oldArr('edu_exam', $i) !== '' ? $oldArr('edu_exam', $i) : htmlspecialchars($eduDefaults[$i] ?? ''); ?>" <?php echo $i < 3 ? 'required' : ''; ?>></td>
                                            <td><input class="form-control form-control-sm" name="edu_board[]" value="<?php echo $oldArr('edu_board', $i); ?>" <?php echo $i < 3 ? 'required' : ''; ?>></td>
                                            <td><input class="form-control form-control-sm" name="edu_year[]" value="<?php echo $oldArr('edu_year', $i); ?>" <?php echo $i < 3 ? 'required' : ''; ?>></td>
                                            <td><input class="form-control form-control-sm" name="edu_percent[]" value="<?php echo $oldArr('edu_percent', $i); ?>"></td>
                                            <td><input class="form-control form-control-sm" name="edu_subjects[]" value="<?php echo $oldArr('edu_subjects', $i); ?>"></td>
                                        </tr>
                                    <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button class="btn btn-outline-primary btn-sm mb-4" type="button" id="addEduRow"><i class="fas fa-plus me-1"></i> Add examination row</button>

                            <h6 class="mb-2">Experience (latest first)</h6>
                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <label class="form-label">Total experience</label>
                                    <input class="form-control" name="experience_years" placeholder="e.g. 2 years 3 months" value="<?php echo $old('experience_years'); ?>">
                                </div>
                            </div>
                            <div class="rec-table table-responsive mb-2">
                                <table class="table table-sm align-middle exp-table mb-0" id="expTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Organisation</th>
                                            <th>Post held</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Duration</th>
                                            <th>Nature of duties / pay</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php for ($i = 0; $i < $expCount; $i++): ?>
                                        <tr>
                                            <td><input class="form-control form-control-sm" name="exp_org[]" value="<?php echo $oldArr('exp_org', $i); ?>"></td>
                                            <td><input class="form-control form-control-sm" name="exp_post[]" value="<?php echo $oldArr('exp_post', $i); ?>"></td>
                                            <td><input class="form-control form-control-sm" type="month" name="exp_from[]" value="<?php echo $oldArr('exp_from', $i); ?>"></td>
                                            <td><input class="form-control form-control-sm" type="month" name="exp_to[]" value="<?php echo $oldArr('exp_to', $i); ?>"></td>
                                            <td><input class="form-control form-control-sm" name="exp_duration[]" value="<?php echo $oldArr('exp_duration', $i); ?>"></td>
                                            <td><input class="form-control form-control-sm" name="exp_nature[]" value="<?php echo $oldArr('exp_nature', $i); ?>"></td>
                                        </tr>
                                    <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button class="btn btn-outline-primary btn-sm mb-4" type="button" id="addExpRow"><i class="fas fa-plus me-1"></i> Add experience row</button>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Computer knowledge / additional qualifications</label>
                                    <textarea class="form-control" name="computer_knowledge" rows="3"><?php echo $old('computer_knowledge'); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Any other information</label>
                                    <textarea class="form-control" name="additional_info" rows="3"><?php echo $old('additional_info'); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="rec-panel" data-panel="3">
                            <h3>Documents</h3>
                            <p class="rec-help">Upload self-attested scanned copies (PDF / JPG / PNG). <strong>Each file must be 5 MB or smaller.</strong> Incomplete applications will be rejected.</p>
                            <div class="row g-3">
                            <?php foreach (recruitmentOfficialDocuments() as $doc): ?>
                                <?php if (in_array($doc['key'], ['photo', 'signature'], true)) { continue; } ?>
                                <div class="col-md-6" data-doc-key="<?php echo htmlspecialchars($doc['key']); ?>">
                                    <label class="rec-file">
                                        <span class="ttl"><?php
                                            echo $doc['item'] !== '' ? htmlspecialchars($doc['item']) . ') ' : '';
                                            echo htmlspecialchars($doc['label']);
                                            echo !empty($doc['required']) ? ' *' : '';
                                        ?></span>
                                        <span class="meta">Tap to choose a file (max 5 MB)</span>
                                        <input class="form-control" type="file" name="<?php echo htmlspecialchars($doc['key']); ?>" accept="<?php echo htmlspecialchars($doc['accept']); ?>" <?php echo !empty($doc['required']) ? 'required' : ''; ?>>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="rec-panel" data-panel="4">
                            <h3>Undertaking &amp; submit</h3>
                            <p class="rec-help">Please read each point and confirm before you submit.</p>
                            <div class="rec-check">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="declaration_docs" id="decDocs" value="1" required>
                                    <label class="form-check-label" for="decDocs">All the documents mentioned above are attached (Yes).</label>
                                </div>
                            </div>
                            <div class="rec-check">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="declaration_terms" id="decTerms" value="1" required>
                                    <label class="form-check-label" for="decTerms">I have gone through the Terms &amp; Conditions / instructions for this post and shall abide by the same.</label>
                                </div>
                            </div>
                            <div class="rec-check">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="declaration" id="declaration" value="1" required>
                                    <label class="form-check-label" for="declaration">All information furnished above is true, complete and correct to the best of my knowledge and belief.</label>
                                </div>
                            </div>
                            <div class="rec-check">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="declaration_one" id="decOne" value="1" required>
                                    <label class="form-check-label" for="decOne">I have submitted only one application for this position. I have never been debarred by any organisation for illegal activity during my education / service. I understand that false / suppressed information will cancel my candidature, and that NIELIT may accept or reject this application without assigning a reason.</label>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                    <label class="form-label">Place *</label>
                                    <input class="form-control" name="application_place" required value="<?php echo $old('application_place'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Date</label>
                                    <input class="form-control" value="<?php echo date('d-m-Y'); ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Signature of the candidate * <span class="text-muted fw-normal">(JPG / PNG, max 5 MB)</span></label>
                                    <input class="form-control" type="file" name="signature" accept=".jpg,.jpeg,.png" required>
                                </div>
                            </div>
                        </div>

                        <div class="rec-nav">
                            <button class="btn btn-outline-secondary" type="button" id="prevStep">Back</button>
                            <div class="text-muted small" id="stepHint">Step 1 of 4</div>
                            <button class="btn btn-primary" type="button" id="nextStep">Continue</button>
                            <button class="btn btn-primary d-none" type="submit" id="submitStep">Submit application</button>
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
    <script>
    (function () {
        var asOn = <?php echo json_encode($asOnDate); ?>;
        var current = <?php echo (int) $startStep; ?>;
        var total = 4;
        var form = document.getElementById('applyForm');

        var dob = document.getElementById('dobField');
        function fillAge() {
            var y = document.getElementById('ageYears');
            var m = document.getElementById('ageMonths');
            var d = document.getElementById('ageDays');
            if (!y || !dob || !dob.value || !asOn) { return; }
            var from = new Date(dob.value + 'T00:00:00');
            var to = new Date(asOn + 'T00:00:00');
            if (isNaN(from.getTime()) || isNaN(to.getTime()) || from > to) {
                y.value = m.value = d.value = '';
                return;
            }
            var years = to.getFullYear() - from.getFullYear();
            var months = to.getMonth() - from.getMonth();
            var days = to.getDate() - from.getDate();
            if (days < 0) {
                months -= 1;
                days += new Date(to.getFullYear(), to.getMonth(), 0).getDate();
            }
            if (months < 0) { years -= 1; months += 12; }
            y.value = years; m.value = months; d.value = days;
        }
        if (dob) { dob.addEventListener('change', fillAge); fillAge(); }

        var photo = document.getElementById('photoFile');
        var preview = document.getElementById('photoPreview');
        var maxBytes = 5 * 1024 * 1024;
        function checkFileSize(inp) {
            var f = inp.files && inp.files[0];
            if (!f) return true;
            if (f.size > maxBytes) {
                alert('Each file must be 5 MB or smaller: ' + f.name);
                inp.value = '';
                var box = inp.closest('.rec-file');
                if (box) {
                    box.classList.remove('has-file');
                    var meta = box.querySelector('.meta');
                    if (meta) meta.textContent = 'Tap to choose a file (max 5 MB)';
                }
                return false;
            }
            return true;
        }
        if (photo && preview) {
            photo.addEventListener('change', function () {
                if (!checkFileSize(photo)) { preview.style.display = 'none'; return; }
                var f = photo.files && photo.files[0];
                if (!f) { preview.style.display = 'none'; return; }
                preview.src = URL.createObjectURL(f);
                preview.style.display = 'block';
            });
        }

        function addRow(tableId) {
            var tbody = document.querySelector('#' + tableId + ' tbody');
            if (!tbody || !tbody.rows.length) { return; }
            var row = tbody.rows[0].cloneNode(true);
            row.querySelectorAll('input').forEach(function (inp) {
                inp.value = '';
                inp.removeAttribute('required');
            });
            tbody.appendChild(row);
        }
        var addEdu = document.getElementById('addEduRow');
        var addExp = document.getElementById('addExpRow');
        if (addEdu) addEdu.addEventListener('click', function () { addRow('eduTable'); });
        if (addExp) addExp.addEventListener('click', function () { addRow('expTable'); });

        function togglePerm() {
            var on = document.getElementById('samePermanent');
            document.querySelectorAll('.perm-fields').forEach(function (el) {
                el.style.display = (on && on.checked) ? 'none' : '';
            });
        }
        var same = document.getElementById('samePermanent');
        if (same) { same.addEventListener('change', togglePerm); togglePerm(); }

        function togglePwd() {
            var yes = document.getElementById('pwdField') && document.getElementById('pwdField').value === 'Yes';
            ['pwdTypeWrap', 'pwdPctWrap'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.style.display = yes ? '' : 'none';
            });
            var pwdInput = document.querySelector('[data-doc-key="pwd_cert"] input');
            if (pwdInput) pwdInput.required = yes;
        }
        var pwd = document.getElementById('pwdField');
        if (pwd) { pwd.addEventListener('change', togglePwd); togglePwd(); }

        function toggleCategory() {
            var cat = document.getElementById('categoryField');
            var need = cat && cat.value && cat.value !== 'General';
            var wrap = document.querySelector('[data-doc-key="category_cert"]');
            var inp = wrap ? wrap.querySelector('input') : null;
            if (wrap) wrap.style.display = need ? '' : 'none';
            if (inp) inp.required = !!need;
        }
        var cat = document.getElementById('categoryField');
        if (cat) { cat.addEventListener('change', toggleCategory); toggleCategory(); }

        document.querySelectorAll('.rec-file input[type="file"]').forEach(function (inp) {
            inp.addEventListener('change', function () {
                if (!checkFileSize(inp)) return;
                var box = inp.closest('.rec-file');
                var meta = box ? box.querySelector('.meta') : null;
                if (inp.files && inp.files[0]) {
                    if (box) box.classList.add('has-file');
                    if (meta) meta.textContent = inp.files[0].name + ' (max 5 MB)';
                } else {
                    if (box) box.classList.remove('has-file');
                    if (meta) meta.textContent = 'Tap to choose a file (max 5 MB)';
                }
            });
        });
        document.querySelectorAll('#applyForm input[type="file"]').forEach(function (inp) {
            if (inp === photo) return;
            inp.addEventListener('change', function () { checkFileSize(inp); });
        });

        if (!form) return;

        function panelFields(n) {
            var panel = document.querySelector('[data-panel="' + n + '"]');
            if (!panel) return [];
            return Array.prototype.slice.call(panel.querySelectorAll('input, select, textarea')).filter(function (el) {
                if (el.disabled) return false;
                var wrap = el.closest('.perm-fields, #pwdTypeWrap, #pwdPctWrap, [data-doc-key]');
                if (wrap && wrap.style.display === 'none') return false;
                return true;
            });
        }
        function validateStep(n) {
            var fields = panelFields(n);
            for (var i = 0; i < fields.length; i++) {
                if (fields[i].type === 'file' && !checkFileSize(fields[i])) {
                    return false;
                }
                if (!fields[i].checkValidity()) {
                    fields[i].reportValidity();
                    return false;
                }
            }
            return true;
        }
        function showStep(n, scroll) {
            current = n;
            document.querySelectorAll('[data-panel]').forEach(function (p) {
                p.classList.toggle('is-active', parseInt(p.getAttribute('data-panel'), 10) === n);
            });
            document.querySelectorAll('[data-step-btn]').forEach(function (b) {
                var s = parseInt(b.getAttribute('data-step-btn'), 10);
                b.classList.toggle('is-active', s === n);
                b.classList.toggle('is-done', s < n);
            });
            document.querySelectorAll('#stepTrack span').forEach(function (bar, idx) {
                bar.className = '';
                if (idx + 1 < n) bar.className = 'done';
                else if (idx + 1 === n) bar.className = 'now';
            });
            document.getElementById('stepHint').textContent = 'Step ' + n + ' of ' + total;
            document.getElementById('prevStep').disabled = n === 1;
            document.getElementById('nextStep').classList.toggle('d-none', n === total);
            document.getElementById('submitStep').classList.toggle('d-none', n !== total);
            if (scroll && form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        document.getElementById('nextStep').addEventListener('click', function () {
            if (!validateStep(current)) return;
            if (current < total) showStep(current + 1, true);
        });
        document.getElementById('prevStep').addEventListener('click', function () {
            if (current > 1) showStep(current - 1, true);
        });
        document.querySelectorAll('[data-step-btn]').forEach(function (b) {
            b.addEventListener('click', function () {
                var s = parseInt(b.getAttribute('data-step-btn'), 10);
                if (s <= current) { showStep(s, true); return; }
                for (var i = current; i < s; i++) {
                    if (!validateStep(i)) return;
                }
                showStep(s, true);
            });
        });
        form.addEventListener('submit', function (ev) {
            for (var i = 1; i <= total; i++) {
                if (!validateStep(i)) {
                    ev.preventDefault();
                    showStep(i, true);
                    return;
                }
            }
        });
        showStep(current, false);
    })();
    </script>
<?php public_skeleton_render_script(); ?>
</body>
</html>
