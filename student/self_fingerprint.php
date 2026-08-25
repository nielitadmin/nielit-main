<?php
/**
 * Student self-service fingerprint kiosk.
 *
 * Flow:
 *   1) Page only works from a static IP the master admin has allowed
 *      (Admin -> Student Fingerprint Kiosk). Any other IP is blocked.
 *   2) New student (no fingerprint yet): Student ID / Aadhaar / mobile
 *      -> OTP emailed -> confirm record details -> capture thumb twice to enrol.
 *   3) Existing student (already enrolled): last 6 digits of Aadhaar
 *      -> one fingerprint capture -> 1:1 match -> IN/OUT for the active session.
 *
 * Open this page on the kiosk PC where the fingerprint reader + its WebAPI /
 * client service are running.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/otp_logger.php';
require_once __DIR__ . '/../includes/phpmailer_smtp.php';
require_once __DIR__ . '/../includes/attendance_in_out_helper.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';
require_once __DIR__ . '/../includes/biometric_attendance_helper.php';
require_once __DIR__ . '/../includes/mantra_mfs100_helper.php';
require_once __DIR__ . '/../includes/student_kiosk_helper.php';

ensureStudentKioskTables($conn);
ensureFingerprintTemplateTables($conn);
ensureBiometricAttendanceTables($conn);
ensureAttendanceInOutTables($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];

$clientIp = studentKioskClientIp();
$ipAllowed = studentKioskIpAllowed($conn, $clientIp);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ini_set('display_errors', '0');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        biometricKioskJsonExit(['success' => false, 'message' => 'Invalid security token. Refresh the page.']);
    }
    if (!$ipAllowed) {
        biometricKioskJsonExit(['success' => false, 'message' => 'This device is not authorised for fingerprint attendance.']);
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'request_otp') {
            $found = studentKioskLookup($conn, (string) ($_POST['identifier'] ?? ''));
            if (empty($found['ok']) || empty($found['row'])) {
                biometricKioskJsonExit(['success' => false, 'message' => (string) ($found['message'] ?? 'Student not found.')]);
            }
            $status = strtolower(trim((string) ($found['row']['status'] ?? 'active')));
            if ($status !== '' && !in_array($status, ['active', 'approved'], true)) {
                biometricKioskJsonExit(['success' => false, 'message' => 'This student account is not active.']);
            }
            $sid = (string) ($found['row']['student_id'] ?? '');
            if (studentHasFingerprintTemplate($conn, $sid)) {
                biometricKioskJsonExit([
                    'success' => false,
                    'already_enrolled' => true,
                    'message' => 'Your fingerprint is already registered. Use Mark attendance and enter the last 6 digits of your Aadhaar.',
                ]);
            }
            $sent = studentKioskSendOtp($conn, $found['row']);
            if (empty($sent['success'])) {
                biometricKioskJsonExit(['success' => false, 'message' => (string) ($sent['message'] ?? 'Could not send OTP email.')]);
            }
            biometricKioskJsonExit([
                'success' => true,
                'message' => 'OTP sent to ' . ($sent['masked'] ?? 'your registered email') . '. Open that Gmail inbox, then check Spam and Promotions. If that is not your email, contact the office.',
            ]);
        }

        if ($action === 'verify_otp') {
            $res = studentKioskVerifyOtp((string) ($_POST['otp'] ?? ''));
            if (empty($res['success'])) {
                biometricKioskJsonExit(['success' => false, 'message' => $res['message']]);
            }
            $sid = (string) ($res['student_id'] ?? '');
            $look = studentKioskLookup($conn, $sid);
            $details = ($look['ok'] && !empty($look['row'])) ? studentKioskPublicDetails($conn, $look['row']) : [];
            biometricKioskJsonExit([
                'success' => true,
                'message' => 'Identity verified. Confirm your details, then capture your thumb.',
                'student_id' => $sid,
                'name' => (string) ($details['name'] ?? ''),
                'details' => $details,
                'has_fingerprint' => studentHasFingerprintTemplate($conn, $sid),
            ]);
        }

        // Existing enrolled student: last 6 Aadhaar digits — no OTP.
        if ($action === 'lookup_existing') {
            $found = studentKioskFindEnrolledByAadhaarLast6($conn, (string) ($_POST['aadhaar_last6'] ?? ''));
            if (empty($found['ok'])) {
                biometricKioskJsonExit(['success' => false, 'message' => (string) ($found['message'] ?? 'Not found.')]);
            }
            biometricKioskJsonExit([
                'success' => true,
                'candidates' => $found['candidates'],
            ]);
        }

        if ($action === 'mark_attendance_existing') {
            $last6 = preg_replace('/\D/', '', (string) ($_POST['aadhaar_last6'] ?? ''));
            $sid = trim((string) ($_POST['student_id'] ?? ''));
            $iso = trim((string) ($_POST['iso_template'] ?? ''));
            if (strlen((string) $last6) !== 6 || $sid === '' || strlen($iso) < 40) {
                biometricKioskJsonExit(['success' => false, 'message' => 'Enter the last 6 digits of Aadhaar and capture your thumb again.']);
            }
            if (!studentHasFingerprintTemplate($conn, $sid)) {
                biometricKioskJsonExit(['success' => false, 'message' => 'No registered fingerprint for this student. Use New registration first.']);
            }
            $look = studentKioskLookup($conn, $sid);
            if (empty($look['ok']) || empty($look['row']) || !studentKioskAadhaarLast6Matches((string) ($look['row']['aadhar'] ?? ''), $last6)) {
                biometricKioskJsonExit(['success' => false, 'message' => 'Aadhaar digits do not match this student.']);
            }
            $digits = preg_replace('/\D/', '', (string) ($look['row']['aadhar'] ?? ''));
            $aadhaarLast4 = strlen((string) $digits) >= 4 ? substr((string) $digits, -4) : substr($last6, -4);
            $result = studentKioskPunchAllActiveSessions(
                $conn,
                $sid,
                $iso,
                $aadhaarLast4,
                (string) ($_POST['client_matched'] ?? '') === '1',
                is_array($look['row'] ?? null) ? $look['row'] : []
            );
            biometricKioskJsonExit(is_array($result) ? $result : ['success' => false, 'message' => 'Could not save attendance.']);
        }

        if ($action === 'reset') {
            studentKioskClearVerification();
            biometricKioskJsonExit(['success' => true]);
        }

        // Enrolment actions require OTP-verified identity.
        $verifiedSid = studentKioskVerifiedStudent();
        if ($verifiedSid === '') {
            biometricKioskJsonExit(['success' => false, 'message' => 'Verify your identity with OTP first.']);
        }

        if ($action === 'status') {
            $look = studentKioskLookup($conn, $verifiedSid);
            biometricKioskJsonExit([
                'success' => true,
                'student_id' => $verifiedSid,
                'name' => $look['ok'] ? (string) ($look['row']['name'] ?? '') : '',
                'has_fingerprint' => studentHasFingerprintTemplate($conn, $verifiedSid),
            ]);
        }

        if ($action === 'get_gallery') {
            if (!studentHasFingerprintTemplate($conn, $verifiedSid)) {
                biometricKioskJsonExit(['success' => false, 'message' => 'No fingerprint is registered yet. Register first.']);
            }
            biometricKioskJsonExit(['success' => true, 'iso_template' => loadStudentFingerprintTemplate($conn, $verifiedSid)]);
        }

        if ($action === 'enroll_save') {
            if (studentHasFingerprintTemplate($conn, $verifiedSid)) {
                biometricKioskJsonExit(['success' => false, 'message' => 'Your fingerprint is already registered. You can mark attendance now.']);
            }
            $iso = trim((string) ($_POST['iso_template'] ?? ''));
            $quality = (int) ($_POST['quality'] ?? 0);
            if (strlen($iso) < 40) {
                biometricKioskJsonExit(['success' => false, 'message' => 'Capture the fingerprint again.']);
            }
            $ok = saveStudentFingerprintTemplate($conn, $verifiedSid, $iso, 'self:' . $verifiedSid, 'R1', $quality);
            biometricKioskJsonExit([
                'success' => $ok,
                'saved' => $ok,
                'message' => $ok ? 'Fingerprint registered. You can now mark attendance.' : 'Could not save fingerprint. Try again.',
            ]);
        }

        if ($action === 'mark_attendance') {
            $iso = trim((string) ($_POST['iso_template'] ?? ''));
            if (strlen($iso) < 40) {
                biometricKioskJsonExit(['success' => false, 'message' => 'Capture the fingerprint again.']);
            }
            $aadhaarLast4 = '';
            $look = studentKioskLookup($conn, $verifiedSid);
            if ($look['ok']) {
                $digits = preg_replace('/\D/', '', (string) ($look['row']['aadhar'] ?? ''));
                if (strlen((string) $digits) >= 4) {
                    $aadhaarLast4 = substr((string) $digits, -4);
                }
            }
            $result = studentKioskPunchAllActiveSessions(
                $conn,
                $verifiedSid,
                $iso,
                $aadhaarLast4,
                (string) ($_POST['client_matched'] ?? '') === '1',
                $look['ok'] ? (array) $look['row'] : []
            );
            biometricKioskJsonExit(is_array($result) ? $result : ['success' => false, 'message' => 'Could not save attendance.']);
        }

        biometricKioskJsonExit(['success' => false, 'message' => 'Unknown action.']);
    } catch (Throwable $e) {
        error_log('student self_fingerprint: ' . $e->getMessage());
        biometricKioskJsonExit(['success' => false, 'message' => 'Could not complete that step: ' . $e->getMessage()]);
    }
}

$sgLic = defined('SECUGEN_WEBAPI_LICSTR') ? (string) SECUGEN_WEBAPI_LICSTR : '';
$sgThreshold = defined('SECUGEN_MATCH_THRESHOLD') ? (int) SECUGEN_MATCH_THRESHOLD : 100;
$jsBase = (defined('APP_URL') ? rtrim(APP_URL, '/') : '');
$vsecu = '/assets/js/secugen_webapi.js?v=' . (@filemtime(__DIR__ . '/../assets/js/secugen_webapi.js') ?: time());
$vmantra = '/assets/js/mantra_mfs100.js?v=' . (@filemtime(__DIR__ . '/../assets/js/mantra_mfs100.js') ?: time());
$vrd = '/assets/js/mantra_rd.js?v=' . (@filemtime(__DIR__ . '/../assets/js/mantra_rd.js') ?: time());
$vdev = '/assets/js/biometric_device.js?v=' . (@filemtime(__DIR__ . '/../assets/js/biometric_device.js') ?: time());
$cssKiosk = '/assets/css/student-kiosk.css?v=' . (@filemtime(__DIR__ . '/../assets/css/student-kiosk.css') ?: time());
$logoUrl = app_url('assets/images/bhubaneswar_logo.png');
$bgSlides = studentKioskBackgroundSlides();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fingerprint Self-Service - NIELIT Bhubaneswar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($jsBase . $cssKiosk); ?>">
    <link rel="icon" href="<?php echo htmlspecialchars(app_url('assets/images/favicon.ico')); ?>" type="image/x-icon">
</head>
<body class="kiosk-page">
<div class="kiosk-bg" aria-hidden="true">
    <?php if (!empty($bgSlides)): ?>
        <?php foreach ($bgSlides as $i => $slide): ?>
            <div class="kiosk-slide<?php echo $i === 0 ? ' active' : ''; ?>">
                <img src="<?php echo htmlspecialchars((string) $slide['url']); ?>" alt="<?php echo htmlspecialchars((string) $slide['alt']); ?>" <?php echo $i === 0 ? 'fetchpriority="high"' : 'loading="lazy" decoding="async"'; ?>>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <div class="kiosk-vignette"></div>
</div>
<div class="kiosk-shell">
    <header class="kiosk-brand">
        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="NIELIT Bhubaneswar">
        <div>
            <h1>NIELIT Bhubaneswar</h1>
            <p>Fingerprint registration &amp; attendance</p>
        </div>
    </header>

    <?php if (!$ipAllowed): ?>
        <div class="card kiosk-card">
            <div class="card-body text-center p-4">
                <div class="blocked-icon mb-2"><i class="fas fa-ban"></i></div>
                <h4 class="mb-2">This device is not authorised</h4>
                <p class="text-muted mb-2">Fingerprint self-service is only available from the institute's approved computer/network.</p>
                <p class="small text-muted mb-0">Your IP: <span class="muted-ip"><?php echo htmlspecialchars($clientIp !== '' ? $clientIp : 'unknown'); ?></span></p>
                <p class="small text-muted">Ask the master admin to allow this IP under <em>Admin → Student Fingerprint Kiosk</em>.</p>
            </div>
        </div>
        <p class="kiosk-foot">This PC · IP <span class="muted-ip"><?php echo htmlspecialchars($clientIp !== '' ? $clientIp : 'unknown'); ?></span></p>
    <?php else: ?>
        <div class="card kiosk-card">
            <div class="card-body p-4">
                <div id="devBanner" class="bio-banner wait">Checking for a fingerprint reader on this PC…</div>

                <div class="mode-tabs">
                    <button type="button" class="active" id="tabAttend">Mark attendance</button>
                    <button type="button" id="tabNew">New registration</button>
                </div>

                <!-- Existing student: last 6 Aadhaar + fingerprint -->
                <div class="step active" id="stepAttend">
                    <div id="attendResult" class="attend-result" hidden>
                        <div id="attendPhoto" class="kiosk-photo-wrap"></div>
                        <div id="attendWho" class="attend-who"></div>
                    </div>
                    <label class="form-label fw-semibold">Last 6 digits of your Aadhaar</label>
                    <input class="form-control form-control-lg mb-3" id="aadhaarLast6" inputmode="numeric" maxlength="6" autocomplete="off" placeholder="______">
                    <button class="btn btn-attend big-btn w-100 mb-2" id="btnAttend"><i class="fas fa-fingerprint"></i> Capture fingerprint for IN / OUT</button>
                    <div id="msgAttend" class="small mt-2"></div>
                </div>

                <!-- New student: identify -->
                <div class="step" id="step1">
                    <label class="form-label fw-semibold">Enter your Student ID, Aadhaar, or mobile number</label>
                    <input class="form-control form-control-lg mb-3" id="identifier" autocomplete="off" placeholder="Student ID / Aadhaar / Mobile">
                    <button class="btn btn-otp big-btn w-100" id="btnOtp"><i class="fas fa-paper-plane"></i> Send OTP to my email</button>
                    <div id="msg1" class="small mt-2"></div>
                </div>

                <!-- New student: OTP -->
                <div class="step" id="step2">
                    <label class="form-label fw-semibold">Enter the 6-digit OTP sent to your email</label>
                    <input class="form-control form-control-lg mb-3" id="otp" inputmode="numeric" maxlength="6" placeholder="______">
                    <button class="btn btn-otp big-btn w-100 mb-2" id="btnVerify"><i class="fas fa-check"></i> Verify OTP</button>
                    <button class="btn btn-link w-100" id="btnResend">Resend / change ID</button>
                    <div id="msg2" class="small mt-2"></div>
                </div>

                <!-- New student: confirm details + enrol fingerprint -->
                <div class="step" id="step3">
                    <div class="text-center mb-3">
                        <div id="regPhoto" class="kiosk-photo-wrap">
                            <div class="kiosk-photo-empty"><i class="fas fa-user"></i></div>
                        </div>
                        <div class="fw-semibold mb-2">Confirm your details</div>
                        <dl class="details-card mb-3" id="whoDetails"></dl>
                        <div class="small text-muted" id="whoState">Capture the same thumb twice to register.</div>
                    </div>
                    <button class="btn btn-attend big-btn w-100 mb-2" id="btnCapture"><i class="fas fa-fingerprint"></i> Capture thumb (1 of 2)</button>
                    <button class="btn btn-link w-100" id="btnDone">Start again</button>
                    <div id="msg3" class="small mt-2"></div>
                </div>
            </div>
        </div>
        <p class="kiosk-foot">Approved device · IP <span class="muted-ip"><?php echo htmlspecialchars($clientIp); ?></span></p>
    <?php endif; ?>
</div>

<?php if ($ipAllowed): ?>
<script>
    window.SECUGEN_WEBAPI_LICSTR = <?php echo json_encode($sgLic); ?>;
    window.SECUGEN_MATCH_THRESHOLD = <?php echo (int) $sgThreshold; ?>;
</script>
<script src="<?php echo htmlspecialchars($jsBase . $vsecu); ?>"></script>
<script src="<?php echo htmlspecialchars($jsBase . $vmantra); ?>"></script>
<script src="<?php echo htmlspecialchars($jsBase . $vrd); ?>"></script>
<script src="<?php echo htmlspecialchars($jsBase . $vdev); ?>"></script>
<script>
(function () {
    const csrf = <?php echo json_encode($csrf); ?>;
    let deviceBase = '';
    let firstIso = '';
    let resetTimer = null;

    function el(id) { return document.getElementById(id); }
    function banner(cls, html) { const b = el('devBanner'); b.className = 'bio-banner ' + cls; b.innerHTML = html; }
    function show(id) {
        ['stepAttend', 'step1', 'step2', 'step3'].forEach(function (s) {
            el(s).classList.toggle('active', s === id);
        });
    }
    function setTab(mode) {
        el('tabAttend').classList.toggle('active', mode === 'attend');
        el('tabNew').classList.toggle('active', mode === 'new');
        if (mode === 'attend') { show('stepAttend'); el('aadhaarLast6').focus(); }
        else { show('step1'); el('identifier').focus(); }
    }
    function msg(id, text, ok) {
        const m = el(id);
        m.className = 'small mt-2 ' + (ok ? 'text-success' : 'text-danger');
        m.textContent = text || '';
    }
    function post(data) {
        const fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(data).forEach(function (k) { if (data[k] != null) fd.append(k, data[k]); });
        return fetch(window.location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) {
                return r.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(text ? text.replace(/<[^>]+>/g, ' ').trim().slice(0, 180) : ('Server error (' + r.status + ')'));
                    }
                });
            });
    }
    function fillDetails(d) {
        renderPhoto('regPhoto', d && d.photo);
        const box = el('whoDetails');
        box.textContent = '';
        const rows = [
            ['Name', d.name || ''],
            ['Student ID', d.student_id || ''],
            ['Mobile', d.mobile_masked || ''],
            ['Aadhaar', d.aadhaar_masked || ''],
            ['Email', d.email_masked || '']
        ];
        rows.forEach(function (r) {
            const dt = document.createElement('dt');
            dt.textContent = r[0];
            const dd = document.createElement('dd');
            dd.textContent = r[1];
            box.appendChild(dt);
            box.appendChild(dd);
        });
    }
    function renderPhoto(containerId, url) {
        const box = el(containerId);
        if (!box) { return; }
        box.textContent = '';
        const clean = String(url || '').trim();
        if (!clean) {
            const empty = document.createElement('div');
            empty.className = 'kiosk-photo-empty';
            empty.innerHTML = '<i class="fas fa-user"></i>';
            box.appendChild(empty);
            return;
        }
        const img = document.createElement('img');
        img.className = 'kiosk-photo';
        img.alt = 'Student photo';
        img.src = clean;
        box.appendChild(img);
    }
    function showAttendResult(name, photoUrl) {
        const wrap = el('attendResult');
        wrap.hidden = false;
        renderPhoto('attendPhoto', photoUrl);
        el('attendWho').textContent = name || '';
    }
    function hideAttendResult() {
        const wrap = el('attendResult');
        wrap.hidden = true;
        el('attendPhoto').textContent = '';
        el('attendWho').textContent = '';
    }
    function goAttend() {
        firstIso = '';
        el('identifier').value = '';
        el('otp').value = '';
        el('aadhaarLast6').value = '';
        hideAttendResult();
        msg('msg1', '', true); msg('msg2', '', true); msg('msg3', '', true); msg('msgAttend', '', true);
        setTab('attend');
    }

    if (!window.BiometricDevice) {
        banner('bad', 'Fingerprint script failed to load.');
    } else {
        window.BiometricDevice.discover().then(function (found) {
            if (found && found.base) {
                deviceBase = found.base;
                banner('ok', '<strong>' + found.label + ' is ready.</strong>');
            } else if (found && found.rdOnly) {
                banner('bad', 'Only the Mantra RD Service is running. It cannot do 1:1 matching. Install the MFS110 Client Service, or use the SecuGen reader.');
            } else {
                banner('bad', 'No fingerprint reader detected. Start the reader service on this PC and refresh.');
            }
        }).catch(function () { banner('bad', 'Could not check the reader. Refresh and try again.'); });
    }

    el('tabAttend').addEventListener('click', function () { setTab('attend'); });
    el('tabNew').addEventListener('click', function () { setTab('new'); });

    el('btnOtp').addEventListener('click', function () {
        const id = el('identifier').value.trim();
        if (!id) { msg('msg1', 'Enter your Student ID, Aadhaar, or mobile.', false); return; }
        const b = el('btnOtp'); b.disabled = true;
        msg('msg1', 'Sending OTP…', true);
        post({ action: 'request_otp', identifier: id }).then(function (d) {
            b.disabled = false;
            if (d.already_enrolled) {
                msg('msg1', d.message, false);
                setTab('attend');
                msg('msgAttend', d.message, false);
                return;
            }
            if (d.success) { msg('msg1', '', true); show('step2'); el('otp').focus(); msg('msg2', d.message, true); }
            else { msg('msg1', d.message || 'Could not send OTP.', false); }
        }).catch(function (err) { b.disabled = false; msg('msg1', (err && err.message) ? err.message : 'Network error.', false); });
    });

    el('btnVerify').addEventListener('click', function () {
        const otp = el('otp').value.trim();
        if (otp.length < 4) { msg('msg2', 'Enter the OTP from your email.', false); return; }
        const b = el('btnVerify'); b.disabled = true;
        post({ action: 'verify_otp', otp: otp }).then(function (d) {
            b.disabled = false;
            if (!d.success) { msg('msg2', d.message || 'Verification failed.', false); return; }
            if (d.has_fingerprint) {
                setTab('attend');
                msg('msgAttend', 'Your fingerprint is already registered. Enter the last 6 digits of Aadhaar to mark attendance.', false);
                return;
            }
            fillDetails(d.details || { name: d.name, student_id: d.student_id });
            el('whoState').textContent = 'Capture the same thumb twice to register.';
            el('btnCapture').innerHTML = '<i class="fas fa-fingerprint"></i> Capture thumb (1 of 2)';
            firstIso = '';
            show('step3');
        }).catch(function () { b.disabled = false; msg('msg2', 'Network error.', false); });
    });

    el('btnResend').addEventListener('click', function () {
        show('step1'); msg('msg1', '', true); el('identifier').focus();
    });

    el('btnCapture').addEventListener('click', function () {
        if (!deviceBase) { msg('msg3', 'No reader detected on this PC.', false); return; }
        const b = el('btnCapture'); b.disabled = true;
        msg('msg3', 'Place your thumb on the reader…', true);
        window.BiometricDevice.capture(deviceBase).then(function (cap) {
            if (!firstIso) {
                firstIso = cap.iso;
                b.disabled = false;
                b.innerHTML = '<i class="fas fa-fingerprint"></i> Capture thumb (2 of 2)';
                msg('msg3', 'First capture saved. Lift the thumb, then capture again.', true);
                return;
            }
            return window.BiometricDevice.match(deviceBase, cap.iso, firstIso).then(function (m) {
                if (!m.matched) {
                    firstIso = '';
                    b.disabled = false;
                    b.innerHTML = '<i class="fas fa-fingerprint"></i> Capture thumb (1 of 2)';
                    throw new Error('The two captures did not match (score ' + m.score + '). Start again with the same thumb.');
                }
                return post({ action: 'enroll_save', iso_template: firstIso, quality: String(cap.quality || 0) }).then(function (d) {
                    firstIso = '';
                    b.disabled = false;
                    if (d.success) {
                        el('whoState').textContent = 'Registered. Use Mark attendance next time (last 6 digits of Aadhaar).';
                        msg('msg3', d.message, true);
                        if (resetTimer) clearTimeout(resetTimer);
                        resetTimer = setTimeout(goAttend, 2500);
                    } else {
                        b.innerHTML = '<i class="fas fa-fingerprint"></i> Capture thumb (1 of 2)';
                        msg('msg3', d.message || 'Could not register.', false);
                    }
                });
            });
        }).catch(function (err) {
            b.disabled = false;
            msg('msg3', (err && err.message) ? err.message : 'Capture failed.', false);
        });
    });

    el('btnAttend').addEventListener('click', function () {
        if (!deviceBase) { msg('msgAttend', 'No reader detected on this PC.', false); return; }
        const last6 = el('aadhaarLast6').value.replace(/\D/g, '');
        if (last6.length !== 6) { msg('msgAttend', 'Enter the last 6 digits of your Aadhaar.', false); return; }
        const b = el('btnAttend'); b.disabled = true;
        hideAttendResult();
        msg('msgAttend', 'Checking… then place your thumb on the reader.', true);
        post({ action: 'lookup_existing', aadhaar_last6: last6 }).then(function (d) {
            if (!d.success || !d.candidates || !d.candidates.length) {
                throw new Error(d.message || 'No registered fingerprint for this Aadhaar. Use New registration.');
            }
            return window.BiometricDevice.capture(deviceBase).then(function (cap) {
                var seq = Promise.resolve(null);
                d.candidates.forEach(function (c) {
                    seq = seq.then(function (hit) {
                        if (hit) return hit;
                        return window.BiometricDevice.match(deviceBase, cap.iso, c.iso_template).then(function (m) {
                            return m.matched ? { student: c, cap: cap } : null;
                        });
                    });
                });
                return seq.then(function (hit) {
                    if (!hit) {
                        throw new Error('Fingerprint did not match. Attendance not marked.');
                    }
                    return post({
                        action: 'mark_attendance_existing',
                        aadhaar_last6: last6,
                        student_id: hit.student.student_id,
                        iso_template: hit.cap.iso,
                        client_matched: '1'
                    }).then(function (res) {
                        if (!res.success) {
                            throw new Error(res.message || 'Attendance not marked.');
                        }
                        const who = res.name ? (res.name + ' — ') : '';
                        const detail = res.message || (((res.scan_type === 'out') ? 'OUT' : 'IN') + ' attendance recorded' + (res.scan_time ? (' at ' + res.scan_time) : ''));
                        showAttendResult(res.name || '', res.photo || '');
                        msg('msgAttend', who + detail, true);
                        if (resetTimer) clearTimeout(resetTimer);
                        resetTimer = setTimeout(function () {
                            post({ action: 'reset' }).finally(goAttend);
                        }, 4000);
                    });
                });
            });
        }).catch(function (err) {
            msg('msgAttend', (err && err.message) ? err.message : 'Could not mark attendance.', false);
        }).finally(function () {
            b.disabled = false;
        });
    });

    el('btnDone').addEventListener('click', function () {
        post({ action: 'reset' }).finally(function () {
            firstIso = '';
            goAttend();
            setTab('new');
        });
    });
})();
</script>
<?php endif; ?>
<script>
(function () {
    var slides = document.querySelectorAll('.kiosk-slide');
    if (slides.length < 2) { return; }
    var i = 0;
    setInterval(function () {
        slides[i].classList.remove('active');
        i = (i + 1) % slides.length;
        slides[i].classList.add('active');
    }, 8000);
})();
</script>
</body>
</html>
<?php $conn->close(); ?>
