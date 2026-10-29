<?php
/**
 * Add students from a typed list or an Excel sheet on the Student Record Inspector.
 * A name is enough. A student ID is generated for each new person.
 */

require_once __DIR__ . '/../../includes/student_id_helper.php';
require_once __DIR__ . '/../../includes/training_partner_helper.php';

if (!function_exists('inspectorListHeaderKey')) {
    function inspectorListHeaderKey($cell): string
    {
        $text = strtolower(trim((string) $cell));
        return (string) preg_replace('/[^a-z0-9]/', '', $text);
    }
}

if (!function_exists('inspectorListNormalizeGender')) {
    function inspectorListNormalizeGender(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'm' || $value === 'male') {
            return 'Male';
        }
        if ($value === 'f' || $value === 'female') {
            return 'Female';
        }
        if ($value === 'o' || $value === 'other') {
            return 'Other';
        }
        return '';
    }
}

if (!function_exists('inspectorListDigits')) {
    function inspectorListDigits(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }
}

if (!function_exists('inspectorListIsNameHeader')) {
    function inspectorListIsNameHeader(string $label): bool
    {
        if ($label === '') {
            return false;
        }
        $blocked = ['father', 'mother', 'institute', 'partner', 'course', 'employ', 'file', 'user'];
        foreach ($blocked as $word) {
            if (strpos($label, $word) !== false) {
                return false;
            }
        }
        return in_array($label, ['studentname', 'nameofcandidate', 'candidatename', 'student', 'name', 'fullname'], true)
            || strpos($label, 'nameofcandidate') !== false
            || strpos($label, 'studentname') !== false;
    }
}

if (!function_exists('inspectorListBlankRecord')) {
    function inspectorListBlankRecord(): array
    {
        return [
            'name' => '',
            'father_name' => '',
            'mother_name' => '',
            'dob' => '',
            'age' => '',
            'gender' => '',
            'marital_status' => '',
            'mobile' => '',
            'email' => '',
            'aadhar' => '',
            'apaar_id' => '',
            'nationality' => '',
            'religion' => '',
            'category' => '',
            'pwd_status' => '',
            'position' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'pincode' => '',
            'college_name' => '',
            'tenth_marksheet' => '',
            'twelfth_marksheet' => '',
            'graduation_certificate' => '',
            'caste_certificate' => '',
            'registration_date' => '',
            'student_id' => '',
        ];
    }
}

if (!function_exists('inspectorListHeaderField')) {
    function inspectorListHeaderField(string $label): ?string
    {
        if (inspectorListIsNameHeader($label)) {
            return 'name';
        }
        if (strpos($label, 'father') !== false) {
            return 'father_name';
        }
        if (strpos($label, 'mother') !== false) {
            return 'mother_name';
        }
        if ($label === 'dob' || strpos($label, 'dateofbirth') !== false || strpos($label, 'birthdate') !== false) {
            return 'dob';
        }
        if ($label === 'age') {
            return 'age';
        }
        if (strpos($label, 'marital') !== false) {
            return 'marital_status';
        }
        if (strpos($label, '10th') !== false || strpos($label, 'tenth') !== false) {
            return 'tenth_marksheet';
        }
        if (strpos($label, '12th') !== false || strpos($label, 'twelfth') !== false) {
            return 'twelfth_marksheet';
        }
        if (strpos($label, 'graduation') !== false) {
            return 'graduation_certificate';
        }
        if (strpos($label, 'caste') !== false) {
            return 'caste_certificate';
        }
        if (strpos($label, 'registrationdate') !== false) {
            return 'registration_date';
        }
        if (strpos($label, 'email') !== false) {
            return 'email';
        }
        if (strpos($label, 'aadhaar') !== false || strpos($label, 'aadhar') !== false) {
            return 'aadhar';
        }
        if (strpos($label, 'apaar') !== false) {
            return 'apaar_id';
        }
        if (strpos($label, 'nationality') !== false) {
            return 'nationality';
        }
        if (strpos($label, 'religion') !== false) {
            return 'religion';
        }
        if (strpos($label, 'pwd') !== false || strpos($label, 'disabilit') !== false) {
            return 'pwd_status';
        }
        if (strpos($label, 'category') !== false) {
            return 'category';
        }
        if (strpos($label, 'position') !== false || strpos($label, 'occupation') !== false) {
            return 'position';
        }
        if (strpos($label, 'pincode') !== false || $label === 'pin') {
            return 'pincode';
        }
        if ($label === 'city' || strpos($label, 'city') === 0) {
            return 'city';
        }
        if ($label === 'state' || strpos($label, 'state') === 0) {
            return 'state';
        }
        if (strpos($label, 'address') !== false) {
            return 'address';
        }
        if (strpos($label, 'college') !== false || strpos($label, 'education') !== false) {
            return 'college_name';
        }
        if (strpos($label, 'gender') === 0) {
            return 'gender';
        }
        if (strpos($label, 'studentid') !== false) {
            return 'student_id';
        }
        if (strpos($label, 'whatsapp') !== false || strpos($label, 'watsapp') !== false || in_array($label, ['mobile', 'phone', 'mobileno', 'contactno', 'contact'], true)) {
            return 'mobile';
        }
        return null;
    }
}

