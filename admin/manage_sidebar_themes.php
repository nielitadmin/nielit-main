<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/preset_themes_catalog.php';
require_once __DIR__ . '/../includes/themes_schema.php';

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
try {
    seedPresetAppThemes($conn);
} catch (Throwable $e) {
    error_log('seedPresetAppThemes (sidebar page): ' . $e->getMessage());
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_sidebar_style'])) {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'danger';
    } else {
        $styleKey = trim((string) ($_POST['style_key'] ?? ''));
        $alsoTheme = !empty($_POST['also_apply_app_theme']);
        if (setActiveSidebarTheme($conn, $styleKey, (string) ($_SESSION['admin'] ?? 'admin'))) {
            $presets = sidebarThemePresets();
            $label = $presets[$styleKey]['label'] ?? $styleKey;
            $message = 'Sidebar style "' . $label . '" is now active for all admins.';
            $message_type = 'success';

            if ($alsoTheme) {
                $matches = presetThemesSuggestedForSidebar($styleKey);
                if (!empty($matches)) {
                    $targetName = $matches[0]['theme_name'];
                    $stmt = $conn->prepare('SELECT id FROM themes WHERE theme_name = ? LIMIT 1');
                    if ($stmt) {
                        $stmt->bind_param('s', $targetName);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if ($row) {
                            $conn->query('UPDATE themes SET is_active = 0');
                            $upd = $conn->prepare('UPDATE themes SET is_active = 1 WHERE id = ?');
                            if ($upd) {
                                $tid = (int) $row['id'];
                                $upd->bind_param('i', $tid);
                                $upd->execute();
                                $upd->close();
                                if (function_exists('clearThemeCache')) {
                                    clearThemeCache();
                                }
                                $message .= ' Matching app theme "' . $targetName . '" was also activated.';
                            }
                        }
                    }
                }
            }

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $message = 'Could not activate that sidebar style.';
            $message_type = 'danger';
        }
    }
}

$activeStyle = getActiveSidebarTheme($conn);
$definitions = sidebarThemeStyleDefinitions();
$presets = sidebarThemePresets();
$bodySidebarClass = sidebarThemeBodyClass($activeStyle);
$suggestedAppThemes = presetThemesSuggestedForSidebar($activeStyle);
$activeSidebarLabel = $presets[$activeStyle]['label'] ?? $activeStyle;
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
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
            display: flex;
            flex-direction: column;
            padding: 10px 8px;
            gap: 6px;
            width: 120px;
        }
        .sb-mini.is-icon {
            width: 52px;
            align-items: center;
        }
        .sb-mini.is-light {
            border: 1px solid #e2e8f0;
        }
        .sb-mini-logo {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            margin: 0 auto 4px;
            background: #fff;
        }
        .sb-mini.is-light .sb-mini-logo { background: #dbeafe; }
        .sb-mini.is-icon .sb-mini-logo { width: 24px; height: 24px; }
        .sb-mini-line {
            height: 10px;
            border-radius: 6px;
            background: rgba(255,255,255,0.22);
        }
        .sb-mini.is-light .sb-mini-line { background: rgba(15,23,42,0.12); }
        .sb-mini.is-icon .sb-mini-line {
            width: 22px;
            height: 22px;
            border-radius: 8px;
        }
        .sb-mini-line.active { background: rgba(255,255,255,0.4); }
        .sb-mini.is-light .sb-mini-line.active { background: rgba(29,78,216,0.25); }
        .sb-theme-body { padding: 1rem 1.1rem 1.15rem; flex: 1; display: flex; flex-direction: column; }
        .sb-theme-body h6 { margin: 0 0 0.35rem; font-size: 1rem; color: #0f172a; }
        .sb-theme-body p { margin: 0 0 1rem; color: #64748b; font-size: 0.85rem; flex: 1; }
        .sb-theme-meta {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
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

            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-lightbulb"></i> Suggested app themes for “<?php echo htmlspecialchars($activeSidebarLabel); ?>”</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($suggestedAppThemes)): ?>
                        <p class="text-muted mb-0">No paired app themes for this sidebar yet. Browse <a href="<?php echo app_url('admin/manage_themes'); ?>">Manage Themes</a>.</p>
                    <?php else: ?>
                        <p style="color:#64748b;margin-bottom:1rem;">These overall Themes match your active sidebar. Activate one in Manage Themes, or tick “Also apply matching app theme” when switching sidebars below.</p>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;">
                            <?php foreach ($suggestedAppThemes as $sug): ?>
                                <div style="border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;min-width:180px;background:#fff;">
                                    <div style="height:10px;border-radius:6px;margin-bottom:8px;background:linear-gradient(90deg, <?php echo htmlspecialchars($sug['primary_color']); ?>, <?php echo htmlspecialchars($sug['secondary_color']); ?>);"></div>
                                    <strong style="font-size:0.9rem;"><?php echo htmlspecialchars($sug['theme_name']); ?></strong>
                                    <?php if (!empty($sug['tag'])): ?>
                                        <div style="font-size:0.7rem;color:#64748b;margin-top:2px;"><?php echo htmlspecialchars($sug['tag']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:12px;">
                            <a class="btn btn-secondary btn-sm" href="<?php echo app_url('admin/manage_themes'); ?>">
                                <i class="fas fa-palette"></i> Open Manage Themes
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-sliders-h"></i> Available Sidebar Styles (<?php echo count($definitions); ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="sb-themes-grid">
                        <?php foreach ($definitions as $key => $def): ?>
                            <?php
                            $isActive = ($key === $activeStyle);
                            $isIcon = (($def['layout'] ?? '') === 'icon');
                            $isLight = (($def['tone'] ?? '') === 'light');
                            $miniClass = 'sb-mini' . ($isIcon ? ' is-icon' : '') . ($isLight ? ' is-light' : '');
                            ?>
                            <div class="sb-theme-card <?php echo $isActive ? 'is-active' : ''; ?>">
                                <div class="sb-theme-preview">
                                    <div class="<?php echo $miniClass; ?>" style="background: <?php echo htmlspecialchars($def['bg']); ?>;">
                                        <div class="sb-mini-logo"></div>
                                        <?php if (!$isIcon): ?>
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
                                    <span class="sb-theme-meta"><?php echo $isIcon ? 'Icon rail' : 'Expanded'; ?> · <?php echo $isLight ? 'Light' : 'Dark'; ?></span>
                                    <h6><?php echo htmlspecialchars($def['label']); ?></h6>
                                    <p><?php echo htmlspecialchars($def['description']); ?></p>
                                    <div class="sb-theme-actions">
                                        <?php if ($isActive): ?>
                                            <span class="sb-badge-active"><i class="fas fa-check-circle"></i> Active</span>
                                        <?php else: ?>
                                            <form method="post" style="margin:0;width:100%;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="style_key" value="<?php echo htmlspecialchars($key); ?>">
                                                <label style="display:flex;align-items:flex-start;gap:6px;font-size:0.75rem;color:#64748b;margin-bottom:8px;cursor:pointer;">
                                                    <input type="checkbox" name="also_apply_app_theme" value="1" style="margin-top:2px;">
                                                    <span>Also apply matching app theme</span>
                                                </label>
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
