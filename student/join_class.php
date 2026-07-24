<?php
/**
 * On-site live classroom — join via site-generated link.
 * Video room is hosted inside this page (Jitsi).
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/online_class_helper.php';

$token = trim((string) ($_GET['t'] ?? ''));
$isAdmin = isset($_SESSION['admin']);
$isStudent = isset($_SESSION['student_id']);

if (!$isAdmin && !$isStudent) {
    if ($token !== '') {
        $_SESSION['oc_join_redirect_token'] = $token;
    }
    header('Location: login.php');
    exit;
}

ensureOnlineClassesTable($conn);
$class = $token !== '' ? getOnlineClassByJoinToken($conn, $token) : null;

if (!$class) {
    http_response_code(404);
    $errorTitle = 'Class not found';
    $errorMsg = 'This join link is invalid or the class was removed.';
    include __DIR__ . '/includes/join_class_error.php';
    exit;
}

if ($isStudent && !$isAdmin) {
    $studentId = (string) $_SESSION['student_id'];
    $activeRecordId = isset($_SESSION['active_record_id']) ? (int) $_SESSION['active_record_id'] : null;
    if (!onlineClassStudentMayAccess($conn, $class, $studentId, $activeRecordId)) {
        http_response_code(403);
        $errorTitle = 'Access denied';
        $errorMsg = 'This class is for a different batch. Contact your coordinator if you believe this is an error.';
        include __DIR__ . '/includes/join_class_error.php';
        exit;
    }
}

$gate = onlineClassCanJoinNow($class);
$displayName = $isAdmin
    ? (string) ($_SESSION['admin'] ?? 'Admin')
    : (string) ($_SESSION['student_name'] ?? 'Student');
$roleLabel = $isAdmin ? 'Host / Admin' : 'Student';
$roomName = (string) ($class['room_name'] ?? onlineClassRoomName((string) $class['join_token']));
$joinUrl = (string) ($class['join_url'] ?? onlineClassSiteJoinUrl((string) $class['join_token']));
$when = !empty($class['scheduled_at']) ? date('d M Y, h:i A', strtotime($class['scheduled_at'])) : '';
$status = $class['display_status'] ?? onlineClassComputeStatus($class);
$enter = isset($_GET['enter']) && $_GET['enter'] === '1';
$canEnter = !empty($gate['allowed']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($class['title'] ?? 'Classroom'); ?> — NIELIT Classroom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --oc-navy: #0b3a6e;
            --oc-gold: #f0a500;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; background: #0f172a; color: #e2e8f0; font-family: system-ui, Segoe UI, sans-serif; }
        .oc-top {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 10px 16px; background: var(--oc-navy); border-bottom: 2px solid var(--oc-gold);
            flex-wrap: wrap;
        }
        .oc-top h1 { font-size: 1rem; margin: 0; font-weight: 600; }
        .oc-top .meta { font-size: 0.8rem; opacity: 0.85; }
        .oc-lobby {
            max-width: 720px; margin: 3rem auto; padding: 0 1rem;
        }
        .oc-lobby-card {
            background: #1e293b; border: 1px solid #334155; border-radius: 14px; padding: 1.75rem;
        }
        .oc-lobby-card h2 { font-size: 1.35rem; margin-bottom: 0.5rem; color: #fff; }
        .oc-classroom { height: calc(100vh - 54px); }
        #jitsi-container { width: 100%; height: 100%; }
        .btn-enter { background: var(--oc-gold); border: none; color: #111; font-weight: 600; }
        .btn-enter:hover { background: #ffb81c; color: #111; }
        a.back-link { color: #93c5fd; text-decoration: none; }
    </style>
</head>
<body>
    <div class="oc-top">
        <div>
            <h1><i class="fas fa-video me-2"></i><?php echo htmlspecialchars($class['title'] ?? 'Online Class'); ?></h1>
            <div class="meta">
                <?php echo htmlspecialchars($class['batch_name'] ?? ''); ?>
                <?php if ($when): ?> · <?php echo htmlspecialchars($when); ?><?php endif; ?>
                · <?php echo htmlspecialchars(onlineClassStatusLabel($status)); ?>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="small"><?php echo htmlspecialchars($displayName); ?> (<?php echo htmlspecialchars($roleLabel); ?>)</span>
            <a class="btn btn-sm btn-outline-light" href="<?php echo $isAdmin ? htmlspecialchars(app_url('admin/manage_online_classes')) : 'online_classes.php'; ?>">
                Leave
            </a>
        </div>
    </div>

    <?php if (!$enter || !$canEnter): ?>
        <div class="oc-lobby">
            <div class="oc-lobby-card">
                <h2>NIELIT Classroom</h2>
                <p class="mb-3 text-secondary">
                    Your class opens on this website. Click Enter Classroom when you are ready
                    (camera/mic permission may be requested).
                </p>
                <?php if (!empty($class['description'])): ?>
                    <p class="mb-3"><?php echo nl2br(htmlspecialchars($class['description'])); ?></p>
                <?php endif; ?>

                <?php if (!$canEnter): ?>
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-info-circle"></i>
                        <?php echo htmlspecialchars($gate['reason'] ?? 'Classroom is closed.'); ?>
                    </div>
                    <a class="back-link" href="<?php echo $isAdmin ? htmlspecialchars(app_url('admin/manage_online_classes')) : 'online_classes.php'; ?>">
                        ← Back to Online Classes
                    </a>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <a class="btn btn-enter btn-lg" href="?t=<?php echo rawurlencode($token); ?>&enter=1">
                            <i class="fas fa-door-open"></i> Enter Classroom
                        </a>
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="navigator.clipboard.writeText(<?php echo json_encode($joinUrl); ?>).then(()=>alert('Join link copied'))">
                            <i class="fas fa-copy"></i> Copy join link
                        </button>
                    </div>
                    <p class="small text-secondary mt-3 mb-0">
                        Join link (on this site): <code style="color:#fde68a;word-break:break-all;"><?php echo htmlspecialchars($joinUrl); ?></code>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="oc-classroom">
            <div id="jitsi-container"></div>
        </div>
        <script src="https://meet.jit.si/external_api.js"></script>
        <script>
        (function () {
            var domain = 'meet.jit.si';
            var options = {
                roomName: <?php echo json_encode($roomName); ?>,
                parentNode: document.querySelector('#jitsi-container'),
                width: '100%',
                height: '100%',
                userInfo: {
                    displayName: <?php echo json_encode($displayName); ?>
                },
                configOverwrite: {
                    startWithAudioMuted: true,
                    startWithVideoMuted: false,
                    prejoinPageEnabled: true,
                    disableDeepLinking: true
                },
                interfaceConfigOverwrite: {
                    SHOW_JITSI_WATERMARK: false,
                    SHOW_WATERMARK_FOR_GUESTS: false,
                    DEFAULT_REMOTE_DISPLAY_NAME: 'Participant',
                    TOOLBAR_BUTTONS: [
                        'microphone', 'camera', 'desktop', 'fullscreen',
                        'fodeviceselection', 'hangup', 'chat', 'raisehand',
                        'tileview', 'settings', 'videoquality'
                    ]
                }
            };
            var api = new JitsiMeetExternalAPI(domain, options);
            api.addListener('readyToClose', function () {
                window.location.href = <?php echo json_encode($isAdmin ? app_url('admin/manage_online_classes') : 'online_classes.php'); ?>;
            });
        })();
        </script>
    <?php endif; ?>
</body>
</html>