if (!function_exists('inspectorListAssignField')) {
    function inspectorListAssignField(array $record, string $field, string $raw): array
    {
        $raw = trim($raw);
        if ($field === 'mobile') {
            $digits = inspectorListDigits($raw);
            $record['mobile'] = strlen($digits) >= 10 ? substr($digits, -10) : '';
        } elseif ($field === 'aadhar') {
            $digits = inspectorListDigits($raw);
            $record['aadhar'] = strlen($digits) === 12 ? $digits : '';
        } elseif ($field === 'gender') {
            $record['gender'] = inspectorListNormalizeGender($raw);
        } elseif ($field === 'email') {
            $record['email'] = filter_var($raw, FILTER_VALIDATE_EMAIL) ? $raw : '';
        } elseif ($field === 'age') {
            $record['age'] = preg_match('/^\d{1,3}$/', inspectorListDigits($raw)) ? inspectorListDigits($raw) : '';
        } elseif ($field === 'pwd_status') {
            $flag = strtolower($raw);
            if (in_array($flag, ['y', 'yes', '1', 'true'], true)) {
                $record['pwd_status'] = 'Yes';
            } elseif (in_array($flag, ['n', 'no', '0', 'false'], true)) {
                $record['pwd_status'] = 'No';
            } else {
                $record['pwd_status'] = $raw;
            }
        } elseif (isset($record[$field])) {
            $record[$field] = $raw;
        }
        return $record;
    }
}

if (!function_exists('inspectorListRecordsFromPost')) {
    function inspectorListRecordsFromPost(array $post): array
    {
        $names = $post['list_name'] ?? null;
        if (!is_array($names)) {
            return [];
        }
        $fields = array_keys(inspectorListBlankRecord());
        $records = [];
        foreach ($names as $index => $name) {
            $record = inspectorListBlankRecord();
            $record['name'] = trim((string) $name);
            if ($record['name'] === '') {
                continue;
            }
            foreach ($fields as $field) {
                if ($field === 'name') {
                    continue;
                }
                $bag = $post['list_' . $field] ?? [];
                $raw = is_array($bag) ? (string) ($bag[$index] ?? '') : '';
                $record = inspectorListAssignField($record, $field, $raw);
            }
            $records[] = $record;
        }
        return $records;
    }
}

