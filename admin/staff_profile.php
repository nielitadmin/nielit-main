<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_once __DIR__ . '/../includes/staff_profile_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id'])) {
    if (!init_admin_session($_SESSION['admin'])) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
}

refresh_session_permissions();

$admin_role = $_SESSION['admin_role'] ?? '';
if (!in_array($admin_role, ['master_admin'], true)) {
    header('Location: dashboard.php');
    exit();
}

ensureStaffProfileSchema($conn);
$active_theme = loadActiveTheme($conn);

$facultyId = (int) ($_GET['id'] ?? 0);
if ($facultyId <= 0) {
    header('Location: manage_faculty.php');
    exit();
}

$staff = loadStaffProfileById($conn, $facultyId);
if (!$staff) {
    $_SESSION['error'] = 'Staff member not found.';
    header('Location: manage_faculty.php');
    exit();
}

$fieldGroups = staffProfileFieldGroups($conn);
$completion = staffProfileCompletionPercent($staff);
$shareLink = buildStaffProfileShareLink($conn, $facultyId);
$profileToken = $shareLink['token'];
$profilePublicUrl = $shareLink['url'];
$profileLinkError = $shareLink['success'] ? '' : ($shareLink['message'] ?? 'Could not create profile link.');
$profileExpiresTs = (int) ($shareLink['expires_ts'] ?? 0);
$profileSecondsRemaining = (int) ($shareLink['seconds_remaining'] ?? 0);
$profileIsExpired = !empty($shareLink['is_expired']);
$profileTimerExpiresTs = ($profilePublicUrl !== '' && $profileSecondsRemaining > 0)
    ? time() + $profileSecondsRemaining
    : 0;
$success_message = null;
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regenerate_profile_link') {
    $regen = regenerateStaffProfileToken($conn, $facultyId);
    if ($regen['success']) {
        $shareLink = [
            'success' => true,
            'token' => $regen['token'],
            'url' => $regen['url'],
            'expires_ts' => (int) ($regen['expires_ts'] ?? 0),
            'is_expired' => false,
            'message' => '',
        ];
        $profileToken = $regen['token'];
        $profilePublicUrl = $regen['url'];
        $profileExpiresTs = (int) ($regen['expires_ts'] ?? 0);
        $profileSecondsRemaining = (int) ($regen['seconds_remaining'] ?? staffProfileLinkTtlSeconds());
        $profileTimerExpiresTs = time() + max(0, $profileSecondsRemaining);
        $profileIsExpired = false;
        $profileLinkError = '';
        $success_message = 'New profile link generated. It is valid for 1 hour — share it with the staff member now.';
    } else {
        $error_message = $regen['message'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile') {
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
    } elseif (!$result['success']) {
        $error_message = $result['message'];
    }
}

