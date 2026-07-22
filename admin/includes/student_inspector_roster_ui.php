<?php
/** @var mysqli $conn */
/** @var array $searchParams */
/** @var array $assignCoursesList */
/** @var bool $canManageEnrollment */

if (empty($canManageEnrollment)) {
    return;
}

$rosterSourceBatches = inspectorGetRosterSourceBatches($conn);
?>
<style>
.roster-preview-table { max-height: 220px; overflow-y: auto; }
.roster-step-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; font-weight: 600; }
</style>

<div class="page-card p-4 mb-4 border border-success">
    <h2 class="h5 mb-2"><i class="fas fa-clone text-success"></i> Copy Batch Roster to Another Course</h2>
    <p class="text-muted small mb-4">
        Copy all students from one batch into a <strong>different course</strong> (and scheme). Same Student ID is reused.
        Source list includes <strong>Active</strong> and <strong>Completed</strong> batches (locked batches can still be copied from).
    </p>

    <form method="POST" action="check_student_exists.php<?php echo $searchParams ? '?' . htmlspecialchars(inspectorSearchParams($searchParams)) : ''; ?>"
          id="inspector-roster-form"
          onsubmit="return inspectorConfirmRosterCopy();">
        <?php echo inspectorHiddenSearchFields($searchParams); ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="roster-step-label mb-2">Step 1 — Source batch</div>
                <label class="form-label">Copy students from</label>
                <select name="source_batch_id" id="roster-source-batch" class="form-select" required>
                    <option value="">-- Select source batch --</option>
                    <?php foreach ($rosterSourceBatches as $batch): ?>
                    <?php
                        $schemePart = !empty($batch['scheme_name']) ? ' — ' . $batch['scheme_name'] : '';
                        $batchCodePart = !empty($batch['batch_code']) ? ' (' . $batch['batch_code'] . ')' : '';
                        $statusLabel = trim((string) ($batch['status'] ?? ''));
                        $statusPart = $statusLabel !== '' ? ' [' . ucfirst(strtolower($statusLabel)) . ']' : '';
                        $label = ($batch['batch_name'] ?? 'Batch')
                            . $batchCodePart
                            . ' (' . ($batch['course_name'] ?? '') . $schemePart . ')'
                            . $statusPart
                            . ' — ' . (int)($batch['enrolled_count'] ?? 0) . ' students';
                    ?>
                    <option value="<?php echo (int)$batch['id']; ?>">
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div id="roster-source-preview" class="mt-3" style="display:none;">
                    <div class="alert alert-light border small mb-2" id="roster-source-summary"></div>
                    <div class="roster-preview-table table-responsive border rounded">
                        <table class="table table-sm table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Mobile</th>
                                </tr>
                            </thead>
                            <tbody id="roster-source-students"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="roster-step-label mb-2">Step 2 — Target course &amp; batch</div>
                <div class="mb-3">
                    <label class="form-label">Target course</label>
                    <select name="target_course_id" id="roster-target-course" class="form-select" required>
                        <option value="">-- Select target course --</option>
                        <?php foreach ($assignCoursesList as $course): ?>
                        <option value="<?php echo (int)$course['id']; ?>">
                            <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3" id="roster-target-scheme-group" style="display:none;">
                    <label class="form-label">Target scheme / project</label>
                    <select name="target_scheme_id" id="roster-target-scheme" class="form-select">
                        <option value="">-- Select scheme / project --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="assign_to_batch" value="1" id="roster-assign-to-batch" checked>
                        <label class="form-check-label" for="roster-assign-to-batch">
                            Also assign to target batch
                        </label>
                    </div>
                    <label class="form-label">Target batch</label>
                    <select name="target_batch_id" id="roster-target-batch" class="form-select">
                        <option value="">-- Select target batch (optional if unchecked above) --</option>
                    </select>
                    <small class="text-muted d-block mt-1" id="roster-target-batch-hint">Shown after you pick target course (and scheme if required).</small>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
            <button type="submit" name="copy_batch_roster" value="1" class="btn btn-success">
                <i class="fas fa-clone"></i> Copy Roster to Target Course
            </button>
            <span class="text-muted small" id="roster-copy-hint">Select a source batch with students first.</span>
        </div>
    </form>