if (!function_exists('inspectorListRecordFromParts')) {
    function inspectorListRecordFromParts(array $parts): ?array
    {
        $clean = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $clean[] = $part;
            }
        }
        if ($clean === []) {
            return null;
        }
        if (isset($clean[1]) && preg_match('/^\d{1,4}$/', $clean[0])) {
            array_shift($clean);
        }
        $record = inspectorListBlankRecord();
        foreach ($clean as $index => $part) {
            $key = inspectorListHeaderKey($part);
            if ($record['name'] === '' && preg_match('/[a-z]/i', $part)) {
                if (in_array($key, ['name', 'studentname', 'nameofcandidate', 'candidatename', 'student', 'slno', 'sno', 'serialno'], true)) {
                    return null;
                }
                $record['name'] = $part;
                continue;
            }
            if ($record['email'] === '' && strpos($part, '@') !== false) {
                $record['email'] = $part;
                continue;
            }
            $digits = inspectorListDigits($part);
            if ($record['aadhar'] === '' && strlen($digits) === 12) {
                $record['aadhar'] = $digits;
                continue;
            }
            if ($record['mobile'] === '' && strlen($digits) >= 10 && strlen($digits) <= 15) {
                $record['mobile'] = substr($digits, -10);
                continue;
            }
            $gender = inspectorListNormalizeGender($part);
            if ($record['gender'] === '' && $gender !== '') {
                $record['gender'] = $gender;
                continue;
            }
            if ($record['father_name'] === '' && $index > 0 && preg_match('/[a-z]/i', $part)) {
                $record['father_name'] = $part;
            }
        }
        $record['name'] = trim($record['name']);
        if ($record['name'] === '') {
            return null;
        }
        if ($record['email'] !== '' && !filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
            $record['email'] = '';
        }
        return $record;
    }
}

if (!function_exists('inspectorListRecordsFromText')) {
    function inspectorListRecordsFromText(string $text): array
    {
        $records = [];
        $lines = preg_split('/\r\n|\r|\n/', $text);
        if (!is_array($lines)) {
            return $records;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\t|,/', $line);
            $record = inspectorListRecordFromParts(is_array($parts) ? $parts : [$line]);
            if ($record !== null) {
                $records[] = $record;
            }
        }
        return $records;
    }
}

if (!function_exists('inspectorListRecordsFromMatrix')) {
    function inspectorListRecordsFromMatrix(array $matrix): array
    {
        $headerIndex = null;
        $map = null;
        $scanUntil = min(count($matrix), 25);
        for ($rowIndex = 0; $rowIndex < $scanUntil; $rowIndex++) {
            $labels = array_map('inspectorListHeaderKey', $matrix[$rowIndex]);
            $found = [];
            foreach ($labels as $index => $label) {
                $field = inspectorListHeaderField($label);
                if ($field !== null && !isset($found[$field])) {
                    $found[$field] = $index;
                }
            }
            if (isset($found['name'])) {
                $headerIndex = $rowIndex;
                $map = $found;
                break;
            }
        }

        $records = [];
        if ($map !== null && $headerIndex !== null) {
            for ($i = $headerIndex + 1; $i < count($matrix); $i++) {
                $row = $matrix[$i];
                $record = inspectorListBlankRecord();
                foreach ($map as $field => $index) {
                    $record = inspectorListAssignField($record, $field, (string) ($row[$index] ?? ''));
                }
                if ($record['name'] === '' || preg_match('/^\d+$/', $record['name'])) {
                    continue;
                }
                $records[] = $record;
            }
            return $records;
        }

        foreach ($matrix as $row) {
            $record = inspectorListRecordFromParts($row);
            if ($record !== null) {
                $records[] = $record;
            }
        }
        return $records;
    }
}

