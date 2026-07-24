<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/public_theme_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: ' . relative_url('login.php'));
    exit();
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $_SESSION['message'] = 'Access denied. Public Themes is available to Master Admin only.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . relative_url('dashboard.php'));
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$active_theme = loadActiveTheme($conn);
ensurePublicThemeSettingsTable($conn);

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_public_theme'])) {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'danger';
    } else {
        $styleKey = trim((string) ($_POST['style_key'] ?? ''));
        if (setActivePublicTheme($conn, $styleKey, (string) ($_SESSION['admin'] ?? 'admin'))) {
            $presets = publicThemePresets();
            $label = $presets[$styleKey]['label'] ?? $styleKey;
            $message = 'Public theme "' . $label . '" is now active on the website.';
            $message_type = 'success';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $message = 'Could not activate that public theme.';
            $message_type = 'danger';
        }
    }
}

$activeKey = getActivePublicThemeKey($conn);
$definitions = publicThemeStyleDefinitions();
$presets = publicThemePresets();
$activeLabel = $presets[$activeKey]['label'] ?? $activeKey;
$activeDef = $definitions[$activeKey] ?? $definitions[publicThemeDefaultKey()];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Themes - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .pt-themes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
        }
        .pt-theme-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            display: flex;
            flex-direction: column;
        }
        .pt-theme-card.is-active {
            border-color: #f59e0b;
            box-shadow: 0 10px 28px rgba(245, 158, 11, 0.22);
        }
        .pt-theme-preview {
            height: 120px;
            padding: 14px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pt-swatch {
            width: 100%;
            max-width: 210px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.16);
            border: 1px solid rgba(15, 23, 42, 0.08);
        }
        .pt-swatch-bar {
            height: 42px;
            display: flex;
            align-items: center;
            padding: 0 12px;
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .pt-swatch-body {
            background: #fff;
            padding: 12px;
        }
        .pt-swatch-line {
            height: 8px;
            border-radius: 999px;
            margin-bottom: 8px;
            background: #e2e8f0;
        }
        .pt-swatch-btn {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 700;
            color: #0f172a;
        }
        .pt-theme-body { padding: 1rem 1.1rem 1.15rem; flex: 1; display: flex; flex-direction: column; }
        .pt-theme-body h6 { margin: 0 0 0.35rem; font-size: 1rem; color: #0f172a; }
        .pt-theme-body p { margin: 0 0 1rem; color: #64748b; font-size: 0.85rem; flex: 1; }
        .pt-theme-meta {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        .pt-colors {
            display: flex;
            gap: 6px;
            margin-bottom: 0.85rem;
        }
        .pt-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 1px solid rgba(15,23,42,0.12);
        }
        .pt-badge-active {
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
        .pt-active-banner {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
        }
        .pt-active-swatches { display: flex; gap: 8px; }
        .pt-active-swatches span {
            width: 28px; height: 28px; border-radius: 8px;
            border: 1px solid rgba(15,23,42,0.1);
        }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-globe"></i> Public Themes</h4>
                <small>Controls colors on the public website (Home, Courses, News, Contact, Management)</small>
            </div>
            <div class="topbar-right">
                <a href="<?php echo APP_URL; ?>/" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">
                    <i class="fas fa-external-link-alt"></i> View Website
                </a>
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

            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-check-circle"></i> Active Public Theme</h5>
                </div>
                <div class="card-body">
                    <div class="pt-active-banner">
                        <div>
                            <strong style="font-size:1.05rem;"><?php echo htmlspecialchars($activeLabel); ?></strong>
                            <div class="text-muted" style="font-size:0.85rem;margin-top:4px;">
                                Key: <code><?php echo htmlspecialchars($activeKey); ?></code>
                                · <?php echo htmlspecialchars($activeDef['tag'] ?? ''); ?>
                            </div>
                            <p class="mb-0 mt-2" style="color:#64748b;max-width:520px;">
                                <?php echo htmlspecialchars($activeDef['description'] ?? ''); ?>
                            </p>
                        </div>
                        <div class="pt-active-swatches" title="Primary / Secondary / Accent">
                            <span style="background:<?php echo htmlspecialchars($activeDef['primary']); ?>;"></span>
                            <span style="background:<?php echo htmlspecialchars($activeDef['secondary']); ?>;"></span>
                            <span style="background:<?php echo htmlspecialchars($activeDef['accent']); ?>;"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-th-large"></i> Available Public Themes (<?php echo count($definitions); ?>)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="margin-bottom:1.25rem;">
                        Activate a theme to update public page colors (navbar, buttons, accents, section styling).
                        Night mode still works; these colors apply to day mode branding.
                    </p>
                    <div class="pt-themes-grid">
                        <?php foreach ($definitions as $key => $def): ?>
                            <?php $isActive = ($key === $activeKey); ?>
                            <div class="pt-theme-card <?php echo $isActive ? 'is-active' : ''; ?>">
                                <div class="pt-theme-preview">
                                    <div class="pt-swatch">
                                        <div class="pt-swatch-bar" style="background: linear-gradient(135deg, <?php echo htmlspecialchars($def['primary']); ?> 0%, <?php echo htmlspecialchars($def['navy_mid']); ?> 100%);">
                                            NIELIT
                                        </div>
                                        <div class="pt-swatch-body" style="background:<?php echo htmlspecialchars($def['cream']); ?>;">
                                            <div class="pt-swatch-line" style="width:78%;background:<?php echo htmlspecialchars($def['secondary']); ?>;opacity:0.35;"></div>
                                            <div class="pt-swatch-line" style="width:55%;"></div>
                                            <span class="pt-swatch-btn" style="background:<?php echo htmlspecialchars($def['accent']); ?>;">Apply</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="pt-theme-body">
                                    <span class="pt-theme-meta"><?php echo htmlspecialchars($def['tag'] ?? 'Theme'); ?></span>
                                    <h6><?php echo htmlspecialchars($def['label']); ?></h6>
                                    <p><?php echo htmlspecialchars($def['description']); ?></p>
                                    <div class="pt-colors">
                                        <span class="pt-dot" style="background:<?php echo htmlspecialchars($def['primary']); ?>;" title="Primary"></span>
                                        <span class="pt-dot" style="background:<?php echo htmlspecialchars($def['secondary']); ?>;" title="Secondary"></span>
                                        <span class="pt-dot" style="background:<?php echo htmlspecialchars($def['accent']); ?>;" title="Accent"></span>
                                    </div>
                                    <div>
                                        <?php if ($isActive): ?>
                                            <span class="pt-badge-active"><i class="fas fa-check-circle"></i> Active</span>
                                        <?php else: ?>
                                            <form method="post" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="style_key" value="<?php echo htmlspecialchars($key); ?>">
                                                <button type="submit" name="activate_public_theme" value="1" class="btn btn-primary btn-sm">
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
