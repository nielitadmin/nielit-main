<?php
/**
 * System Settings → Header & Footer (per centre).
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/institute_branding.php';
require_once __DIR__ . '/../includes/centre_chrome_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $_SESSION['message'] = 'Access denied. Only Master Admin can edit header and footer.';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];
$active_theme = loadActiveTheme($conn);
$centres = centre_chrome_list_centres($conn);
$mainSiteId = centre_chrome_main_site_id($conn);
$selectedCentreId = isset($_GET['centre_id']) ? (int) $_GET['centre_id'] : $mainSiteId;
$validIds = array_map(static function ($row) {
    return (int) $row['id'];
}, $centres);
if ($selectedCentreId > 0 && !in_array($selectedCentreId, $validIds, true) && $selectedCentreId !== $mainSiteId) {
    $selectedCentreId = $mainSiteId;
}

$notice = '';
$noticeType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $notice = 'Invalid security token. Refresh and try again.';
        $noticeType = 'danger';
    } else {
        $selectedCentreId = (int) ($_POST['centre_id'] ?? $selectedCentreId);
        $input = [];
        foreach (centre_chrome_field_keys() as $key) {
            $input[$key] = (string) ($_POST[$key] ?? '');
        }
        $current = centre_chrome_get($conn, $selectedCentreId);
        $input['left_logo_path'] = (string) ($current['left_logo_path'] ?? '');
        $input['right_logo_path'] = (string) ($current['right_logo_path'] ?? '');

        if (!empty($_POST['clear_left_logo'])) {
            $input['left_logo_path'] = '';
        }
        if (!empty($_POST['clear_right_logo'])) {
            $input['right_logo_path'] = '';
        }

        $uploadError = '';
        if (!empty($_FILES['left_logo']['name'])) {
            $up = centre_chrome_upload_logo($_FILES['left_logo'], 'left');
            if (!empty($up['skip'])) {
                // keep existing
            } elseif (!empty($up['success'])) {
                $input['left_logo_path'] = $up['path'];
            } else {
                $uploadError = $up['message'] ?? 'Left logo upload failed.';
            }
        }
        if ($uploadError === '' && !empty($_FILES['right_logo']['name'])) {
            $up = centre_chrome_upload_logo($_FILES['right_logo'], 'right');
            if (!empty($up['skip'])) {
                // keep existing
            } elseif (!empty($up['success'])) {
                $input['right_logo_path'] = $up['path'];
            } else {
                $uploadError = $up['message'] ?? 'Right logo upload failed.';
            }
        }

        if ($uploadError !== '') {
            $notice = $uploadError;
            $noticeType = 'danger';
        } else {
            $result = centre_chrome_save($conn, $selectedCentreId, $input, (string) ($_SESSION['admin'] ?? 'admin'));
            $notice = !empty($result['success'])
                ? 'Header and footer saved for this centre.'
                : (string) ($result['message'] ?? 'Could not save.');
            $noticeType = !empty($result['success']) ? 'success' : 'danger';
        }
    }
}

$branding = centre_chrome_get($conn, $selectedCentreId);
$selectedName = 'Main website';
foreach ($centres as $c) {
    if ((int) $c['id'] === $selectedCentreId) {
        $selectedName = function_exists('normalize_nielit_centre_name')
            ? normalize_nielit_centre_name((string) $c['name'])
            : (string) $c['name'];
        break;
    }
}
$page_title = 'Header & Footer';
$leftUrl = centre_chrome_public_url((string) ($branding['left_logo_path'] ?? ''));
$rightUrl = centre_chrome_public_url((string) ($branding['right_logo_path'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .centre-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 1rem; }
        .centre-tabs a {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            text-decoration: none;
            font-size: 0.92rem;
            background: #fff;
        }
        .centre-tabs a.active {
            background: #0c2340;
            color: #fff;
            border-color: #0c2340;
        }
        .logo-preview {
            height: 64px;
            width: auto;
            max-width: 180px;
            object-fit: contain;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px;
        }
        .logo-box { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-main">
        <div class="container-fluid py-4">
            <div class="mb-4">
                <h2><i class="fas fa-window-maximize"></i> Header &amp; Footer</h2>
                <p class="text-muted mb-0">Set left/right logos, institute names, ticker, and footer for each centre. NIELIT Bhubaneswar drives the main website. NIELIT Raipur drives the Raipur public report.</p>
            </div>

            <?php if ($notice !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($noticeType); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($notice); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="centre-tabs">
                <?php foreach ($centres as $c): ?>
                    <?php
                    $cid = (int) $c['id'];
                    $label = function_exists('normalize_nielit_centre_name')
                        ? normalize_nielit_centre_name((string) $c['name'])
                        : (string) $c['name'];
                    if ($cid === $mainSiteId) {
                        $label .= ' (main website)';
                    }
                    ?>
                    <a href="?centre_id=<?php echo $cid; ?>" class="<?php echo $cid === $selectedCentreId ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($label); ?>
                    </a>
                <?php endforeach; ?>
                <?php if ($centres === []): ?>
                    <span class="text-muted">Add training centres first under System Settings → Training Centres.</span>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <strong>Editing: <?php echo htmlspecialchars($selectedName); ?></strong>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="centre_id" value="<?php echo (int) $selectedCentreId; ?>">

                        <h5 class="mb-3">Header</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Left logo</label>
                                <div class="logo-box mb-2">
                                    <?php if ($leftUrl !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($leftUrl); ?>" alt="Left logo" class="logo-preview">
                                    <?php endif; ?>
                                    <input type="file" class="form-control" name="left_logo" accept="image/*">
                                </div>
                                <label class="small"><input type="checkbox" name="clear_left_logo" value="1"> Use default left logo</label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Right logo</label>
                                <div class="logo-box mb-2">
                                    <?php if ($rightUrl !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($rightUrl); ?>" alt="Right logo" class="logo-preview">
                                    <?php endif; ?>
                                    <input type="file" class="form-control" name="right_logo" accept="image/*">
                                </div>
                                <label class="small"><input type="checkbox" name="clear_right_logo" value="1"> Use default right logo</label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Institute name (Hindi)</label>
                                <input type="text" class="form-control" name="header_name_hi" value="<?php echo htmlspecialchars((string) $branding['header_name_hi']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Institute name (English)</label>
                                <input type="text" class="form-control" name="header_name_en" value="<?php echo htmlspecialchars((string) $branding['header_name_en']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ministry name (Hindi)</label>
                                <input type="text" class="form-control" name="ministry_name_hi" value="<?php echo htmlspecialchars((string) $branding['ministry_name_hi']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ministry name (English)</label>
                                <input type="text" class="form-control" name="ministry_name_en" value="<?php echo htmlspecialchars((string) $branding['ministry_name_en']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Orange ticker text</label>
                                <input type="text" class="form-control" name="ticker_text" maxlength="500" value="<?php echo htmlspecialchars((string) $branding['ticker_text']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Report banner title</label>
                                <input type="text" class="form-control" name="banner_title" value="<?php echo htmlspecialchars((string) $branding['banner_title']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Report banner tagline</label>
                                <input type="text" class="form-control" name="banner_tagline" value="<?php echo htmlspecialchars((string) $branding['banner_tagline']); ?>">
                            </div>
                        </div>

                        <h5 class="mb-3">Footer</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Footer brand</label>
                                <input type="text" class="form-control" name="footer_brand" value="<?php echo htmlspecialchars((string) $branding['footer_brand']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Footer centre name</label>
                                <input type="text" class="form-control" name="footer_centre_label" value="<?php echo htmlspecialchars((string) $branding['footer_centre_label']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="footer_phone" value="<?php echo htmlspecialchars((string) $branding['footer_phone']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">About text</label>
                                <textarea class="form-control" name="footer_about" rows="2"><?php echo htmlspecialchars((string) $branding['footer_about']); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="footer_address" value="<?php echo htmlspecialchars((string) $branding['footer_address']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="footer_email" value="<?php echo htmlspecialchars((string) $branding['footer_email']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Copyright (use <code>{year}</code> for the current year)</label>
                                <input type="text" class="form-control" name="footer_copyright" value="<?php echo htmlspecialchars((string) $branding['footer_copyright']); ?>">
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save header &amp; footer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
