<?php
/**
 * Enrol student fingerprint ISO templates on the Mantra MFS110 kiosk PC.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/attendance_in_out_helper.php';
require_once __DIR__ . '/../includes/biometric_attendance_helper.php';
require_once __DIR__ . '/../includes/mantra_mfs100_helper.php';

if (!isset($_SESSION['admin'])) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        biometricKioskJsonExit(['success' => false, 'message' => 'Session expired. Refresh the page and log in again.']);
    }
    header('Location: login.php');
    exit;
}

$admin_id = (string) $_SESSION['admin'];
ensureFingerprintTemplateTables($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ini_set('display_errors', '0');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        biometricKioskJsonExit(['success' => false, 'message' => 'Invalid security token. Refresh the page.']);
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'mfs100_discover') {
            $found = mantraMfs100DiscoverLocal();
            if ($found) {
                biometricKioskJsonExit(['success' => true, 'base' => $found['base'], 'via' => 'php']);
            }
            biometricKioskJsonExit(['success' => false, 'message' => 'MFS110 Client Service was not found on this PC.']);
        }
        if ($action === 'lookup_student') {
            $found = lookupStudentForFingerprintEnrol($conn, (string) ($_POST['q'] ?? ''));
            if (empty($found['ok']) || empty($found['row'])) {
                biometricKioskJsonExit(['success' => false, 'message' => (string) ($found['message'] ?? 'Student not found.')]);
            }
            $row = $found['row'];
            unset($_SESSION['fp_enroll_iso'], $_SESSION['fp_enroll_student']);
            biometricKioskJsonExit([
                'success' => true,
                'student_id' => (string) $row['student_id'],
                'name' => (string) ($row['name'] ?? ''),
                'photo' => biometricStudentPhotoUrl($row),
                'has_fingerprint' => studentHasFingerprintTemplate($conn, (string) $row['student_id']),
            ]);
        }
        if ($action === 'enroll_capture') {
            @set_time_limit(90);
            $studentId = trim((string) ($_POST['student_id'] ?? ''));
            if ($studentId === '') {
                biometricKioskJsonExit(['success' => false, 'message' => 'Find the student first.']);
            }
            $cap = mantraMfs100CaptureLocal();
            if (!$cap['ok']) {
                biometricKioskJsonExit(['success' => false, 'message' => $cap['message']]);
            }
            $first = (string) ($_SESSION['fp_enroll_iso'] ?? '');
            $sid = (string) ($_SESSION['fp_enroll_student'] ?? '');
            if ($first === '' || $sid !== $studentId) {
                $_SESSION['fp_enroll_iso'] = $cap['iso'];
                $_SESSION['fp_enroll_student'] = $studentId;
                $_SESSION['fp_enroll_quality'] = (int) $cap['quality'];
                biometricKioskJsonExit([
                    'success' => true,
                    'step' => 1,
                    'message' => 'First capture saved. Lift the thumb, then capture again to confirm.',
                ]);
            }
            $match = mantraMfs100MatchLocal($cap['iso'], $first);
            if (!$match['ok'] || !$match['matched']) {
                unset($_SESSION['fp_enroll_iso'], $_SESSION['fp_enroll_student']);
                biometricKioskJsonExit([
                    'success' => false,
                    'message' => 'The two captures did not match. Start again with the same thumb.',
                ]);
            }
            saveStudentFingerprintTemplate(
                $conn,
                $studentId,
                $first,
                $admin_id,
                'R1',
                (int) ($_SESSION['fp_enroll_quality'] ?? $cap['quality'])
            );
            unset($_SESSION['fp_enroll_iso'], $_SESSION['fp_enroll_student'], $_SESSION['fp_enroll_quality']);
            biometricKioskJsonExit([
                'success' => true,
                'step' => 2,
                'saved' => true,
                'message' => 'Fingerprint enrolled. Attendance will now accept only this student’s thumb.',
            ]);
        }
        if ($action === 'enroll_save') {
            $studentId = trim((string) ($_POST['student_id'] ?? ''));
            $iso = trim((string) ($_POST['iso_template'] ?? ''));
            $quality = (int) ($_POST['quality'] ?? 0);
            if ($studentId === '' || strlen($iso) < 40) {
                biometricKioskJsonExit(['success' => false, 'message' => 'Capture the fingerprint again.']);
            }
            $found = lookupStudentForFingerprintEnrol($conn, $studentId);
            if (empty($found['ok'])) {
                biometricKioskJsonExit(['success' => false, 'message' => 'Student not found.']);
            }
            saveStudentFingerprintTemplate($conn, $studentId, $iso, $admin_id, 'R1', $quality);
            biometricKioskJsonExit([
                'success' => true,
                'saved' => true,
                'message' => 'Fingerprint enrolled. Attendance will now accept only this student’s thumb.',
            ]);
        }
        if ($action === 'enroll_reset') {
            unset($_SESSION['fp_enroll_iso'], $_SESSION['fp_enroll_student'], $_SESSION['fp_enroll_quality']);
            biometricKioskJsonExit(['success' => true]);
        }
        biometricKioskJsonExit(['success' => false, 'message' => 'Unknown action.']);
    } catch (Throwable $e) {
        error_log('fingerprint enrol: ' . $e->getMessage());
        biometricKioskJsonExit(['success' => false, 'message' => 'Could not enrol fingerprint: ' . $e->getMessage()]);
    }
}

$active_theme = loadActiveTheme($conn);
$jsPath = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/assets/js/secugen_webapi.js?v=' . (@filemtime(__DIR__ . '/../assets/js/secugen_webapi.js') ?: time());
$sgLic = defined('SECUGEN_WEBAPI_LICSTR') ? (string) SECUGEN_WEBAPI_LICSTR : '';
$sgThreshold = defined('SECUGEN_MATCH_THRESHOLD') ? (int) SECUGEN_MATCH_THRESHOLD : 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fingerprint Enrolment - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .bio-banner { border-radius: 10px; padding: 0.85rem 1rem; margin-bottom: 1rem; }
        .bio-banner.ok { background: #d1fae5; color: #065f46; }
        .bio-banner.wait { background: #fef3c7; color: #92400e; }
        .bio-banner.bad { background: #fee2e2; color: #991b1b; }
        .kiosk-photo { width: 160px; height: 190px; object-fit: cover; border-radius: 10px; background: #e2e8f0; }
        .kiosk-photo-empty { width: 160px; height: 190px; border-radius: 10px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="mb-0"><i class="fas fa-fingerprint"></i> Fingerprint Enrolment</h2>
                <p class="text-muted mb-0">Save each student’s thumb once. Attendance will then reject any other finger.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(app_url('admin/attendance_biometric')); ?>">Fingerprint Attendance</a>
        </div>

        <div id="mfsBanner" class="bio-banner wait">Checking SecuGen WebAPI on this PC…</div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Scanner PC setup (SecuGen Hamster Pro 20)</h5></div>
            <div class="card-body">
                <ol class="mb-0">
                    <li>Plug the <strong>SecuGen Hamster Pro 20</strong> into a direct USB port and install the SecuGen driver.</li>
                    <li>Install and start the <strong>SecuGen WebAPI</strong> service (<code>SGIBIOSRV</code>) on this PC.</li>
                    <li>Open <a href="https://localhost:8443/SGIFPCapture" target="_blank" rel="noopener">https://localhost:8443/SGIFPCapture</a> once and accept the certificate if the browser warns.</li>
                    <li>Find the student, confirm the photo, capture the same finger twice.</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Student ID</label>
                        <div class="input-group mb-2">
                            <input class="form-control" id="lookupQ" autocomplete="off" placeholder="Type full Student ID then Find">
                            <button class="btn btn-secondary" type="button" id="btnFind">Find</button>
                        </div>
                        <button class="btn btn-success w-100 mb-2" type="button" id="btnCapture" disabled>
                            <i class="fas fa-fingerprint"></i> Capture thumb
                        </button>
                        <div id="enrolMsg" class="small"></div>
                    </div>
                    <div class="col-md-5 text-center">
                        <div id="photoBox" class="d-flex justify-content-center mb-2">
                            <div class="kiosk-photo-empty"><i class="fas fa-user fa-3x"></i></div>
                        </div>
                        <div id="studentMeta" class="fw-semibold"></div>
                        <div id="enrolStatus" class="small text-muted mt-1"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    window.SECUGEN_WEBAPI_LICSTR = <?php echo json_encode($sgLic); ?>;
    window.SECUGEN_MATCH_THRESHOLD = <?php echo (int) $sgThreshold; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo htmlspecialchars($jsPath); ?>"></script>
<script>
(function () {
    const csrf = <?php echo json_encode($csrf); ?>;
    let foundStudent = null;
    let mfsBase = '';
    let firstIso = '';

    function banner(cls, html) {
        const el = document.getElementById('mfsBanner');
        el.className = 'bio-banner ' + cls;
        el.innerHTML = html;
    }
    function msg(text, ok) {
        const el = document.getElementById('enrolMsg');
        el.className = 'small ' + (ok ? 'text-success' : 'text-danger');
        el.textContent = text || '';
    }
    function post(data) {
        const fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(data).forEach(function (k) {
            if (data[k] !== undefined && data[k] !== null) {
                fd.append(k, data[k]);
            }
        });
        return fetch(window.location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
            return r.json();
        });
    }

    if (!window.SecuGenWebApi) {
        banner('bad', 'Fingerprint script failed to load.');
    } else {
        window.SecuGenWebApi.discover().then(function (found) {
            if (found && found.base) {
                mfsBase = found.base;
                banner('ok', '<strong>SecuGen WebAPI is running</strong> (' + found.base + '). Find a student and enrol their finger.');
                const btn = document.getElementById('btnCapture');
                if (foundStudent && btn) {
                    btn.disabled = false;
                }
                return;
            }
            banner('bad', '<strong>SecuGen WebAPI is not listening.</strong> Start the SGIBIOSRV service on this PC, plug in the Hamster Pro 20, then refresh.');
        }).catch(function () {
            banner('bad', 'Could not check SecuGen WebAPI. Start SGIBIOSRV on this computer, then refresh.');
        });
    }

    document.getElementById('btnFind').addEventListener('click', findStudent);
    document.getElementById('lookupQ').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            findStudent();
        }
    });

    function findStudent() {
        firstIso = '';
        post({ action: 'enroll_reset' });
        post({ action: 'lookup_student', q: document.getElementById('lookupQ').value }).then(function (data) {
            if (!data.success) {
                foundStudent = null;
                document.getElementById('btnCapture').disabled = true;
                msg(data.message || 'Not found', false);
                return;
            }
            foundStudent = data;
            document.getElementById('studentMeta').textContent = data.name + '  ·  ' + data.student_id;
            document.getElementById('enrolStatus').textContent = data.has_fingerprint ? 'Already enrolled — capture twice to replace.' : 'Not enrolled yet.';
            if (data.photo) {
                document.getElementById('photoBox').innerHTML = '<img class="kiosk-photo" alt="" src="' + data.photo + '">';
            } else {
                document.getElementById('photoBox').innerHTML = '<div class="kiosk-photo-empty">No photo</div>';
            }
            document.getElementById('btnCapture').disabled = !mfsBase;
            msg('Confirm the photo, then capture the same finger twice.', true);
        });
    }

    document.getElementById('btnCapture').addEventListener('click', function () {
        if (!foundStudent) {
            return;
        }
        if (!mfsBase) {
            msg('SecuGen WebAPI is not running on this PC.', false);
            return;
        }
        const btn = document.getElementById('btnCapture');
        btn.disabled = true;
        msg('Place the finger on the SecuGen reader…', true);
        const after = function (data) {
            if (data.success && data.saved) {
                msg(data.message, true);
                document.getElementById('enrolStatus').textContent = 'Enrolled.';
                firstIso = '';
                btn.disabled = false;
                return;
            }
            msg(data.message || 'Enrolment failed', false);
            btn.disabled = false;
        };
        window.SecuGenWebApi.capture(mfsBase).then(function (cap) {
            if (!firstIso) {
                firstIso = cap.iso;
                msg('First capture saved. Lift the finger, then capture again to confirm.', true);
                btn.disabled = false;
                return;
            }
            return window.SecuGenWebApi.match(mfsBase, cap.iso, firstIso).then(function (m) {
                if (!m.matched) {
                    firstIso = '';
                    throw new Error('The two captures did not match (score ' + m.score + '). Start again with the same finger.');
                }
                return post({
                    action: 'enroll_save',
                    student_id: foundStudent.student_id,
                    iso_template: firstIso,
                    quality: String(cap.quality || 0)
                }).then(function (data) {
                    firstIso = '';
                    after(data);
                });
            });
        }).catch(function (err) {
            msg((err && err.message) ? err.message : 'Capture failed.', false);
            btn.disabled = false;
        });
    });
})();
</script>
</body>
</html>
