<?php
/** @var array $searchParams */
/** @var array $assignCoursesList */
/** @var array $directoryCriteria */
/** @var bool $canManageEnrollment */
if (empty($canManageEnrollment)) {
    return;
}
$inspectorPageQuery = inspectorPageQueryString($searchParams, $directoryCriteria ?? []);
$inspectorCombinedHiddenFields = inspectorHiddenSearchFields($searchParams)
    . inspectorDirectoryHiddenFields($directoryCriteria ?? []);
?>
<style>
.inspector-modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.inspector-modal-content {
    background: #fff;
    margin: 8% auto;
    padding: 28px;
    border-radius: 12px;
    width: 92%;
    max-width: 520px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}
.inspector-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e2e8f0;
}
.inspector-modal-header h3 { margin: 0; font-size: 1.15rem; color: #1e293b; }
.inspector-close {
    font-size: 26px; color: #64748b; cursor: pointer; background: none; border: none; line-height: 1;
}
.inspector-info { background: #f8fafc; padding: 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9rem; }
.inspector-info p { margin: 4px 0; color: #475569; }
</style>

<div id="inspectorCourseModal" class="inspector-modal">
    <div class="inspector-modal-content">
        <div class="inspector-modal-header">
            <h3><i class="fas fa-book"></i> Assign Course</h3>
            <button type="button" class="inspector-close" onclick="inspectorCloseCourseModal()">&times;</button>
        </div>
        <div class="inspector-info" id="inspector-course-single-info">
            <p><strong>Student:</strong> <span id="inspector-course-student-name"></span></p>
            <p><strong>Student ID:</strong> <span id="inspector-course-student-id-label"></span></p>
            <p class="text-muted small mb-0">Same Student ID is reused. New course enrollments are created as <strong>Pending</strong> until approved in Manage Students (or when added to a batch).</p>
        </div>
        <div class="inspector-info" id="inspector-course-bulk-info" style="display:none;">
            <p><strong>Selected students:</strong> <span id="inspector-course-bulk-count">0</span></p>
            <ul id="inspector-course-bulk-names" class="small mb-0 ps-3 text-muted" style="max-height:120px;overflow-y:auto;"></ul>
            <p class="text-muted small mb-0 mt-2">Each student gets a separate <strong>Pending</strong> enrollment. Students already in this course are skipped.</p>
        </div>
        <form method="POST" action="check_student_exists.php?<?php echo htmlspecialchars($inspectorPageQuery); ?>" id="inspector-course-form">
            <input type="hidden" name="student_id" id="inspector-course-student-id-input">
            <div id="inspector-course-bulk-ids"></div>
            <?php echo $inspectorCombinedHiddenFields; ?>
            <div class="mb-3">
                <label class="form-label">Select Course</label>
                <select name="course_id" id="inspector-course-select" class="form-select" required>
                    <option value="">-- Select a Course --</option>
                    <?php foreach ($assignCoursesList as $course): ?>
                    <option value="<?php echo (int)$course['id']; ?>">
                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3" id="inspector-course-scheme-group" style="display:none;">
                <label class="form-label">Scheme / Project</label>
                <select name="scheme_id" id="inspector-course-scheme-select" class="form-select">
                    <option value="">-- Select Scheme / Project --</option>
                </select>
                <small id="inspector-course-scheme-hint" class="text-muted d-block mt-1"></small>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="assign_course" value="1" id="inspector-course-submit-single" class="btn btn-primary flex-fill">
                    <i class="fas fa-check"></i> Assign Course
                </button>
                <button type="submit" name="assign_course_bulk" value="1" id="inspector-course-submit-bulk" class="btn btn-primary flex-fill" style="display:none;">
                    <i class="fas fa-check"></i> Assign to Selected
                </button>
                <button type="button" class="btn btn-secondary flex-fill" onclick="inspectorCloseCourseModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="inspectorSchemeModal" class="inspector-modal">
    <div class="inspector-modal-content">
        <div class="inspector-modal-header">
            <h3><i class="fas fa-project-diagram"></i> Manage Schemes / Projects</h3>
            <button type="button" class="inspector-close" onclick="inspectorCloseSchemeModal()">&times;</button>
        </div>
        <div class="inspector-info">
            <p><strong>Student:</strong> <span id="inspector-scheme-student-name"></span></p>
            <p><strong>Course:</strong> <span id="inspector-scheme-course-name"></span></p>
            <p class="text-muted small mb-0">Tick to assign, untick to clear. Remove from batch first if needed.</p>
        </div>
        <form method="POST" action="check_student_exists.php?<?php echo htmlspecialchars($inspectorPageQuery); ?>" id="inspector-scheme-form">
            <input type="hidden" name="student_id" id="inspector-scheme-student-id">
            <input type="hidden" name="course_id" id="inspector-scheme-course-id">
            <?php echo $inspectorCombinedHiddenFields; ?>
            <div class="mb-3">
                <label class="form-label">Scheme / Project enrollments</label>
                <div id="inspector-scheme-checkboxes" class="border rounded p-3 bg-light" style="max-height:220px;overflow-y:auto;"></div>
                <small id="inspector-scheme-hint" class="text-muted d-block mt-2"></small>
            </div>
            <div id="inspector-scheme-orphan-wrap" class="alert alert-warning py-2 small" style="display:none;"></div>
            <div class="d-flex gap-2">
                <button type="submit" name="sync_student_schemes" value="1" id="inspector-scheme-save-btn" class="btn btn-primary flex-fill" disabled>
                    <i class="fas fa-check"></i> Save Schemes
                </button>
                <button type="button" class="btn btn-secondary flex-fill" onclick="inspectorCloseSchemeModal()">Close</button>
            </div>
        </form>
        <form method="POST" action="check_student_exists.php?<?php echo htmlspecialchars($inspectorPageQuery); ?>" id="inspector-scheme-orphan-form" class="mt-2" style="display:none;">
            <input type="hidden" name="student_id" id="inspector-orphan-student-id">
            <input type="hidden" name="course_id" id="inspector-orphan-course-id">
            <?php echo $inspectorCombinedHiddenFields; ?>
            <button type="submit" name="cleanup_orphan_schemes" value="1" class="btn btn-outline-warning btn-sm w-100">
                <i class="fas fa-broom"></i> Remove empty duplicate rows
            </button>
        </form>
    </div>
</div>

<script>
let inspectorCourseModalBulkMode = false;

function inspectorResetCourseSchemeFields() {
    const group = document.getElementById('inspector-course-scheme-group');
    const select = document.getElementById('inspector-course-scheme-select');
    const hint = document.getElementById('inspector-course-scheme-hint');
    if (!group || !select || !hint) {
        return;
    }
    select.innerHTML = '<option value="">-- Select Scheme / Project --</option>';
    select.value = '';
    select.required = false;
    group.style.display = 'none';
    hint.textContent = '';
}

async function inspectorLoadCourseSchemes(studentId, courseId, options) {
    const opts = options || {};
    const bulkMode = !!opts.bulk;
    const group = document.getElementById('inspector-course-scheme-group');
    const select = document.getElementById('inspector-course-scheme-select');
    const hint = document.getElementById('inspector-course-scheme-hint');
    inspectorResetCourseSchemeFields();
    if (!courseId) {
        return;
    }

    try {
        const res = await fetch('get_course_schemes_for_student.php?student_id=' + encodeURIComponent(studentId) + '&course_id=' + encodeURIComponent(courseId));
        const data = await res.json();
        if (!data.success) {
            hint.textContent = data.message || 'Could not load schemes.';
            return;
        }
        const requiresScheme = !!(data.requires_scheme || ((data.course_schemes || []).length > 0));
        const courseSchemes = data.course_schemes || [];
        if (requiresScheme) {
            group.style.display = 'block';
            if (!bulkMode && data.already_enrolled_null) {
                hint.textContent = 'Student already has a general enrollment. Use Manage Schemes instead.';
                return;
            }
            const availableSchemes = bulkMode
                ? courseSchemes
                : (data.schemes || courseSchemes.filter(s => !s.enrolled));
            if (availableSchemes.length === 0) {
                hint.textContent = bulkMode
                    ? 'No schemes linked to this course.'
                    : 'Already enrolled in all schemes for this course.';
                return;
            }
            availableSchemes.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.scheme_name + ' (' + s.scheme_code + ')';
                select.appendChild(opt);
            });
            select.required = true;
            hint.textContent = bulkMode
                ? 'Select a scheme/project — it will be applied to all selected students who need it.'
                : 'Select a scheme/project before assigning.';
        } else {
            hint.textContent = 'No schemes linked — assign without selecting a scheme.';
        }
    } catch (e) {
        hint.textContent = 'Could not load schemes. Please try again.';
    }
}

function inspectorSetCourseModalMode(bulkMode) {
    inspectorCourseModalBulkMode = bulkMode;
    const singleInfo = document.getElementById('inspector-course-single-info');
    const bulkInfo = document.getElementById('inspector-course-bulk-info');
    const studentInput = document.getElementById('inspector-course-student-id-input');
    const bulkIds = document.getElementById('inspector-course-bulk-ids');
    const submitSingle = document.getElementById('inspector-course-submit-single');
    const submitBulk = document.getElementById('inspector-course-submit-bulk');
    const title = document.querySelector('#inspectorCourseModal .inspector-modal-header h3');

    if (singleInfo) {
        singleInfo.style.display = bulkMode ? 'none' : 'block';
    }
    if (bulkInfo) {
        bulkInfo.style.display = bulkMode ? 'block' : 'none';
    }
    if (studentInput) {
        studentInput.disabled = bulkMode;
        if (bulkMode) {
            studentInput.value = '';
        }
    }
    if (bulkIds) {
        bulkIds.innerHTML = '';
    }
    if (submitSingle) {
        submitSingle.style.display = bulkMode ? 'none' : '';
        submitSingle.disabled = bulkMode;
    }
    if (submitBulk) {
        submitBulk.style.display = bulkMode ? '' : 'none';
        submitBulk.disabled = !bulkMode;
    }
    if (title) {
        title.innerHTML = bulkMode
            ? '<i class="fas fa-users"></i> Assign Course to Selected'
            : '<i class="fas fa-book"></i> Assign Course';
    }
}

function inspectorOpenCourseModal(studentId, studentName) {
    inspectorSetCourseModalMode(false);
    document.getElementById('inspector-course-student-id-input').value = studentId;
    document.getElementById('inspector-course-student-id-label').textContent = studentId;
    document.getElementById('inspector-course-student-name').textContent = studentName;
    const courseSelect = document.getElementById('inspector-course-select');
    if (courseSelect) {
        courseSelect.value = '';
    }
    inspectorResetCourseSchemeFields();
    document.getElementById('inspectorCourseModal').style.display = 'block';
}

function inspectorOpenBulkCourseModal(selectedStudents) {
    if (!selectedStudents || !selectedStudents.length) {
        alert('Please select at least one student.');
        return;
    }
    inspectorSetCourseModalMode(true);
    const bulkIds = document.getElementById('inspector-course-bulk-ids');
    const bulkNames = document.getElementById('inspector-course-bulk-names');
    const bulkCount = document.getElementById('inspector-course-bulk-count');
    if (bulkIds) {
        selectedStudents.forEach(s => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'student_ids[]';
            input.value = s.id;
            bulkIds.appendChild(input);
        });
    }
    if (bulkCount) {
        bulkCount.textContent = String(selectedStudents.length);
    }
    if (bulkNames) {
        bulkNames.innerHTML = '';
        selectedStudents.slice(0, 12).forEach(s => {
            const li = document.createElement('li');
            li.textContent = (s.name || 'Student') + ' (' + s.id + ')';
            bulkNames.appendChild(li);
        });
        if (selectedStudents.length > 12) {
            const li = document.createElement('li');
            li.textContent = '… and ' + (selectedStudents.length - 12) + ' more';
            bulkNames.appendChild(li);
        }
    }
    const courseSelect = document.getElementById('inspector-course-select');
    if (courseSelect) {
        courseSelect.value = '';
    }
    inspectorResetCourseSchemeFields();
    document.getElementById('inspectorCourseModal').style.display = 'block';
}

