<?php
/** @var mysqli $conn */
/** @var array<int, array<string, string>> $importRows */

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
        Type names or upload an Excel sheet. A name is enough when that is all you have.
        Each new student gets an ID such as <code>NIELIT/<?php echo date('Y'); ?>/COURSE/0001</code>.
        Mobile, email, father's name, gender, and Aadhaar are saved when the list includes them.
    </p>
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
            <div class="col-lg-6">
                <label class="form-label">Type the student list</label>
                <textarea name="student_list_text" class="form-control" rows="8" placeholder="Ramesh Kumar&#10;Sita Das, 9876543210&#10;Amit Kumar, 9876500000, amit@email.com"></textarea>
                <small class="text-muted">One student per line. A name is enough. You can also add mobile and email after a comma.</small>
            </div>
            <div class="col-lg-6">
                <label class="form-label">Or upload Excel / CSV</label>
                <input type="file" name="student_list_file" class="form-control" accept=".xlsx,.xls,.csv">
                <small class="text-muted d-block mt-2">
                    A sheet with only names is accepted. If the file has headings, use Name, Mobile, Email, Father name, or Gender.
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
})();
</script>
