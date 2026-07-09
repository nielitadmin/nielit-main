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

$directoryPhotoCount = 0;
foreach ($directoryRows as $dirRow) {
    if (inspectorStudentPhotoExists($dirRow['passport_photo'] ?? null)) {
        $directoryPhotoCount++;
    }
}
?>
<style>
.inspector-directory-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.inspector-directory-card {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}
.inspector-directory-card-photo {
    flex: 0 0 180px;
    width: 180px;
    height: 230px;
    border-radius: 10px;
    overflow: hidden;
    border: 3px solid #94a3b8;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
}
.inspector-directory-card-photo a {
    display: block;
    width: 100%;
    height: 100%;
    cursor: zoom-in;
}
.inspector-directory-card-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center top;
    display: block;
}
.inspector-directory-card-photo-placeholder {
    color: #94a3b8;
    text-align: center;
    font-size: 0.85rem;
    line-height: 1.35;
    padding: 12px;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.inspector-directory-card-photo-placeholder i {
    font-size: 3rem;
    margin-bottom: 10px;
}
.inspector-directory-card-body {
    flex: 1;
    min-width: 0;
    padding-top: 4px;
}
.inspector-directory-field-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    font-weight: 700;
    margin-bottom: 2px;
}
.inspector-directory-field-value {
    font-size: 0.92rem;
    color: #0f172a;
    line-height: 1.35;
    word-break: break-word;
}
.inspector-directory-field-value.name {
    font-size: 1.1rem;
    font-weight: 700;
}
.inspector-directory-photo-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(15, 23, 42, 0.88);
    align-items: center;
    justify-content: center;
    padding: 24px;
}
.inspector-directory-photo-modal.is-open {
    display: flex;
}
.inspector-directory-photo-modal img {
    max-width: min(420px, 92vw);
    max-height: 92vh;
    width: auto;
    height: auto;
    border-radius: 12px;
    border: 4px solid #fff;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45);
    object-fit: contain;
    background: #fff;
}
.inspector-directory-photo-modal-close {
    position: absolute;
    top: 16px;
    right: 20px;
    border: none;
    background: #fff;
    color: #0f172a;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    font-size: 1.25rem;
    cursor: pointer;
}
.inspector-directory-photo-modal-caption {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 255, 255, 0.95);
    color: #0f172a;
    padding: 8px 16px;
    border-radius: 999px;
    font-size: 0.9rem;
    max-width: 90vw;
    text-align: center;
}
@media (max-width: 767px) {
    .inspector-directory-card {
        flex-direction: column;
        align-items: center;
    }
    .inspector-directory-card-photo {
        width: 200px;
        height: 255px;
    }
}
</style>