function inspectorCloseCourseModal() {
    document.getElementById('inspectorCourseModal').style.display = 'none';
    inspectorSetCourseModalMode(false);
    inspectorResetCourseSchemeFields();
    const courseSelect = document.getElementById('inspector-course-select');
    if (courseSelect) {
        courseSelect.value = '';
    }
}

function inspectorGetSelectedDirectoryStudents() {
    return Array.from(document.querySelectorAll('.inspector-directory-student-check:checked')).map(cb => ({
        id: cb.value,
        name: cb.dataset.studentName || 'Student',
    }));
}

function inspectorUpdateDirectorySelectionUi() {
    const checks = document.querySelectorAll('.inspector-directory-student-check');
    const checked = document.querySelectorAll('.inspector-directory-student-check:checked');
    const countEl = document.getElementById('inspector-directory-selected-count');
    const bulkBtn = document.getElementById('inspector-directory-bulk-assign-btn');
    const selectAll = document.getElementById('inspector-directory-select-all');
    if (countEl) {
        countEl.textContent = String(checked.length);
    }
    if (bulkBtn) {
        bulkBtn.disabled = checked.length === 0;
    }
    if (selectAll && checks.length) {
        selectAll.checked = checked.length === checks.length;
        selectAll.indeterminate = checked.length > 0 && checked.length < checks.length;
    }
}