function staffFieldValue(array $staff, string $key, string $col): string
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
    <title>NIELIT Centre Staff Profile - <?php echo htmlspecialchars($staff['name']); ?></title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css?v=<?php echo time(); ?>">
    <style>
        .profile-hero {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            color: #fff;
            border-radius: 12px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.5rem;
        }
        .profile-section {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
        }
        .profile-section-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1e3a5f;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }
        .completion-bar {
            height: 8px;
            border-radius: 999px;
            background: rgba(255,255,255,0.25);
            overflow: hidden;
        }
        .completion-bar-fill {
            height: 100%;
            background: #fbbf24;
            border-radius: 999px;
        }
        .form-label { font-weight: 600; color: #334155; }
        .sticky-actions {
            position: sticky;
            bottom: 0;
            background: rgba(255,255,255,0.95);
            border-top: 1px solid #e2e8f0;
            padding: 1rem 0;
            margin-top: 1rem;
            z-index: 10;
        }
        .profile-link-timer {
            background: #eff6ff;
            border: 1px solid #93c5fd;
            color: #1e40af;
            border-radius: 8px;
            padding: 0.65rem 0.85rem;
            font-size: 0.9rem;
        }
        .profile-link-timer.is-expired {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-id-card"></i> NIELIT Centre Staff Profile</h4>
                <small>Non - Scientific and Technical staffs — detailed profile & PDF</small>
            </div>
            <div class="topbar-right">
                <a href="manage_faculty.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Staff List
                </a>
            </div>
        </div>

        <div class="admin-main">
            <div class="content-card mb-3">
                <div class="card-body">
                    <h6 class="mb-2"><i class="fas fa-link text-primary"></i> Share profile link with staff</h6>
                    <p class="text-muted small mb-2">Each link is valid for <strong>1 hour</strong> only. Send it by WhatsApp or email so the staff member can fill their profile without admin login.</p>
                    <?php if ($profilePublicUrl !== ''): ?>
                    <div id="profileLinkActiveBlock">
                        <div class="profile-link-timer mb-2" id="adminProfileLinkTimer" data-expires-ts="<?php echo (int) $profileTimerExpiresTs; ?>">
                            <i class="fas fa-clock me-1"></i>
                            Link expires in <strong data-timer-text>--:--</strong>
                        </div>
                        <div class="input-group mb-2" id="profileLinkInputGroup">
                            <input type="text" class="form-control" id="staffProfilePublicUrl" readonly value="<?php echo htmlspecialchars($profilePublicUrl); ?>">
                            <button type="button" class="btn btn-outline-primary" id="copyProfileLinkBtn" onclick="copyStaffProfileLink()"><i class="fas fa-copy"></i> Copy</button>
                        </div>
                    </div>
                    <div class="text-muted small mb-2 d-none" id="profileLinkExpiredHint">
                        <i class="fas fa-hourglass-end text-danger me-1"></i> Link expired — click <strong>Generate link</strong> below for a new 1-hour link.
                    </div>
                    <?php elseif ($profileIsExpired): ?>
                    <div class="text-muted small mb-2" id="profileLinkStatusHint">
                        <i class="fas fa-hourglass-end text-danger me-1"></i> Previous link expired — generate a new one below.
                    </div>
                    <?php elseif ($profileLinkError !== ''): ?>
                    <div class="text-muted small mb-2" id="profileLinkStatusHint">
                        <i class="fas fa-info-circle me-1"></i> <?php echo htmlspecialchars($profileLinkError); ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST" class="d-inline" id="regenerateProfileLinkForm">
                        <input type="hidden" name="action" value="regenerate_profile_link">
                        <button type="submit" class="btn btn-sm btn-outline-secondary" id="regenerateProfileLinkBtn">
                            <i class="fas fa-sync"></i> <span id="regenerateProfileLinkBtnText"><?php echo $profilePublicUrl !== '' ? 'Regenerate link' : 'Generate link'; ?></span>
                        </button>
                    </form>
                </div>
            </div>

            <form method="POST" id="staffProfileForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_profile">

            <div class="profile-hero">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <h3 class="mb-1"><?php echo htmlspecialchars($staff['name']); ?></h3>
                        <div class="opacity-75">
                            <?php echo htmlspecialchars($staff['designation'] ?: 'Staff Member'); ?>
                            <?php if (!empty($staff['department'])): ?>
                                · <?php echo htmlspecialchars($staff['department']); ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($staff['staff_category'])): ?>
                            <span class="badge bg-light text-dark mt-2"><?php echo htmlspecialchars($staff['staff_category']); ?></span>
                        <?php endif; ?>
                        <div class="mt-3 d-flex flex-wrap gap-2">
                            <a href="generate_staff_profile_pdf.php?id=<?php echo $facultyId; ?>" target="_blank" class="btn btn-warning btn-sm">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </a>
                        </div>
                    </div>
                    <div style="min-width: 260px;">
                        <div class="border border-2 border-light rounded p-2 text-center bg-white bg-opacity-10" style="min-height: 150px;">
                            <?php if (!empty($staff['profile_photo']) && is_file(__DIR__ . '/../' . ltrim($staff['profile_photo'], '/'))): ?>
                                <img src="<?php echo APP_URL . '/' . htmlspecialchars(ltrim($staff['profile_photo'], '/')); ?>" alt="Staff photo" class="img-fluid rounded mb-2" style="max-height: 120px;">
                                <div class="small opacity-75">Photo saved — appears on PDF</div>
                            <?php else: ?>
                                <div class="text-white-50 py-3">
                                    <i class="fas fa-camera fa-2x mb-2"></i><br>
                                    <strong>Upload Photo</strong><br>
                                    <small>Required for PDF (top-right box)</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <label class="form-label text-white mt-2 mb-1" for="profile_photo"><i class="fas fa-upload"></i> Upload passport photo</label>
                        <input type="file" class="form-control form-control-sm" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/jpg">
                        <div class="small opacity-75 mt-1">JPG/PNG, max 5 MB. Save profile after selecting.</div>
                        <div class="d-flex justify-content-between small mt-2 mb-1">
                            <span>Profile completion</span>
                            <strong><?php echo $completion; ?>%</strong>
                        </div>
                        <div class="completion-bar">
                            <div class="completion-bar-fill" style="width: <?php echo $completion; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>

                <?php foreach ($fieldGroups as $groupKey => $group): ?>
                <div class="profile-section">
                    <div class="profile-section-title">
                        <i class="fas <?php echo htmlspecialchars($group['icon']); ?> me-2"></i>
                        <?php echo htmlspecialchars($group['title']); ?>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($group['fields'] as $key => $field):
                            $col = $field['col'];
                            $value = staffFieldValue($staff, $key, $col);
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
                                        <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $value === $opt ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($opt); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($field['type'] === 'textarea'): ?>
                                <textarea class="form-control" id="field_<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" rows="<?php echo (int) ($field['rows'] ?? 3); ?>" <?php echo $required ? 'required' : ''; ?> placeholder="<?php echo htmlspecialchars($field['placeholder'] ?? ''); ?>"><?php echo $value; ?></textarea>
                            <?php else: ?>
                                <input
                                    type="<?php echo htmlspecialchars($field['type']); ?>"
                                    class="form-control"
                                    id="field_<?php echo htmlspecialchars($key); ?>"
                                    name="<?php echo htmlspecialchars($key); ?>"
                                    value="<?php echo $value; ?>"
                                    <?php echo $required ? 'required' : ''; ?>
                                    <?php if (!empty($field['placeholder'])): ?>placeholder="<?php echo htmlspecialchars($field['placeholder']); ?>"<?php endif; ?>
                                    <?php if (!empty($field['step'])): ?>step="<?php echo htmlspecialchars($field['step']); ?>"<?php endif; ?>
                                    <?php if (isset($field['min'])): ?>min="<?php echo htmlspecialchars($field['min']); ?>"<?php endif; ?>
                                >
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="sticky-actions d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span class="text-muted small">
                        <i class="fas fa-info-circle"></i> Save profile before downloading PDF for latest data.
                    </span>
                    <div class="d-flex gap-2">
                        <a href="manage_faculty.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Profile
                        </button>
                        <a href="generate_staff_profile_pdf.php?id=<?php echo $facultyId; ?>" target="_blank" class="btn btn-danger">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($success_message): ?>
    toast.success(<?php echo json_encode($success_message); ?>);
    <?php endif; ?>
    <?php if ($error_message): ?>
    toast.error(<?php echo json_encode($error_message); ?>);
    <?php endif; ?>
    <?php if ($profileIsExpired && $profilePublicUrl === ''): ?>
    toast.warning('Profile link expired. Click Generate link to create a new one (valid for 1 hour).', 6000);
    <?php elseif (!$shareLink['success'] && !$profileIsExpired && $profilePublicUrl === '' && $profileLinkError !== ''): ?>
    toast.info(<?php echo json_encode($profileLinkError); ?>, 5000);
    <?php endif; ?>

    initStaffProfileLinkTimer(document.getElementById('adminProfileLinkTimer'), showProfileLinkExpiredState);

    const regenerateForm = document.getElementById('regenerateProfileLinkForm');
    if (regenerateForm) {
        let allowRegenerateSubmit = false;
        let hasActiveLink = <?php echo $profilePublicUrl !== '' ? 'true' : 'false'; ?>;

        regenerateForm.addEventListener('submit', function (e) {
            if (allowRegenerateSubmit) {
                return;
            }
            e.preventDefault();

            showConfirm({
                title: hasActiveLink ? 'Regenerate Profile Link?' : 'Generate Profile Link?',
                message: hasActiveLink
                    ? 'The current link will stop working immediately. The new link will be valid for <strong>1 hour</strong>.'
                    : 'Generate a profile link for this staff member? It will be valid for <strong>1 hour</strong>.',
                type: 'warning',
                confirmText: hasActiveLink ? 'Regenerate Link' : 'Generate Link',
                cancelText: 'Cancel'
            }).then(function (confirmed) {
                if (!confirmed) {
                    toast.info('Link generation cancelled.');
                    return;
                }
                toast.loading('Generating profile link...');
                allowRegenerateSubmit = true;
                regenerateForm.submit();
            });
        });

        window.setProfileLinkActiveState = function (active) {
            hasActiveLink = active;
        };
    }
});

