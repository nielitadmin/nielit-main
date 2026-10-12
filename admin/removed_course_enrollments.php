<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../includes/teaching_access.php';
admin_redirect_faculty_from_restricted_page();

$active_theme = loadActiveTheme($conn);
$admin_role = $_SESSION['admin_role'] ?? '';
$is_course_coordinator = ($admin_role === 'course_coordinator');
$is_front_office = ($admin_role === 'front_office_desk');

if ($is_front_office) {
    $_SESSION['message'] = 'Access denied.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . relative_url('students.php'));
    exit();
}

$admin_course_ids = [];
if ($is_course_coordinator) {
    $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($admin_id > 0) {
        $tmp = $conn->prepare('SELECT course_id FROM admin_course_assignments WHERE admin_id = ? AND is_active = 1');
        if ($tmp) {
            $tmp->bind_param('i', $admin_id);
            $tmp->execute();
            $res = $tmp->get_result();
            while ($row = $res->fetch_assoc()) {
                $admin_course_ids[] = (int) $row['course_id'];
            }
            $tmp->close();
        }
    }
}

ensureStudentCourseRemovalArchive($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_removal_id'])) {
    $removalId = (int) $_POST['restore_removal_id'];
    $allowed = true;
    if ($is_course_coordinator) {
        $chk = $conn->prepare('SELECT course_id FROM student_course_removals WHERE id = ? LIMIT 1');
        $courseId = 0;
        if ($chk) {
            $chk->bind_param('i', $removalId);
            $chk->execute();
            $hit = $chk->get_result()->fetch_assoc();
            $chk->close();
            $courseId = (int) ($hit['course_id'] ?? 0);
        }
        $allowed = in_array($courseId, $admin_course_ids, true);
    }
    if (!$allowed) {
        $_SESSION['message'] = 'Access denied. You can only restore enrollments for your assigned courses.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $result = adminRestoreStudentCourseRemoval($conn, $removalId);
        $_SESSION['message'] = $result['message'] ?? 'Restore finished.';
        $_SESSION['message_type'] = !empty($result['success']) ? 'success' : 'danger';
    }
    header('Location: ' . relative_url('removed_course_enrollments.php'));
    exit();
}

$where = 'WHERE r.restored_at IS NULL';
if ($is_course_coordinator) {
    if ($admin_course_ids === []) {
        $where .= ' AND 1=0';
    } else {
        $where .= ' AND r.course_id IN (' . implode(',', array_map('intval', $admin_course_ids)) . ')';
    }
}

$rows = [];
$sql = "SELECT r.id, r.student_id, r.student_name, r.course_id, r.course_name,
               r.removed_by, r.removed_by_role, r.removed_at
        FROM student_course_removals r
        {$where}
        ORDER BY r.removed_at DESC, r.id DESC
        LIMIT 500";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
}

$show_restored = isset($_GET['show']) && $_GET['show'] === 'restored';
$restored_rows = [];
if ($show_restored) {
    $restoredWhere = 'WHERE r.restored_at IS NOT NULL';
    if ($is_course_coordinator) {
        if ($admin_course_ids === []) {
            $restoredWhere .= ' AND 1=0';
        } else {
            $restoredWhere .= ' AND r.course_id IN (' . implode(',', array_map('intval', $admin_course_ids)) . ')';
        }
    }
    $restoredSql = "SELECT r.id, r.student_id, r.student_name, r.course_id, r.course_name,
                           r.removed_by, r.removed_at, r.restored_by, r.restored_at
                    FROM student_course_removals r
                    {$restoredWhere}
                    ORDER BY r.restored_at DESC
                    LIMIT 200";
    $restoredRes = $conn->query($restoredSql);
    if ($restoredRes) {
        while ($row = $restoredRes->fetch_assoc()) {
            $restored_rows[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recover Removed Enrollments</title>
    <?php adminEmitHeadAssets($active_theme); ?>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-undo"></i> Recover Removed Enrollments</h4>
                <small>Course removals are archived so they can be restored. Records deleted before this feature cannot be recovered from here.</small>
            </div>
            <div class="topbar-right">
                <a href="<?php echo htmlspecialchars(relative_url('students.php')); ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Students
                </a>
            </div>
        </div>
        <div class="admin-main">
            <?php if (!empty($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo ($_SESSION['message_type'] ?? 'info') === 'danger' ? 'danger' : htmlspecialchars($_SESSION['message_type']); ?>">
                    <?php echo htmlspecialchars((string) $_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
                </div>
            <?php endif; ?>

            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-inbox"></i> Waiting to restore (<?php echo count($rows); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Removed on</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Course</th>
                                <th>Removed by</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows === []): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted" style="padding:28px;">
                                    No removable-course archives waiting. New “Remove from course” actions will appear here.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) $row['removed_at']); ?></td>
                                    <td><strong><?php echo htmlspecialchars((string) $row['student_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars((string) ($row['student_name'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['course_name'] ?? '')); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars((string) ($row['removed_by'] ?? '')); ?>
                                        <?php if (!empty($row['removed_by_role'])): ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars((string) $row['removed_by_role']); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Restore this student to the course? They will need a batch assigned again.');">
                                            <input type="hidden" name="restore_removal_id" value="<?php echo (int) $row['id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <p style="margin-top:16px;">
                <?php if ($show_restored): ?>
                    <a href="<?php echo htmlspecialchars(relative_url('removed_course_enrollments.php')); ?>">Hide already restored</a>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars(relative_url('removed_course_enrollments.php')); ?>?show=restored">Show already restored</a>
                <?php endif; ?>
            </p>

            <?php if ($show_restored): ?>
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">Already restored</h5>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Restored on</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Course</th>
                                <th>Restored by</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($restored_rows === []): ?>
                            <tr><td colspan="5" class="text-center text-muted">None yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($restored_rows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) $row['restored_at']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['student_name'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['course_name'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['restored_by'] ?? '')); ?></td>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
