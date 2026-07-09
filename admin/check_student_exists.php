<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';
require_once __DIR__ . '/includes/student_record_inspector.php';
require_once __DIR__ . '/includes/student_inspector_enrollment.php';
require_once __DIR__ . '/includes/student_inspector_roster.php';
require_once __DIR__ . '/includes/student_inspector_directory.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

$adminRole = $_SESSION['admin_role'] ?? '';
if ($adminRole !== 'master_admin') {
    $_SESSION['message'] = 'Access denied. Student Record Inspector is for Master Admin only.';
    $_SESSION['message_type'] = 'danger';
    header('Location: students.php');
    exit();
}

$canDelete = true;
$canManageEnrollment = true;
$assignCoursesList = inspectorGetCoursesForAssign($conn);
$flashMessage = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searchParams = [
        'aadhar' => trim($_POST['aadhar'] ?? ''),
        'mobile' => trim($_POST['mobile'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'student_id' => trim($_POST['student_id'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'course_name' => trim($_POST['course_name'] ?? ''),
        'course_id' => (int)($_POST['course_id'] ?? 0),
    ];
    $directoryCriteria = inspectorDirectoryCriteriaFromRequest($_POST);
    $redirectQs = inspectorPageQueryString($searchParams, $directoryCriteria);
    $redirectUrl = 'check_student_exists.php' . ($redirectQs !== '' ? '?' . $redirectQs : '');

    $enrollmentResult = inspectorHandleEnrollmentPost($conn, $adminRole);
    if ($enrollmentResult === null) {
        $enrollmentResult = inspectorHandleRosterPost($conn, $adminRole);
    }
    if ($enrollmentResult !== null) {
        $flashMessage = $enrollmentResult['message'];
        $flashType = $enrollmentResult['type'];
    } elseif ($canDelete) {
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

$searchCriteria = inspectorCriteriaFromRequest(array_merge($_GET, $_POST));
$aadhar = $searchCriteria['aadhar'];
$mobile = $searchCriteria['mobile'];
$email = $searchCriteria['email'];
$studentId = $searchCriteria['student_id'];
$name = $searchCriteria['name'];
$courseName = $searchCriteria['course_name'];
$courseId = (int)$searchCriteria['course_id'];

$searched = inspectorHasIdentityCriteria($searchCriteria) || inspectorHasCourseCriteria($searchCriteria);
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
$enrollmentContext = ['primary_student_id' => '', 'primary_name' => '', 'course_items' => []];
$courseFilterLabel = '';
$searchParams = $searchCriteria;
$directoryCriteria = inspectorDirectoryCriteriaFromRequest($_GET);
$directoryRows = [];
$directorySearched = false;
$directoryContextTitle = 'Student directory';

if ($searched) {
    $searchResult = inspectorRunSearch($conn, $searchCriteria);
    $studentRows = $searchResult['studentRows'];
    $hiddenStudentRows = $searchResult['hiddenStudentRows'];
    $accountRows = $searchResult['accountRows'];
    $enrollmentRows = $searchResult['enrollmentRows'];
    $courseFilterLabel = $searchResult['courseFilterLabel'];

    $inAdminPanel = !empty($studentRows);
    $hasOrphanEnrollments = empty($studentRows) && !empty($enrollmentRows);
    $exists = $inAdminPanel || !empty($accountRows) || !empty($enrollmentRows) || !empty($hiddenStudentRows);

    if ($inAdminPanel && inspectorHasCourseCriteria($searchCriteria) && !inspectorHasIdentityCriteria($searchCriteria)) {
        $summary = 'Found ' . count($studentRows) . ' student(s) for course filter'
            . ($courseFilterLabel !== '' ? ': ' . $courseFilterLabel : '') . '.';
    } elseif ($inAdminPanel) {
        $summary = 'Student is in the database and SHOULD appear in Admin → Students.';
    } elseif ($hasOrphanEnrollments) {
        $summary = 'Orphan enrollment only — NOT visible in Admin → Students (missing students row).';
    } elseif (!empty($hiddenStudentRows)) {
        $summary = 'Student exists but is rejected/inactive — hidden from Admin → Students.';
    } elseif ($exists) {
        $summary = 'Partial record found (account/enrollment) but not in main students list.';
    } else {
        $summary = 'No matching student found for your search/filter.';
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
    $enrollmentContext = inspectorBuildEnrollmentContext(
        $conn,
        $studentRows,
        $hiddenStudentRows,
        $accountRows,
        $enrollmentRows,
        $relatedRecords
    );
}

if (inspectorDirectoryHasCriteria($directoryCriteria)) {
    $directorySearched = true;
    $directoryRows = inspectorDedupeDirectoryRowsByStudentId(
        inspectorFetchDirectoryProfiles($conn, $directoryCriteria)
    );
    $directoryContextTitle = 'Directory — filtered list';
} elseif ($searched && inspectorHasCourseCriteria($searchCriteria) && !inspectorHasIdentityCriteria($searchCriteria)) {
    $directorySearched = true;
    $courseDirectoryCriteria = array_merge($directoryCriteria, [
        'course_id' => (int)$searchCriteria['course_id'],
    ]);
    if (($courseDirectoryCriteria['status'] ?? '') === '') {
        $courseDirectoryCriteria['status'] = 'all';
    }
    $directoryRows = inspectorDedupeDirectoryRowsByStudentId(
        inspectorFetchDirectoryProfiles($conn, $courseDirectoryCriteria)
    );
    $directoryContextTitle = 'Directory — all students in selected course';
} elseif ($searched && $exists) {
    $directoryRecordIds = inspectorCollectDirectoryRecordIds(
        $studentRows,
        $hiddenStudentRows,
        $relatedRecords['students_all'] ?? []
    );
    if (!empty($directoryRecordIds)) {
        $directorySearched = true;
        $directoryRows = inspectorDedupeDirectoryRowsByStudentId(
            inspectorFetchDirectoryProfiles($conn, [], $directoryRecordIds)
        );
        $directoryContextTitle = 'Directory — search matches';
    }
}

// When using top "Filter by Course" only, mirror it in the directory course dropdown.
if ($searched
    && inspectorHasCourseCriteria($searchCriteria)
    && !inspectorHasIdentityCriteria($searchCriteria)
    && (int)($directoryCriteria['course_id'] ?? 0) === 0) {
    $directoryCriteria['course_id'] = (int)$searchCriteria['course_id'];
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
            <p class="text-muted mb-0">Search by student details or filter by course. Shows all linked records, schemes, batches, and enrollments.</p>
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
            <div class="col-md-4">
                <label class="form-label">Student Name</label>
                <input type="text" name="name" class="form-control"
                       value="<?php echo htmlspecialchars($name); ?>" placeholder="Partial name match">
            </div>
            <div class="col-md-4">
                <label class="form-label">Filter by Course</label>
                <select name="course_id" class="form-select">
                    <option value="">-- All courses --</option>
                    <?php foreach ($assignCoursesList as $course): ?>
                    <option value="<?php echo (int)$course['id']; ?>"<?php echo $courseId === (int)$course['id'] ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Course Name / Code</label>
                <input type="text" name="course_name" class="form-control"
                       value="<?php echo htmlspecialchars($courseName); ?>" placeholder="e.g. Data Annotation">
                <small class="text-muted">Or type course name/code (partial match)</small>
            </div>
            <div class="col-12 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                <a href="check_student_exists.php" class="btn btn-light">Clear</a>
            </div>
        </form>
    </div>

    <?php if ($canManageEnrollment): ?>
    <?php include __DIR__ . '/includes/student_inspector_roster_ui.php'; ?>
    <?php endif; ?>

    <?php include __DIR__ . '/includes/student_inspector_directory_ui.php'; ?>

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
            <?php if ($courseFilterLabel !== ''): ?> | <strong>course:</strong> <?php echo htmlspecialchars($courseFilterLabel); ?><?php endif; ?>
        </div>
        <?php if ($hasOrphanEnrollments): ?>
        <div class="small mt-2">
            <i class="fas fa-exclamation-triangle"></i>
            Admin → Students will <strong>not</strong> show this person until a row exists in the <code>students</code> table.
            Ask them to register again, or add manually in <a href="students.php" class="alert-link">Students</a>.
        </div>
        <?php endif; ?>
    </div>

    <?php if ($searched && $exists && $canManageEnrollment && $enrollmentContext['primary_student_id'] !== ''): ?>
    <div class="page-card p-4 mb-4 border border-primary">
        <h2 class="h5 mb-3"><i class="fas fa-book-medical text-primary"></i> Assign Course &amp; Schemes</h2>
        <p class="text-muted small mb-3">
            <strong><?php echo htmlspecialchars($enrollmentContext['primary_name']); ?></strong>
            — <code><?php echo htmlspecialchars($enrollmentContext['primary_student_id']); ?></code>
        </p>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="button" class="btn btn-primary inspector-assign-course-btn"
                    data-student-id="<?php echo htmlspecialchars($enrollmentContext['primary_student_id']); ?>"
                    data-student-name="<?php echo htmlspecialchars($enrollmentContext['primary_name']); ?>">
                <i class="fas fa-book"></i> Assign Course
            </button>
        </div>
        <?php if (!empty($enrollmentContext['course_items'])): ?>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Course</th>
                        <th>Record ID</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollmentContext['course_items'] as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['course_name']); ?></td>
                        <td><?php echo !empty($item['is_orphan']) ? '<span class="text-danger">missing</span>' : (int)$item['student_record_id']; ?></td>
                        <td><?php echo htmlspecialchars($item['status']); ?></td>
                        <td>
                            <?php if (!empty($item['has_linked_schemes']) && empty($item['is_orphan'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary inspector-assign-scheme-btn"
                                    data-student-id="<?php echo htmlspecialchars($item['student_id']); ?>"
                                    data-student-record-id="<?php echo (int)$item['student_record_id']; ?>"
                                    data-student-name="<?php echo htmlspecialchars($enrollmentContext['primary_name']); ?>"
                                    data-course-id="<?php echo (int)$item['course_id']; ?>"
                                    data-course-name="<?php echo htmlspecialchars($item['course_name']); ?>">
                                <i class="fas fa-project-diagram"></i> Manage Schemes
                            </button>
                            <?php elseif (!empty($item['has_linked_schemes']) && !empty($item['is_orphan'])): ?>
                            <span class="text-muted small">Fix orphan record first, then manage schemes</span>
                            <?php else: ?>
                            <span class="text-muted small">No schemes for this course</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted small mb-0">No course enrollments yet. Use <strong>Assign Course</strong> to add one.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Scheme</th>
                        <th>Batch</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>View</th>
                        <?php if ($canDelete): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentRows as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['student_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?>
                            <?php if (!empty($row['course_code'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($row['course_code']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['scheme_name'] ?? '—'); ?></td>
                        <td><?php echo !empty($row['batch_name']) ? htmlspecialchars($row['batch_name']) : '—'; ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td><?php echo !empty($row['registration_date']) ? date('d M Y', strtotime($row['registration_date'])) : '—'; ?></td>
                        <td><a href="<?php echo htmlspecialchars(inspectorDrillDownUrl($searchParams, $row['student_id'])); ?>" class="btn btn-sm btn-outline-primary">Details</a></td>
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
                        <th>Scheme</th>
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
                        <td><?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?>
                            <?php if (!empty($row['course_code'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($row['course_code']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['scheme_name'] ?? '—'); ?></td>
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
        <div class="p-3 border-bottom"><h2 class="h5 mb-0"><i class="fas fa-layer-group"></i> student_enrollments<?php if ($hasOrphanEnrollments): ?> <span class="badge bg-warning text-dark" title="Admin → Students lists the students table only">Orphan — missing students row</span><?php endif; ?></h2></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Enrollment ID</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Scheme</th>
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
                        <td><?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?>
                            <?php if (!empty($row['course_code'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($row['course_code']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['scheme_name'] ?? '—'); ?></td>
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
                        <th>Record ID</th><th>Student ID</th><th>Name</th><th>Course</th><th>Scheme</th><th>Account</th><th>Status</th>
                        <?php if ($canDelete): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($extraStudents as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?>
                            <?php if (!empty($row['course_code'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($row['course_code']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['scheme_name'] ?? '—'); ?></td>
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
                        <td><?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?>
                            <?php if (!empty($row['course_code'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($row['course_code']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['scheme_name'] ?? '—'); ?></td>
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
<?php
if ($canManageEnrollment) {
    include __DIR__ . '/includes/student_inspector_enrollment_ui.php';
}
?>
</body>
</html>
