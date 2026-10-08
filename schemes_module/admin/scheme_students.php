<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/url_helper.php';
require_once __DIR__ . '/../../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../../includes/admin_assets.php';
require_once __DIR__ . '/../../includes/theme_loader.php';
require_once __DIR__ . '/../includes/scheme_students_helper.php';
require_once __DIR__ . '/../../batch_module/includes/batch_functions.php';

if (!isset($_SESSION['admin'])) {
    header('Location: ../../admin/login.php');
    exit();
}

$admin_role = $_SESSION['admin_role'] ?? ($_SESSION['role'] ?? '');
if (!in_array($admin_role, ['master_admin'], true)) {
    header('Location: ../../admin/dashboard.php');
    exit();
}

$scheme_id = (int) ($_GET['id'] ?? 0);
$view = strtolower(trim((string) ($_GET['view'] ?? 'batches')));
if (!in_array($view, ['batches', 'courses', 'students'], true)) {
    $view = 'batches';
}
$filter_batch_id = (int) ($_GET['batch_id'] ?? 0);
$filter_course_id = (int) ($_GET['course_id'] ?? 0);

if ($scheme_id <= 0) {
    header('Location: manage_schemes.php');
    exit();
}

$stmt = $conn->prepare('SELECT * FROM schemes WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $scheme_id);
$stmt->execute();
$scheme = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$scheme) {
    header('Location: manage_schemes.php');
    exit();
}

$batches = getSchemeBatchesWithStudents($conn, $scheme_id);
$allRoster = getSchemeStudentsFromBatches($conn, $scheme_id);

$uniqueStudentIds = [];
$courses = [];
foreach ($allRoster as $student) {
    $uniqueStudentIds[(int) $student['id']] = true;
    $courseId = (int) ($student['course_id'] ?? 0);
    $label = trim((string) ($student['course_name'] ?? ''));
    if ($label === '') {
        $label = 'Unassigned course';
    }
    if (!isset($courses[$courseId])) {
        $courses[$courseId] = [
            'course_id' => $courseId,
            'course_name' => $label,
            'students' => [],
            'batches' => [],
        ];
    }
    $courses[$courseId]['students'][(int) $student['id']] = true;
    $courses[$courseId]['batches'][(int) $student['batch_id']] = true;
}
$uniqueStudentCount = count($uniqueStudentIds);
$batchStudentSum = 0;
foreach ($batches as $batch) {
    $batchStudentSum += (int) ($batch['student_count'] ?? 0);
}

$roster = array_values(array_filter($allRoster, static function ($student) use ($filter_batch_id, $filter_course_id) {
    if ($filter_batch_id > 0 && (int) $student['batch_id'] !== $filter_batch_id) {
        return false;
    }
    if ($filter_course_id > 0 && (int) $student['course_id'] !== $filter_course_id) {
        return false;
    }
    return true;
}));
$courseRows = [];
foreach ($courses as $course) {
    $courseRows[] = [
        'course_id' => $course['course_id'],
        'course_name' => $course['course_name'],
        'student_count' => count($course['students']),
        'batch_count' => count($course['batches']),
    ];
}
usort($courseRows, static function ($a, $b) {
    return strcasecmp($a['course_name'], $b['course_name']);
});

$baseUrl = 'scheme_students.php?id=' . $scheme_id;
$active_theme = loadActiveTheme($conn);

