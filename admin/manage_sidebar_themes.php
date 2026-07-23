<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: ' . relative_url('login.php'));
    exit();
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $_SESSION['message'] = 'Access denied. Sidebar Themes is available to Master Admin only.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . relative_url('dashboard.php'));
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$active_theme = loadActiveTheme($conn);
ensureSidebarThemeSettingsTable($conn);

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_sidebar_style'])) {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'danger';
    } else {
        $styleKey = trim((string) ($_POST['style_key'] ?? ''));
        if (setActiveSidebarTheme($conn, $styleKey, (string) ($_SESSION['admin'] ?? 'admin'))) {
            $presets = sidebarThemePresets();
            $label = $presets[$styleKey]['label'] ?? $styleKey;
            $message = 'Sidebar style "' . $label . '" is now active for all admins.';
            $message_type = 'success';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $message = 'Could not activate that sidebar style.';
            $message_type = 'danger';
        }
    }
}

$activeStyle = getActiveSidebarTheme($conn);
$presets = sidebarThemePresets();
$bodySidebarClass = sidebarThemeBodyClass($activeStyle);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sidebar Themes - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin-theme.css') ?: time(); ?>">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
    <style>
        .sb-themes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
        }
        .sb-theme-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            display: flex;
            flex-direction: column;
        }
        .sb-theme-card.is-active {
            border-color: #f59e0b;
            box-shadow: 0 10px 28px rgba(245, 158, 11, 0.2);
        }
        .sb-theme-preview {
            height: 220px;
            padding: 14px;
            background: #f1f5f9;
            display: flex;
            align-items: stretch;
            justify-content: center;
        }
        .sb-mini {
            width: 92px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
            display: flex;
            flex-direction: column;
            padding: 10px 8px;
            gap: 6px;
        }
        .sb-mini.soft_navy {
            width: 120px;
            background: linear-gradient(180deg, #0c2340 0%, #123a66 55%, #0f3d7a 100%);
        }
        .sb-mini.dark { width: 120px; background: #1e293b; }
        .sb-mini.light {
            width: 120px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        .sb-mini.icon {
            width: 52px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            align-items: center;
        }
        .sb-mini-logo {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            margin: 0 auto 4px;
            background: #fff;
        }
        .sb-mini.light .sb-mini-logo { background: #dbeafe; }
        .sb-mini.icon .sb-mini-logo { width: 24px; height: 24px; }
        .sb-mini-line {
            height: 10px;
            border-radius: 6px;
            background: rgba(255,255,255,0.22);
        }
        .sb-mini.light .sb-mini-line { background: #e2e8f0; }
        .sb-mini.icon .sb-mini-line {
            width: 22px;
            height: 22px;
            border-radius: 8px;
            background: #e2e8f0;
        }
        .sb-mini-line.active {
            background: rgba(255,255,255,0.38);
        }
        .sb-mini.light .sb-mini-line.active { background: #dbeafe; }
        .sb-mini.icon .sb-mini-line.active { background: #bfdbfe; }
        .sb-theme-body { padding: 1rem 1.1rem 1.15rem; flex: 1; display: flex; flex-direction: column; }
        .sb-theme-body h6 { margin: 0 0 0.35rem; font-size: 1rem; color: #0f172a; }
        .sb-theme-body p { margin: 0 0 1rem; color: #64748b; font-size: 0.85rem; flex: 1; }
        .sb-theme-actions { display: flex; gap: 0.5rem; align-items: center; }
        .sb-badge-active {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ecfdf5;
            color: #047857;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars($bodySidebarClass); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-columns"></i> Sidebar Themes</h4>
                <small>Choose a site-wide admin sidebar style for all administrators</small>
            </div>
            <div class="topbar-right">
                <a href="<?php echo app_url('admin/manage_themes'); ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-palette"></i> App Themes
                </a>
            </div>
        </div>

        <div class="admin-main">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-sliders-h"></i> Available Sidebar Styles</h5>
                </div>
                <div class="card-body">
                    <div class="sb-themes-grid">
                        <?php foreach ($presets as $key => $meta): ?>
                            <?php $isActive = ($key === $activeStyle); ?>
                            <div class="sb-theme-card <?php echo $isActive ? 'is-active' : ''; ?>">
                                <div class="sb-theme-preview">
                                    <div class="sb-mini <?php echo htmlspecialchars($key); ?>">
                                        <div class="sb-mini-logo"></div>
                                        <?php if ($key !== 'icon'): ?>
                                            <div class="sb-mini-line" style="width:70%;margin:0 auto;"></div>
                                            <div class="sb-mini-line" style="width:50%;margin:0 auto 6px;"></div>
                                        <?php endif; ?>
                                        <div class="sb-mini-line active"></div>
                                        <div class="sb-mini-line"></div>
                                        <div class="sb-mini-line"></div>
                                        <div class="sb-mini-line"></div>
                                        <div class="sb-mini-line"></div>
                                    </div>
                                </div>
                                <div class="sb-theme-body">
                                    <h6><?php echo htmlspecialchars($meta['label']); ?></h6>
                                    <p><?php echo htmlspecialchars($meta['description']); ?></p>
                                    <div class="sb-theme-actions">
                                        <?php if ($isActive): ?>
                                            <span class="sb-badge-active"><i class="fas fa-check-circle"></i> Active</span>
                                        <?php else: ?>
                                            <form method="post" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="style_key" value="<?php echo htmlspecialchars($key); ?>">
                                                <button type="submit" name="activate_sidebar_style" value="1" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-check"></i> Activate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