if (!function_exists('inspectorListMatricesFromUpload')) {
    function inspectorListMatricesFromUpload(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['matrices' => []];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['error' => 'The Excel file could not be uploaded.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $name = (string) ($file['name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['error' => 'The Excel file could not be read.'];
        }
        if (defined('MAX_FILE_SIZE') && (int) ($file['size'] ?? 0) > (int) MAX_FILE_SIZE) {
            return ['error' => 'The Excel file is larger than the allowed upload size.'];
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $matrices = [];
        if ($ext === 'csv') {
            $matrices[] = training_partner_read_csv($tmp);
        } elseif ($ext === 'xlsx') {
            $matrices = training_partner_read_xlsx_sheets($tmp);
        } elseif ($ext === 'xls') {
            $raw = file_get_contents($tmp);
            if (is_string($raw) && strpos($raw, '<Workbook') !== false) {
                $matrices[] = training_partner_read_spreadsheetml($raw);
            } else {
                $matrices = training_partner_read_xlsx_sheets($tmp);
            }
        } else {
            return ['error' => 'Upload a .xlsx, .xls, or .csv file.'];
        }
        return ['matrices' => $matrices];
    }
}

if (!function_exists('inspectorListAllocateStudentId')) {
    function inspectorListAllocateStudentId(mysqli $conn, int $courseId): ?string
    {
        $id = getNextStudentID($courseId, $conn);
        if ($id === null || $id === '') {
            $id = getNextGlobalStudentID($conn);
        }
        if ($id === null || $id === '') {
            return null;
        }
        if (!globalStudentIDExists($conn, $id)) {
            return $id;
        }
        $slash = strrpos($id, '/');
        if ($slash === false) {
            return null;
        }
        $prefix = substr($id, 0, $slash + 1);
        $seq = (int) substr($id, $slash + 1);
        $width = max(4, strlen(substr($id, $slash + 1)));
        for ($n = $seq + 1; $n < $seq + 80; $n++) {
            $candidate = $prefix . str_pad((string) $n, $width, '0', STR_PAD_LEFT);
            if (!globalStudentIDExists($conn, $candidate)) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('inspectorListPlaceholderAadhar')) {
    function inspectorListPlaceholderAadhar(mysqli $conn, string $studentId): string
    {
        $base = abs(crc32($studentId));
        for ($i = 0; $i < 30; $i++) {
            $aadhar = '8' . str_pad((string) (($base + $i) % 100000000000), 11, '0', STR_PAD_LEFT);
            $stmt = $conn->prepare('SELECT id FROM student_accounts WHERE aadhar = ? LIMIT 1');
            if (!$stmt) {
                return $aadhar;
            }
            $stmt->bind_param('s', $aadhar);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if (!$exists) {
                return $aadhar;
            }
        }
        return '';
    }
}

if (!function_exists('inspectorListAlreadyEnrolled')) {
    function inspectorListAlreadyEnrolled(mysqli $conn, string $studentId, int $courseId, ?int $schemeId, bool $hasScheme): bool
    {
        if ($hasScheme) {
            if ($schemeId === null) {
                $stmt = $conn->prepare(
                    "SELECT id FROM students WHERE student_id = ? AND course_id = ? AND scheme_id IS NULL AND LOWER(status) NOT IN ('rejected', 'inactive') LIMIT 1"
                );
                if (!$stmt) {
                    return false;
                }
                $stmt->bind_param('si', $studentId, $courseId);
            } else {
                $stmt = $conn->prepare(
                    "SELECT id FROM students WHERE student_id = ? AND course_id = ? AND scheme_id = ? AND LOWER(status) NOT IN ('rejected', 'inactive') LIMIT 1"
                );
                if (!$stmt) {
                    return false;
                }
                $stmt->bind_param('sii', $studentId, $courseId, $schemeId);
            }
        } else {
            $stmt = $conn->prepare(
                "SELECT id FROM students WHERE student_id = ? AND course_id = ? AND LOWER(status) NOT IN ('rejected', 'inactive') LIMIT 1"
            );
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('si', $studentId, $courseId);
        }
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $found;
    }
}

if (!function_exists('inspectorImportStudentList')) {
    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{message: string, type: string, rows: array<int, array<string, string>>}
     */
    function inspectorImportStudentList(mysqli $conn, array $post, array $files): array
    {
        $courseId = (int) ($post['list_course_id'] ?? 0);
        $schemeId = normalizeEnrollmentSchemeId($post['list_scheme_id'] ?? null);
        if ($courseId <= 0) {
            return ['message' => 'Select the course these students belong to.', 'type' => 'warning', 'rows' => []];
        }

        $courseStmt = $conn->prepare('SELECT course_name, training_center FROM courses WHERE id = ? LIMIT 1');
        if (!$courseStmt) {
            return ['message' => 'Could not read the selected course.', 'type' => 'danger', 'rows' => []];
        }
        $courseStmt->bind_param('i', $courseId);
        $courseStmt->execute();
        $course = $courseStmt->get_result()->fetch_assoc();
        $courseStmt->close();
        if (!$course) {
            return ['message' => 'The selected course was not found.', 'type' => 'danger', 'rows' => []];
        }

        $courseSchemes = getSchemesForCourse($conn, $courseId);
        if (!empty($courseSchemes) && $schemeId === null) {
            return ['message' => 'Select the scheme or project for this course.', 'type' => 'warning', 'rows' => []];
        }
        if ($schemeId !== null && !validateSchemeForCourse($conn, $courseId, $schemeId)) {
            return ['message' => 'That scheme is not linked to the selected course.', 'type' => 'warning', 'rows' => []];
        }

        $records = inspectorListRecordsFromPost($post);
        foreach (inspectorListRecordsFromText((string) ($post['student_list_text'] ?? '')) as $typedRecord) {
            $records[] = $typedRecord;
        }
        $upload = inspectorListMatricesFromUpload($files['student_list_file'] ?? []);
        if (!empty($upload['error'])) {
            return ['message' => (string) $upload['error'], 'type' => 'danger', 'rows' => []];
        }
        foreach ($upload['matrices'] as $matrix) {
            if (is_array($matrix)) {
                foreach (inspectorListRecordsFromMatrix($matrix) as $record) {
                    $records[] = $record;
                }
            }
        }
        if ($records === []) {
            return ['message' => 'Enter at least one student name, or upload a sheet that contains names.', 'type' => 'warning', 'rows' => []];
        }
        if (count($records) > 1000) {
            return ['message' => 'A single upload can add up to 1000 students. Split the list and try again.', 'type' => 'warning', 'rows' => []];
        }

        $hasScheme = hasSchemeEnrollmentColumns($conn);
        $courseName = (string) $course['course_name'];
        $trainingCenter = trim((string) ($course['training_center'] ?? ''));
        if ($trainingCenter === '') {
            $trainingCenter = 'NIELIT BHUBANESWAR';
        }
        $status = 'active';
        $passport = '';
        $aadharDoc = '';
        $optionalColumns = [
            'apaar_id' => 'apaar_id',
            'pwd_status' => 'pwd_status',
            'tenth_marksheet_doc' => 'tenth_marksheet',
            'twelfth_marksheet_doc' => 'twelfth_marksheet',
            'graduation_certificate_doc' => 'graduation_certificate',
            'caste_certificate_doc' => 'caste_certificate',
        ];
        $presentOptional = [];
        foreach ($optionalColumns as $column => $field) {
            $columnCheck = $conn->query("SHOW COLUMNS FROM students LIKE '" . $conn->real_escape_string($column) . "'");
            if ($columnCheck && $columnCheck->num_rows > 0) {
                $presentOptional[$column] = $field;
            }
        }

        $schemeSql = $hasScheme ? ', scheme_id' : '';
        $schemeVal = $hasScheme ? ', ?' : '';
        $optionalSql = $presentOptional === [] ? '' : ', ' . implode(', ', array_keys($presentOptional));
        $optionalVal = $presentOptional === [] ? '' : ', ' . implode(', ', array_fill(0, count($presentOptional), '?'));
        $sql = "INSERT INTO students (
            course, course_id, training_center, name, father_name, mother_name,
            dob, age, mobile, aadhar, gender, religion, marital_status,
            category, nationality, email, position,
            state, city, pincode, address, college_name, education_details,
            passport_photo, aadhar_card_doc, student_id, password, status{$optionalSql}{$schemeSql}, registration_date
        ) VALUES (
            ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?{$optionalVal}{$schemeVal}, ?
        )";
        $insert = $conn->prepare($sql);
        if (!$insert) {
            return ['message' => 'Could not prepare the student save: ' . $conn->error, 'type' => 'danger', 'rows' => []];
        }

        $added = 0;
        $skipped = 0;
        $rows = [];
        $seenMobile = [];
        $seenAadhar = [];
        $multiCourse = isMultiCourseSystemInstalled($conn);

        foreach ($records as $record) {
            $name = $record['name'];
            $mobile = $record['mobile'];
            $email = $record['email'];
            $father = $record['father_name'];
            $mother = $record['mother_name'];
            $dob = training_partner_parse_date($record['dob']);
            $age = ($record['age'] !== '' && ctype_digit($record['age'])) ? (int) $record['age'] : 0;
            if ($age <= 0 && $dob !== null) {
                $born = DateTime::createFromFormat('Y-m-d', $dob);
                if ($born instanceof DateTime) {
                    $age = (int) $born->diff(new DateTime('today'))->y;
                }
            }
            $gender = $record['gender'] !== '' ? $record['gender'] : 'Other';
            $religion = $record['religion'] !== '' ? $record['religion'] : 'Other';
            $marital = $record['marital_status'] !== '' ? $record['marital_status'] : 'Single';
            $category = $record['category'];
            $nationality = $record['nationality'] !== '' ? $record['nationality'] : 'Indian';
            $position = $record['position'] !== '' ? $record['position'] : 'Student';
            $state = $record['state'];
            $city = $record['city'];
            $pincode = $record['pincode'];
            $address = $record['address'];
            $college = $record['college_name'];
            $education = json_encode(['source' => 'student_list_import', 'education' => $college]);
            $registrationDate = training_partner_parse_date($record['registration_date']);
            if ($registrationDate === null || $registrationDate === '') {
                $registrationDate = date('Y-m-d');
            }
            $aadhar = $record['aadhar'];
            $givenId = trim($record['student_id']);

            if ($mobile !== '' && isset($seenMobile[$mobile])) {
                $skipped++;
                $rows[] = ['name' => $name, 'student_id' => '', 'mobile' => $mobile, 'result' => 'Skipped. This mobile is repeated in the list.'];
                continue;
            }
            if ($aadhar !== '' && isset($seenAadhar[$aadhar])) {
                $skipped++;
                $rows[] = ['name' => $name, 'student_id' => '', 'mobile' => $mobile, 'result' => 'Skipped. This Aadhaar is repeated in the list.'];
                continue;
            }

            $studentId = '';
            $account = null;
            if ($aadhar !== '' && function_exists('findAccountByAadhar')) {
                $account = findAccountByAadhar($conn, $aadhar);
            }
            if (is_array($account) && !empty($account['student_id'])) {
                $studentId = (string) $account['student_id'];
            } elseif ($givenId !== '') {
                if (globalStudentIDExists($conn, $givenId)) {
                    $skipped++;
                    $rows[] = ['name' => $name, 'student_id' => $givenId, 'mobile' => $mobile, 'result' => 'Skipped. That student ID already exists.'];
                    continue;
                }
                $studentId = $givenId;
            } else {
                $studentId = (string) inspectorListAllocateStudentId($conn, $courseId);
            }
            if ($studentId === '') {
                $skipped++;
                $rows[] = ['name' => $name, 'student_id' => '', 'mobile' => $mobile, 'result' => 'Skipped. A student ID could not be generated. Set a course abbreviation and try again.'];
                continue;
            }

            if ($aadhar !== '' && function_exists('isAadharEnrolledInCourseScheme') && isAadharEnrolledInCourseScheme($conn, $aadhar, $courseId, $schemeId)) {
                $skipped++;
                $rows[] = ['name' => $name, 'student_id' => $studentId, 'mobile' => $mobile, 'result' => 'Skipped. This Aadhaar is already in the selected course.'];
                continue;
            }
            if (inspectorListAlreadyEnrolled($conn, $studentId, $courseId, $schemeId, $hasScheme)) {
                $skipped++;
                $rows[] = ['name' => $name, 'student_id' => $studentId, 'mobile' => $mobile, 'result' => 'Skipped. This student is already in the selected course.'];
                continue;
            }

            $passwordPlain = bin2hex(random_bytes(4));
            $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);
            $bindTypes = 'sisssssissssssssssssssssssss';
            $bindArgs = [
                $courseName, $courseId, $trainingCenter, $name, $father, $mother,
                $dob, $age, $mobile, $aadhar, $gender, $religion, $marital,
                $category, $nationality, $email, $position,
                $state, $city, $pincode, $address, $college, $education,
                $passport, $aadharDoc, $studentId, $passwordHash, $status,
            ];
            foreach ($presentOptional as $field) {
                $bindTypes .= 's';
                if ($field === 'pwd_status') {
                    $bindArgs[] = $record['pwd_status'] !== '' ? $record['pwd_status'] : 'No';
                } else {
                    $bindArgs[] = (string) ($record[$field] ?? '');
                }
            }
            if ($hasScheme) {
                $bindTypes .= 'i';
                $bindArgs[] = $schemeId;
            }
            $bindTypes .= 's';
            $bindArgs[] = $registrationDate;
            $insert->bind_param($bindTypes, ...$bindArgs);
            if (!$insert->execute()) {
                $skipped++;
                $rows[] = ['name' => $name, 'student_id' => $studentId, 'mobile' => $mobile, 'result' => 'Could not save: ' . $insert->error];
                continue;
            }
            $recordId = (int) $insert->insert_id;
            if (function_exists('syncNielitRegistrationNoDefault')) {
                syncNielitRegistrationNoDefault($conn, $recordId, null);
            }

            if ($multiCourse && $recordId > 0) {
                $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
                if ($accountId <= 0) {
                    $accountAadhar = $aadhar !== '' ? $aadhar : inspectorListPlaceholderAadhar($conn, $studentId);
                    if ($accountAadhar !== '') {
                        $accountId = (int) createStudentAccount($conn, [
                            'student_id' => $studentId,
                            'aadhar' => $accountAadhar,
                            'name' => $name,
                            'email' => $email,
                            'mobile' => $mobile,
                            'password' => $passwordHash,
                            'dob' => $dob,
                            'gender' => $gender,
                        ]);
                    }
                }
                if ($accountId > 0) {
                    linkStudentRecordToAccount($conn, $recordId, $accountId);
                    createStudentEnrollment($conn, $accountId, $courseId, $recordId, $status, $schemeId);
                }
            }

            if ($mobile !== '') {
                $seenMobile[$mobile] = true;
            }
            if ($aadhar !== '') {
                $seenAadhar[$aadhar] = true;
            }
            $added++;
            $rows[] = ['name' => $name, 'student_id' => $studentId, 'mobile' => $mobile, 'result' => 'Added'];
        }
        $insert->close();

        if ($added > 0 && function_exists('logStudentAdminActivity')) {
            logStudentAdminActivity(
                $conn,
                'student_list_import',
                'Added ' . $added . ' student' . ($added === 1 ? '' : 's') . ' from a list to "' . $courseName . '".',
                (string) $courseId,
                $courseName,
                ['source' => 'student_record_inspector', 'added' => $added, 'skipped' => $skipped]
            );
        }

        if ($added === 0) {
            return [
                'message' => 'No students were added. ' . $skipped . ' row' . ($skipped === 1 ? ' was' : 's were') . ' skipped.',
                'type' => 'warning',
                'rows' => $rows,
            ];
        }
        $message = $added . ' student' . ($added === 1 ? '' : 's') . ' added to ' . $courseName . '. Student IDs were generated for new names.';
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' row' . ($skipped === 1 ? ' was' : 's were') . ' skipped.';
        }
        return ['message' => $message, 'type' => $skipped > 0 ? 'warning' : 'success', 'rows' => $rows];
    }
}
