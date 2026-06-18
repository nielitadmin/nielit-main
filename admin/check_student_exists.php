<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';
require_once __DIR__ . '/includes/student_record_inspector.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login_new.php');
    exit();
}

$adminRole = $_SESSION['admin_role'] ?? '';
$canDelete = ($adminRole !== 'front_office_desk');
$flashMessage = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canDelete) {
    $searchParams = [
        'aadhar' => trim($_POST['aadhar'] ?? ''),
        'mobile' => trim($_POST['mobile'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'student_id' => trim($_POST['student_id'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
    ];
    $redirectQs = inspectorSearchParams($searchParams);
    $redirectUrl = 'check_student_exists.php' . ($redirectQs !== '' ? '?' . $redirectQs : '');

    if (($_POST['delete_action'] ?? '') === 'purge_all' && !empty($_POST['confirm_purge'])) {
        $ids = [
            'record_ids' => array_values(array_filter(array_map('intval', explode(',', $_POST['purge_record_ids'] ?? '')))),
            'account_ids' => array_values(array_filter(array_map('intval', explode(',', $_POST['purge_account_ids'] ?? '')))),
            'student_id_strings' => array_values(array_filter(array_map('trim', explode(',', $_POST['purge_student_ids'] ?? '')))),
        ];
        $result = inspectorPurgeAllRelated($conn, $ids);
        $flashMessage = $result['message'];
        $flashType = $result['success'] ? 'success' : 'danger';
    } elseif (($_POST['delete_action'] ?? '') === 'delete_one') {
        $result = inspectorDeleteRecord($conn, (string)($_POST['record_type'] ?? ''), (int)($_POST['record_id'] ?? 0));
        $flashMessage = $result['message'];
        $flashType = $result['success'] ? 'success' : 'danger';
    }

    if ($flashMessage !== '') {
        $_SESSION['inspector_flash'] = ['message' => $flashMessage, 'type' => $flashType];
    }
    header('Location: ' . $redirectUrl);
    exit();
}

if (!empty($_SESSION['inspector_flash'])) {
    $flashMessage = $_SESSION['inspector_flash']['message'] ?? '';
    $flashType = $_SESSION['inspector_flash']['type'] ?? 'success';
    unset($_SESSION['inspector_flash']);
}

function normalizeDigits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

$aadhar = normalizeDigits(trim($_GET['aadhar'] ?? $_POST['aadhar'] ?? ''));
$mobile = normalizeDigits(trim($_GET['mobile'] ?? $_POST['mobile'] ?? ''));
$email = strtolower(trim($_GET['email'] ?? $_POST['email'] ?? ''));
$studentId = trim($_GET['student_id'] ?? $_POST['student_id'] ?? '');
$name = trim($_GET['name'] ?? $_POST['name'] ?? '');

$searched = ($aadhar !== '' || $mobile !== '' || $email !== '' || $studentId !== '' || $name !== '');
$studentRows = [];
$accountRows = [];
$enrollmentRows = [];
$hiddenStudentRows = [];
$exists = false;
$inAdminPanel = false;
$hasOrphanEnrollments = false;
$summary = 'No search performed yet.';
$relatedRecords = [];
$relatedTotal = 0;
$collectedIds = ['record_ids' => [], 'account_ids' => [], 'student_id_strings' => []];
$searchParams = [
    'aadhar' => $aadhar,
    'mobile' => $mobile,
    'email' => $email,
    'student_id' => $studentId,
    'name' => $name,
];

if ($searched) {
    $studentConds = [];
    $studentParams = [];
    $studentTypes = '';

    if ($aadhar !== '') {
        $studentConds[] = "REPLACE(REPLACE(REPLACE(aadhar,' ',''),'-',''),'.','') = ?";
        $studentParams[] = $aadhar;
        $studentTypes .= 's';
    }
    if ($mobile !== '') {
        $studentConds[] = "REPLACE(REPLACE(mobile,' ',''),'-','') = ?";
        $studentParams[] = $mobile;
        $studentTypes .= 's';
    }
    if ($email !== '') {
        $studentConds[] = 'LOWER(TRIM(email)) = ?';
        $studentParams[] = $email;
        $studentTypes .= 's';
    }
    if ($studentId !== '') {
        $studentConds[] = 'student_id = ?';
        $studentParams[] = $studentId;
        $studentTypes .= 's';
    }
    if ($name !== '') {
        $studentConds[] = 'name LIKE ?';
        $studentParams[] = '%' . $name . '%';
        $studentTypes .= 's';
    }

    if (!empty($studentConds)) {
        $sql = 'SELECT s.id, s.student_id, s.name, s.aadhar, s.mobile, s.email, s.dob, s.course_id,
                       c.course_name, s.scheme_id, s.batch_id, s.status, s.registration_date
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
                WHERE (' . implode(' OR ', $studentConds) . ')
                AND LOWER(s.status) NOT IN (\'rejected\', \'inactive\')
                ORDER BY s.registration_date DESC, s.id DESC
                LIMIT 50';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($studentTypes, ...$studentParams);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $studentRows[] = $row;
            }
            $stmt->close();
        }
    }

    $hasAccounts = $conn->query("SHOW TABLES LIKE 'student_accounts'");
    if ($hasAccounts && $hasAccounts->num_rows > 0) {
        $accountConds = [];
        $accountParams = [];
        $accountTypes = '';

        if ($aadhar !== '') {
            $accountConds[] = "REPLACE(REPLACE(REPLACE(aadhar,' ',''),'-',''),'.','') = ?";
            $accountParams[] = $aadhar;
            $accountTypes .= 's';
        }
        if ($mobile !== '') {
            $accountConds[] = "REPLACE(REPLACE(mobile,' ',''),'-','') = ?";
            $accountParams[] = $mobile;
            $accountTypes .= 's';
        }
        if ($email !== '') {
            $accountConds[] = 'LOWER(TRIM(email)) = ?';
            $accountParams[] = $email;
            $accountTypes .= 's';
        }
        if ($studentId !== '') {
            $accountConds[] = 'student_id = ?';
            $accountParams[] = $studentId;
            $accountTypes .= 's';
        }
        if ($name !== '') {
            $accountConds[] = 'name LIKE ?';
            $accountParams[] = '%' . $name . '%';
            $accountTypes .= 's';
        }

        if (!empty($accountConds)) {
            $sql = 'SELECT id, student_id, name, aadhar, mobile, email, status, created_at
                    FROM student_accounts
                    WHERE ' . implode(' OR ', $accountConds) . '
                    ORDER BY created_at DESC, id DESC
                    LIMIT 20';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($accountTypes, ...$accountParams);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $accountRows[] = $row;
                }
                $stmt->close();
            }
        }
    }

    $hasEnrollments = $conn->query("SHOW TABLES LIKE 'student_enrollments'");
    if ($hasEnrollments && $hasEnrollments->num_rows > 0 && ($email !== '' || $aadhar !== '' || $studentId !== '' || $mobile !== '' || $name !== '')) {
        $enrConds = [];
        $enrParams = [];
        $enrTypes = '';

        if ($aadhar !== '') {
            $enrConds[] = "REPLACE(REPLACE(REPLACE(sa.aadhar,' ',''),'-',''),'.','') = ?";
            $enrParams[] = $aadhar;
            $enrTypes .= 's';
        }
        if ($mobile !== '') {
            $enrConds[] = "REPLACE(REPLACE(sa.mobile,' ',''),'-','') = ?";
            $enrParams[] = $mobile;
            $enrTypes .= 's';
        }
        if ($email !== '') {
            $enrConds[] = 'LOWER(TRIM(sa.email)) = ?';
            $enrParams[] = $email;
            $enrTypes .= 's';
        }
        if ($studentId !== '') {
            $enrConds[] = 'sa.student_id = ?';
            $enrParams[] = $studentId;
            $enrTypes .= 's';
        }
        if ($name !== '') {
            $enrConds[] = 'sa.name LIKE ?';
            $enrParams[] = '%' . $name . '%';
            $enrTypes .= 's';
        }

        if (!empty($enrConds)) {
            $sql = 'SELECT se.id, se.account_id, se.course_id, se.student_record_id, se.scheme_id, se.status, se.registered_at,
                           c.course_name,
                           sa.id AS linked_account_id, sa.student_id, sa.name, sa.aadhar, sa.mobile, sa.email
                    FROM student_enrollments se
                    INNER JOIN student_accounts sa ON sa.id = se.account_id
                    LEFT JOIN courses c ON c.id = se.course_id
                    WHERE ' . implode(' OR ', $enrConds) . '
                    ORDER BY se.registered_at DESC, se.id DESC
                    LIMIT 50';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($enrTypes, ...$enrParams);
                $stmt->execute();
                $res = $stmt->get_result();
                $seenAccountIds = array_column($accountRows, 'id');
                while ($row = $res->fetch_assoc()) {
                    $enrollmentRows[] = $row;
                    $linkedAccountId = (int)($row['linked_account_id'] ?? 0);
                    if ($linkedAccountId > 0 && !in_array($linkedAccountId, $seenAccountIds, true)) {
                        $accountRows[] = [
                            'id' => $linkedAccountId,
                            'student_id' => $row['student_id'],
                            'name' => $row['name'],
                            'aadhar' => $row['aadhar'],
                            'mobile' => $row['mobile'],
                            'email' => $row['email'],
                            'status' => 'via enrollment link',
                            'created_at' => null,
                        ];
                        $seenAccountIds[] = $linkedAccountId;
                    }
                }
                $stmt->close();
            }
        }
    }

    // Also find rejected / inactive rows (hidden from Admin → Students list)
    $hiddenStudentRows = [];
    if (!empty($studentConds)) {
        $sql = 'SELECT s.id, s.student_id, s.name, s.aadhar, s.mobile, s.email, s.course_id,
                       c.course_name, s.status, s.registration_date
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
                WHERE (' . implode(' OR ', $studentConds) . ')
                AND LOWER(s.status) IN (\'rejected\', \'inactive\')
                ORDER BY s.registration_date DESC, s.id DESC
                LIMIT 20';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($studentTypes, ...$studentParams);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $hiddenStudentRows[] = $row;
            }
            $stmt->close();
        }
    }

    $inAdminPanel = !empty($studentRows);
    $hasOrphanEnrollments = empty($studentRows) && !empty($enrollmentRows);
    $exists = $inAdminPanel || !empty($accountRows) || !empty($enrollmentRows) || !empty($hiddenStudentRows);

    if ($inAdminPanel) {
        $summary = 'Student is in the database and SHOULD appear in Admin → Students.';
    } elseif ($hasOrphanEnrollments) {
        $summary = 'Orphan enrollment only — NOT visible in Admin → Students (missing students row).';
    } elseif (!empty($hiddenStudentRows)) {
        $summary = 'Student exists but is rejected/inactive — hidden from Admin → Students.';
    } elseif ($exists) {
        $summary = 'Partial record found (account/enrollment) but not in main students list.';
    } else {
        $summary = 'No matching student found. They are NOT registered yet.';
    }

    $collectedIds = inspectorCollectIds($studentRows, $hiddenStudentRows, $accountRows, $enrollmentRows);
    $relatedRecords = inspectorExpandRelatedRecords($conn, $collectedIds);
    foreach ($relatedRecords['students_all'] ?? [] as $row) {
        $collectedIds['record_ids'][] = (int)$row['id'];
        if (!empty($row['student_id'])) {
            $collectedIds['student_id_strings'][] = (string)$row['student_id'];
        }
        if (!empty($row['account_id'])) {
            $collectedIds['account_ids'][] = (int)$row['account_id'];
        }
    }
    foreach ($relatedRecords['enrollments_all'] ?? [] as $row) {
        if (!empty($row['account_id'])) {
            $collectedIds['account_ids'][] = (int)$row['account_id'];
        }
        if (!empty($row['student_record_id'])) {
            $collectedIds['record_ids'][] = (int)$row['student_record_id'];
        }
        if (!empty($row['student_id'])) {
            $collectedIds['student_id_strings'][] = (string)$row['student_id'];
        }
    }
    $collectedIds['record_ids'] = array_values(array_unique(array_filter($collectedIds['record_ids'])));
    $collectedIds['account_ids'] = array_values(array_unique(array_filter($collectedIds['account_ids'])));
    $collectedIds['student_id_strings'] = array_values(array_unique(array_filter($collectedIds['student_id_strings'])));
    $relatedTotal = inspectorCountRelated($relatedRecords);
}

