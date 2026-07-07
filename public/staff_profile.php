<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/institute_branding.php';
require_once __DIR__ . '/../includes/staff_profile_helper.php';

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
            if (!empty($linkMeta['expires_at'])) {
                $linkExpiresAtLabel = date('g:i A', strtotime((string) $linkMeta['expires_at']));
            }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <style>
        body { background: #f1f5f9; }
        .page-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            color: #fff;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .form-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
        }
        .section-title {
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
            background: #e2e8f0;
            overflow: hidden;
        }
        .completion-bar-fill {
            height: 100%;
            background: #f59e0b;
            border-radius: 999px;
        }
        .link-timer {
            background: #fff7ed;
            border: 1px solid #fdba74;
            color: #9a3412;
            border-radius: 8px;
            padding: 0.85rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            box-shadow: 0 2px 8px rgba(154, 52, 18, 0.08);
        }
        .link-timer .timer-countdown {
            font-size: 1.15rem;
            font-weight: 700;
            color: #c2410c;
        }
        .link-timer.is-expired {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        .link-timer.is-expired .timer-countdown {
            color: #991b1b;
        }
    </style>
</head>
<body>
<div class="page-header">
    <div class="container">
        <div class="d-flex align-items-center gap-3">
            <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT" style="height:48px;background:#fff;border-radius:8px;padding:4px;">
            <div>
                <h1 class="h4 mb-1">NIELIT Centre Staff Profile</h1>
                <div class="small opacity-75">Fill your details for the official staff profile & PDF</div>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <?php if (!empty($pageError)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($pageError); ?></div>
    <?php elseif ($submitted && empty($error_message)): ?>
        <div class="alert alert-success">
            <h5 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Profile submitted successfully</h5>
            <p class="mb-0">Thank you, <strong><?php echo htmlspecialchars($staff['name']); ?></strong>. Your NIELIT Centre staff profile has been saved. Admin can download the PDF from the staff panel.</p>
        </div>
    <?php endif; ?>

    <?php if ($staff && (!$submitted || !empty($error_message))): ?>
        <div class="link-timer" id="publicProfileLinkTimer" data-expires-ts="<?php echo (int) $linkExpiresTs; ?>">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <i class="fas fa-clock me-2"></i>
                    <span>This link expires in</span>
                    <strong class="timer-countdown ms-1" data-timer-text><?php echo $linkExpiresTs > 0 ? '--:--' : 'Soon'; ?></strong>
                </div>
                <?php if ($linkExpiresAtLabel !== ''): ?>
                <div class="small opacity-75">
                    Valid until <?php echo htmlspecialchars($linkExpiresAtLabel); ?> IST
                </div>
                <?php endif; ?>
            </div>
            <div class="small mt-1 opacity-75">Complete and submit your profile before the link expires.</div>
        </div>
        <?php if ($linkExpiresTs <= 0): ?>
        <div class="alert alert-warning py-2">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Expiry time could not be loaded. If the form stops working, ask admin to send a new link.
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($staff): ?>
        <?php if (!$submitted || !empty($error_message)): ?>
        <div class="form-card" id="publicProfileFormCard">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1"><?php echo htmlspecialchars($staff['name']); ?></h2>
                    <?php if (!empty($staff['designation']) || !empty($staff['department'])): ?>
                        <div class="text-muted small">
                            <?php echo htmlspecialchars(trim($staff['designation'] . ($staff['department'] ? ' · ' . $staff['department'] : ''))); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($staff['staff_category'])): ?>
                        <span class="badge bg-primary mt-2"><?php echo htmlspecialchars($staff['staff_category']); ?></span>
                    <?php endif; ?>
                </div>
                <div style="min-width:200px;">
                    <div class="small text-muted mb-1">Profile completion: <strong><?php echo $completion; ?>%</strong></div>
                    <div class="completion-bar"><div class="completion-bar-fill" style="width:<?php echo $completion; ?>%;"></div></div>
                </div>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message && !$submitted): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="publicStaffProfileForm">
            <input type="hidden" name="action" value="save_profile">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="form-card">
                <div class="section-title"><i class="fas fa-camera me-2"></i>Passport-size Photo (for PDF)</div>
                <div class="row g-3 align-items-center">
                    <div class="col-md-3">
                        <div class="border rounded p-2 text-center bg-light" style="min-height:140px;">
                            <?php if (!empty($staff['profile_photo']) && is_file(__DIR__ . '/../' . ltrim($staff['profile_photo'], '/'))): ?>
                                <img src="<?php echo APP_URL . '/' . htmlspecialchars(ltrim($staff['profile_photo'], '/')); ?>" alt="Photo" class="img-fluid" style="max-height:120px;">
                            <?php else: ?>
                                <div class="text-muted py-4"><i class="fas fa-user fa-2x"></i><br><small>Photo</small></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label" for="profile_photo">Upload passport photo (JPG/PNG, max 5 MB)</label>
                        <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/jpg">
                    </div>
                </div>
            </div>

            <?php foreach ($fieldGroups as $group): ?>
            <div class="form-card">
                <div class="section-title"><i class="fas <?php echo htmlspecialchars($group['icon']); ?> me-2"></i><?php echo htmlspecialchars($group['title']); ?></div>
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
                            <input type="<?php echo htmlspecialchars($field['type']); ?>" class="form-control" id="field_<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo $value; ?>" <?php echo $required ? 'required' : ''; ?>>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane me-2"></i>Submit Profile
                </button>
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
});
</script>
</body>
</html>
