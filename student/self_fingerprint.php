<?php
/**
 * Student self-service fingerprint kiosk.
 *
 * Flow:
 *   1) Page only works from a static IP the master admin has allowed
 *      (Admin -> Student Fingerprint Kiosk). Any other IP is blocked.
 *   2) Student enters Student ID / Aadhaar / mobile -> OTP emailed -> verify.
 *   3) If not enrolled: capture the same thumb twice to register it.
 *      If enrolled: capture once to mark attendance (1:1 match) for the
 *      active session of their class.
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
            if ($status !== '' && $status !== 'active') {
                biometricKioskJsonExit(['success' => false, 'message' => 'This student account is not active.']);
            }
            $sent = studentKioskSendOtp($conn, $found['row']);
            biometricKioskJsonExit([
                'success' => $sent['success'],
                'message' => $sent['success'] ? ('OTP sent to ' . ($sent['masked'] ?? 'your email') . '. Check inbox and spam.') : $sent['message'],
            ]);
        }

        if ($action === 'verify_otp') {
            $res = studentKioskVerifyOtp((string) ($_POST['otp'] ?? ''));
            if (empty($res['success'])) {
                biometricKioskJsonExit(['success' => false, 'message' => $res['message']]);
            }
            $sid = (string) ($res['student_id'] ?? '');
            $look = studentKioskLookup($conn, $sid);
            $name = $look['ok'] ? (string) ($look['row']['name'] ?? '') : '';
            biometricKioskJsonExit([
                'success' => true,
                'message' => 'Identity verified.',
                'student_id' => $sid,
                'name' => $name,
                'has_fingerprint' => studentHasFingerprintTemplate($conn, $sid),
            ]);
        }

        // All actions below require a verified identity in the session.
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
            $sess = studentKioskActiveSessionForStudent($conn, $verifiedSid);
            if (empty($sess['ok'])) {
                biometricKioskJsonExit(['success' => false, 'message' => (string) $sess['message']]);
            }
            // Auto-supply the student's own Aadhaar last-4 (identity already proven by OTP).
            $aadhaarLast4 = '';
            $look = studentKioskLookup($conn, $verifiedSid);
            if ($look['ok']) {
                $digits = preg_replace('/\D/', '', (string) ($look['row']['aadhar'] ?? ''));
                if (strlen((string) $digits) >= 4) {
                    $aadhaarLast4 = substr((string) $digits, -4);
                }
            }
            $result = processBiometricMatchAttendanceFromIso(
                $conn,
                (int) $sess['session_id'],
                $verifiedSid,
                'self:' . $verifiedSid,
                $iso,
                $aadhaarLast4,
                null,
                (string) ($_POST['client_matched'] ?? '') === '1'
            );
            biometricKioskJsonExit(is_array($result) ? $result : ['success' => false, 'message' => 'Could not save attendance.']);
        }

        if ($action === 'reset') {
            studentKioskClearVerification();
            biometricKioskJsonExit(['success' => true]);
        }

        biometricKioskJsonExit(['success' => false, 'message' => 'Unknown action.']);
    } catch (Throwable $e) {
        error_log('student self_fingerprint: ' . $e->getMessage());
        biometricKioskJsonExit(['success' => false, 'message' => 'Something went wrong. Try again.']);
    }
}

$sgLic = defined('SECUGEN_WEBAPI_LICSTR') ? (string) SECUGEN_WEBAPI_LICSTR : '';
$sgThreshold = defined('SECUGEN_MATCH_THRESHOLD') ? (int) SECUGEN_MATCH_THRESHOLD : 100;
$jsBase = (defined('APP_URL') ? rtrim(APP_URL, '/') : '');
$vsecu = '/assets/js/secugen_webapi.js?v=' . (@filemtime(__DIR__ . '/../assets/js/secugen_webapi.js') ?: time());
$vmantra = '/assets/js/mantra_mfs100.js?v=' . (@filemtime(__DIR__ . '/../assets/js/mantra_mfs100.js') ?: time());
$vrd = '/assets/js/mantra_rd.js?v=' . (@filemtime(__DIR__ . '/../assets/js/mantra_rd.js') ?: time());
$vdev = '/assets/js/biometric_device.js?v=' . (@filemtime(__DIR__ . '/../assets/js/biometric_device.js') ?: time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fingerprint Self-Service - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f1f5f9; }
        .kiosk-shell { max-width: 560px; margin: 0 auto; padding: 24px 16px 48px; }
        .kiosk-card { border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        .brand { text-align:center; margin-bottom: 18px; }
        .brand h1 { font-size: 1.35rem; font-weight: 800; color:#0a1628; margin:0; }
        .brand p { color:#64748b; margin:2px 0 0; }
        .bio-banner { border-radius: 10px; padding: 0.8rem 1rem; margin-bottom: 1rem; font-size:.95rem; }
        .bio-banner.ok { background:#d1fae5; color:#065f46; }
        .bio-banner.wait { background:#fef3c7; color:#92400e; }
        .bio-banner.bad { background:#fee2e2; color:#991b1b; }
        .step { display:none; }
        .step.active { display:block; }
        .big-btn { padding: 0.9rem; font-size: 1.05rem; border-radius: 12px; }
        .fp-icon { font-size: 3rem; color:#0d9488; }
        .muted-ip { font-family: Consolas, Monaco, monospace; }
    </style>
</head>
<body>
<div class="kiosk-shell">
    <div class="brand">
        <h1><i class="fas fa-fingerprint text-teal"></i> NIELIT Bhubaneswar</h1>
        <p>Student Fingerprint Registration &amp; Attendance</p>
    </div>

    <?php if (!$ipAllowed): ?>
        <div class="card kiosk-card">
            <div class="card-body text-center p-4">
                <div class="fp-icon mb-2"><i class="fas fa-ban text-danger"></i></div>
                <h4 class="mb-2">This device is not authorised</h4>
                <p class="text-muted mb-2">Fingerprint self-service is only available from the institute's approved computer/network.</p>
                <p class="small text-muted mb-0">Your IP: <span class="muted-ip"><?php echo htmlspecialchars($clientIp !== '' ? $clientIp : 'unknown'); ?></span></p>
                <p class="small text-muted">Ask the master admin to allow this IP under <em>Admin → Student Fingerprint Kiosk</em>.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card kiosk-card">
            <div class="card-body p-4">
                <div id="devBanner" class="bio-banner wait">Checking for a fingerprint reader on this PC…</div>

                <!-- Step 1: identify -->
                <div class="step active" id="step1">
                    <label class="form-label fw-semibold">Enter your Student ID, Aadhaar, or mobile number</label>
                    <input class="form-control form-control-lg mb-3" id="identifier" autocomplete="off" placeholder="Student ID / Aadhaar / Mobile">
                    <button class="btn btn-primary big-btn w-100" id="btnOtp"><i class="fas fa-paper-plane"></i> Send OTP to my email</button>
                    <div id="msg1" class="small mt-2"></div>
                </div>

                <!-- Step 2: OTP -->
                <div class="step" id="step2">
                    <label class="form-label fw-semibold">Enter the 6-digit OTP sent to your email</label>
                    <input class="form-control form-control-lg mb-3" id="otp" inputmode="numeric" maxlength="6" placeholder="______">
                    <button class="btn btn-primary big-btn w-100 mb-2" id="btnVerify"><i class="fas fa-check"></i> Verify OTP</button>
                    <button class="btn btn-link w-100" id="btnResend">Resend / change ID</button>
                    <div id="msg2" class="small mt-2"></div>
                </div>

                <!-- Step 3: fingerprint -->
                <div class="step" id="step3">
                    <div class="text-center mb-3">
                        <div class="fp-icon mb-2"><i class="fas fa-fingerprint"></i></div>
                        <div class="fw-semibold" id="whoName"></div>
                        <div class="small text-muted" id="whoState"></div>
                    </div>
                    <button class="btn btn-success big-btn w-100 mb-2" id="btnCapture"><i class="fas fa-fingerprint"></i> Capture thumb</button>
                    <button class="btn btn-link w-100" id="btnDone">Finish / next student</button>
                    <div id="msg3" class="small mt-2"></div>
                </div>
            </div>
        </div>
        <p class="text-center small text-muted mt-3">Approved device · IP <span class="muted-ip"><?php echo htmlspecialchars($clientIp); ?></span></p>
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
    let enrolled = false;
    let firstIso = '';

    function el(id) { return document.getElementById(id); }
    function banner(cls, html) { const b = el('devBanner'); b.className = 'bio-banner ' + cls; b.innerHTML = html; }
    function showStep(n) {
        ['step1', 'step2', 'step3'].forEach(function (s, i) {
            el(s).classList.toggle('active', (i + 1) === n);
        });
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
        return fetch(window.location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    // Detect reader
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

    // Step 1 -> OTP
    el('btnOtp').addEventListener('click', function () {
        const id = el('identifier').value.trim();
        if (!id) { msg('msg1', 'Enter your Student ID, Aadhaar, or mobile.', false); return; }
        const b = el('btnOtp'); b.disabled = true;
        msg('msg1', 'Sending OTP…', true);
        post({ action: 'request_otp', identifier: id }).then(function (d) {
            b.disabled = false;
            if (d.success) { msg('msg1', '', true); showStep(2); el('otp').focus(); msg('msg2', d.message, true); }
            else { msg('msg1', d.message || 'Could not send OTP.', false); }
        }).catch(function () { b.disabled = false; msg('msg1', 'Network error.', false); });
    });

    // Step 2 -> verify
    el('btnVerify').addEventListener('click', function () {
        const otp = el('otp').value.trim();
        if (otp.length < 4) { msg('msg2', 'Enter the OTP from your email.', false); return; }
        const b = el('btnVerify'); b.disabled = true;
        post({ action: 'verify_otp', otp: otp }).then(function (d) {
            b.disabled = false;
            if (!d.success) { msg('msg2', d.message || 'Verification failed.', false); return; }
            enrolled = !!d.has_fingerprint;
            el('whoName').textContent = (d.name || '') + '  ·  ' + (d.student_id || '');
            el('whoState').textContent = enrolled
                ? 'Registered. Capture your thumb to mark attendance.'
                : 'Not registered yet. Capture the same thumb twice to register.';
            el('btnCapture').innerHTML = enrolled
                ? '<i class="fas fa-fingerprint"></i> Capture to mark attendance'
                : '<i class="fas fa-fingerprint"></i> Capture thumb (1 of 2)';
            firstIso = '';
            showStep(3);
        }).catch(function () { b.disabled = false; msg('msg2', 'Network error.', false); });
    });

    el('btnResend').addEventListener('click', function () {
        showStep(1); msg('msg1', '', true); el('identifier').focus();
    });

    // Step 3 -> capture (enrol or mark)
    el('btnCapture').addEventListener('click', function () {
        if (!deviceBase) { msg('msg3', 'No reader detected on this PC.', false); return; }
        const b = el('btnCapture'); b.disabled = true;
        msg('msg3', 'Place your thumb on the reader…', true);

        if (!enrolled) {
            // Enrolment: capture twice, confirm match, save.
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
                        if (d.success) {
                            enrolled = true;
                            el('whoState').textContent = 'Registered. Capture your thumb to mark attendance.';
                            b.innerHTML = '<i class="fas fa-fingerprint"></i> Capture to mark attendance';
                            b.disabled = false;
                            msg('msg3', d.message, true);
                        } else {
                            b.disabled = false;
                            b.innerHTML = '<i class="fas fa-fingerprint"></i> Capture thumb (1 of 2)';
                            msg('msg3', d.message || 'Could not register.', false);
                        }
                    });
                });
            }).catch(function (err) {
                b.disabled = false;
                msg('msg3', (err && err.message) ? err.message : 'Capture failed.', false);
            });
            return;
        }

        // Attendance: fetch stored template, capture once, match, mark.
        post({ action: 'get_gallery' }).then(function (gal) {
            if (!gal.success || !gal.iso_template) { throw new Error(gal.message || 'No registered fingerprint.'); }
            return window.BiometricDevice.capture(deviceBase).then(function (cap) {
                return window.BiometricDevice.match(deviceBase, cap.iso, gal.iso_template).then(function (m) {
                    if (!m.matched) { throw new Error('Fingerprint did not match (score ' + m.score + '). Attendance not marked.'); }
                    return post({ action: 'mark_attendance', iso_template: cap.iso, client_matched: '1' }).then(function (d) {
                        b.disabled = false;
                        if (d.success) {
                            const kind = (d.scan_type === 'out') ? 'OUT' : 'IN';
                            const time = d.scan_time ? (' at ' + d.scan_time) : '';
                            msg('msg3', kind + ' attendance recorded' + time + '. Done!', true);
                        } else {
                            msg('msg3', d.message || 'Attendance not marked.', false);
                        }
                    });
                });
            });
        }).catch(function (err) {
            b.disabled = false;
            msg('msg3', (err && err.message) ? err.message : 'Capture failed.', false);
        });
    });

    el('btnDone').addEventListener('click', function () {
        post({ action: 'reset' }).finally(function () {
            firstIso = ''; enrolled = false;
            el('identifier').value = ''; el('otp').value = '';
            msg('msg1', '', true); msg('msg2', '', true); msg('msg3', '', true);
            showStep(1); el('identifier').focus();
        });
    });
})();
</script>
<?php endif; ?>
</body>
</html>
<?php $conn->close(); ?>