$page_title = 'Student Record Inspector';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NIELIT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f1f5f9; }
        .page-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(15,23,42,.08); }
        .status-found { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .status-missing { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .status-warning { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .status-hidden { background: #e0e7ff; color: #3730a3; border: 1px solid #a5b4fc; }
        .table th { background: #0f172a; color: #fff; font-size: 0.85rem; }
        .link-badge { font-size: 0.75rem; }
        .section-actions { display: flex; gap: 0.5rem; align-items: center; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-user-check text-primary"></i> Student Record Inspector</h1>
            <p class="text-muted mb-0">Search all related records. Admin → Students only lists the <strong>students</strong> table.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="students.php" class="btn btn-outline-secondary"><i class="fas fa-users"></i> Students</a>
            <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>

    <?php if ($flashMessage !== ''): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($flashMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="page-card p-4 mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Aadhar Number</label>
                <input type="text" name="aadhar" class="form-control" maxlength="12"
                       value="<?php echo htmlspecialchars($aadhar); ?>" placeholder="993041115828">
            </div>
            <div class="col-md-3">
                <label class="form-label">Mobile</label>
                <input type="text" name="mobile" class="form-control" maxlength="15"
                       value="<?php echo htmlspecialchars($mobile); ?>" placeholder="8926374271">
            </div>
            <div class="col-md-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                       value="<?php echo htmlspecialchars($email); ?>" placeholder="name@email.com">
            </div>
            <div class="col-md-3">
                <label class="form-label">Student ID</label>
                <input type="text" name="student_id" class="form-control"
                       value="<?php echo htmlspecialchars($studentId); ?>" placeholder="NIELIT/2026/BBSR/0001">
            </div>
            <div class="col-md-6">
                <label class="form-label">Name (optional, partial match)</label>
                <input type="text" name="name" class="form-control"
                       value="<?php echo htmlspecialchars($name); ?>" placeholder="Student name">
            </div>
            <div class="col-md-6 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Check</button>
                <a href="check_student_exists.php" class="btn btn-light">Clear</a>
            </div>
        </form>
    </div>

    <?php if ($searched):
        $alertClass = 'status-missing';
        if ($inAdminPanel) {
            $alertClass = 'status-found';
        } elseif ($hasOrphanEnrollments) {
            $alertClass = 'status-warning';
        } elseif (!empty($hiddenStudentRows)) {
            $alertClass = 'status-hidden';
        } elseif ($exists) {
            $alertClass = 'status-warning';
        }
    ?>
    <div class="alert <?php echo $alertClass; ?> mb-4">
        <strong><?php echo htmlspecialchars($summary); ?></strong>
        <div class="small mt-1">
            <strong>students (admin list):</strong> <?php echo count($studentRows); ?> |
            accounts: <?php echo count($accountRows); ?> |
            enrollments: <?php echo count($enrollmentRows); ?>
            <?php if ($relatedTotal > 0): ?> | <strong>all linked:</strong> <?php echo (int)$relatedTotal; ?><?php endif; ?>
            <?php if (!empty($hiddenStudentRows)): ?> | hidden rejected/inactive: <?php echo count($hiddenStudentRows); ?><?php endif; ?>
        </div>
        <?php if ($hasOrphanEnrollments): ?>
        <div class="small mt-2">
            <i class="fas fa-exclamation-triangle"></i>
            Admin → Students will <strong>not</strong> show this person until a row exists in the <code>students</code> table.
            Ask them to register again, or add manually in <a href="students.php" class="alert-link">Students</a>.
        </div>
        <?php endif; ?>
    </div>

    <?php if ($searched && $exists && $canDelete): ?>
    <div class="page-card p-4 mb-4 border border-danger">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h2 class="h5 mb-1 text-danger"><i class="fas fa-trash-alt"></i> Delete all related records</h2>
                <p class="small text-muted mb-0">Removes enrollments, batch links, attendance, education, certificates, students, and accounts (in safe order).</p>
            </div>
            <form method="POST" action="check_student_exists.php?<?php echo htmlspecialchars(inspectorSearchParams($searchParams)); ?>"
                  onsubmit="return confirm('Delete ALL related records for this search? This cannot be undone.');">
                <input type="hidden" name="delete_action" value="purge_all">
                <input type="hidden" name="confirm_purge" value="1">
                <input type="hidden" name="purge_record_ids" value="<?php echo htmlspecialchars(implode(',', $collectedIds['record_ids'])); ?>">
                <input type="hidden" name="purge_account_ids" value="<?php echo htmlspecialchars(implode(',', $collectedIds['account_ids'])); ?>">
                <input type="hidden" name="purge_student_ids" value="<?php echo htmlspecialchars(implode(',', $collectedIds['student_id_strings'])); ?>">
                <?php echo inspectorHiddenSearchFields($searchParams); ?>
                <button type="submit" class="btn btn-danger"><i class="fas fa-bomb"></i> Delete all (<?php echo (int)$relatedTotal; ?> linked)</button>
            </form>
        </div>
    </div>
    <?php elseif ($searched && $exists && !$canDelete): ?>
    <div class="alert alert-secondary">Delete actions are disabled for Front Office Desk role.</div>
    <?php endif; ?>

    <?php if ($searched && $relatedTotal > 0): ?>
    <div class="page-card p-3 mb-4">
        <h2 class="h6 mb-2"><i class="fas fa-link"></i> All related records found</h2>
        <div class="d-flex flex-wrap gap-2">
            <?php
            $relatedLabels = [
                'students_all' => 'students (all statuses)',
                'enrollments_all' => 'enrollments',
                'batch_students' => 'batch_students',
                'batch_attendance' => 'batch_attendance',
                'education_details' => 'education_details',
                'certificates' => 'certificates',
                'attendance' => 'attendance',
                'attendance_logs' => 'attendance_logs',
                'attendance_summary' => 'attendance_summary',
            ];
            foreach ($relatedLabels as $key => $label):
                $count = count($relatedRecords[$key] ?? []);
                if ($count === 0) {
                    continue;
                }
            ?>
            <span class="badge bg-dark"><?php echo htmlspecialchars($label); ?>: <?php echo $count; ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($studentRows)): ?>
    <div class="page-card p-0 mb-4 overflow-hidden">
        <div class="p-3 border-bottom"><h2 class="h5 mb-0"><i class="fas fa-id-card"></i> students table <span class="badge bg-success">shown in Admin</span></h2></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Record ID</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Aadhar</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <?php if ($canDelete): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentRows as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['student_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['aadhar']); ?></td>
                        <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td><?php echo !empty($row['registration_date']) ? date('d M Y', strtotime($row['registration_date'])) : '—'; ?></td>
                        <?php if ($canDelete): ?>
                        <td><?php echo inspectorDeleteForm('student', (int)$row['id'], $searchParams); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($hiddenStudentRows)): ?>
    <div class="page-card p-0 mb-4 overflow-hidden">
        <div class="p-3 border-bottom"><h2 class="h5 mb-0"><i class="fas fa-eye-slash"></i> students table (rejected / inactive — hidden from Admin)</h2></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Record ID</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <?php if ($canDelete): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hiddenStudentRows as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['student_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?></td>
                        <td><span class="badge bg-danger"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td><?php echo !empty($row['registration_date']) ? date('d M Y', strtotime($row['registration_date'])) : '—'; ?></td>
                        <?php if ($canDelete): ?>
                        <td><?php echo inspectorDeleteForm('student', (int)$row['id'], $searchParams); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($accountRows)): ?>
    <div class="page-card p-0 mb-4 overflow-hidden">
        <div class="p-3 border-bottom"><h2 class="h5 mb-0"><i class="fas fa-user-circle"></i> student_accounts table</h2></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Account ID</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Aadhar</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Linked</th>
                        <?php if ($canDelete): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accountRows as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['student_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['aadhar']); ?></td>
                        <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['status'] ?? '—'); ?></td>
                        <td><?php echo !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '—'; ?></td>
                        <td class="small">
                            <?php
                            $enrCount = 0;
                            foreach ($relatedRecords['enrollments_all'] ?? [] as $enr) {
                                if ((int)($enr['account_id'] ?? 0) === (int)$row['id']) {
                                    $enrCount++;
                                }
                            }
                            echo $enrCount . ' enrollment(s)';
                            ?>
                        </td>
                        <?php if ($canDelete): ?>
                        <td><?php echo inspectorDeleteForm('account', (int)$row['id'], $searchParams); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($enrollmentRows)): ?>
    <div class="page-card p-0 mb-4 overflow-hidden">
        <div class="p-3 border-bottom"><h2 class="h5 mb-0"><i class="fas fa-layer-group"></i> student_enrollments <span class="badge bg-warning text-dark">not shown in Admin list alone</span></h2></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Enrollment ID</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Record ID</th>
                        <th>Account</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <?php if ($canDelete): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollmentRows as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?></td>
                        <td><?php
                            $recordId = (int)($row['student_record_id'] ?? 0);
                            echo $recordId > 0 ? (string)$recordId : '<span class="text-danger">missing</span>';
                        ?></td>
                        <td><span class="badge bg-light text-dark border">#<?php echo (int)($row['account_id'] ?? 0); ?></span></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <td><?php echo !empty($row['registered_at']) ? date('d M Y', strtotime($row['registered_at'])) : '—'; ?></td>
                        <?php if ($canDelete): ?>
                        <td><?php echo inspectorDeleteForm('enrollment', (int)$row['id'], $searchParams); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php
    $shownStudentIds = array_map(static fn($r) => (int)$r['id'], array_merge($studentRows, $hiddenStudentRows));
    $extraStudents = array_values(array_filter(
        $relatedRecords['students_all'] ?? [],
        static fn($r) => !in_array((int)$r['id'], $shownStudentIds, true)
    ));
    ?>
    <?php if (!empty($extraStudents)): ?>
    <div class="page-card p-0 mb-4 overflow-hidden">
        <div class="p-3 border-bottom"><h2 class="h5 mb-0"><i class="fas fa-database"></i> students (linked by Student ID)</h2></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Record ID</th><th>Student ID</th><th>Name</th><th>Course</th><th>Account</th><th>Status</th>
                        <?php if ($canDelete): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($extraStudents as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?></td>
                        <td><?php echo (int)($row['account_id'] ?? 0) > 0 ? '#' . (int)$row['account_id'] : '—'; ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <?php if ($canDelete): ?>
                        <td><?php echo inspectorDeleteForm('student', (int)$row['id'], $searchParams); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php
    $shownEnrollmentIds = array_map(static fn($r) => (int)$r['id'], $enrollmentRows);
    $extraEnrollments = array_values(array_filter(
        $relatedRecords['enrollments_all'] ?? [],
        static fn($r) => !in_array((int)$r['id'], $shownEnrollmentIds, true)
    ));
    ?>
    <?php if (!empty($extraEnrollments)): ?>
    <div class="page-card p-0 mb-4 overflow-hidden">
        <div class="p-3 border-bottom"><h2 class="h5 mb-0"><i class="fas fa-link"></i> student_enrollments (linked via account)</h2></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th><th>Student ID</th><th>Course</th><th>Account</th><th>Record ID</th><th>Status</th>
                        <?php if ($canDelete): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($extraEnrollments as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['student_id'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?></td>
                        <td>#<?php echo (int)($row['account_id'] ?? 0); ?></td>
                        <td><?php echo (int)($row['student_record_id'] ?? 0) > 0 ? (int)$row['student_record_id'] : '<span class="text-danger">missing</span>'; ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <?php if ($canDelete): ?>
                        <td><?php echo inspectorDeleteForm('enrollment', (int)$row['id'], $searchParams); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($relatedRecords['batch_students'])): ?>
    <div class="page-card p-0 mb-4 overflow-hidden">
        <div class="p-3 border-bottom"><h2 class="h5 mb-0"><i class="fas fa-users-class"></i> batch_students</h2></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th><th>Batch</th><th>Student record</th><th>Enrolled</th><th>Fees</th>
                        <?php if ($canDelete): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($relatedRecords['batch_students'] as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><?php echo htmlspecialchars(($row['batch_name'] ?? 'Batch') . ' (' . ($row['batch_code'] ?? $row['batch_id']) . ')'); ?></td>
                        <td>#<?php echo (int)($row['student_record_id'] ?? $row['student_id']); ?></td>
                        <td><?php echo !empty($row['enrollment_date']) ? date('d M Y', strtotime($row['enrollment_date'])) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($row['fees_status'] ?? '—'); ?></td>
                        <?php if ($canDelete): ?>
                        <td><?php echo inspectorDeleteForm('batch_student', (int)$row['id'], $searchParams); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php
    $simpleRelated = [
        'batch_attendance' => ['title' => 'batch_attendance', 'type' => 'batch_attendance', 'cols' => ['id', 'batch_id', 'student_id', 'attendance_date', 'status']],
        'education_details' => ['title' => 'education_details', 'type' => 'education', 'cols' => ['id', 'student_id', 'exam_passed', 'year_of_passing', 'percentage']],
        'certificates' => ['title' => 'certificates', 'type' => 'certificate', 'cols' => ['id', 'student_id', 'certificate_type', 'certificate_number', 'issue_date']],
        'attendance' => ['title' => 'attendance', 'type' => 'attendance', 'cols' => ['id', 'student_id', 'date', 'status']],
        'attendance_logs' => ['title' => 'attendance_logs', 'type' => 'attendance_log', 'cols' => ['id', 'student_id', 'scan_type', 'scan_time']],
        'attendance_summary' => ['title' => 'attendance_summary', 'type' => 'attendance_summary', 'cols' => ['id', 'student_id', 'date', 'status', 'time_in', 'time_out']],
    ];
    foreach ($simpleRelated as $key => $meta):
        if (empty($relatedRecords[$key])) {
            continue;
        }
    ?>
    <div class="page-card p-0 mb-4 overflow-hidden">
        <div class="p-3 border-bottom"><h2 class="h5 mb-0"><i class="fas fa-table"></i> <?php echo htmlspecialchars($meta['title']); ?></h2></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <?php foreach ($meta['cols'] as $col): ?>
                        <th><?php echo htmlspecialchars($col); ?></th>
                        <?php endforeach; ?>
                        <?php if ($canDelete): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($relatedRecords[$key] as $row): ?>
                    <tr>
                        <?php foreach ($meta['cols'] as $col): ?>
                        <td><?php echo htmlspecialchars((string)($row[$col] ?? '—')); ?></td>
                        <?php endforeach; ?>
                        <?php if ($canDelete): ?>
                        <td><?php echo inspectorDeleteForm($meta['type'], (int)$row['id'], $searchParams); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($hasOrphanEnrollments): ?>
    <div class="page-card p-4 mb-4 border border-warning">
        <h2 class="h5 text-warning"><i class="fas fa-tools"></i> What to do for orphan enrollments</h2>
        <ol class="mb-0">
            <li>These enrollment rows are <strong>leftover</strong> — the main <code>students</code> row is missing.</li>
            <li>They will <strong>not</strong> appear in Admin → Students until you fix this.</li>
            <li>Ask the student to <strong>register again</strong> from Apply Now (on a computer), <strong>or</strong> add them manually in <a href="students.php">Students</a>.</li>
            <li>Optionally delete orphan enrollments using the <strong>Delete</strong> buttons above, or <strong>Delete all</strong>.</li>
        </ol>
    </div>
    <?php elseif (!$exists): ?>
    <div class="page-card p-4">
        <h2 class="h5">What to do next</h2>
        <ul class="mb-0">
            <li>Ask the student to register again from the course <strong>Apply Now</strong> link (preferably on a computer).</li>
            <li>Or add them manually from <a href="students.php">Admin → Students</a>.</li>
        </ul>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
