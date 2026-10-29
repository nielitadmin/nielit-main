<?php
/** @var mysqli $conn */
/** @var array<int, array<string, string>> $importRows */

if (!function_exists('inspectorStudentListEntryRow')) {
    function inspectorStudentListEntryRow(int $serial): string
    {
        $selects = [
            'gender' => ['' => 'Select', 'Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'],
            'marital_status' => ['' => 'Select', 'Single' => 'Single', 'Married' => 'Married'],
            'nationality' => ['' => 'Select', 'Indian' => 'Indian', 'Foreign' => 'Foreign'],
            'religion' => ['' => 'Select', 'Hindu' => 'Hindu', 'Muslim' => 'Muslim', 'Christian' => 'Christian', 'Sikh' => 'Sikh', 'Other' => 'Other'],
            'category' => ['' => 'Select', 'General' => 'General', 'OBC' => 'OBC', 'SC' => 'SC', 'ST' => 'ST', 'EWS' => 'EWS'],
            'pwd_status' => ['' => 'Select', 'No' => 'No', 'Yes' => 'Yes'],
            'position' => ['' => 'Select', 'Student' => 'Student', 'Researcher' => 'Researcher', 'Faculty' => 'Faculty', 'Industrial' => 'Industrial', 'Services Holder' => 'Services Holder'],
        ];
        $textFields = [
            'father_name' => 'text',
            'mother_name' => 'text',
            'dob' => 'date',
            'age' => 'number',
            'mobile' => 'text',
            'email' => 'email',
            'aadhar' => 'text',
            'apaar_id' => 'text',
            'address' => 'text',
            'city' => 'text',
            'state' => 'text',
            'pincode' => 'text',
            'college_name' => 'text',
            'tenth_marksheet' => 'text',
            'twelfth_marksheet' => 'text',
            'graduation_certificate' => 'text',
            'caste_certificate' => 'text',
            'registration_date' => 'date',
        ];
        $order = [
            'father_name', 'mother_name', 'dob', 'age', 'gender', 'marital_status', 'mobile', 'email',
            'aadhar', 'apaar_id', 'nationality', 'religion', 'category', 'pwd_status', 'position',
            'address', 'city', 'state', 'pincode', 'college_name', 'tenth_marksheet', 'twelfth_marksheet',
            'graduation_certificate', 'caste_certificate', 'registration_date',
        ];
        $html = '<tr class="inspector-student-entry">';
        $html .= '<td class="list-sl">' . $serial . '</td>';
        $html .= '<td class="col-name"><input type="text" class="form-control form-control-sm" name="list_name[]" maxlength="255"></td>';
        foreach ($order as $field) {
            if (isset($selects[$field])) {
                $html .= '<td><select class="form-select form-select-sm" name="list_' . $field . '[]">';
                foreach ($selects[$field] as $value => $label) {
                    $html .= '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
                }
                $html .= '</select></td>';
            } else {
                $type = $textFields[$field];
                $extra = $type === 'number' ? ' min="0" max="120"' : '';
                if ($field === 'aadhar') {
                    $extra .= ' maxlength="12"';
                }
                if ($field === 'mobile') {
                    $extra .= ' maxlength="15"';
                }
                $html .= '<td><input type="' . $type . '" class="form-control form-control-sm" name="list_' . $field . '[]"' . $extra . '></td>';
            }
        }
        $html .= '<td><button type="button" class="btn btn-sm btn-outline-danger inspector-remove-student-row">Remove</button></td>';
        $html .= '</tr>';
        return $html;
    }
}

$listCourses = [];
$courseResult = $conn->query('SELECT id, course_name, course_code, course_abbreviation FROM courses ORDER BY course_name');
if ($courseResult) {
    while ($courseRow = $courseResult->fetch_assoc()) {
        $listCourses[] = $courseRow;
    }
}