function showProfileLinkExpiredState() {
    const activeBlock = document.getElementById('profileLinkActiveBlock');
    const expiredHint = document.getElementById('profileLinkExpiredHint');
    const btnText = document.getElementById('regenerateProfileLinkBtnText');

    if (activeBlock) {
        activeBlock.classList.add('d-none');
    }
    if (expiredHint) {
        expiredHint.classList.remove('d-none');
    }
    if (btnText) {
        btnText.textContent = 'Generate link';
    }
    if (typeof window.setProfileLinkActiveState === 'function') {
        window.setProfileLinkActiveState(false);
    }
    toast.warning('Profile link has expired. Click Generate link to create a new one.', 6000);
}

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
    if (!expiresTs || !textEl) return;

    let expiredHandled = false;

    function tick() {
        const left = Math.max(0, expiresTs - Math.floor(Date.now() / 1000));
        if (left <= 0) {
            container.classList.add('is-expired');
            textEl.textContent = 'Expired';
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

function copyStaffProfileLink() {
    const input = document.getElementById('staffProfilePublicUrl');
    const url = input ? input.value.trim() : '';
    if (!url) {
        toast.error('No profile link to copy. Click Generate link first.');
        return;
    }

    const copyFallback = function () {
        if (input) {
            input.focus();
            input.select();
            input.setSelectionRange(0, input.value.length);
        }
        const tmp = document.createElement('textarea');
        tmp.value = url;
        tmp.setAttribute('readonly', '');
        tmp.style.position = 'fixed';
        tmp.style.left = '-9999px';
        document.body.appendChild(tmp);
        tmp.select();
        tmp.setSelectionRange(0, tmp.value.length);
        let copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (e) {
            copied = false;
        }
        document.body.removeChild(tmp);
        if (copied) {
            toast.success('Profile link copied to clipboard.');
        } else {
            window.prompt('Copy this profile link:', url);
        }
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(function () {
            toast.success('Profile link copied to clipboard.');
        }).catch(copyFallback);
    } else {
        copyFallback();
    }
}
</script>
</body>
</html>
