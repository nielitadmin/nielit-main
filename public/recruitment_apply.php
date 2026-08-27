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
    <style>
        .form-of-app .card-header { letter-spacing:.04em; }
        .rec-photo-box { width:148px; min-height:188px; border:2px dashed #94a3b8; border-radius:8px; padding:.5rem; background:#f8fafc; }
        .rec-photo-box img { width:100%; height:140px; object-fit:cover; border-radius:4px; display:none; }
        .rec-photo-box .hint { font-size:.75rem; color:#64748b; text-align:center; }
        .edu-table input, .exp-table input { min-width:110px; }
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
                    <form class="card shadow-sm form-of-app" method="post" enctype="multipart/form-data">
                        <div class="card-header fw-semibold">FORM OF APPLICATION</div>
                        <div class="card-body row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                            <input type="hidden" name="job_id" value="<?php echo (int) $jobId; ?>">
                            <p class="text-muted small mb-0">Fill in CAPITAL letters. Name and Date of Birth must match Class X / Aadhaar. Incomplete applications will be rejected. Application without supporting documents will be treated incomplete and rejected.</p>

                            <div class="col-lg-9">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">1. Post applied for</label>
                                        <input class="form-control" value="<?php echo htmlspecialchars((string) $job['title']); ?>" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Advertisement no.</label>
                                        <input class="form-control" value="<?php echo htmlspecialchars((string) ($job['advt_no'] ?? '')); ?>" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">2. Name in full — First *</label>
                                        <input class="form-control text-uppercase" name="name_first" required value="<?php echo $old('name_first'); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Middle</label>
                                        <input class="form-control text-uppercase" name="name_middle" value="<?php echo $old('name_middle'); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Last *</label>
                                        <input class="form-control text-uppercase" name="name_last" required value="<?php echo $old('name_last'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">3. Father's / Husband's name *</label>
                                        <input class="form-control text-uppercase" name="father_name" required value="<?php echo $old('father_name'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Mother's name</label>
                                        <input class="form-control text-uppercase" name="mother_name" value="<?php echo $old('mother_name'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 d-flex justify-content-lg-end">
                                <div class="rec-photo-box">
                                    <div class="hint fw-semibold mb-1">Affix a recent passport size photograph</div>
                                    <img id="photoPreview" alt="Photograph preview">
                                    <input class="form-control form-control-sm mt-1" type="file" name="photo" id="photoFile" accept=".jpg,.jpeg,.png" required>
                                    <div class="hint mt-1">JPG / PNG</div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">4. (a) Date of Birth *</label>
                                <input class="form-control" type="date" name="dob" id="dobField" required value="<?php echo $old('dob'); ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">4. (b) Age as on last date of application (<?php echo htmlspecialchars(recruitmentFormatDate($asOnDate)); ?>)</label>
                                <div class="row g-2">
                                    <div class="col-4"><input class="form-control" id="ageYears" readonly placeholder="Years"><div class="form-text">Years</div></div>
                                    <div class="col-4"><input class="form-control" id="ageMonths" readonly placeholder="Months"><div class="form-text">Months</div></div>
                                    <div class="col-4"><input class="form-control" id="ageDays" readonly placeholder="Days"><div class="form-text">Days</div></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">5. Gender *</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select</option>
                                    <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                                        <option value="<?php echo $g; ?>" <?php echo $old('gender') === $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">6. Marital status *</label>
                                <select class="form-select" name="marital_status" required>
                                    <option value="">Select</option>
                                    <?php foreach (['Unmarried', 'Married', 'Divorcee', 'Other'] as $m): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $old('marital_status') === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">7. Nationality *</label>
                                <input class="form-control" name="nationality" required value="<?php echo $old('nationality') !== '' ? $old('nationality') : 'Indian'; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">8. Category *</label>
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
                                <label class="form-label">9. Aadhaar number *</label>
                                <input class="form-control" name="aadhar" required maxlength="12" inputmode="numeric" value="<?php echo $old('aadhar'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">10. (a) Mobile *</label>
                                <input class="form-control" name="mobile" required maxlength="10" inputmode="numeric" value="<?php echo $old('mobile'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Alternate mobile</label>
                                <input class="form-control" name="alt_mobile" maxlength="10" inputmode="numeric" value="<?php echo $old('alt_mobile'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">10. (b) Email *</label>
                                <input class="form-control" type="email" name="email" required value="<?php echo $old('email'); ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">11. Address for correspondence *</label>
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
                                <label class="form-label">12. Permanent address</label>
                                <input class="form-control" name="permanent_address" value="<?php echo $old('permanent_address'); ?>">
                            </div>
                            <div class="col-md-4 perm-fields">
                                <label class="form-label">Permanent PIN</label>
                                <input class="form-control" name="permanent_pincode" value="<?php echo $old('permanent_pincode'); ?>">
                            </div>

                            <div class="col-12"><hr>
                                <h6 class="mb-1">13. Particulars of all examinations passed and degrees / technical qualifications</h6>
                                <p class="small text-muted">Commence from School Board or equivalent examination. Attach separate details if required.</p>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle edu-table" id="eduTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Examination / Degree</th>
                                                <th>University / Board</th>
                                                <th>Year of passing</th>
                                                <th>% of marks / CGPA</th>
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
                                <button class="btn btn-outline-secondary btn-sm" type="button" id="addEduRow">Add examination row</button>
                            </div>

                            <div class="col-12">
                                <h6 class="mb-1">14. Experience (start with the latest)</h6>
                                <p class="small text-muted">Attach attested copies of experience certificates. Leave blank if not applicable.</p>
                                <div class="col-md-4 mb-2 px-0">
                                    <label class="form-label">Total experience</label>
                                    <input class="form-control" name="experience_years" placeholder="e.g. 2 years 3 months" value="<?php echo $old('experience_years'); ?>">
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle exp-table" id="expTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name of organisation</th>
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
                                <button class="btn btn-outline-secondary btn-sm" type="button" id="addExpRow">Add experience row</button>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">15. Computer knowledge / additional qualifications</label>
                                <textarea class="form-control" name="computer_knowledge" rows="3"><?php echo $old('computer_knowledge'); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Any other information</label>
                                <textarea class="form-control" name="additional_info" rows="3"><?php echo $old('additional_info'); ?></textarea>
                            </div>

                            <div class="col-12"><hr>
                                <h6>16. Documents to be attached (self-attested scanned copies)</h6>
                                <p class="small text-muted mb-3">Whether all the documents mentioned above are attached — confirm in the undertaking below. Application without supporting documents will be rejected.</p>
                            </div>
                            <?php foreach (recruitmentOfficialDocuments() as $doc): ?>
                                <?php if (in_array($doc['key'], ['photo', 'signature'], true)) { continue; } ?>
                                <div class="col-md-6" data-doc-key="<?php echo htmlspecialchars($doc['key']); ?>">
                                    <label class="form-label"><?php
                                        echo $doc['item'] !== '' ? htmlspecialchars($doc['item']) . ') ' : '';
                                        echo htmlspecialchars($doc['label']);
                                        echo !empty($doc['required']) ? ' *' : '';
                                    ?></label>
                                    <input class="form-control" type="file" name="<?php echo htmlspecialchars($doc['key']); ?>" accept="<?php echo htmlspecialchars($doc['accept']); ?>" <?php echo !empty($doc['required']) ? 'required' : ''; ?>>
                                </div>
                            <?php endforeach; ?>

                            <div class="col-12"><hr>
                                <h6>Undertaking</h6>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="declaration_docs" id="decDocs" value="1" required>
                                    <label class="form-check-label" for="decDocs">All the documents mentioned above are attached (Yes).</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="declaration_terms" id="decTerms" value="1" required>
                                    <label class="form-check-label" for="decTerms">I have gone through the Terms &amp; Conditions / instructions for this post and shall abide by the same.</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="declaration" id="declaration" value="1" required>
                                    <label class="form-check-label" for="declaration">All information furnished above is true, complete and correct to the best of my knowledge and belief.</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="declaration_one" id="decOne" value="1" required>
                                    <label class="form-check-label" for="decOne">I have submitted only one application for this position. I have never been debarred by any organisation for illegal activity during my education / service. I understand that false / suppressed information will cancel my candidature, and that NIELIT may accept or reject this application without assigning a reason.</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Place *</label>
                                <input class="form-control" name="application_place" required value="<?php echo $old('application_place'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date</label>
                                <input class="form-control" value="<?php echo date('d-m-Y'); ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Signature of the candidate *</label>
                                <input class="form-control" type="file" name="signature" accept=".jpg,.jpeg,.png" required>
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
    <script>
    (function () {
        var asOn = <?php echo json_encode($asOnDate); ?>;
        var dob = document.getElementById('dobField');
        function fillAge() {
            var y = document.getElementById('ageYears');
            var m = document.getElementById('ageMonths');
            var d = document.getElementById('ageDays');
            if (!dob || !dob.value || !asOn) { return; }
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
                var prev = new Date(to.getFullYear(), to.getMonth(), 0).getDate();
                days += prev;
            }
            if (months < 0) { years -= 1; months += 12; }
            y.value = years; m.value = months; d.value = days;
        }
        if (dob) { dob.addEventListener('change', fillAge); fillAge(); }

        var photo = document.getElementById('photoFile');
        var preview = document.getElementById('photoPreview');
        if (photo && preview) {
            photo.addEventListener('change', function () {
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
    })();
    </script>
<?php public_skeleton_render_script(); ?>
</body>
</html>
