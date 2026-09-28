<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/festival_theme_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: ' . relative_url('login.php'));
    exit();
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $_SESSION['message'] = 'Access denied. Festival Themes is available to Master Admin only.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . relative_url('dashboard.php'));
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$active_theme = loadActiveTheme($conn);
ensureFestivalThemeSchema($conn);

$message = '';
$message_type = 'success';
$year = (int) ($_GET['year'] ?? date('Y'));
if ($year < 2024 || $year > 2035) {
    $year = (int) date('Y');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'danger';
    } elseif (isset($_POST['save_settings'])) {
        $enabled = isset($_POST['enabled']);
        $force = trim((string) ($_POST['force_pack'] ?? ''));
        if ($force === '') {
            $force = null;
        }
        if (setFestivalThemeSettings($conn, $enabled, $force, (string) ($_SESSION['admin'] ?? 'admin'))) {
            $message = 'Festival theme settings saved.';
            $message_type = 'success';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $message = 'Could not save settings.';
            $message_type = 'danger';
        }
    } elseif (isset($_POST['save_calendar_row'])) {
        $id = (int) ($_POST['row_id'] ?? 0);
        $start = trim((string) ($_POST['start_date'] ?? ''));
        $end = trim((string) ($_POST['end_date'] ?? ''));
        $enabled = isset($_POST['row_enabled']);
        if (updateFestivalThemeCalendarRow($conn, $id, $start, $end, $enabled)) {
            $message = 'Festival date updated.';
            $message_type = 'success';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $message = 'Could not update that festival date.';
            $message_type = 'danger';
        }
    } elseif (isset($_POST['reseed_year'])) {
        festivalThemeSeedCalendar($conn, $year);
        $message = 'Seeded missing festival dates for ' . $year . '.';
        $message_type = 'success';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

$settings = getFestivalThemeSettings($conn);
$packs = festivalThemePackDefinitions();
$calendar = listFestivalThemeCalendar($conn, $year);
$activePack = resolveActiveFestivalTheme($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Festival Themes - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .ft-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; }
        .ft-card { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; overflow: hidden; box-shadow: 0 8px 24px rgba(15,23,42,.06); }
        .ft-card.is-live { border-color: #f59e0b; box-shadow: 0 10px 28px rgba(245,158,11,.22); }
        .ft-preview { height: 88px; padding: 12px; }
        .ft-swatch { height: 100%; border-radius: 10px; display: flex; align-items: flex-end; padding: 8px; color: #fff; font-size: .78rem; font-weight: 700; }
        .ft-body { padding: 12px 14px 14px; }
        .ft-tag { display: inline-block; font-size: .7rem; padding: 2px 8px; border-radius: 999px; background: #e2e8f0; color: #334155; margin-bottom: 6px; }
        .ft-live-banner { border-radius: 12px; padding: 14px 16px; background: linear-gradient(135deg, #0c2340, #1a56db); color: #fff; }
        .cal-table input[type="date"] { min-width: 140px; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="mb-1"><i class="fas fa-gift"></i> Festival Themes</h2>
                <p class="text-muted mb-0">Different packs for each festival — colors, ribbon, motif, and small UI details across public pages, registration, and biometric kiosks.</p>
            </div>
            <a href="<?php echo htmlspecialchars(app_url('admin/manage_public_themes')); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-globe"></i> Public Themes
            </a>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="ft-live-banner mb-4">
            <?php if ($activePack): ?>
                <div class="fw-bold mb-1"><i class="fas fa-star"></i> Live now: <?php echo htmlspecialchars((string) $activePack['label']); ?></div>
                <div style="opacity:.9;font-size:.92rem;">
                    Greeting: “<?php echo htmlspecialchars((string) ($activePack['greeting'] ?? '')); ?>”
                    · Motif: <?php echo htmlspecialchars((string) ($activePack['motif'] ?? '')); ?>
                    · Source: <?php echo htmlspecialchars((string) ($activePack['_source'] ?? 'calendar')); ?>
                </div>
            <?php else: ?>
                <div class="fw-bold mb-1"><i class="fas fa-moon"></i> No festival pack active today</div>
                <div style="opacity:.9;font-size:.92rem;">Normal public / admin theme is showing. Use “Preview pack” below to force a look for testing.</div>
            <?php endif; ?>
        </div>

        <div class="card mb-4">
            <div class="card-header"><strong><i class="fas fa-sliders-h"></i> Global settings</strong></div>
            <div class="card-body">
                <form method="post" class="row g-3 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $_SESSION['csrf_token']); ?>">
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="fest_enabled" name="enabled" value="1" <?php echo $settings['enabled'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="fest_enabled">Enable festival auto-themes</label>
                        </div>
                        <small class="text-muted">When off, only your normal Public / Admin theme is used.</small>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Preview / force pack (optional)</label>
                        <select name="force_pack" class="form-select">
                            <option value="">— Auto by calendar date —</option>
                            <?php foreach ($packs as $key => $pack): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($settings['force_pack'] ?? '') === $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $pack['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Forces one pack site-wide until cleared (useful for design checks).</small>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="save_settings" value="1" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Save settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <h5 class="mb-3"><i class="fas fa-palette"></i> Theme packs (<?php echo count($packs); ?>)</h5>
        <div class="ft-grid mb-4">
            <?php foreach ($packs as $key => $pack):
                $isLive = $activePack && (($activePack['key'] ?? '') === $key);
                $grad = 'linear-gradient(135deg, ' . ($pack['primary'] ?? '#0a1628') . ', ' . ($pack['secondary'] ?? '#1a56db') . ')';
                ?>
                <div class="ft-card <?php echo $isLive ? 'is-live' : ''; ?>">
                    <div class="ft-preview" style="background: <?php echo htmlspecialchars((string) ($pack['cream'] ?? '#f8fafc')); ?>;">
                        <div class="ft-swatch" style="background: <?php echo htmlspecialchars($grad); ?>;">
                            <span style="background: <?php echo htmlspecialchars((string) ($pack['accent'] ?? '#f59e0b')); ?>;color:#111;padding:2px 8px;border-radius:999px;">
                                <?php echo htmlspecialchars((string) ($pack['motif'] ?? '')); ?>
                            </span>
                        </div>
                    </div>
                    <div class="ft-body">
                        <span class="ft-tag"><?php echo htmlspecialchars((string) ($pack['tag'] ?? 'Festival')); ?></span>
                        <?php if ($isLive): ?><span class="ft-tag" style="background:#fef3c7;color:#92400e;">Live</span><?php endif; ?>
                        <div class="fw-semibold"><?php echo htmlspecialchars((string) $pack['label']); ?></div>
                        <div class="text-muted small mb-2"><?php echo htmlspecialchars((string) ($pack['description'] ?? '')); ?></div>
                        <div class="small text-muted">
                            Ribbon · <?php echo !empty($pack['ribbon']) ? 'Yes' : 'No'; ?>
                            · Pattern: <?php echo htmlspecialchars((string) ($pack['pattern'] ?? 'none')); ?>
                            · Radius: <?php echo htmlspecialchars((string) ($pack['card_radius'] ?? '')); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <strong><i class="fas fa-calendar-alt"></i> Festival calendar — <?php echo (int) $year; ?></strong>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-secondary" href="?year=<?php echo (int) ($year - 1); ?>">Prev year</a>
                    <a class="btn btn-sm btn-outline-secondary" href="?year=<?php echo (int) ($year + 1); ?>">Next year</a>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $_SESSION['csrf_token']); ?>">
                        <button type="submit" name="reseed_year" value="1" class="btn btn-sm btn-outline-primary">Seed missing</button>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 cal-table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Festival</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>On</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($calendar)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No calendar rows for this year. Click “Seed missing”.</td></tr>
                        <?php else: foreach ($calendar as $row):
                            $pack = $packs[$row['pack_key']] ?? null;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($row['label'] ?: ($pack['label'] ?? $row['pack_key']))); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars((string) $row['pack_key']); ?>
                                        <?php if (!empty($pack['movable_hint'])): ?> · <?php echo htmlspecialchars((string) $pack['movable_hint']); ?><?php endif; ?>
                                    </div>
                                </td>
                                <td colspan="4">
                                    <form method="post" class="row g-2 align-items-center">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="row_id" value="<?php echo (int) $row['id']; ?>">
                                        <div class="col-auto">
                                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string) $row['start_date']); ?>" required>
                                        </div>
                                        <div class="col-auto">
                                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string) $row['end_date']); ?>" required>
                                        </div>
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="row_enabled" value="1" id="en_<?php echo (int) $row['id']; ?>" <?php echo !empty($row['is_enabled']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="en_<?php echo (int) $row['id']; ?>">Enabled</label>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" name="save_calendar_row" value="1" class="btn btn-sm btn-primary">Save</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
