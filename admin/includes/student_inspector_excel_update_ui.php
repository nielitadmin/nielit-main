<?php
/** @var mysqli $conn */
/** @var array<int, array<string, string>> $updateImportRows */

$updateCourses = [];
$courseResult = $conn->query('SELECT id, course_name, course_code FROM courses ORDER BY course_name');
if ($courseResult) {
    while ($courseRow = $courseResult->fetch_assoc()) {
        $updateCourses[] = $courseRow;
    }
}
?>
<div class="page-card student-update-card p-4 mb-4 border border-success">
    <h2 class="h5 mb-2"><i class="fas fa-file-import text-success"></i> Update existing students from Excel</h2>
    <p class="text-muted small mb-3">
        Upload a sheet when users send corrections (name, mobile, email, category, NIELIT reg. no., status, etc.).
        Each row must include <strong>Student ID</strong> (preferred) or <strong>Aadhaar</strong>.
        Only <strong>non-empty cells</strong> are applied — blank cells leave the database unchanged.
        Optional course filter updates only that course&apos;s enrollment row when the same Student ID has multiple courses.
    </p>
    <form method="POST" action="check_student_exists.php" enctype="multipart/form-data">
        <input type="hidden" name="update_student_excel" value="1">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Excel / CSV file *</label>
                <input type="file" name="student_update_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                <small class="text-muted d-block mt-1">
                    <a href="check_student_exists.php?student_update_template=1">Download update template</a>
                </small>
            </div>
            <div class="col-md-5">
                <label class="form-label">Limit to course (optional)</label>
                <select name="update_course_id" class="form-select">
                    <option value="0">All enrollments for this Student ID</option>
                    <?php foreach ($updateCourses as $course): ?>
                        <option value="<?php echo (int) $course['id']; ?>">
                            <?php
                            echo htmlspecialchars((string) $course['course_name']);
                            if (!empty($course['course_code'])) {
                                echo ' (' . htmlspecialchars((string) $course['course_code']) . ')';
                            }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100">
                    <i class="fas fa-sync-alt"></i> Apply updates
                </button>
            </div>
        </div>
    </form>
    <?php if (!empty($updateImportRows)): ?>
        <div class="table-responsive mt-4">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                <tr>
                    <th>Key</th>
                    <th>Name (sheet)</th>
                    <th>Result</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($updateImportRows as $updateRow): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($updateRow['key'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($updateRow['name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($updateRow['result'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>