<div class="page-card p-4 mb-4 border border-info">
    <h2 class="h5 mb-2"><i class="fas fa-address-book text-info"></i> Student Quick Directory</h2>
    <p class="text-muted small mb-4">
        Each student card shows a <strong>large passport photo</strong> (click to enlarge), plus name, <strong>status</strong>, assigned course(s), address, category, mobile, and apply date.
        Use <strong>Assigned Course</strong> and <strong>Status</strong> to narrow the list, then click <strong>Show Directory</strong>.
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
            <label class="form-label">Assigned Course</label>
            <select name="dir_course_id" id="dir-course-id" class="form-select">
                <option value="">-- All assigned courses --</option>
                <?php foreach ($assignCoursesList as $course): ?>
                <option value="<?php echo (int)$course['id']; ?>"
                    <?php echo (int)($directoryCriteria['course_id'] ?? 0) === (int)$course['id'] ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Includes students assigned to this course via enrollment.</small>
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
                <?php foreach (inspectorDirectoryStatusOptions() as $statusValue => $statusLabel): ?>
                <option value="<?php echo htmlspecialchars($statusValue); ?>"
                    <?php echo strtolower((string)($directoryCriteria['status'] ?? 'all')) === $statusValue ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($statusLabel); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Pending, Active, Rejected, or Inactive.</small>
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
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h3 class="h6 mb-0"><?php echo htmlspecialchars($directoryContextTitle); ?></h3>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-info text-dark"><?php echo count($directoryRows); ?> student(s)</span>
            <?php if (!empty($directoryRows)): ?>
            <span class="badge bg-success"><?php echo (int)$directoryPhotoCount; ?> with photo</span>
            <?php if ($directoryPhotoCount < count($directoryRows)): ?>
            <span class="badge bg-secondary"><?php echo count($directoryRows) - $directoryPhotoCount; ?> no photo</span>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($directoryRows)): ?>
    <div class="alert alert-warning border mb-0">
        <strong>No students found for these filters.</strong>
        <div class="small mt-2"><?php echo inspectorDirectoryEmptyHint($conn, $directoryCriteria); ?></div>
    </div>
    <?php else: ?>
    <div class="inspector-directory-list">
        <?php foreach ($directoryRows as $row):
            $photoUrl = inspectorStudentPhotoUrl($row['passport_photo'] ?? null);
            $applyRaw = $row['apply_date'] ?? null;
            $applyLabel = !empty($applyRaw) ? date('d M Y', strtotime((string)$applyRaw)) : '—';
            $category = trim((string)($row['category'] ?? ''));
            $statusRaw = trim((string)($row['status'] ?? ''));
            $statusLabel = inspectorDirectoryStatusLabel($statusRaw);
            $statusBadgeClass = inspectorDirectoryStatusBadgeClass($statusRaw);
            $courseLabel = trim((string)($row['course_name'] ?? ('Course ID ' . ($row['course_id'] ?? ''))));
            if (!empty($row['course_code'])) {
                $courseLabel .= ' (' . $row['course_code'] . ')';
            }
            $assignedCourses = trim((string)($row['assigned_courses'] ?? ''));
            if ($assignedCourses === '') {
                $assignedCourses = $courseLabel;
            }
        ?>
        <article class="inspector-directory-card">
            <div class="inspector-directory-card-photo">
                <?php if ($photoUrl): ?>
                <a href="<?php echo htmlspecialchars($photoUrl); ?>"
                   class="inspector-directory-photo-zoom"
                   data-photo="<?php echo htmlspecialchars($photoUrl); ?>"
                   data-name="<?php echo htmlspecialchars($row['name'] ?? 'Student'); ?>"
                   title="Click to enlarge photo">
                    <img src="<?php echo htmlspecialchars($photoUrl); ?>"
                         alt="Photo of <?php echo htmlspecialchars($row['name'] ?? 'student'); ?>"
                         loading="lazy">
                </a>
                <?php else: ?>
                <div class="inspector-directory-card-photo-placeholder" title="No passport photo on file">
                    <i class="fas fa-user"></i>
                    No photo
                </div>
                <?php endif; ?>
            </div>
            <div class="inspector-directory-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="inspector-directory-field-label">Name</div>
                        <div class="inspector-directory-field-value name">
                            <?php echo htmlspecialchars($row['name'] ?? '—'); ?>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                            <?php if (!empty($row['student_id'])): ?>
                            <small class="text-muted"><code><?php echo htmlspecialchars($row['student_id']); ?></code></small>
                            <?php endif; ?>
                            <?php if ($statusRaw !== ''): ?>
                            <span class="badge <?php echo htmlspecialchars($statusBadgeClass); ?>">
                                <?php echo htmlspecialchars($statusLabel); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="inspector-directory-field-label">Assigned Course(s)</div>
                        <div class="inspector-directory-field-value">
                            <?php
                            $classLabel = trim((string)($row['class_standard'] ?? ''));
                            if ($classLabel !== '') {
                                echo 'Class ' . htmlspecialchars($classLabel) . ' — ';
                            }
                            echo htmlspecialchars($assignedCourses);
                            ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="inspector-directory-field-label">Address</div>
                        <div class="inspector-directory-field-value"><?php echo htmlspecialchars(inspectorFormatStudentAddress($row)); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="inspector-directory-field-label">Category</div>
                        <div class="inspector-directory-field-value">
                            <?php if ($category !== ''): ?>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($category); ?></span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="inspector-directory-field-label">Mobile</div>
                        <div class="inspector-directory-field-value"><?php echo htmlspecialchars($row['mobile'] ?? '—'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="inspector-directory-field-label">Apply Date</div>
                        <div class="inspector-directory-field-value"><?php echo htmlspecialchars($applyLabel); ?></div>
                    </div>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php if (count($directoryRows) >= 300): ?>
    <p class="small text-muted mt-3 mb-0">Showing first 300 records. Narrow filters to see more.</p>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div id="inspector-directory-photo-modal" class="inspector-directory-photo-modal" aria-hidden="true">
    <button type="button" class="inspector-directory-photo-modal-close" aria-label="Close">&times;</button>
    <img src="" alt="Student photo preview" id="inspector-directory-photo-modal-img">
    <div class="inspector-directory-photo-modal-caption" id="inspector-directory-photo-modal-caption"></div>
</div>

<script>
(function () {
    const courseSelect = document.getElementById('dir-course-id');
    const batchSelect = document.getElementById('dir-batch-id');
    if (courseSelect && batchSelect) {
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
    }

    const modal = document.getElementById('inspector-directory-photo-modal');
    const modalImg = document.getElementById('inspector-directory-photo-modal-img');
    const modalCaption = document.getElementById('inspector-directory-photo-modal-caption');
    const modalClose = modal ? modal.querySelector('.inspector-directory-photo-modal-close') : null;

    function closePhotoModal() {
        if (!modal) {
            return;
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (modalImg) {
            modalImg.src = '';
        }
    }

    function openPhotoModal(url, name) {
        if (!modal || !modalImg) {
            return;
        }
        modalImg.src = url;
        modalImg.alt = 'Photo of ' + name;
        if (modalCaption) {
            modalCaption.textContent = name;
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    document.querySelectorAll('.inspector-directory-photo-zoom').forEach(link => {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            openPhotoModal(this.dataset.photo || this.href, this.dataset.name || 'Student');
        });
    });

    if (modalClose) {
        modalClose.addEventListener('click', closePhotoModal);
    }
    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closePhotoModal();
            }
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePhotoModal();
        }
    });
})();
</script>