async function inspectorOpenSchemeModal(studentId, studentRecordId, studentName, courseId, courseName) {
    document.getElementById('inspector-scheme-student-id').value = studentId;
    document.getElementById('inspector-scheme-course-id').value = courseId;
    document.getElementById('inspector-orphan-student-id').value = studentId;
    document.getElementById('inspector-orphan-course-id').value = courseId;
    document.getElementById('inspector-scheme-student-name').textContent = studentName;
    document.getElementById('inspector-scheme-course-name').textContent = courseName || '';

    const hint = document.getElementById('inspector-scheme-hint');
    const wrap = document.getElementById('inspector-scheme-checkboxes');
    const orphanWrap = document.getElementById('inspector-scheme-orphan-wrap');
    const orphanForm = document.getElementById('inspector-scheme-orphan-form');
    const saveBtn = document.getElementById('inspector-scheme-save-btn');

    wrap.innerHTML = '';
    hint.textContent = 'Loading…';
    orphanWrap.style.display = 'none';
    orphanForm.style.display = 'none';
    saveBtn.disabled = true;

    try {
        const url = 'get_course_schemes_for_student.php?student_id=' + encodeURIComponent(studentId)
            + '&course_id=' + encodeURIComponent(courseId)
            + '&student_record_id=' + encodeURIComponent(studentRecordId || '0');
        const res = await fetch(url);
        const data = await res.json();
        if (!data.success) {
            hint.textContent = data.message || 'Could not load schemes.';
            document.getElementById('inspectorSchemeModal').style.display = 'block';
            return;
        }
        const schemes = data.course_schemes || [];
        if (!schemes.length) {
            hint.textContent = 'No schemes linked to this course. Add schemes in Edit Course first.';
            document.getElementById('inspectorSchemeModal').style.display = 'block';
            return;
        }
        schemes.forEach(s => {
            const label = document.createElement('label');
            label.className = 'd-flex gap-2 mb-2';
            label.innerHTML = '<input type="checkbox" name="scheme_ids[]" value="' + s.id + '" class="mt-1"' + (s.enrolled ? ' checked' : '') + '>'
                + '<span><strong>' + s.scheme_name + '</strong> <small class="text-muted">(' + s.scheme_code + ')</small>'
                + (s.enrolled ? ' <span class="text-success small">✓ enrolled</span>' : '') + '</span>';
            wrap.appendChild(label);
        });
        const enrolledCount = (data.enrolled_schemes || []).length;
        hint.textContent = enrolledCount > 0
            ? enrolledCount + ' scheme(s) assigned. Tick/untick then Save Schemes.'
            : 'Tick scheme(s) to assign, then Save Schemes.';
        saveBtn.disabled = false;
        if ((data.orphan_row_count || 0) > 0) {
            orphanWrap.style.display = 'block';
            orphanWrap.textContent = data.orphan_row_count + ' empty "Not set" row(s) found. Save Schemes will clean up, or use the button below.';
            orphanForm.style.display = 'block';
        }
    } catch (e) {
        hint.textContent = 'Could not load schemes. Please try again.';
    }
    document.getElementById('inspectorSchemeModal').style.display = 'block';
}
function inspectorCloseSchemeModal() {
    document.getElementById('inspectorSchemeModal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    const courseSelect = document.getElementById('inspector-course-select');
    if (courseSelect) {
        courseSelect.addEventListener('change', function () {
            const courseId = this.value;
            if (!courseId) {
                inspectorResetCourseSchemeFields();
                return;
            }
            let studentId = document.getElementById('inspector-course-student-id-input').value;
            if (inspectorCourseModalBulkMode) {
                const firstBulk = document.querySelector('#inspector-course-bulk-ids input[name="student_ids[]"]');
                studentId = firstBulk ? firstBulk.value : studentId;
            }
            if (!studentId) {
                inspectorResetCourseSchemeFields();
                return;
            }
            inspectorLoadCourseSchemes(studentId, courseId, { bulk: inspectorCourseModalBulkMode });
        });
    }
    const courseForm = document.getElementById('inspector-course-form');
    if (courseForm) {
        courseForm.addEventListener('submit', function (e) {
            const group = document.getElementById('inspector-course-scheme-group');
            const schemeSelect = document.getElementById('inspector-course-scheme-select');
            if (group.style.display !== 'none' && schemeSelect.required && !schemeSelect.value) {
                e.preventDefault();
                alert('Please select a scheme/project for this course.');
                schemeSelect.focus();
            }
        });
    }

    document.addEventListener('click', function (event) {
        const assignBtn = event.target.closest('.inspector-assign-course-btn');
        if (assignBtn) {
            inspectorOpenCourseModal(assignBtn.dataset.studentId, assignBtn.dataset.studentName);
            return;
        }
        const schemeBtn = event.target.closest('.inspector-assign-scheme-btn');
        if (schemeBtn) {
            inspectorOpenSchemeModal(
                schemeBtn.dataset.studentId,
                schemeBtn.dataset.studentRecordId,
                schemeBtn.dataset.studentName,
                schemeBtn.dataset.courseId,
                schemeBtn.dataset.courseName
            );
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.classList && event.target.classList.contains('inspector-directory-student-check')) {
            inspectorUpdateDirectorySelectionUi();
        }
    });

    const selectAll = document.getElementById('inspector-directory-select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.inspector-directory-student-check').forEach(cb => {
                cb.checked = selectAll.checked;
            });
            inspectorUpdateDirectorySelectionUi();
        });
    }

    const clearBtn = document.getElementById('inspector-directory-clear-selection');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            document.querySelectorAll('.inspector-directory-student-check').forEach(cb => {
                cb.checked = false;
            });
            inspectorUpdateDirectorySelectionUi();
        });
    }

    const bulkAssignBtn = document.getElementById('inspector-directory-bulk-assign-btn');
    if (bulkAssignBtn) {
        bulkAssignBtn.addEventListener('click', function () {
            inspectorOpenBulkCourseModal(inspectorGetSelectedDirectoryStudents());
        });
    }

    inspectorUpdateDirectorySelectionUi();
});
</script>