$schemesByCourse = [];
$schemeTable = $conn->query("SHOW TABLES LIKE 'course_schemes'");
if ($schemeTable && $schemeTable->num_rows > 0) {
    $schemeResult = $conn->query(
        "SELECT cs.course_id, s.id, s.scheme_name
         FROM course_schemes cs
         INNER JOIN schemes s ON s.id = cs.scheme_id
         WHERE LOWER(s.status) = 'active'
         ORDER BY s.scheme_name"
    );
    if ($schemeResult) {
        while ($schemeRow = $schemeResult->fetch_assoc()) {
            $courseKey = (string) (int) $schemeRow['course_id'];
            if (!isset($schemesByCourse[$courseKey])) {
                $schemesByCourse[$courseKey] = [];
            }
            $schemesByCourse[$courseKey][] = [
                'id' => (int) $schemeRow['id'],
                'name' => (string) $schemeRow['scheme_name'],
            ];
        }
    }
}
?>
<div class="page-card p-4 mb-4 border border-primary">
    <h2 class="h5 mb-2"><i class="fas fa-file-upload text-primary"></i> Add students from a list</h2>
    <p class="text-muted small mb-3">
        Fill one row per student, or upload a sheet with the same columns. A student name is enough.
        Each new student gets an ID such as <code>NIELIT/<?php echo date('Y'); ?>/COURSE/0001</code>.
        Course and scheme are the ones selected above.
    </p>
    <style>
        .student-list-scroll { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
        .student-list-grid { min-width: 3200px; margin-bottom: 0; }
        .student-list-grid th { position: sticky; top: 0; background: #0f172a; color: #fff; font-size: 0.75rem; white-space: nowrap; }
        .student-list-grid td { min-width: 140px; vertical-align: top; }
        .student-list-grid td.col-name { min-width: 180px; }
        .student-list-grid input, .student-list-grid select { min-width: 120px; }
    </style>
    <form method="POST" action="check_student_exists.php" enctype="multipart/form-data" id="inspector-student-list-form">
        <input type="hidden" name="import_student_list" value="1">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Course *</label>
                <select name="list_course_id" id="inspector_list_course" class="form-select" required>
                    <option value="">Select course</option>
                    <?php foreach ($listCourses as $course): ?>
                        <?php
                        $abbr = trim((string) ($course['course_abbreviation'] ?? ''));
                        $label = (string) $course['course_name'];
                        if (!empty($course['course_code'])) {
                            $label .= ' (' . $course['course_code'] . ')';
                        }
                        if ($abbr !== '') {
                            $label .= ' — ' . $abbr;
                        }
                        ?>
                        <option value="<?php echo (int) $course['id']; ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6" id="inspector_list_scheme_wrap" style="display:none;">
                <label class="form-label">Scheme / project *</label>
                <select name="list_scheme_id" id="inspector_list_scheme" class="form-select">
                    <option value="">Select scheme</option>
                </select>
                <small class="text-muted">Shown only when the course is linked to a scheme or project.</small>
            </div>
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label mb-0">Student details</label>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="inspector_add_student_row">Add another student</button>
                </div>
                <div class="student-list-scroll">
                    <table class="table table-sm table-bordered student-list-grid">
                        <thead>
                            <tr>
                                <th>Sl.</th>
                                <th>Student name</th>
                                <th>Father name</th>
                                <th>Mother name</th>
                                <th>Date of birth</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Marital status</th>
                                <th>Mobile</th>
                                <th>Email</th>
                                <th>Aadhaar</th>
                                <th>APAAR ID</th>
                                <th>Nationality</th>
                                <th>Religion</th>
                                <th>Category</th>
                                <th>PWD status</th>
                                <th>Position</th>
                                <th>Address</th>
                                <th>City</th>
                                <th>State</th>
                                <th>Pincode</th>
                                <th>College / education</th>
                                <th>10th marksheet</th>
                                <th>12th marksheet</th>
                                <th>Graduation certificate</th>
                                <th>Caste certificate</th>
                                <th>Registration date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="inspector_student_rows">
                            <?php echo inspectorStudentListEntryRow(1); ?>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted">Leave a row blank if you are not using it. Scroll sideways to reach every column.</small>
            </div>
            <div class="col-lg-6">
                <label class="form-label">Or upload Excel / CSV</label>
                <input type="file" name="student_list_file" class="form-control" accept=".xlsx,.xls,.csv">
                <small class="text-muted d-block mt-2">
                    Use the same column names as the table. A sheet with only student names is still accepted.
                    <a href="check_student_exists.php?student_list_template=1">Download a sample sheet</a>.
                </small>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add students</button>
            </div>
        </div>
    </form>
    <?php if (!empty($importRows)): ?>
    <div class="table-responsive mt-4">
        <table class="table table-sm table-bordered mb-0">
            <thead>
                <tr>
                    <th>Sl. No.</th>
                    <th>Name</th>
                    <th>Student ID</th>
                    <th>Mobile</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                <?php $importSl = 0; ?>
                <?php foreach ($importRows as $importRow): ?>
                    <?php $importSl++; ?>
                    <tr>
                        <td><?php echo $importSl; ?></td>
                        <td><?php echo htmlspecialchars((string) ($importRow['name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($importRow['student_id'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($importRow['mobile'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($importRow['result'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<script>
(function () {
    var schemes = <?php echo json_encode($schemesByCourse, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var course = document.getElementById('inspector_list_course');
    var wrap = document.getElementById('inspector_list_scheme_wrap');
    var scheme = document.getElementById('inspector_list_scheme');
    if (!course || !wrap || !scheme) {
        return;
    }
    function fillSchemes() {
        var list = schemes[course.value] || [];
        scheme.innerHTML = '<option value="">Select scheme</option>';
        list.forEach(function (item) {
            var option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            scheme.appendChild(option);
        });
        var show = list.length > 0;
        wrap.style.display = show ? '' : 'none';
        scheme.required = show;
        if (!show) {
            scheme.value = '';
        }
    }
    course.addEventListener('change', fillSchemes);
    fillSchemes();

    var addBtn = document.getElementById('inspector_add_student_row');
    var body = document.getElementById('inspector_student_rows');
    function numberRows() {
        if (!body) {
            return;
        }
        var rows = body.querySelectorAll('.inspector-student-entry');
        rows.forEach(function (row, index) {
            var cell = row.querySelector('.list-sl');
            if (cell) {
                cell.textContent = String(index + 1);
            }
        });
    }
    if (addBtn && body) {
        addBtn.addEventListener('click', function () {
            var first = body.querySelector('.inspector-student-entry');
            if (!first) {
                return;
            }
            var copy = first.cloneNode(true);
            copy.querySelectorAll('input, select').forEach(function (field) {
                if (field.tagName === 'SELECT') {
                    field.selectedIndex = 0;
                } else {
                    field.value = '';
                }
            });
            body.appendChild(copy);
            numberRows();
        });
        body.addEventListener('click', function (event) {
            var button = event.target.closest('.inspector-remove-student-row');
            if (!button) {
                return;
            }
            var rows = body.querySelectorAll('.inspector-student-entry');
            var row = button.closest('.inspector-student-entry');
            if (!row) {
                return;
            }
            if (rows.length === 1) {
                row.querySelectorAll('input, select').forEach(function (field) {
                    if (field.tagName === 'SELECT') {
                        field.selectedIndex = 0;
                    } else {
                        field.value = '';
                    }
                });
                return;
            }
            row.remove();
            numberRows();
        });
    }
})();
</script>
