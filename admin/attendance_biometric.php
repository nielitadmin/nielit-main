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

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$admin_id = (string) $_SESSION['admin'];
$admin_name = (string) ($_SESSION['admin_name'] ?? 'Administrator');
ensureBiometricAttendanceTables($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Refresh the page.']);
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');

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
        echo json_encode($result);
        exit;
    }
    if ($action === 'activate_session') {
        echo json_encode(['success' => activateAttendanceSession((int) ($_POST['session_id'] ?? 0), $admin_id, $conn)]);
        exit;
    }
    if ($action === 'deactivate_session') {
        echo json_encode(['success' => deactivateAttendanceSession((int) ($_POST['session_id'] ?? 0), $admin_id, $conn)]);
        exit;
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
            echo json_encode(['success' => false, 'message' => 'Start an attendance session first.']);
            exit;
        }
        $row = lookupBiometricKioskStudent($conn, $q, (int) $session['course_id']);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'No matching student in this course. Use full Student ID, mobile, or Aadhaar.']);
            exit;
        }
        $status = (string) ($row['status'] ?? '');
        if ($status !== '' && $status !== 'active') {
            echo json_encode(['success' => false, 'message' => 'This student is not active.']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'student_id' => (string) $row['student_id'],
            'name' => (string) ($row['name'] ?? ''),
            'photo' => biometricStudentPhotoUrl($row),
            'need_aadhaar_last4' => biometricAadhaarLast4((string) ($row['aadhar'] ?? '')) !== '',
        ]);
        exit;
    }
    if ($action === 'mark') {
        $result = processBiometricKioskAttendance(
            $conn,
            (int) ($_POST['session_id'] ?? 0),
            (string) ($_POST['student_id'] ?? ''),
            (string) ($_POST['aadhaar_last4'] ?? ''),
            (string) ($_POST['pid_xml'] ?? ''),
            $admin_id
        );
        echo json_encode($result);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

$active_sessions = getActiveAttendanceSessions($admin_id, $conn);
$openSessionId = (int) ($_GET['session_id'] ?? 0);
$courses_query = "SELECT id, course_name, course_code FROM courses WHERE status = 'active' ORDER BY course_name";
$courses_result = $conn->query($courses_query);
$courses = $courses_result ? $courses_result->fetch_all(MYSQLI_ASSOC) : [];
if ($courses === []) {
    $courses_result_all = $conn->query('SELECT id, course_name, course_code FROM courses ORDER BY course_name');
    $courses = $courses_result_all ? $courses_result_all->fetch_all(MYSQLI_ASSOC) : [];
}
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
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="row mb-3">
            <div class="col-12">
                <h2><i class="fas fa-fingerprint"></i> Fingerprint Attendance</h2>
                <p class="text-muted mb-0">Mantra L1 kiosk — a live fingerprint is required. QR codes can be shared; a finger cannot. Open this page on the PC where the scanner is plugged in.</p>
            </div>
        </div>

        <div id="rdBanner" class="bio-banner wait">Checking Mantra RD Service on this PC…</div>

        <div class="row mb-4">
            <div class="col-md-6">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSessionModal">
                    <i class="fas fa-plus"></i> Create session
                </button>
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
                                        <p class="mb-1"><strong>Course:</strong> <?php echo htmlspecialchars((string) $session['course_name']); ?></p>
                                        <p class="mb-1"><strong>Subject:</strong> <?php echo htmlspecialchars((string) $session['subject']); ?></p>
                                        <p class="mb-3"><strong>Date:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime((string) $session['date']))); ?></p>
                                        <?php if ($session['status'] === 'scheduled'): ?>
                                            <button class="btn btn-success btn-sm w-100" type="button" onclick="activateSession(<?php echo (int) $session['id']; ?>)">Start session</button>
                                        <?php elseif ($session['status'] === 'active'): ?>
                                            <button class="btn btn-primary btn-sm w-100 mb-2" type="button" onclick="openKiosk(<?php echo (int) $session['id']; ?>, <?php echo json_encode((string) $session['session_name']); ?>)">
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
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id" required onchange="document.getElementById('course_name').value = this.options[this.selectedIndex].getAttribute('data-name') || '';">
                            <option value="">Select course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo (int) $course['id']; ?>" data-name="<?php echo htmlspecialchars((string) $course['course_name']); ?>">
                                    <?php echo htmlspecialchars((string) $course['course_name']); ?>
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

<div class="modal fade" id="kioskModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kioskTitle">Fingerprint kiosk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted" id="kioskHint">Find the student, confirm the photo, then capture fingerprint. One person at a time — QR sharing cannot mark someone else.</p>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo htmlspecialchars($jsPath); ?>"></script>
<script>
(function () {
    const csrf = <?php echo json_encode($csrf); ?>;
    const openSessionId = <?php echo (int) $openSessionId; ?>;
    let rdOrigin = '';
    let sessionId = 0;
    let foundStudent = null;

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
    function post(data) {
        const fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        return fetch('attendance_biometric.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
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

    if (window.MantraRd) {
        MantraRd.discover().then(function (found) {
            if (found && found.origin) {
                rdOrigin = found.origin;
                banner('ok', '<strong>Mantra device ready</strong> on this PC (' + found.origin + '). Start a session and open the kiosk.');
            } else {
                banner('bad', '<strong>Mantra RD Service not found.</strong> Install Mantra L1 driver + RD Service on this Windows PC, plug in the scanner, then open <code>http://127.0.0.1:11100/</code>. This page must be used on that same PC — not on a phone.');
            }
        });
    } else {
        banner('bad', 'Fingerprint script failed to load.');
    }

    document.getElementById('createSessionForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'create_session');
        fd.append('csrf_token', csrf);
        fetch('attendance_biometric.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
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
    window.openKiosk = function (id, name) {
        sessionId = id;
        resetStudent();
        kioskMsg('');
        document.getElementById('kioskTitle').textContent = 'Fingerprint kiosk — ' + (name || '');
        new bootstrap.Modal(document.getElementById('kioskModal')).show();
        document.getElementById('lookupQ').focus();
    };

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
        MantraRd.capture(rdOrigin).then(function (xml) {
            return post({
                action: 'mark',
                session_id: String(sessionId),
                student_id: foundStudent.student_id,
                aadhaar_last4: document.getElementById('aadhaarLast4').value,
                pid_xml: xml
            });
        }).then(function (data) {
            if (data.success) {
                const kind = (data.scan_type === 'out') ? 'OUT' : 'IN';
                kioskMsg(kind + ' recorded for ' + data.student_name + (data.scan_time ? (' at ' + data.scan_time) : ''), true);
                setTimeout(function () {
                    resetStudent();
                    kioskMsg('Ready for the next student.', true);
                    document.getElementById('lookupQ').focus();
                }, 2500);
            } else {
                kioskMsg(data.message || 'Attendance not marked', false);
                btn.disabled = false;
            }
        }).catch(function () {
            kioskMsg('Capture failed. Is the Mantra RD Service running on this PC?', false);
            btn.disabled = false;
        });
    });

    if (openSessionId > 0) {
        const match = <?php echo json_encode(array_values(array_map(static function ($s) {
            return ['id' => (int) $s['id'], 'name' => (string) $s['session_name'], 'status' => (string) $s['status']];
        }, $active_sessions))); ?>.find(function (s) { return s.id === openSessionId && s.status === 'active'; });
        if (match) {
            openKiosk(match.id, match.name);
        }
    }
})();
</script>
</body>
</html>