$filterBatchLabel = '';
$filterCourseLabel = '';
if ($filter_batch_id > 0) {
    foreach ($batches as $batch) {
        if ((int) $batch['id'] === $filter_batch_id) {
            $filterBatchLabel = trim((string) ($batch['batch_name'] ?? '')) . (!empty($batch['batch_code']) ? ' (' . $batch['batch_code'] . ')' : '');
            break;
        }
    }
}
if ($filter_course_id > 0) {
    foreach ($courseRows as $course) {
        if ((int) $course['course_id'] === $filter_course_id) {
            $filterCourseLabel = $course['course_name'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheme Students - <?php echo htmlspecialchars($scheme['scheme_code']); ?></title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .scheme-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:1.25rem; }
        .scheme-tabs a {
            display:inline-flex; align-items:center; gap:8px;
            padding:8px 14px; border-radius:8px; text-decoration:none;
            background:#e2e8f0; color:#334155; font-weight:600; font-size:.9rem;
        }
        .scheme-tabs a.active { background:#0ea5e9; color:#fff; }
        .scheme-stat { display:inline-flex; align-items:center; gap:6px; margin-right:1rem; color:#475569; }
        .filter-chip { display:inline-flex; align-items:center; gap:6px; background:#e0f2fe; color:#0369a1; padding:4px 10px; border-radius:999px; font-size:.8rem; font-weight:600; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/../../admin/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-users"></i> <?php echo htmlspecialchars($scheme['scheme_name']); ?></h4>
                <small>Student list from batch records · <?php echo htmlspecialchars($scheme['scheme_code']); ?></small>
            </div>
            <div class="topbar-right">
                <a href="<?php echo htmlspecialchars(app_url('schemes_module/admin/manage_schemes')); ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> All Schemes
                </a>
            </div>
        </div>
        <div class="admin-main">
            <div class="content-card" style="margin-bottom:1rem; padding:1rem 1.25rem;">
                <span class="scheme-stat"><i class="fas fa-layer-group"></i> <?php echo count($batches); ?> batches</span>
                <span class="scheme-stat"><i class="fas fa-book"></i> <?php echo count($courseRows); ?> courses</span>
                <span class="scheme-stat"><i class="fas fa-user-check"></i> <?php echo number_format($uniqueStudentCount); ?> unique students</span>
                <span class="scheme-stat"><i class="fas fa-list"></i> <?php echo number_format($batchStudentSum); ?> batch enrollments</span>
            </div>

            <div class="scheme-tabs">
                <a class="<?php echo $view === 'batches' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($baseUrl . '&view=batches'); ?>">
                    <i class="fas fa-layer-group"></i> View by batch
                </a>
                <a class="<?php echo $view === 'courses' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($baseUrl . '&view=courses'); ?>">
                    <i class="fas fa-book"></i> View by course
                </a>
                <a class="<?php echo $view === 'students' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($baseUrl . '&view=students'); ?>">
                    <i class="fas fa-users"></i> Student list
                </a>
            </div>

            <?php if ($filterBatchLabel !== '' || $filterCourseLabel !== ''): ?>
                <p style="margin-bottom:1rem;">
                    <?php if ($filterBatchLabel !== ''): ?>
                        <span class="filter-chip">Batch: <?php echo htmlspecialchars($filterBatchLabel); ?></span>
                    <?php endif; ?>
                    <?php if ($filterCourseLabel !== ''): ?>
                        <span class="filter-chip">Course: <?php echo htmlspecialchars($filterCourseLabel); ?></span>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars($baseUrl . '&view=students'); ?>" style="margin-left:8px;">Clear filter</a>
                </p>
            <?php endif; ?>

            <?php if ($view === 'batches'): ?>
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-layer-group"></i> Batches in this scheme</h5>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Batch</th>
                                <th>Course</th>
                                <th>Students</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($batches) === 0): ?>
                                <tr><td colspan="6" style="text-align:center;padding:2rem;color:#64748b;">No batches linked to this scheme.</td></tr>
                            <?php else: ?>
                                <?php $i = 1; foreach ($batches as $batch): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($batch['batch_name'] ?? ''); ?></strong>
                                        <?php if (!empty($batch['batch_code'])): ?>
                                            <div class="cell-meta"><?php echo htmlspecialchars($batch['batch_code']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($batch['course_name'] ?? '—'); ?></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($baseUrl . '&view=students&batch_id=' . (int) $batch['id']); ?>">
                                            <span class="badge badge-success"><?php echo number_format((int) $batch['student_count']); ?></span>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($batch['status'] ?? ''); ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars($baseUrl . '&view=students&batch_id=' . (int) $batch['id']); ?>">View students</a>
                                        <a class="btn btn-sm btn-secondary" href="<?php echo htmlspecialchars(app_url('batch_module/admin/batch_details') . '?id=' . (int) $batch['id']); ?>">Open batch</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif ($view === 'courses'): ?>
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-book"></i> Courses in this scheme</h5>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Course</th>
                                <th>Batches</th>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($courseRows) === 0): ?>
                                <tr><td colspan="5" style="text-align:center;padding:2rem;color:#64748b;">No course enrollments found in scheme batches.</td></tr>
                            <?php else: ?>
                                <?php $i = 1; foreach ($courseRows as $course): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($course['course_name']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo number_format((int) $course['batch_count']); ?></span></td>
                                    <td><span class="badge badge-success"><?php echo number_format((int) $course['student_count']); ?></span></td>
                                    <td>
                                        <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars($baseUrl . '&view=students&course_id=' . (int) $course['course_id']); ?>">View students</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-users"></i> Students from batch records</h5>
                    <span class="badge badge-success"><?php echo number_format(count($roster)); ?> rows</span>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Email</th>
                                <th>Course</th>
                                <th>Batch</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($roster) === 0): ?>
                                <tr><td colspan="8" style="text-align:center;padding:2rem;color:#64748b;">No students found in batch records for this scheme.</td></tr>
                            <?php else: ?>
                                <?php $i = 1; foreach ($roster as $student): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars((string) $student['student_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars((string) $student['name']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $student['mobile']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $student['email']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $student['course_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars((string) $student['batch_name']); ?>
                                        <?php if (!empty($student['batch_code'])): ?>
                                            <div class="cell-meta"><?php echo htmlspecialchars((string) $student['batch_code']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(ucfirst((string) $student['status'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
