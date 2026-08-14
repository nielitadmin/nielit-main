<?php
/**
 * Mantra L1 fingerprint attendance kiosk.
 * Must be opened on the Windows PC where the scanner and RD Service are installed.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/attendance_qr_helper.php';
require_once __DIR__ . '/../includes/attendance_in_out_helper.php';
require_once __DIR__ . '/../includes/biometric_attendance_helper.php';
require_once __DIR__ . '/../includes/mantra_rd_proxy.php';

if (!isset($_SESSION['admin'])) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        biometricKioskJsonExit(['success' => false, 'message' => 'Session expired. Refresh the page and log in again.']);
    }
    header('Location: login.php');
    exit;
}

$admin_id = (string) $_SESSION['admin'];
$admin_name = (string) ($_SESSION['admin_name'] ?? 'Administrator');
ensureBiometricAttendanceTables($conn);
ensureAttendanceInOutTables($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ini_set('display_errors', '0');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
    $action = '';
    try {
        $token = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrf, $token)) {
            biometricKioskJsonExit(['success' => false, 'message' => 'Invalid security token. Refresh the page.']);
        }
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'rd_discover') {
            $found = mantraRdDiscoverLocal();
            if ($found) {
                biometricKioskJsonExit(['success' => true, 'origin' => $found['origin'], 'via' => 'php']);
            }
            biometricKioskJsonExit(['success' => false, 'message' => 'Mantra RD Service was not found on this PC.']);
        }
        if ($action === 'rd_capture' || $action === 'capture_and_mark') {
            @set_time_limit(90);
            $origin = rtrim(trim((string) ($_POST['rd_origin'] ?? '')), '/');
            if ($origin === '' || !mantraRdOriginIsAllowed($origin)) {
                $found = mantraRdDiscoverLocal();
                $origin = (string) ($found['origin'] ?? '');
            }
            $cap = mantraRdCaptureLocal($origin);
            if (!$cap['ok']) {
                biometricKioskJsonExit(['success' => false, 'message' => $cap['message']]);
            }
            if ($action === 'rd_capture') {
                $check = validateMantraPidCapture($cap['xml']);
                biometricKioskJsonExit([
                    'success' => $check['ok'],
                    'message' => $check['message'],
                    'meta' => $check['meta'],
                    'hash' => $check['hash'],
                ]);
            }
            $result = processBiometricKioskAttendance(
                $conn,
                (int) ($_POST['session_id'] ?? 0),
                (string) ($_POST['student_id'] ?? ''),
                (string) ($_POST['aadhaar_last4'] ?? ''),
                $cap['xml'],
                $admin_id
            );
            biometricKioskJsonExit(is_array($result) ? $result : ['success' => false, 'message' => 'Could not save attendance.']);
        }

        if ($action === 'create_session') {
            $result = createAttendanceSession([
                'session_name' => $_POST['session_name'] ?? '',
                'course_id' => $_POST['course_id'] ?? 0,
                'course_name' => $_POST['course_name'] ?? '',
                'subject' => $_POST['subject'] ?? '',
                'date' => $_POST['date'] ?? date('Y-m-d'),
                'start_time' => $_POST['start_time'] ?? '',
                'end_time' => $_POST['end_time'] ?? '',
                'coordinator_id' => $admin_id,
                'coordinator_name' => $admin_name,
            ], $conn);
            biometricKioskJsonExit($result);
        }
        if ($action === 'activate_session') {
            biometricKioskJsonExit(['success' => activateAttendanceSession((int) ($_POST['session_id'] ?? 0), $admin_id, $conn)]);
        }
        if ($action === 'deactivate_session') {
            biometricKioskJsonExit(['success' => deactivateAttendanceSession((int) ($_POST['session_id'] ?? 0), $admin_id, $conn)]);
        }
        if ($action === 'lookup_student') {
            $sessionId = (int) ($_POST['session_id'] ?? 0);
            $q = trim((string) ($_POST['q'] ?? ''));
            $sessionStmt = $conn->prepare("SELECT * FROM attendance_sessions WHERE id = ? AND status = 'active'");
            $session = null;
            if ($sessionStmt) {
                $sessionStmt->bind_param('i', $sessionId);
                $sessionStmt->execute();
                $session = $sessionStmt->get_result()->fetch_assoc();
                $sessionStmt->close();
            }
            if (!$session) {
                biometricKioskJsonExit(['success' => false, 'message' => 'Start an attendance session first.']);
            }
            $found = lookupBiometricKioskStudent(
                $conn,
                $q,
                (int) $session['course_id'],
                (string) ($session['course_name'] ?? '')
            );
            if (empty($found['ok']) || empty($found['row'])) {
                biometricKioskJsonExit(['success' => false, 'message' => (string) ($found['message'] ?? 'Student not found.')]);
            }
            $row = $found['row'];
            $status = (string) ($row['status'] ?? '');
            if ($status !== '' && $status !== 'active') {
                biometricKioskJsonExit(['success' => false, 'message' => 'This student is not active.']);
            }
            biometricKioskJsonExit([
                'success' => true,
                'student_id' => (string) $row['student_id'],
                'name' => (string) ($row['name'] ?? ''),
                'photo' => biometricStudentPhotoUrl($row),
                'need_aadhaar_last4' => biometricAadhaarLast4((string) ($row['aadhar'] ?? '')) !== '',
            ]);
        }
        if ($action === 'mark') {
            $result = processBiometricKioskAttendance(
                $conn,
                (int) ($_POST['session_id'] ?? 0),
                (string) ($_POST['student_id'] ?? ''),
                (string) ($_POST['aadhaar_last4'] ?? ''),
                (string) ($_POST['pid_xml'] ?? ''),
                $admin_id,
                [
                    'err_code' => (string) ($_POST['pid_err_code'] ?? ''),
                    'err_info' => (string) ($_POST['pid_err_info'] ?? ''),
                    'q_score' => (string) ($_POST['pid_q_score'] ?? ''),
                    'nm_points' => (string) ($_POST['pid_nm_points'] ?? ''),
                    'ts' => (string) ($_POST['pid_ts'] ?? ''),
                    'dc' => (string) ($_POST['pid_dc'] ?? ''),
                    'mi' => (string) ($_POST['pid_mi'] ?? ''),
                    'rds_id' => (string) ($_POST['pid_rds_id'] ?? ''),
                    'has_data' => (string) ($_POST['pid_has_data'] ?? '0'),
                    'hash' => (string) ($_POST['pid_hash'] ?? ''),
                ]
            );
            biometricKioskJsonExit($result);
        }
        biometricKioskJsonExit(['success' => false, 'message' => 'Unknown action.']);
    } catch (Throwable $e) {
        $detail = $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        error_log('attendance_biometric POST action=' . $action . ' ' . $detail);
        @file_put_contents(
            dirname(__DIR__) . '/uploads/biometric_kiosk_error.log',
            date('c') . ' action=' . $action . ' ' . $detail . "\n" . $e->getTraceAsString() . "\n\n",
            FILE_APPEND
        );
        biometricKioskJsonExit([
            'success' => false,
            'message' => 'Fingerprint save failed: ' . $e->getMessage(),
        ]);
    }
}

$centreId = (int) ($_GET['centre_id'] ?? 0);
$active_sessions = getActiveAttendanceSessions($admin_id, $conn, $centreId);
$openSessionId = (int) ($_GET['session_id'] ?? 0);
$centres = attendanceListCentres($conn);
$allCourses = attendanceListCoursesForCentre($conn, 0);
$courses = $centreId > 0 ? attendanceListCoursesForCentre($conn, $centreId) : $allCourses;
$active_theme = loadActiveTheme($conn);
$jsPath = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/assets/js/mantra_rd.js?v=' . (@filemtime(__DIR__ . '/../assets/js/mantra_rd.js') ?: time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fingerprint Attendance - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .session-card { border-left: 4px solid #0d9488; }
        .bio-banner { border-radius: 10px; padding: 0.85rem 1rem; margin-bottom: 1rem; }
        .bio-banner.ok { background: #d1fae5; color: #065f46; }
        .bio-banner.wait { background: #fef3c7; color: #92400e; }
        .bio-banner.bad { background: #fee2e2; color: #991b1b; }
        .kiosk-photo { width: 160px; height: 190px; object-fit: cover; border-radius: 10px; background: #e2e8f0; }
        .kiosk-photo-empty { width: 160px; height: 190px; border-radius: 10px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b; }
        .scan-type-in { color: #047857; font-weight: 700; }
        .scan-type-out { color: #b45309; font-weight: 700; }
        #kioskPanel { display: none; }
        #kioskPanel.is-open { display: block; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="row mb-3">
            <div class="col-12">
                <h2><i class="fas fa-fingerprint"></i> Fingerprint Attendance</h2>
                <p class="text-muted mb-0">Mantra L1 kiosk — pick a centre, then mark students for that centre’s courses. Open this page on the PC where the scanner is plugged in.</p>
            </div>
        </div>

        <div id="rdBanner" class="bio-banner wait">Checking Mantra RD Service on this PC…</div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">If 127.0.0.1:11100 does not open</h5></div>
            <div class="card-body">
                <p class="mb-2"><strong>Update Chrome first, then disable Local Network Access Checks</strong> so this page can talk to the scanner on this PC.</p>
                <ol class="mb-3">
                    <li>In Chrome click the <strong>three dots</strong> (top right) → <strong>Help</strong> → <strong>About Google Chrome</strong>. Let Chrome update to the latest version, then relaunch.</li>
                    <li>Paste this in the address bar: <code>chrome://flags/#local-network-access-check</code>. Set <strong>Local Network Access Checks</strong> to <strong>Disabled</strong>.</li>
                    <li>Paste this in the address bar: <code>chrome://flags/#block-insecure-private-network-requests</code>. If that flag is still listed, set it to <strong>Disabled</strong>.</li>
                    <li>Relaunch Chrome when it asks, then return to this page.</li>
                </ol>
                <ol class="mb-2" start="5">
                    <li>Try <a href="https://127.0.0.1:11100/" target="_blank" rel="noopener">https://127.0.0.1:11100/</a> — accept the certificate warning if Chrome shows one.</li>
                    <li>Press Win+R → <code>services.msc</code> → start <strong>Mantra RD Service</strong> (or MFS110 RDService) and set it to Automatic.</li>
                </ol>
                <p class="text-muted mb-0">Until RD Service is running on this PC, use QR attendance with a coordinator watching, so students cannot scan other people’s codes.</p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Centre</label>
                        <select class="form-select" name="centre_id" onchange="this.form.submit()">
                            <option value="0">All centres</option>
                            <?php foreach ($centres as $centre): ?>
                                <option value="<?php echo (int) $centre['id']; ?>" <?php echo (int) $centre['id'] === $centreId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $centre['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createSessionModal">
                            <i class="fas fa-plus"></i> Create session
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Your sessions</h5></div>
            <div class="card-body">
                <?php if (count($active_sessions) === 0): ?>
                    <p class="text-muted mb-0">Create a session, start it, then open the fingerprint kiosk.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($active_sessions as $session): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card session-card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?php echo htmlspecialchars((string) $session['session_name']); ?></h6>
                                        <span class="badge bg-<?php echo $session['status'] === 'active' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars((string) $session['status']); ?></span>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-1"><strong>Centre:</strong> <?php echo htmlspecialchars(trim((string) ($session['centre_name'] ?? '')) !== '' ? (string) $session['centre_name'] : '—'); ?></p>
                                        <p class="mb-1"><strong>Course:</strong> <?php echo htmlspecialchars((string) $session['course_name']); ?></p>
                                        <p class="mb-1"><strong>Subject:</strong> <?php echo htmlspecialchars((string) $session['subject']); ?></p>
                                        <p class="mb-3"><strong>Date:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime((string) $session['date']))); ?></p>
                                        <?php if ($session['status'] === 'scheduled'): ?>
                                            <button class="btn btn-success btn-sm w-100" type="button" onclick="activateSession(<?php echo (int) $session['id']; ?>)">Start session</button>
                                        <?php elseif ($session['status'] === 'active'): ?>
                                            <button class="btn btn-primary btn-sm w-100 mb-2 js-open-kiosk" type="button"
                                                    data-session-id="<?php echo (int) $session['id']; ?>"
                                                    data-session-name="<?php echo htmlspecialchars((string) $session['session_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-course-name="<?php echo htmlspecialchars((string) $session['course_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fas fa-fingerprint"></i> Open fingerprint kiosk
                                            </button>
                                            <button class="btn btn-danger btn-sm w-100" type="button" onclick="deactivateSession(<?php echo (int) $session['id']; ?>)">End session</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4" id="kioskPanel">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0" id="kioskTitle">Fingerprint kiosk</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCloseKiosk">Close</button>
            </div>
            <div class="card-body">
                <p class="text-muted" id="kioskHint">Find the student, confirm the photo, then capture fingerprint. One person at a time. Use the full Student ID — this session only marks students enrolled in its course.</p>
                <div id="lastPunch" class="bio-banner ok mb-3" style="display:none;"></div>
            <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Student ID, mobile, or Aadhaar</label>
                        <div class="input-group mb-2">
                            <input class="form-control" id="lookupQ" autocomplete="off" placeholder="Type full ID then Find">
                            <button class="btn btn-secondary" type="button" id="btnFind">Find</button>
                        </div>
                        <div id="aadhaarWrap" class="mb-2" style="display:none;">
                            <label class="form-label">Last 4 digits of Aadhaar</label>
                            <input class="form-control" id="aadhaarLast4" maxlength="4" inputmode="numeric" autocomplete="off">
                        </div>
                        <button class="btn btn-success w-100 mb-2" type="button" id="btnCapture" disabled>
                            <i class="fas fa-fingerprint"></i> Capture fingerprint &amp; mark
                        </button>
                        <div id="kioskMsg" class="small"></div>
                    </div>
                    <div class="col-md-5 text-center">
                        <div id="photoBox" class="d-flex justify-content-center mb-2">
                            <div class="kiosk-photo-empty"><i class="fas fa-user fa-3x"></i></div>
                        </div>
                        <div id="studentMeta" class="fw-semibold"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create attendance session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createSessionForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Session name</label>
                        <input class="form-control" name="session_name" required placeholder="Morning batch — Lab">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Centre</label>
                        <select class="form-select" id="sessionCentre" required>
                            <option value="">Select centre</option>
                            <?php foreach ($centres as $centre): ?>
                                <option value="<?php echo (int) $centre['id']; ?>" <?php echo (int) $centre['id'] === $centreId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $centre['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id" id="sessionCourse" required>
                            <option value="">Select course</option>
                            <?php foreach ($allCourses as $course): ?>
                                <option value="<?php echo (int) $course['id']; ?>"
                                        data-name="<?php echo htmlspecialchars((string) $course['course_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-centre="<?php echo (int) ($course['centre_id'] ?? 0); ?>">
                                    <?php
                                    $cLabel = (string) $course['course_name'];
                                    $cCentre = trim((string) ($course['centre_name'] ?? ''));
                                    echo htmlspecialchars($cCentre !== '' ? ($cLabel . ' — ' . $cCentre) : $cLabel);
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="course_name" id="course_name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input class="form-control" name="subject" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start</label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End</label>
                            <input type="time" class="form-control" name="end_time" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo htmlspecialchars($jsPath); ?>"></script>
<script>
(function () {
    const csrf = <?php echo json_encode($csrf); ?>;
    const openSessionId = <?php echo (int) $openSessionId; ?>;
    let rdOrigin = '';
    let sessionId = 0;
    let foundStudent = null;
    let usePhpCapture = false;

    function banner(cls, html) {
        const el = document.getElementById('rdBanner');
        el.className = 'bio-banner ' + cls;
        el.innerHTML = html;
    }
    function kioskMsg(text, ok) {
        const el = document.getElementById('kioskMsg');
        el.className = 'small ' + (ok ? 'text-success' : 'text-danger');
        el.textContent = text || '';
    }
    function parseServerJson(text) {
        text = String(text || '').replace(/^\uFEFF/, '');
        const trimmed = text.trim();
        if (!trimmed) {
            throw new Error('Empty response from the server. Try Capture again.');
        }
        try {
            return JSON.parse(trimmed);
        } catch (e1) {
            const start = trimmed.indexOf('{');
            const end = trimmed.lastIndexOf('}');
            if (start >= 0 && end > start) {
                try {
                    return JSON.parse(trimmed.slice(start, end + 1));
                } catch (e2) {}
            }
            if (/<html/i.test(trimmed) && /login/i.test(trimmed)) {
                throw new Error('Session expired. Refresh the page and log in again.');
            }
            const plain = trimmed.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180);
            throw new Error(plain || 'Server did not return JSON. Refresh the page and try again.');
        }
    }
    function postUrl() {
        const path = String(window.location.pathname || '');
        if (/attendance_biometric/i.test(path)) {
            return path;
        }
        return 'attendance_biometric.php';
    }
    function post(data) {
        const fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(data).forEach(function (k) {
            if (data[k] !== undefined && data[k] !== null) {
                fd.append(k, data[k]);
            }
        });
        return fetch(postUrl(), { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
            return r.text().then(function (text) {
                return parseServerJson(text);
            });
        });
    }
    function markPayload(meta, hash) {
        meta = meta || {};
        return {
            action: 'mark',
            session_id: String(sessionId),
            student_id: foundStudent.student_id,
            aadhaar_last4: document.getElementById('aadhaarLast4').value,
            pid_err_code: meta.err_code || '',
            pid_err_info: meta.err_info || '',
            pid_q_score: meta.q_score || '',
            pid_nm_points: meta.nm_points || '',
            pid_ts: meta.ts || '',
            pid_dc: meta.dc || '',
            pid_mi: meta.mi || '',
            pid_rds_id: meta.rds_id || '',
            pid_has_data: meta.has_data || '0',
            pid_hash: hash || ''
        };
    }
    function captureViaPhp() {
        return post({
            action: 'capture_and_mark',
            rd_origin: rdOrigin,
            session_id: String(sessionId),
            student_id: foundStudent.student_id,
            aadhaar_last4: document.getElementById('aadhaarLast4').value
        });
    }
    function captureViaBrowser() {
        if (!window.MantraRd || !rdOrigin) {
            return Promise.reject(new Error('Mantra RD Service is not available in this browser.'));
        }
        return MantraRd.capture(rdOrigin).then(function (xml) {
            const meta = MantraRd.parsePid(xml);
            return MantraRd.sha256(xml).then(function (hash) {
                const payload = markPayload(meta, hash);
                payload.pid_xml = xml;
                return post(payload);
            });
        });
    }
    function discoverDevice() {
        const localSite = /^(localhost|127\.0\.0\.1)$/i.test(location.hostname);
        const start = localSite ? post({ action: 'rd_discover' }) : Promise.resolve({ success: false });
        return start.then(function (data) {
            if (localSite && data.success && data.origin) {
                rdOrigin = data.origin;
                usePhpCapture = true;
                banner('ok', '<strong>Mantra device ready</strong> on this PC (' + data.origin + '). Start a session and open the kiosk.');
                return;
            }
            if (!window.MantraRd) {
                banner('bad', 'Fingerprint script failed to load.');
                return;
            }
            return MantraRd.discover().then(function (found) {
                if (found && found.origin) {
                    rdOrigin = found.origin;
                    usePhpCapture = localSite;
                    banner('ok', '<strong>Mantra RD Service is running</strong> (' + found.origin + '). Start a session and open the kiosk.');
                    return;
                }
                banner('bad', '<strong>Mantra RD Service is not listening on this PC.</strong> Use Fingerprint Attendance on the computer where the scanner is plugged in, start Mantra RD Service, then refresh.');
            });
        }).catch(function () {
            banner('bad', 'Could not check the scanner. Start Mantra RD Service on this computer, then refresh.');
        });
    }
    function resetStudent() {
        foundStudent = null;
        document.getElementById('lookupQ').value = '';
        document.getElementById('aadhaarLast4').value = '';
        document.getElementById('aadhaarWrap').style.display = 'none';
        document.getElementById('btnCapture').disabled = true;
        document.getElementById('studentMeta').textContent = '';
        document.getElementById('photoBox').innerHTML = '<div class="kiosk-photo-empty"><i class="fas fa-user fa-3x"></i></div>';
    }

    discoverDevice();

    function filterSessionCourses() {
        const centreSel = document.getElementById('sessionCentre');
        const courseSel = document.getElementById('sessionCourse');
        if (!centreSel || !courseSel) {
            return;
        }
        const cid = String(centreSel.value || '');
        Array.prototype.forEach.call(courseSel.options, function (opt) {
            if (!opt.value) {
                opt.hidden = false;
                return;
            }
            const match = cid === '' || String(opt.getAttribute('data-centre') || '0') === cid;
            opt.hidden = !match;
            if (!match && opt.selected) {
                courseSel.value = '';
                const hiddenName = document.getElementById('course_name');
                if (hiddenName) {
                    hiddenName.value = '';
                }
            }
        });
    }
    const sessionCentre = document.getElementById('sessionCentre');
    const sessionCourse = document.getElementById('sessionCourse');
    if (sessionCentre) {
        sessionCentre.addEventListener('change', filterSessionCourses);
        filterSessionCourses();
    }
    if (sessionCourse) {
        sessionCourse.addEventListener('change', function () {
            const hiddenName = document.getElementById('course_name');
            if (hiddenName) {
                hiddenName.value = this.options[this.selectedIndex].getAttribute('data-name') || '';
            }
        });
    }

    document.getElementById('createSessionForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'create_session');
        fd.append('csrf_token', csrf);
        fetch(postUrl(), { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (text) { return parseServerJson(text); })
            .then(function (data) {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Could not create session');
                }
            });
    });

    window.activateSession = function (id) {
        post({ action: 'activate_session', session_id: String(id) }).then(function (data) {
            if (data.success) {
                location.reload();
            } else {
                alert('Could not start session');
            }
        });
    };
    window.deactivateSession = function (id) {
        if (!confirm('End this session?')) {
            return;
        }
        post({ action: 'deactivate_session', session_id: String(id) }).then(function (data) {
            if (data.success) {
                location.reload();
            }
        });
    };
    function openKiosk(id, name, courseName) {
        sessionId = parseInt(id, 10) || 0;
        resetStudent();
        kioskMsg('');
        document.getElementById('kioskTitle').textContent = 'Fingerprint kiosk — ' + (name || '');
        const hint = document.getElementById('kioskHint');
        if (hint) {
            hint.textContent = 'This session is for ' + (courseName || 'the selected course') + '. Type the full Student ID (not the name), then Find. Only students enrolled in that course can be marked.';
        }
        document.getElementById('kioskPanel').classList.add('is-open');
        document.getElementById('kioskPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(function () {
            document.getElementById('lookupQ').focus();
        }, 50);
    }
    window.openKiosk = openKiosk;

    document.querySelectorAll('.js-open-kiosk').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openKiosk(
                btn.getAttribute('data-session-id'),
                btn.getAttribute('data-session-name') || '',
                btn.getAttribute('data-course-name') || ''
            );
        });
    });
    document.getElementById('btnCloseKiosk').addEventListener('click', function () {
        document.getElementById('kioskPanel').classList.remove('is-open');
    });

    document.getElementById('btnFind').addEventListener('click', findStudent);
    document.getElementById('lookupQ').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            findStudent();
        }
    });

    function findStudent() {
        kioskMsg('');
        post({ action: 'lookup_student', session_id: String(sessionId), q: document.getElementById('lookupQ').value }).then(function (data) {
            if (!data.success) {
                foundStudent = null;
                document.getElementById('btnCapture').disabled = true;
                kioskMsg(data.message || 'Not found', false);
                return;
            }
            foundStudent = data;
            document.getElementById('studentMeta').textContent = data.name + '  ·  ' + data.student_id;
            if (data.photo) {
                document.getElementById('photoBox').innerHTML = '<img class="kiosk-photo" alt="" src="' + data.photo + '">';
            } else {
                document.getElementById('photoBox').innerHTML = '<div class="kiosk-photo-empty">No photo</div>';
            }
            document.getElementById('aadhaarWrap').style.display = data.need_aadhaar_last4 ? 'block' : 'none';
            document.getElementById('btnCapture').disabled = !rdOrigin;
            if (!rdOrigin) {
                kioskMsg('Mantra RD Service is not running on this PC.', false);
            } else {
                kioskMsg('Confirm the photo, then capture fingerprint.', true);
            }
        });
    }

    document.getElementById('btnCapture').addEventListener('click', function () {
        if (!foundStudent || !rdOrigin) {
            return;
        }
        if (foundStudent.need_aadhaar_last4 && document.getElementById('aadhaarLast4').value.replace(/\D/g, '').length !== 4) {
            kioskMsg('Enter the last 4 digits of Aadhaar.', false);
            return;
        }
        const btn = document.getElementById('btnCapture');
        btn.disabled = true;
        kioskMsg('Waiting for finger on the Mantra device…', true);
        const run = usePhpCapture ? captureViaPhp() : captureViaBrowser();
        run.then(function (data) {
            if (data.success) {
                const kind = (data.scan_type === 'out') ? 'OUT' : 'IN';
                const name = data.student_name || (foundStudent && foundStudent.name) || 'student';
                const time = data.scan_time || '';
                const line = kind + ' recorded for ' + name + (time ? (' at ' + time) : '');
                kioskMsg(line + '. Ready for the next student.', true);
                const punch = document.getElementById('lastPunch');
                if (punch) {
                    punch.style.display = 'block';
                    punch.className = 'bio-banner mb-3 ' + (kind === 'OUT' ? 'wait' : 'ok');
                    punch.innerHTML = '<strong style="font-size:1.35rem;">' + kind + '</strong> &nbsp; ' + line + '. Ready for the next student.';
                }
                resetStudent();
                document.getElementById('lookupQ').focus();
            } else {
                kioskMsg(data.message || 'Attendance not marked', false);
                btn.disabled = false;
            }
        }).catch(function (err) {
            const msg = (err && err.message) ? err.message : 'Could not read the fingerprint result.';
            kioskMsg(msg, false);
            btn.disabled = false;
        });
    });

    if (openSessionId > 0) {
        const match = <?php echo json_encode(array_values(array_map(static function ($s) {
            return [
                'id' => (int) $s['id'],
                'name' => (string) $s['session_name'],
                'course' => (string) $s['course_name'],
                'status' => (string) $s['status'],
            ];
        }, $active_sessions))); ?>.find(function (s) { return s.id === openSessionId && s.status === 'active'; });
        if (match) {
            openKiosk(match.id, match.name, match.course);
        }
    }
})();
</script>
</body>
</html>
