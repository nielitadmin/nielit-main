<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/institute_branding.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/staff_profile_helper.php';

$active_theme = loadActiveTheme($conn);

ensureStaffProfileSchema($conn);

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$tokenAccess = ['status' => 'invalid', 'staff' => null];
$pageError = null;
$linkExpiresTs = 0;
$linkSecondsRemaining = 0;
$linkExpiresAtLabel = '';

if ($token === '') {
    http_response_code(400);
    $pageError = 'Invalid or missing profile link. Please open the full link sent by admin (it includes ?token= at the end).';
    $staff = null;
} else {
    $tokenAccess = validateStaffProfileTokenAccess($conn, $token);
    if ($tokenAccess['status'] === 'valid') {
        $staff = $tokenAccess['staff'];
        $linkMeta = getStaffProfileLinkMeta($conn, (int) $staff['id']);
        $linkSecondsRemaining = (int) ($linkMeta['seconds_remaining'] ?? 0);
        if ($linkSecondsRemaining <= 0) {
            $linkSecondsRemaining = staffProfileLinkSecondsRemaining($staff);
        }
        if ($linkSecondsRemaining > 0) {
            $linkExpiresTs = time() + $linkSecondsRemaining;
            $expiresTsForLabel = (int) ($linkMeta['expires_ts'] ?? 0);
            if ($expiresTsForLabel <= 0) {
                $expiresTsForLabel = $linkExpiresTs;
            }
            $linkExpiresAtLabel = formatStaffProfileExpiryIst($expiresTsForLabel);
        }
    } else {
        $staff = null;
        http_response_code($tokenAccess['status'] === 'expired' ? 410 : 404);
        $pageError = $tokenAccess['status'] === 'expired'
            ? 'This profile link has expired after 1 hour. Please ask admin to generate a new link.'
            : 'This profile link is invalid. Please ask admin for a new link.';
    }
}

$facultyId = $staff ? (int) $staff['id'] : 0;
$fieldGroups = $staff ? staffProfilePublicFieldGroups($conn) : [];
$completion = $staff ? staffProfileCompletionPercent($staff) : 0;
$success_message = null;
$error_message = null;
$submitted = false;

if ($staff && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile') {
    $tokenAccess = validateStaffProfileTokenAccess($conn, $token);
    if ($tokenAccess['status'] !== 'valid') {
        $staff = null;
        $pageError = $tokenAccess['status'] === 'expired'
            ? 'This profile link has expired. Please ask admin to generate a new link.'
            : 'This profile link is no longer valid. Please ask admin for a new link.';
    } elseif (trim($_POST['token'] ?? '') !== $token) {
        $error_message = 'Invalid form token. Please refresh and try again.';
    } else {
        $result = saveStaffProfile($conn, $facultyId, $_POST);
        if ($result['success'] && !empty($_FILES['profile_photo']['name'])) {
            $photoResult = uploadStaffProfilePhoto($conn, $facultyId, $_FILES['profile_photo']);
            if (!$photoResult['success']) {
                $error_message = $photoResult['message'];
            } elseif ($photoResult['message'] !== '') {
                $success_message = $result['message'] . ' ' . $photoResult['message'];
            }
        }
        if ($result['success'] && empty($error_message)) {
            if (empty($success_message)) {
                $success_message = $result['message'];
            }
            $staff = loadStaffProfileById($conn, $facultyId);
            $completion = staffProfileCompletionPercent($staff);
            $submitted = true;
        } elseif (!$result['success']) {
            $error_message = $result['message'];
        }
    }
}

