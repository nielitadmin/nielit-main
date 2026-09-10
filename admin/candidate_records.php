<?php
/**
 * Master Admin — enter workshop / awareness participant records (same fields as the short form).
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/candidate_records_helper.php';
require_once __DIR__ . '/../includes/state_city_registration.php';

candidateRecordsRequireAccess();
ensureWorkshopRegistrationSchema($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];
$adminUser = (string) ($_SESSION['admin'] ?? '');

$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? 'all');
$courseId = (int) ($_GET['course_id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$showForm = isset($_GET['new']);

$workshops = workshopAdminListCourses($conn);

$listParams = array_filter([
    'q' => $q !== '' ? $q : null,
    'status' => ($status !== '' && $status !== 'all') ? $status : null,
    'course_id' => $courseId > 0 ? $courseId : null,
], static function ($v) {
    return $v !== null && $v !== '';
});
$listUrl = app_url('admin/candidate_records');
if ($listParams !== []) {
    $listUrl .= '?' . http_build_query($listParams);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['message'] = 'Invalid security token. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . $listUrl);
        exit();
    }
    $result = workshopAdminCreateParticipant($conn, $_POST, $_FILES, $adminUser);
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    header('Location: ' . ($result['success'] ? $listUrl : (app_url('admin/candidate_records') . '?new=1')));
    exit();
}

$result = workshopRecordsList($conn, [
    'q' => $q,
    'status' => $status,
    'course_id' => $courseId,
    'page' => $page,
    'per_page' => 25,
]);

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="workshop-records.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID', 'Name', 'Father', 'Class', 'School', 'Mobile', 'Email', 'Workshop', 'Status', 'Registered']);
    $export = workshopRecordsList($conn, [
        'q' => $q,
        'status' => $status,
        'course_id' => $courseId,
        'page' => 1,
        'per_page' => 100,
    ]);
    $maxPages = min(50, max(1, (int) $export['pages']));
    for ($p = 1; $p <= $maxPages; $p++) {
        $chunk = $p === 1 ? $export : workshopRecordsList($conn, [
            'q' => $q,
            'status' => $status,
            'course_id' => $courseId,
            'page' => $p,
            'per_page' => 100,
        ]);
        foreach ($chunk['rows'] as $row) {
            fputcsv($out, [
                $row['student_id'] ?? '',
                $row['name'] ?? '',
                $row['father_name'] ?? '',
                $row['class_standard'] ?? '',
                $row['college_name'] ?? '',
                $row['mobile'] ?? '',
                $row['email'] ?? '',
                $row['course'] ?? '',
                $row['status'] ?? '',
                $row['registration_date'] ?? '',
            ]);
        }
    }
    fclose($out);
    exit;
}

$notice = (string) ($_SESSION['message'] ?? '');
$noticeType = (string) ($_SESSION['message_type'] ?? 'success');
unset($_SESSION['message'], $_SESSION['message_type']);

$active_theme = loadActiveTheme($conn);
$page_title = 'Workshop Records';
$stats = $result['stats'];
$formData = $_POST ?? [];
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
        .required-mark { color:#dc2626; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Workshop Records</h2>
                <p class="text-muted mb-0">Master Admin only. Fill what you have — only workshop and name are required. The public Apply form is unchanged.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($listUrl . (strpos($listUrl, '?') === false ? '?' : '&') . 'export=csv'); ?>">
                    <i class="fas fa-download"></i> Export CSV
                </a>
                <?php if (!$showForm): ?>
                    <a class="btn btn-primary" href="<?php echo htmlspecialchars(app_url('admin/candidate_records') . '?new=1'); ?>">
                        <i class="fas fa-plus"></i> Add workshop candidate
                    </a>
                <?php else: ?>
                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($listUrl); ?>">Back to list</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($notice !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($noticeType); ?>"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <?php if ($workshops === []): ?>
            <div class="alert alert-warning">
                No workshop course is set up yet. Open a course, set <strong>Registration form</strong> to <strong>Workshop</strong>, then come back here.
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="req-stat"><span>Workshop participants</span><b><?php echo (int) $stats['total']; ?></b></div></div>
            <div class="col-md-4"><div class="req-stat"><span>Pending</span><b><?php echo (int) $stats['pending']; ?></b></div></div>
            <div class="col-md-4"><div class="req-stat"><span>Active</span><b><?php echo (int) $stats['active']; ?></b></div></div>
        </div>

        <?php if ($showForm && $workshops !== []): ?>
            <form class="card mb-4" method="post" enctype="multipart/form-data">
                <div class="card-header fw-semibold">New workshop candidate</div>
                <div class="card-body row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <div class="col-md-12">
                        <label class="form-label">Workshop / awareness course <span class="required-mark">*</span></label>
                        <select class="form-select" name="course_id" required>
                            <option value="">Select workshop</option>
                            <?php foreach ($workshops as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>">
                                    <?php echo htmlspecialchars(trim((string) ($c['course_code'] ?? '')) !== '' ? ($c['course_code'] . ' — ' . $c['course_name']) : $c['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Student full name <span class="required-mark">*</span></label>
                        <input class="form-control" name="name" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Class / Level</label>
                        <select class="form-select" name="class_standard">
                            <option value="">Select class or level</option>
                            <?php foreach (getWorkshopClassStandardOptions() as $groupLabel => $options): ?>
                                <optgroup label="<?php echo htmlspecialchars($groupLabel); ?>">
                                    <?php foreach ($options as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date of birth</label>
                        <input class="form-control" type="date" name="dob" id="workshop_dob" max="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Age</label>
                        <input class="form-control" name="age" id="workshop_age" readonly placeholder="Auto from DOB">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Gender</label>
                        <select class="form-select" name="gender">
                            <option value="">Select</option>
                            <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                                <option value="<?php echo $g; ?>"><?php echo $g; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <option value="">Select</option>
                            <?php foreach (['General', 'OBC', 'SC', 'ST', 'EWS'] as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Father's name</label>
                        <input class="form-control" name="father_name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mother's name</label>
                        <input class="form-control" name="mother_name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mobile (parent)</label>
                        <input class="form-control" name="mobile" maxlength="10" pattern="[0-9]{10}" placeholder="10-digit mobile">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Aadhar number</label>
                        <input class="form-control" name="aadhar" maxlength="12" pattern="[0-9]{12}" placeholder="12 digits">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Aadhar card</label>
                        <input class="form-control" type="file" name="aadhar_card" accept="image/jpeg,image/png,application/pdf">
                    </div>
                    <div class="col-12">
                        <label class="form-label">School / College name</label>
                        <input class="form-control" name="school_name">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                    <?php renderStateCityPincodeFields($formData, ['required' => false]); ?>
                    <div class="col-md-6">
                        <label class="form-label">Passport photo</label>
                        <input class="form-control" type="file" name="passport_photo" accept="image/jpeg,image/png">
                    </div>
                    <div class="col-md-6 d-flex flex-column justify-content-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="approve_now" value="1" id="approve_now">
                            <label class="form-check-label" for="approve_now">Approve now (mark Active)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="send_email" value="1" id="send_email">
                            <label class="form-check-label" for="send_email">Email login ID and password to the candidate</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Save workshop record</button>
                        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($listUrl); ?>">Cancel</a>
                    </div>
                </div>
            </form>
            <?php renderStateCityPincodeScript($formData); ?>
            <script>
            (function () {
                var dob = document.getElementById('workshop_dob');
                var age = document.getElementById('workshop_age');
                if (!dob || !age) return;
                function calc() {
                    if (!dob.value) { age.value = ''; return; }
                    var d = new Date(dob.value + 'T00:00:00');
                    var t = new Date();
                    var a = t.getFullYear() - d.getFullYear();
                    var m = t.getMonth() - d.getMonth();
                    if (m < 0 || (m === 0 && t.getDate() < d.getDate())) a--;
                    age.value = a >= 0 ? a : '';
                }
                dob.addEventListener('change', calc);
            })();
            </script>
        <?php endif; ?>

        <form class="card mb-3" method="get">
            <div class="card-body row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Name, mobile, email, student ID">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Workshop</label>
                    <select class="form-select" name="course_id">
                        <option value="0">All workshops</option>
                        <?php foreach ($workshops as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>" <?php echo $courseId === (int) $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $c['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="all">All</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary" type="submit">Filter</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student ID</th>
                            <th>Candidate</th>
                            <th>Class / School</th>
                            <th>Mobile / Email</th>
                            <th>Workshop</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($result['rows'])): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No workshop records yet.</td></tr>
                    <?php else: foreach ($result['rows'] as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $row['student_id']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string) $row['name']); ?></strong>
                                <?php if (trim((string) ($row['father_name'] ?? '')) !== ''): ?>
                                    <div class="small text-muted">S/o <?php echo htmlspecialchars((string) $row['father_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string) ($row['class_standard'] ?? '')); ?>
                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($row['college_name'] ?? '')); ?></div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string) $row['mobile']); ?>
                                <br><span class="small text-muted"><?php echo htmlspecialchars((string) ($row['email'] ?? '')); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($row['course'] ?? '')); ?></td>
                            <td>
                                <span class="badge text-bg-<?php echo strtolower((string) $row['status']) === 'active' ? 'success' : (strtolower((string) $row['status']) === 'rejected' ? 'danger' : 'warning'); ?>">
                                    <?php echo htmlspecialchars((string) $row['status']); ?>
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(app_url('admin/edit_student') . '?id=' . rawurlencode((string) $row['student_id'])); ?>">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ((int) $result['pages'] > 1): ?>
                <div class="card-footer d-flex justify-content-between">
                    <span class="text-muted"><?php echo (int) $result['total']; ?> records</span>
                    <div>
                        <?php if ($result['page'] > 1): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/candidate_records') . '?' . http_build_query(array_merge($listParams, ['page' => $result['page'] - 1]))); ?>">Previous</a>
                        <?php endif; ?>
                        <?php if ($result['page'] < $result['pages']): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/candidate_records') . '?' . http_build_query(array_merge($listParams, ['page' => $result['page'] + 1]))); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
