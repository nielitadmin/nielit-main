<?php
/**
 * Candidate Requirements — list of candidates with document / fingerprint status.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';
require_once __DIR__ . '/../includes/biometric_attendance_helper.php';
require_once __DIR__ . '/../includes/requirements_helper.php';

requirementsRequireAccess($conn);

$q = trim((string) ($_GET['q'] ?? ''));
$courseId = (int) ($_GET['course_id'] ?? 0);
$status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$docs = (string) ($_GET['docs'] ?? '');
$fingerprint = (string) ($_GET['fingerprint'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 25);

$assigned = requirementsAssignedCourseIds($conn);
$courses = requirementsListCourses($conn, $assigned);

$result = requirementsListCandidates($conn, [
    'q' => $q,
    'course_id' => $courseId,
    'status' => $status,
    'docs' => $docs,
    'fingerprint' => $fingerprint,
    'page' => $page,
    'per_page' => $perPage,
]);

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="candidate-requirements.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID', 'Name', 'Course', 'Mobile', 'Email', 'Status', 'Required documents', 'Fingerprint']);
    $export = requirementsListCandidates($conn, [
        'q' => $q,
        'course_id' => $courseId,
        'status' => $status,
        'docs' => $docs,
        'fingerprint' => $fingerprint,
        'page' => 1,
        'per_page' => 100,
    ]);
    // Walk pages for CSV (cap 2000)
    $maxPages = min(20, max(1, (int) $export['pages']));
    for ($p = 1; $p <= $maxPages; $p++) {
        $chunk = $p === 1 ? $export : requirementsListCandidates($conn, [
            'q' => $q,
            'course_id' => $courseId,
            'status' => $status,
            'docs' => $docs,
            'fingerprint' => $fingerprint,
            'page' => $p,
            'per_page' => 100,
        ]);
        foreach ($chunk['rows'] as $row) {
            fputcsv($out, [
                $row['student_id'] ?? '',
                $row['name'] ?? '',
                $row['course_name'] ?? '',
                $row['mobile'] ?? '',
                $row['email'] ?? '',
                $row['status'] ?? '',
                !empty($row['docs_complete']) ? 'Complete' : 'Missing',
                !empty($row['has_fingerprint']) ? 'Yes' : 'No',
            ]);
        }
    }
    fclose($out);
    exit;
}

$filterQs = array_filter([
    'q' => $q !== '' ? $q : null,
    'course_id' => $courseId > 0 ? $courseId : null,
    'status' => ($status !== '' && $status !== 'all') ? $status : null,
    'docs' => $docs !== '' ? $docs : null,
    'fingerprint' => $fingerprint !== '' ? $fingerprint : null,
    'per_page' => $perPage !== 25 ? $perPage : null,
], static function ($v) {
    return $v !== null && $v !== '';
});

$active_theme = loadActiveTheme($conn);
$page_title = 'Requirements';
$stats = $result['stats'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .req-stat { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.1rem; }
        .req-stat b { display:block; font-size:1.6rem; color:#0f172a; }
        .req-stat span { color:#64748b; font-size:0.85rem; }
        .req-photo { width:40px; height:40px; border-radius:50%; object-fit:cover; background:#e2e8f0; }
        .req-photo-ph { width:40px; height:40px; border-radius:50%; background:#e2e8f0; display:inline-flex; align-items:center; justify-content:center; color:#94a3b8; }
        .req-pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:0.75rem; font-weight:600; }
        .req-pill.ok { background:#dcfce7; color:#166534; }
        .req-pill.miss { background:#fee2e2; color:#991b1b; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="mb-0"><i class="fas fa-clipboard-list"></i> Requirements</h2>
                <p class="text-muted mb-0">Open any candidate to see personal details, documents, courses, fingerprint and attendance.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(app_url('admin/requirements') . '?' . http_build_query(array_merge($filterQs, ['export' => 'csv']))); ?>">
                <i class="fas fa-download"></i> Export CSV
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="req-stat"><span>Candidates</span><b><?php echo (int) $stats['total']; ?></b></div></div>
            <div class="col-md-3"><div class="req-stat"><span>Required documents complete</span><b><?php echo (int) $stats['complete']; ?></b></div></div>
            <div class="col-md-3"><div class="req-stat"><span>Missing required documents</span><b><?php echo (int) $stats['missing_docs']; ?></b></div></div>
            <div class="col-md-3"><div class="req-stat"><span>Fingerprint enrolled</span><b><?php echo (int) $stats['fingerprint']; ?></b></div></div>
        </div>

        <form class="card mb-4" method="get">
            <div class="card-body row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Student ID, name, mobile, Aadhaar">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Course</label>
                    <select class="form-select" name="course_id">
                        <option value="0">All courses</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>" <?php echo $courseId === (int) $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(trim(($c['course_code'] ?? '') !== '' ? ($c['course_code'] . ' — ' . $c['course_name']) : $c['course_name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach (['all' => 'All', 'pending' => 'Pending', 'active' => 'Active', 'rejected' => 'Rejected'] as $val => $label): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $status === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Documents</label>
                    <select class="form-select" name="docs">
                        <option value="" <?php echo $docs === '' ? 'selected' : ''; ?>>All</option>
                        <option value="missing" <?php echo $docs === 'missing' ? 'selected' : ''; ?>>Missing required</option>
                        <option value="complete" <?php echo $docs === 'complete' ? 'selected' : ''; ?>>Complete</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Fingerprint</label>
                    <select class="form-select" name="fingerprint">
                        <option value="" <?php echo $fingerprint === '' ? 'selected' : ''; ?>>All</option>
                        <option value="yes" <?php echo $fingerprint === 'yes' ? 'selected' : ''; ?>>Enrolled</option>
                        <option value="no" <?php echo $fingerprint === 'no' ? 'selected' : ''; ?>>Not enrolled</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Filter</button>
                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/requirements')); ?>">Reset</a>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th></th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Mobile</th>
                            <th>Status</th>
                            <th>Documents</th>
                            <th>Fingerprint</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($result['rows'])): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No candidates match these filters.</td></tr>
                    <?php else: foreach ($result['rows'] as $row): ?>
                        <?php
                        $sid = (string) ($row['student_id'] ?? '');
                        $detailUrl = app_url('admin/requirements_candidate') . '?id=' . rawurlencode($sid);
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($row['photo_url'])): ?>
                                    <img class="req-photo" src="<?php echo htmlspecialchars($row['photo_url']); ?>" alt="">
                                <?php else: ?>
                                    <span class="req-photo-ph"><i class="fas fa-user"></i></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($sid); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['course_name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['mobile'] ?? '')); ?></td>
                            <td>
                                <span class="badge text-bg-<?php echo htmlspecialchars(requirementsStatusBadgeClass((string) ($row['status'] ?? ''))); ?>">
                                    <?php echo htmlspecialchars(ucfirst((string) ($row['status'] ?? ''))); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row['docs_complete'])): ?>
                                    <span class="req-pill ok">Complete</span>
                                <?php else: ?>
                                    <span class="req-pill miss">Missing</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['has_fingerprint'])): ?>
                                    <span class="req-pill ok">Enrolled</span>
                                <?php else: ?>
                                    <span class="req-pill miss">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars($detailUrl); ?>">View details</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($result['pages'] > 1): ?>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        Page <?php echo (int) $result['page']; ?> of <?php echo (int) $result['pages']; ?>
                        (<?php echo (int) $result['total']; ?> candidates)
                    </span>
                    <div class="d-flex gap-2">
                        <?php if ($result['page'] > 1): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="?<?php echo htmlspecialchars(http_build_query(array_merge($filterQs, ['page' => $result['page'] - 1]))); ?>">Previous</a>
                        <?php endif; ?>
                        <?php if ($result['page'] < $result['pages']): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="?<?php echo htmlspecialchars(http_build_query(array_merge($filterQs, ['page' => $result['page'] + 1]))); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