</div>

<script>
(function () {
    const sourceSelect = document.getElementById('roster-source-batch');
    const targetCourse = document.getElementById('roster-target-course');
    const targetScheme = document.getElementById('roster-target-scheme');
    const targetSchemeGroup = document.getElementById('roster-target-scheme-group');
    const targetBatch = document.getElementById('roster-target-batch');
    const assignCheckbox = document.getElementById('roster-assign-to-batch');
    const previewBox = document.getElementById('roster-source-preview');
    const previewSummary = document.getElementById('roster-source-summary');
    const previewBody = document.getElementById('roster-source-students');
    const copyHint = document.getElementById('roster-copy-hint');
    const targetBatchHint = document.getElementById('roster-target-batch-hint');

    let sourceStudentCount = 0;
    let targetCourseRequiresScheme = false;

    function rosterEsc(text) {
        const d = document.createElement('div');
        d.textContent = text || '';
        return d.innerHTML;
    }

    function loadSourcePreview(batchId) {
        if (!batchId) {
            previewBox.style.display = 'none';
            sourceStudentCount = 0;
            copyHint.textContent = 'Select a source batch with students first.';
            return;
        }
        previewSummary.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading roster…';
        previewBox.style.display = 'block';
        previewBody.innerHTML = '';

        fetch('ajax_inspector_roster.php?action=preview_batch&batch_id=' + encodeURIComponent(batchId))
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    previewSummary.textContent = data.message || 'Could not load batch.';
                    sourceStudentCount = 0;
                    return;
                }
                const b = data.batch || {};
                sourceStudentCount = (data.students || []).length;
                const batchCodeLabel = b.batch_code ? ' (' + rosterEsc(b.batch_code) + ')' : '';
                previewSummary.innerHTML = '<strong>' + rosterEsc(b.batch_name) + batchCodeLabel + '</strong> — '
                    + rosterEsc(b.course_name)
                    + (b.scheme_name ? ' (' + rosterEsc(b.scheme_name) + ')' : '')
                    + ' — <strong>' + sourceStudentCount + '</strong> student(s) will be processed.';
                copyHint.textContent = sourceStudentCount > 0
                    ? sourceStudentCount + ' student(s) ready to copy.'
                    : 'Source batch has no students.';

                previewBody.innerHTML = (data.students || []).map(s =>
                    '<tr><td><code>' + rosterEsc(s.student_id) + '</code></td>'
                    + '<td>' + rosterEsc(s.name) + '</td>'
                    + '<td>' + rosterEsc(s.mobile) + '</td></tr>'
                ).join('');
            })
            .catch(() => {
                previewSummary.textContent = 'Failed to load batch preview.';
                sourceStudentCount = 0;
            });
    }

    function setTargetBatchHint(text) {
        if (targetBatchHint) {
            targetBatchHint.textContent = text;
        }
    }

    function loadTargetSchemes(courseId) {
        targetScheme.innerHTML = '<option value="">-- Select scheme / project --</option>';
        targetSchemeGroup.style.display = 'none';
        targetScheme.required = false;
        targetCourseRequiresScheme = false;
        targetBatch.innerHTML = '<option value="">-- Select target batch --</option>';
        setTargetBatchHint('Shown after you pick target course (and scheme if required).');

        if (!courseId) {
            return;
        }

        fetch('get_schemes_for_course.php?course_id=' + encodeURIComponent(courseId))
            .then(r => r.json())
            .then(data => {
                if (data.requires_scheme && data.schemes && data.schemes.length) {
                    targetCourseRequiresScheme = true;
                    targetSchemeGroup.style.display = 'block';
                    targetScheme.required = true;
                    data.schemes.forEach(sch => {
                        const opt = document.createElement('option');
                        opt.value = sch.id;
                        opt.textContent = sch.scheme_name + (sch.scheme_code ? ' (' + sch.scheme_code + ')' : '');
                        targetScheme.appendChild(opt);
                    });
                    setTargetBatchHint('Select a scheme/project to load batches for this course.');
                    return;
                }
                loadTargetBatches(courseId, null);
            })
            .catch(() => loadTargetBatches(courseId, null));
    }

    function loadTargetBatches(courseId, schemeId) {
        targetBatch.innerHTML = '<option value="">-- Select target batch --</option>';
        if (!courseId) {
            setTargetBatchHint('Select a target course first.');
            return;
        }
        if (targetCourseRequiresScheme && !schemeId) {
            setTargetBatchHint('Select a scheme/project to load batches for this course.');
            return;
        }

        setTargetBatchHint('Loading batches…');
        let url = 'ajax_inspector_roster.php?action=target_batches&course_id=' + encodeURIComponent(courseId);
        if (schemeId) {
            url += '&scheme_id=' + encodeURIComponent(schemeId);
        }
        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    setTargetBatchHint(data.message || 'Could not load batches.');
                    return;
                }
                const batches = data.batches || [];
                if (batches.length === 0) {
                    setTargetBatchHint(data.message || 'No active batches found. Create one in Manage Batches.');
                    return;
                }
                batches.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.id;
                    const seats = b.seats_total > 0 ? ' — ' + b.enrolled_count + '/' + b.seats_total + ' seats' : '';
                    const schemeLabel = b.scheme_name
                        ? ' — ' + b.scheme_name
                        : (b.needs_scheme_set ? ' — scheme will be set on copy' : '');
                    opt.textContent = b.batch_name + (b.batch_code ? ' (' + b.batch_code + ')' : '') + schemeLabel + seats;
                    targetBatch.appendChild(opt);
                });
                setTargetBatchHint(batches.length + ' active batch(es) available.');
            })
            .catch(() => {
                setTargetBatchHint('Failed to load batches. Refresh the page and try again.');
            });
    }

    function syncBatchRequired() {
        targetBatch.required = assignCheckbox.checked;
        targetBatch.disabled = !assignCheckbox.checked;
        if (!assignCheckbox.checked) {
            targetBatch.value = '';
        }
    }

    window.inspectorConfirmRosterCopy = function () {
        if (!sourceSelect.value) {
            alert('Please select a source batch.');
            return false;
        }
        if (!targetCourse.value) {
            alert('Please select a target course.');
            return false;
        }
        if (targetSchemeGroup.style.display !== 'none' && targetScheme.required && !targetScheme.value) {
            alert('Please select a target scheme/project.');
            return false;
        }
        if (assignCheckbox.checked && !targetBatch.value) {
            alert('Please select a target batch, or uncheck "Also assign to target batch".');
            return false;
        }
        if (sourceStudentCount === 0) {
            alert('Source batch has no students to copy.');
            return false;
        }
        const courseLabel = targetCourse.options[targetCourse.selectedIndex].text;
        return confirm(
            'Copy ' + sourceStudentCount + ' student(s) to "' + courseLabel + '"?\n\n'
            + 'Students already enrolled in the target course will be skipped or only added to the batch.'
        );
    };

    if (sourceSelect) {
        sourceSelect.addEventListener('change', function () {
            loadSourcePreview(this.value);
        });
    }
    if (targetCourse) {
        targetCourse.addEventListener('change', function () {
            loadTargetSchemes(this.value);
        });
    }
    if (targetScheme) {
        targetScheme.addEventListener('change', function () {
            loadTargetBatches(targetCourse.value, this.value || null);
        });
    }
    if (assignCheckbox) {
        assignCheckbox.addEventListener('change', syncBatchRequired);
        syncBatchRequired();
    }
})();
</script>
