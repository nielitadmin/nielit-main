<?php
/** @var mysqli $conn */
/** @var array $assignCoursesList */
/** @var array $directoryCriteria */
/** @var bool $directorySearched */
/** @var array<int, array<string, mixed>> $directoryRows */
/** @var string $directoryContextTitle */

$directoryCategoryOptions = inspectorDirectoryCategoryOptions();
$directoryBatches = ($directoryCriteria['course_id'] ?? 0) > 0
    ? inspectorGetDirectoryBatches($conn, (int)$directoryCriteria['course_id'])
    : [];
?>
<style>
.inspector-directory-photo {
    width: 48px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}
.inspector-directory-photo-placeholder {
    width: 48px;
    height: 60px;
    border-radius: 6px;
    border: 1px dashed #cbd5e1;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 1.1rem;
}
.inspector-directory-address {
    max-width: 260px;
    white-space: normal;
    font-size: 0.85rem;
    line-height: 1.35;
}
</style>

<div class="page-card p-4 mb-4 border border-info">
    <h2 class="h5 mb-2"><i class="fas fa-address-book text-info"></i> Student Quick Directory</h2>
    <p class="text-muted small mb-4">
        View <strong>name, mobile, category, photo, address, course,</strong> and <strong>apply date</strong> only.
        Filter by course or use the main search above — matching students also appear below after search.
    </p>

    <form method="GET" class="row g-3 mb-4" id="inspector-directory-form">
        <?php
        foreach (['aadhar', 'mobile', 'email', 'student_id', 'name', 'course_name'] as $preserveKey):
            $preserveVal = $_GET[$preserveKey] ?? '';
            if ($preserveVal === '') {
                continue;
            }
        ?>
        <input type="hidden" name="<?php echo htmlspecialchars($preserveKey); ?>"
               value="<?php echo htmlspecialchars((string)$preserveVal); ?>">
        <?php endforeach; ?>
        <?php if (!empty($_GET['course_id'])): ?>
        <input type="hidden" name="course_id" value="<?php echo (int)$_GET['course_id']; ?>">
        <?php endif; ?>

        <div class="col-md-4">
            <label class="form-label">Course</label>
            <select name="dir_course_id" id="dir-course-id" class="form-select">
                <option value="">-- All courses --</option>
                <?php foreach ($assignCoursesList as $course): ?>
                <option value="<?php echo (int)$course['id']; ?>"
                    <?php echo (int)($directoryCriteria['course_id'] ?? 0) === (int)$course['id'] ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Batch</label>
            <select name="dir_batch_id" id="dir-batch-id" class="form-select">
                <option value="">-- All batches --</option>
                <?php foreach ($directoryBatches as $batch): ?>
                <option value="<?php echo (int)$batch['id']; ?>"
                    <?php echo (int)($directoryCriteria['batch_id'] ?? 0) === (int)$batch['id'] ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars(($batch['batch_name'] ?? 'Batch') . ' (' . (int)($batch['enrolled_count'] ?? 0) . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Category</label>
            <select name="dir_category" class="form-select">
                <option value="">-- All categories --</option>
                <?php foreach ($directoryCategoryOptions as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"
                    <?php echo ($directoryCriteria['category'] ?? '') === $cat ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="dir_status" class="form-select">
                <option value="all">All (except inactive)</option>
                <option value="pending" <?php echo ($directoryCriteria['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="active" <?php echo ($directoryCriteria['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="rejected" <?php echo ($directoryCriteria['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Apply date from</label>
            <input type="date" name="dir_date_from" class="form-control"
                   value="<?php echo htmlspecialchars($directoryCriteria['date_from'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Apply date to</label>
            <input type="date" name="dir_date_to" class="form-control"
                   value="<?php echo htmlspecialchars($directoryCriteria['date_to'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Name contains</label>
            <input type="text" name="dir_name" class="form-control"
                   value="<?php echo htmlspecialchars($directoryCriteria['name'] ?? ''); ?>" placeholder="Partial name">
        </div>
        <div class="col-md-3">
            <label class="form-label">Mobile contains</label>
            <input type="text" name="dir_mobile" class="form-control"
                   value="<?php echo htmlspecialchars($directoryCriteria['mobile'] ?? ''); ?>" placeholder="Last digits">
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-info text-white"><i class="fas fa-list"></i> Show Directory</button>
            <a href="check_student_exists.php" class="btn btn-light">Clear directory filters</a>
        </div>
    </form>

    <?php if ($directorySearched): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 mb-0"><?php echo htmlspecialchars($directoryContextTitle); ?></h3>
        <span class="badge bg-info text-dark"><?php echo count($directoryRows); ?> record(s)</span>
    </div>

    <?php if (empty($directoryRows)): ?>
    <div class="alert alert-light border mb-0">No students found for these filters.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-bordered mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;">Photo</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Category</th>
                    <th>Address</th>
                    <th>Course</th>
                    <th>Apply Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($directoryRows as $row):
                    $photoUrl = inspectorStudentPhotoUrl($row['passport_photo'] ?? null);
                    $applyRaw = $row['apply_date'] ?? null;
                    $applyLabel = !empty($applyRaw) ? date('d M Y', strtotime((string)$applyRaw)) : '—';
                    $category = trim((string)($row['category'] ?? ''));
                ?>
                <tr>
                    <td>
                        <?php if ($photoUrl): ?>
                        <a href="<?php echo htmlspecialchars($photoUrl); ?>" target="_blank" rel="noopener" title="View photo">
                            <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="" class="inspector-directory-photo">
                        </a>
                        <?php else: ?>
                        <div class="inspector-directory-photo-placeholder" title="No photo"><i class="fas fa-user"></i></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($row['name'] ?? '—'); ?></strong>
                        <?php if (!empty($row['student_id'])): ?>
                        <br><small class="text-muted"><code><?php echo htmlspecialchars($row['student_id']); ?></code></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['mobile'] ?? '—'); ?></td>
                    <td>
                        <?php if ($category !== ''): ?>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($category); ?></span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="inspector-directory-address"><?php echo htmlspecialchars(inspectorFormatStudentAddress($row)); ?></td>
                    <td>
                        <?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?>
                        <?php if (!empty($row['course_code'])): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($row['course_code']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($applyLabel); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($directoryRows) >= 300): ?>
    <p class="small text-muted mt-2 mb-0">Showing first 300 records. Narrow filters to see more.</p>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    const courseSelect = document.getElementById('dir-course-id');
    const batchSelect = document.getElementById('dir-batch-id');
    if (!courseSelect || !batchSelect) {
        return;
    }

    courseSelect.addEventListener('change', function () {
        const courseId = this.value;
        batchSelect.innerHTML = '<option value="">-- All batches --</option>';
        if (!courseId) {
            return;
        }

        fetch('ajax_inspector_directory.php?action=batches&course_id=' + encodeURIComponent(courseId))
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    return;
                }
                (data.batches || []).forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = b.batch_name + (b.enrolled_count ? ' (' + b.enrolled_count + ')' : '');
                    batchSelect.appendChild(opt);
                });
            })
            .catch(() => {});
    });
})();
</script>
