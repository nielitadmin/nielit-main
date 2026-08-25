<?php
/**
 * Admin roster of students who have registered a fingerprint, and (by course)
 * those who have not yet enrolled.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/attendance_in_out_helper.php';
require_once __DIR__ . '/../includes/mantra_mfs100_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

ensureFingerprintTemplateTables($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];

$notice = '';
$noticeType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $notice = 'Invalid security token. Refresh and try again.';
        $noticeType = 'danger';
    } elseif ((string) ($_POST['action'] ?? '') === 'delete') {
        $sid = trim((string) ($_POST['student_id'] ?? ''));
        if (deleteStudentFingerprintTemplate($conn, $sid)) {
            $notice = 'Fingerprint removed for ' . $sid . '. They can enrol again.';
            $noticeType = 'success';
        } else {
            $notice = 'Could not remove that fingerprint.';
            $noticeType = 'danger';
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$courseId = (int) ($_GET['course_id'] ?? 0);
$tab = ((string) ($_GET['tab'] ?? 'registered') === 'pending') ? 'pending' : 'registered';

$courses = attendanceListCoursesForCentre($conn, 0);
$registered = listStudentFingerprintRegistry($conn, $q, $courseId);
$pending = $courseId > 0 ? listStudentsMissingFingerprint($conn, $courseId) : [];

$selfCount = 0;
foreach ($registered as $row) {
    if (stripos((string) ($row['enrolled_by'] ?? ''), 'self:') === 0) {
        $selfCount++;
    }
}

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fingerprint-registered-candidates.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID', 'Name', 'Course', 'Mobile', 'Email', 'Status', 'Enrolled by', 'Enrolled at', 'Quality']);
    foreach ($registered as $row) {
        fputcsv($out, [
            $row['student_id'] ?? '',
            $row['name'] ?? '',
            $row['course_name'] ?? '',
            $row['mobile'] ?? '',
            $row['email'] ?? '',
            $row['status'] ?? '',
            fingerprintEnrolmentSourceLabel((string) ($row['enrolled_by'] ?? '')),
            $row['created_at'] ?? '',
            $row['quality'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$active_theme = loadActiveTheme($conn);
$page_title = 'Registered Candidates';
$enrollUrl = app_url('admin/attendance_fingerprint_enroll');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="mb-0"><i class="fas fa-user-check"></i> <?php echo htmlspecialchars($page_title); ?></h2>
                <p class="text-muted mb-0">Students who have enrolled a fingerprint for attendance, and those still pending.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($enrollUrl); ?>">Enrol fingerprint</a>
                <a class="btn btn-outline-primary" href="?<?php echo htmlspecialchars(http_build_query(array_filter(['q' => $q, 'course_id' => $courseId ?: null, 'export' => 'csv']))); ?>">Export CSV</a>
            </div>
        </div>

        <?php if ($notice !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($noticeType); ?>"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <div class="text-muted small">Fingerprint registered</div>
                    <div class="fs-3 fw-bold"><?php echo count($registered); ?></div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <div class="text-muted small">Self-registered (student kiosk)</div>
                    <div class="fs-3 fw-bold"><?php echo (int) $selfCount; ?></div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <div class="text-muted small">Not yet registered (selected course)</div>
                    <div class="fs-3 fw-bold"><?php echo $courseId > 0 ? count($pending) : '—'; ?></div>
                </div></div>
            </div>
        </div>

        <form class="card mb-4" method="get">
            <div class="card-body row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Student ID, name, mobile, email">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Course</label>
                    <select class="form-select" name="course_id">
                        <option value="0">All courses</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>" <?php echo $courseId === (int) $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) ($c['course_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Filter</button>
                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/attendance_fingerprint_registry')); ?>">Reset</a>
                </div>
            </div>
        </form>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link <?php echo $tab === 'registered' ? 'active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query(['q' => $q, 'course_id' => $courseId, 'tab' => 'registered'])); ?>">
                    Registered (<?php echo count($registered); ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tab === 'pending' ? 'active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query(['q' => $q, 'course_id' => $courseId, 'tab' => 'pending'])); ?>">
                    Not yet registered<?php echo $courseId > 0 ? ' (' . count($pending) . ')' : ''; ?>
                </a>
            </li>
        </ul>

        <?php if ($tab === 'pending'): ?>
            <?php if ($courseId <= 0): ?>
                <div class="alert alert-info">Select a course above to see students who have not enrolled a fingerprint yet.</div>
            <?php else: ?>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Course</th>
                                    <th>Mobile</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($pending)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Everyone in this course has a fingerprint, or no students were found.</td></tr>
                            <?php else: foreach ($pending as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) $row['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['name'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['course_name'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['mobile'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['email'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['status'] ?? '')); ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($enrollUrl . '?q=' . urlencode((string) $row['student_id'])); ?>">Enrol</a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Course</th>
                                <th>Mobile</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Enrolled by</th>
                                <th>Enrolled at</th>
                                <th>Quality</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($registered)): ?>
                            <tr><td colspan="10" class="text-center text-muted py-4">No fingerprint registrations yet.</td></tr>
                        <?php else: foreach ($registered as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $row['student_id']); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['course_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['mobile'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['email'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['status'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(fingerprintEnrolmentSourceLabel((string) ($row['enrolled_by'] ?? ''))); ?></td>
                                <td><?php echo !empty($row['created_at']) ? htmlspecialchars(date('d M Y, h:i A', strtotime((string) $row['created_at']))) : ''; ?></td>
                                <td><?php echo (int) ($row['quality'] ?? 0); ?></td>
                                <td class="text-end">
                                    <form method="post" class="d-inline" onsubmit="return confirm('Remove this fingerprint? The student will need to enrol again.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars((string) $row['student_id']); ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
