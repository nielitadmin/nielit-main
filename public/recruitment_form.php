<?php
/**
 * Public: blank application form PDF, or filled form by application no. + email / token.
 */
require_once __DIR__ . '/../includes/maintenance_check.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';
require_once __DIR__ . '/../includes/render_recruitment_application_form_pdf.php';

ensureRecruitmentTables($conn);

$id = (int) ($_GET['id'] ?? 0);
$jobId = (int) ($_GET['job'] ?? 0);
$no = trim((string) ($_GET['no'] ?? ''));
$email = strtolower(trim((string) ($_GET['email'] ?? '')));
$token = trim((string) ($_GET['t'] ?? ''));
$wantBlank = isset($_GET['blank']);
$isAdmin = isset($_SESSION['admin']) && recruitmentCanAccess(null, $conn);

$app = null;
$error = '';

if ($id > 0) {
    if (!$isAdmin) {
        $error = 'Please sign in as admin to open this application form.';
    } else {
        $app = recruitmentGetApplication($conn, $id);
        if (!$app) {
            $error = 'Application not found.';
        }
    }
} elseif ($no !== '') {
    $found = recruitmentGetApplicationByNo($conn, $no);
    if (!$found) {
        $error = 'No application was found for that number.';
    } else {
        $matchEmail = strtolower(trim((string) ($found['email'] ?? '')));
        $ok = $isAdmin
            || ($email !== '' && hash_equals($matchEmail, $email))
            || recruitmentApplicationFormTokenValid($no, (string) ($found['email'] ?? ''), $token);
        if (!$ok) {
            $error = 'Enter the same email used on the application to download the filled form.';
        } else {
            $app = $found;
        }
    }
}

if ($error === '' && $app !== null) {
    outputRecruitmentApplicationFormPdf($app, 'I');
    exit;
}

if ($error === '' && ($wantBlank || $jobId > 0) && $no === '' && $id === 0) {
    $prefill = [];
    if ($jobId > 0) {
        $job = recruitmentGetJob($conn, $jobId);
        if ($job) {
            $prefill = [
                'job_title' => (string) ($job['title'] ?? ''),
                'advt_no' => (string) ($job['advt_no'] ?? ''),
            ];
        }
    }
    outputRecruitmentApplicationFormPdf($prefill !== [] ? $prefill : null, 'I');
    exit;
}

require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/navigation_helper.php';
require_once __DIR__ . '/../includes/public_theme_helper.php';
$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);
$page_title = 'Application form - NIELIT Bhubaneswar';
injectThemeCSS($active_theme);
emitPublicThemeHead($conn);
$blankUrl = recruitmentApplicationFormUrl(null, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
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
                <div class="col-md-8 d-flex align-items-center">
                    <img src="<?php echo APP_URL . '/' . $theme_logo; ?>" alt="NIELIT Logo" class="me-3" style="height:50px;">
                    <div class="fw-bold"><?php echo htmlspecialchars(INSTITUTE_NAME_EN); ?></div>
                </div>
            </div>
        </div>
    </div>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo app_url('index'); ?>">NIELIT</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto"><?php echo getPublicSiteNavigationHtml($conn, 'recruitment.php'); ?></ul>
            </div>
        </div>
    </nav>
    <section class="page-header">
        <div class="container text-center">
            <h1 class="mb-0">Application form</h1>
            <p class="lead mb-0 mt-2">One form for every NIELIT Bhubaneswar recruitment post</p>
        </div>
    </section>
    <section class="py-5">
        <div class="container" style="max-width:720px;">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5><i class="fas fa-file-pdf me-2 text-danger"></i>Blank form</h5>
                    <p class="text-muted mb-3">Download the official-style form and use it for any advertised post (online copy, walk-in, or offline).</p>
                    <a class="btn btn-primary" href="<?php echo htmlspecialchars($blankUrl); ?>" target="_blank">Download blank application form (PDF)</a>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5><i class="fas fa-download me-2 text-primary"></i>Already applied online?</h5>
                    <p class="text-muted">Enter your application number and the email you used to download the filled form.</p>
                    <form method="get" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Application no.</label>
                            <input class="form-control" name="no" required placeholder="REC/2026/00001" value="<?php echo htmlspecialchars($no); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input class="form-control" type="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-outline-primary" type="submit">Download filled form</button>
                            <a class="btn btn-link" href="<?php echo htmlspecialchars(app_url('public/recruitment')); ?>">Back to openings</a>
                        </div>
                    </form>
                </div>
            </div>
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