function publicStaffFieldValue(array $staff, string $key, string $col): string
{
    if (isset($_POST[$key])) {
        return htmlspecialchars(trim((string) $_POST[$key]), ENT_QUOTES, 'UTF-8');
    }
    $val = $staff[$col] ?? '';
    if ($col === 'date_of_joining' && !empty($val)) {
        return htmlspecialchars(date('Y-m-d', strtotime((string) $val)), ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars(trim((string) $val), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIELIT Centre Staff Profile Form</title>
    <?php injectThemeCSS($active_theme); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/public-theme.css">
    <link rel="icon" href="<?php echo htmlspecialchars(getThemeFaviconUrl($active_theme), ENT_QUOTES, 'UTF-8'); ?>" type="image/x-icon">
    <style>
        :root {
            --nielit-navy: #0f2744;
            --nielit-blue: #1e4a7a;
            --nielit-sky: #3b82f6;
            --nielit-gold: #f59e0b;
            --surface: #ffffff;
            --surface-muted: #f8fafc;
            --border: #e2e8f0;
            --text: #0f172a;
            --text-muted: #64748b;
            --radius: 16px;
            --shadow: 0 10px 40px rgba(15, 39, 68, 0.08);
            --shadow-sm: 0 4px 14px rgba(15, 39, 68, 0.06);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(180deg, #eef4fb 0%, #f8fafc 35%, #f1f5f9 100%);
            color: var(--text);
            min-height: 100vh;
            margin: 0;
        }

        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; }

        .page-shell { max-width: 920px; margin: 0 auto; padding: 0 1rem 6rem; }

        .page-header {
            background: linear-gradient(135deg, var(--nielit-navy) 0%, var(--nielit-blue) 55%, #2563eb 100%);
            color: #fff;
            padding: 1.75rem 0 2.25rem;
            margin-bottom: -1.5rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 85% 15%, rgba(255,255,255,0.12) 0%, transparent 45%);
            pointer-events: none;
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }

        .brand-logo {
            height: 52px;
            background: #fff;
            border-radius: 12px;
            padding: 6px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }

        .brand-title { font-size: 1.35rem; font-weight: 700; margin: 0; }
        .brand-subtitle { font-size: 0.875rem; opacity: 0.88; margin: 0.15rem 0 0; }

        .link-timer {
            background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
            border: 1px solid #fdba74;
            border-radius: var(--radius);
            padding: 1rem 1.15rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-sm);
        }

        .link-timer .timer-countdown {
            font-family: 'Poppins', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: #c2410c;
        }

        .link-timer.is-expired {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-color: #fca5a5;
            color: #991b1b;
        }

        .profile-hero-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,0.8);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            position: relative;
            overflow: hidden;
        }

        .profile-hero-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--nielit-gold), var(--nielit-sky));
        }

        .staff-name { font-size: 1.35rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--nielit-navy); }
        .staff-meta { color: var(--text-muted); font-size: 0.9rem; }

        .category-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            padding: 0.3rem 0.75rem;
            font-size: 0.78rem;
            font-weight: 600;
            margin-top: 0.65rem;
        }

        .completion-wrap {
            min-width: 180px;
            text-align: right;
        }

        .completion-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            font-weight: 600;
        }

        .completion-value {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--nielit-blue);
            line-height: 1.1;
        }

        .completion-bar {
            height: 8px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .completion-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--nielit-gold), #fbbf24);
            border-radius: 999px;
            transition: width 0.4s ease;
        }

        .form-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.35rem 1.5rem;
            margin-bottom: 1.15rem;
            box-shadow: var(--shadow-sm);
            transition: box-shadow 0.2s ease;
        }

        .form-card:hover { box-shadow: var(--shadow); }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-family: 'Poppins', sans-serif;
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--nielit-navy);
            margin-bottom: 1.15rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .section-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            color: var(--nielit-blue);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
        }

        .form-label {
            font-size: 0.84rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.4rem;
        }

        .form-control, .form-select {
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            font-size: 0.92rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--nielit-sky);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
        }

        textarea.form-control { min-height: 100px; resize: vertical; }

        .photo-upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            background: var(--surface-muted);
            padding: 1.25rem;
            text-align: center;
            transition: border-color 0.2s, background 0.2s;
        }

        .photo-upload-zone:hover {
            border-color: var(--nielit-sky);
            background: #f0f9ff;
        }

        .photo-preview-box {
            width: 130px;
            height: 160px;
            margin: 0 auto 1rem;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }

        .photo-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-placeholder {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .photo-placeholder i { font-size: 2rem; margin-bottom: 0.35rem; display: block; opacity: 0.5; }

        .file-input-wrap input[type="file"] {
            font-size: 0.85rem;
        }

        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.15rem;
            box-shadow: var(--shadow-sm);
        }

        .alert-modern.alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert-modern.alert-success {
            background: #f0fdf4;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .alert-modern.alert-warning {
            background: #fffbeb;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .success-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .success-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #16a34a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .sticky-submit {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            border-top: 1px solid var(--border);
            padding: 0.85rem 1rem;
            z-index: 100;
            box-shadow: 0 -8px 30px rgba(15, 39, 68, 0.08);
        }

        .sticky-submit-inner {
            max-width: 920px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .sticky-hint {
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .btn-submit-modern {
            background: linear-gradient(135deg, var(--nielit-blue) 0%, #2563eb 100%);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.75rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-submit-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.4);
            background: linear-gradient(135deg, #1a3f6b 0%, #1d4ed8 100%);
        }

        .btn-submit-modern:disabled {
            opacity: 0.65;
            transform: none;
            box-shadow: none;
        }

        @media (max-width: 576px) {
            .completion-wrap { text-align: left; margin-top: 1rem; }
            .sticky-submit-inner { justify-content: center; text-align: center; }
            .sticky-hint { display: none; }
        }
    </style>
</head>
<body class="public-site">
<div class="page-header">
    <div class="container page-shell px-3 px-md-4">
        <div class="brand-row">
            <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT" class="brand-logo">
            <div>
                <h1 class="brand-title">NIELIT Centre Staff Profile</h1>
                <p class="brand-subtitle">Official profile form for PDF &amp; records</p>
            </div>
        </div>
    </div>
</div>

<div class="container page-shell">
    <?php if (!empty($pageError)): ?>
        <div class="alert alert-modern alert-danger mt-4">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($pageError); ?>
        </div>
    <?php elseif ($submitted && empty($error_message)): ?>
        <div class="success-card mt-4">
            <div class="success-icon"><i class="fas fa-check"></i></div>
            <h2 class="h4 mb-2">Profile submitted successfully</h2>
            <p class="text-muted mb-0">Thank you, <strong><?php echo htmlspecialchars($staff['name']); ?></strong>. Your NIELIT Centre staff profile has been saved. Admin can download the PDF from the staff panel.</p>
        </div>
    <?php endif; ?>

    <?php if ($staff && (!$submitted || !empty($error_message))): ?>
        <div class="link-timer mt-4" id="publicProfileLinkTimer" data-expires-ts="<?php echo (int) $linkExpiresTs; ?>">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <i class="fas fa-clock me-2"></i>
                    <span>Link expires in</span>
                    <strong class="timer-countdown ms-1" data-timer-text><?php echo $linkExpiresTs > 0 ? '--:--' : 'Soon'; ?></strong>
                </div>
                <?php if ($linkExpiresAtLabel !== ''): ?>
                <div class="small opacity-75">
                    Valid until <?php echo htmlspecialchars($linkExpiresAtLabel); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="small mt-1 opacity-75">Please complete and submit before the link expires.</div>
        </div>
        <?php if ($linkExpiresTs <= 0): ?>
        <div class="alert alert-modern alert-warning py-2">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Expiry time could not be loaded. If the form stops working, ask admin to send a new link.
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($staff): ?>
        <?php if (!$submitted || !empty($error_message)): ?>

        <div class="profile-hero-card" id="publicProfileFormCard">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h2 class="staff-name"><?php echo htmlspecialchars($staff['name']); ?></h2>
                    <?php if (!empty($staff['designation']) || !empty($staff['department'])): ?>
                        <div class="staff-meta">
                            <?php echo htmlspecialchars(trim($staff['designation'] . ($staff['department'] ? ' · ' . $staff['department'] : ''))); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($staff['staff_category'])): ?>
                        <span class="category-pill"><i class="fas fa-id-badge"></i><?php echo htmlspecialchars($staff['staff_category']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="completion-wrap">
                    <div class="completion-label">Profile completion</div>
                    <div class="completion-value"><?php echo $completion; ?>%</div>
                    <div class="completion-bar"><div class="completion-bar-fill" style="width:<?php echo $completion; ?>%;"></div></div>
                </div>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-modern alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message && !$submitted): ?>
            <div class="alert alert-modern alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="publicStaffProfileForm">
            <input type="hidden" name="action" value="save_profile">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="form-card">
                <div class="section-title">
                    <span class="section-icon"><i class="fas fa-camera"></i></span>
                    Passport-size Photo
                </div>
                <div class="row g-4 align-items-center">
                    <div class="col-md-4">
                        <div class="photo-upload-zone">
                            <div class="photo-preview-box" id="photoPreviewBox">
                                <?php if (!empty($staff['profile_photo']) && is_file(__DIR__ . '/../' . ltrim($staff['profile_photo'], '/'))): ?>
                                    <img src="<?php echo APP_URL . '/' . htmlspecialchars(ltrim($staff['profile_photo'], '/')); ?>" alt="Photo" id="photoPreviewImg">
                                <?php else: ?>
                                    <div class="photo-placeholder" id="photoPlaceholder">
                                        <i class="fas fa-user"></i>
                                        <span>Photo preview</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="file-input-wrap">
                                <label class="form-label" for="profile_photo">Upload photo (JPG/PNG, max 5 MB)</label>
                                <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/jpg">
                            </div>
                            <div class="small text-muted mt-2">Used on your official PDF profile</div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid var(--border);">
                            <h6 class="fw-semibold mb-2"><i class="fas fa-lightbulb text-warning me-1"></i> Photo tips</h6>
                            <ul class="small text-muted mb-0 ps-3">
                                <li>Use a recent passport-size photo with plain background</li>
                                <li>Face should be clearly visible, formal attire preferred</li>
                                <li>Image will appear on the top-right of your PDF profile</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <?php foreach ($fieldGroups as $group): ?>
            <div class="form-card">
                <div class="section-title">
                    <span class="section-icon"><i class="fas <?php echo htmlspecialchars($group['icon']); ?>"></i></span>
                    <?php echo htmlspecialchars($group['title']); ?>
                </div>
                <div class="row g-3">
                    <?php foreach ($group['fields'] as $key => $field):
                        $col = $field['col'];
                        $value = publicStaffFieldValue($staff, $key, $col);
                        $required = !empty($field['required']);
                        $colClass = ($field['type'] === 'textarea') ? 'col-12' : 'col-md-6';
                    ?>
                    <div class="<?php echo $colClass; ?>">
                        <label class="form-label" for="field_<?php echo htmlspecialchars($key); ?>">
                            <?php echo htmlspecialchars($field['label']); ?>
                            <?php if ($required): ?><span class="text-danger">*</span><?php endif; ?>
                        </label>
                        <?php if ($field['type'] === 'select'): ?>
                            <select class="form-select" id="field_<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" <?php echo $required ? 'required' : ''; ?>>
                                <option value=""><?php echo htmlspecialchars($field['placeholder'] ?? 'Select'); ?></option>
                                <?php foreach ($field['options'] as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $value === $opt ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($field['type'] === 'textarea'): ?>
                            <textarea class="form-control" id="field_<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" rows="<?php echo (int) ($field['rows'] ?? 3); ?>" <?php echo $required ? 'required' : ''; ?> placeholder="<?php echo htmlspecialchars($field['placeholder'] ?? ''); ?>"><?php echo $value; ?></textarea>
                        <?php else: ?>
                            <input type="<?php echo htmlspecialchars($field['type']); ?>" class="form-control" id="field_<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo $value; ?>" <?php echo $required ? 'required' : ''; ?> placeholder="<?php echo htmlspecialchars($field['placeholder'] ?? ''); ?>">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="sticky-submit">
                <div class="sticky-submit-inner">
                    <div class="sticky-hint"><i class="fas fa-shield-alt me-1"></i> Your data is saved securely with NIELIT Bhubaneswar</div>
                    <button type="submit" class="btn btn-primary btn-submit-modern btn-lg" id="submitProfileBtn">
                        <i class="fas fa-paper-plane me-2"></i>Submit Profile
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
function formatProfileLinkCountdown(totalSeconds) {
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    if (h > 0) {
        return h + 'h ' + String(m).padStart(2, '0') + 'm ' + String(s).padStart(2, '0') + 's';
    }
    return String(m).padStart(2, '0') + 'm ' + String(s).padStart(2, '0') + 's';
}

function initStaffProfileLinkTimer(container, onExpire) {
    if (!container) return;
    const expiresTs = parseInt(container.getAttribute('data-expires-ts') || '0', 10);
    const textEl = container.querySelector('[data-timer-text]');
    if (!textEl) return;

    if (!expiresTs) {
        textEl.textContent = '1 hour from open';
        return;
    }

    let expiredHandled = false;

    function tick() {
        const left = Math.max(0, expiresTs - Math.floor(Date.now() / 1000));
        if (left <= 0) {
            container.classList.add('is-expired');
            container.innerHTML = '<i class="fas fa-hourglass-end me-2"></i><strong>Link expired.</strong> Please ask NIELIT admin to generate a new profile link for you.';
            if (!expiredHandled) {
                expiredHandled = true;
                if (onExpire) onExpire();
            }
            return true;
        }
        textEl.textContent = formatProfileLinkCountdown(left);
        return false;
    }

    if (tick()) return;
    const interval = setInterval(function () {
        if (tick()) clearInterval(interval);
    }, 1000);
}

document.addEventListener('DOMContentLoaded', function () {
    initStaffProfileLinkTimer(document.getElementById('publicProfileLinkTimer'), function () {
        const form = document.getElementById('publicStaffProfileForm');
        if (form) {
            form.querySelectorAll('input, select, textarea, button').forEach(function (el) {
                el.disabled = true;
            });
        }
    });

    const photoInput = document.getElementById('profile_photo');
    if (photoInput) {
        photoInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function (e) {
                const box = document.getElementById('photoPreviewBox');
                if (!box) return;
                box.innerHTML = '<img src="' + e.target.result + '" alt="Preview" id="photoPreviewImg" style="width:100%;height:100%;object-fit:cover;">';
            };
            reader.readAsDataURL(file);
        });
    }

    const form = document.getElementById('publicStaffProfileForm');
    const submitBtn = document.getElementById('submitProfileBtn');
    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
        });
    }
});
</script>
</body>
</html>
