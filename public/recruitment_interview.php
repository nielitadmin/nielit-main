<?php
/**
 * Candidate interview waiting room. The meeting link unlocks only when the board calls this person.
 */
require_once __DIR__ . '/../includes/maintenance_check.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/public_theme_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

ensureRecruitmentTables($conn);

$token = trim((string) ($_GET['t'] ?? ''));
$row = $token !== '' ? recruitmentGetInterviewByToken($conn, $token) : null;

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!$row) {
        echo json_encode(['ok' => false, 'status' => 'invalid']);
        exit;
    }
    $status = (string) ($row['call_status'] ?? 'waiting');
    $canJoin = $status === 'called' && strtolower((string) ($row['interview_status'] ?? '')) !== 'cancelled';
    $payload = [
        'ok' => true,
        'status' => $status,
        'can_join' => $canJoin,
        'label' => recruitmentInterviewCallStatuses()[$status] ?? $status,
    ];
    if ($canJoin) {
        $payload['join_url'] = recruitmentInterviewRoomUrl($row);
    }
    echo json_encode($payload);
    exit;
}

if ($row && (string) ($_GET['go'] ?? '') === '1' && ($row['call_status'] ?? '') === 'called') {
    recruitmentMarkInterviewJoined($conn, (int) $row['id']);
    $url = recruitmentInterviewRoomUrl($row);
    header('Location: ' . $url);
    exit;
}

$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);
$page_title = 'Interview waiting room - NIELIT Bhubaneswar';
injectThemeCSS($active_theme);
emitPublicThemeHead($conn);

$status = $row ? (string) ($row['call_status'] ?? 'waiting') : '';
$sessionClosed = $row && in_array(strtolower((string) ($row['interview_status'] ?? '')), ['completed', 'cancelled'], true);
$canJoin = $row && $status === 'called' && !$sessionClosed;
$when = $row ? recruitmentFormatDate((string) ($row['interview_date'] ?? ''), 'd M Y') : '';
$time = $row ? substr((string) ($row['interview_time'] ?? ''), 0, 5) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/public-theme.css">
    <style>
        .iv-box { max-width: 640px; margin: 2.5rem auto; background:#fff; border-radius:16px; box-shadow:0 10px 28px rgba(15,23,42,.08); padding:2rem; }
        .iv-wait { color:#1a56db; }
    </style>
</head>
<body class="public-site">
    <div class="top-bar">
        <div class="container">
            <div class="d-flex align-items-center justify-content-center py-2">
                <img src="<?php echo APP_URL . '/' . $theme_logo; ?>" alt="NIELIT" style="height:44px" class="me-3">
                <strong>NIELIT Bhubaneswar — Recruitment interview</strong>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="iv-box">
            <?php if (!$row): ?>
                <h2 class="h4">Link not valid</h2>
                <p class="text-muted mb-0">This interview link is missing or incorrect. Please use the personal link sent to your email.</p>
            <?php else: ?>
                <p class="text-muted mb-1">Application <?php echo htmlspecialchars((string) $row['application_no']); ?></p>
                <h1 class="h3 mb-2"><?php echo htmlspecialchars((string) $row['name']); ?></h1>
                <p class="mb-3"><?php echo htmlspecialchars((string) ($row['job_title'] ?? $row['interview_title'] ?? '')); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars($when); ?>
                    <?php echo $time && $time !== '00:00' ? ' &nbsp; <strong>Time:</strong> ' . htmlspecialchars($time) : ''; ?>
                    &nbsp; <strong>Mode:</strong> <?php echo htmlspecialchars(ucfirst((string) $row['mode'])); ?>
                </p>
                <?php if (strtolower((string) $row['mode']) === 'offline' && trim((string) ($row['venue'] ?? '')) !== ''): ?>
                    <p><strong>Venue:</strong> <?php echo htmlspecialchars((string) $row['venue']); ?></p>
                <?php endif; ?>

                <div id="ivState">
                    <?php if ($sessionClosed): ?>
                        <div class="alert alert-secondary">This interview session is closed.</div>
                    <?php elseif ($canJoin): ?>
                        <div class="alert alert-success">The board is calling you now. Join immediately.</div>
                        <a class="btn btn-success btn-lg" href="<?php echo htmlspecialchars(app_url('public/recruitment_interview') . '?t=' . rawurlencode($token) . '&go=1'); ?>">Join interview now</a>
                    <?php elseif ($status === 'completed'): ?>
                        <div class="alert alert-light border">Your turn is complete. Thank you.</div>
                    <?php elseif ($status === 'skipped'): ?>
                        <div class="alert alert-warning">You were skipped for now. Keep this page open if the board may call you later.</div>
                    <?php else: ?>
                        <div class="alert alert-primary iv-wait">
                            <i class="fas fa-clock me-1"></i> Please wait. Keep this page open.
                            The interview board will call candidates <strong>one by one</strong>.
                            The online room will unlock only when it is your turn.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($row && !$sessionClosed && !in_array($status, ['completed'], true)): ?>
    <script>
    (function () {
        var box = document.getElementById('ivState');
        if (!box) return;
        var url = <?php echo json_encode(app_url('public/recruitment_interview') . '?t=' . $token . '&ajax=1'); ?>;
        var joinBase = <?php echo json_encode(app_url('public/recruitment_interview') . '?t=' . $token . '&go=1'); ?>;
        function render(d) {
            if (!d || !d.ok) return;
            if (d.can_join) {
                box.innerHTML = '<div class="alert alert-success">The board is calling you now. Join immediately.</div>'
                    + '<a class="btn btn-success btn-lg" href="' + joinBase + '">Join interview now</a>';
                return;
            }
            if (d.status === 'completed') {
                box.innerHTML = '<div class="alert alert-light border">Your turn is complete. Thank you.</div>';
                return;
            }
            if (d.status === 'skipped') {
                box.innerHTML = '<div class="alert alert-warning">You were skipped for now. Keep this page open if the board may call you later.</div>';
                return;
            }
            box.innerHTML = '<div class="alert alert-primary iv-wait"><i class="fas fa-clock me-1"></i> Please wait. Keep this page open. The interview board will call candidates <strong>one by one</strong>. The online room will unlock only when it is your turn.</div>';
        }
        setInterval(function () {
            fetch(url, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(render).catch(function () {});
        }, 5000);
    })();
    </script>
    <?php endif; ?>
</body>
</html>
<?php if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } ?>
