<?php
/**
 * Short registration flow for school workshops (Class 7th / 8th).
 */

if (!function_exists('ensureWorkshopRegistrationSchema')) {
    function ensureWorkshopRegistrationSchema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $courseCol = $conn->query("SHOW COLUMNS FROM courses LIKE 'registration_form'");
        if (!$courseCol || $courseCol->num_rows === 0) {
            $conn->query("ALTER TABLE courses ADD COLUMN registration_form ENUM('full','workshop') NOT NULL DEFAULT 'full' AFTER course_type");
        }
        $conn->query("UPDATE courses SET registration_form = 'workshop' WHERE course_type = 'Workshop' AND registration_form = 'full'");
        $conn->query("UPDATE courses SET registration_form = 'workshop' WHERE category = 'Workshop' AND registration_form = 'full'");

        $studentCol = $conn->query("SHOW COLUMNS FROM students LIKE 'class_standard'");
        if (!$studentCol || $studentCol->num_rows === 0) {
            $conn->query("ALTER TABLE students ADD COLUMN class_standard VARCHAR(30) NULL DEFAULT NULL AFTER category");
        } else {
            $conn->query("ALTER TABLE students MODIFY COLUMN class_standard VARCHAR(30) NULL DEFAULT NULL");
        }
    }
}

if (!function_exists('workshopCourseUsesShortForm')) {
    function workshopCourseUsesShortForm(array $course): bool
    {
        $form = strtolower(trim((string)($course['registration_form'] ?? 'full')));
        if ($form === 'workshop') {
            return true;
        }
        if ($form === 'full') {
            if (function_exists('sub_category_matches')) {
                return sub_category_matches($course['category'] ?? '', 'Workshop');
            }
            return strcasecmp(trim((string)($course['category'] ?? '')), 'Workshop') === 0;
        }
        return strtolower(trim((string)($course['course_type'] ?? ''))) === 'workshop';
    }
}

if (!function_exists('workshopLoadCourseByToken')) {
    function workshopLoadCourseByToken(mysqli $conn, string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        ensureWorkshopRegistrationSchema($conn);
        $stmt = $conn->prepare('SELECT * FROM courses WHERE registration_token = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('workshopRegistrationIsOpen')) {
    function workshopRegistrationIsOpen(array $course): bool
    {
        if (!isset($course['link_published']) || (int)$course['link_published'] !== 1) {
            return false;
        }
        $status = strtolower(trim((string)($course['enrollment_status'] ?? 'ongoing')));
        if ($status === 'closed') {
            return false;
        }
        $closing = trim((string)($course['enrollment_closing_date'] ?? ''));
        if ($closing !== '' && date('Y-m-d') > $closing) {
            return false;
        }
        return true;
    }
}

if (!function_exists('workshopRedirectWithErrors')) {
    function workshopRedirectWithErrors(string $redirectBack, array $errors, array $missingFields = []): void
    {
        $_SESSION['registration_errors'] = array_values(array_filter($errors));
        $_SESSION['registration_missing_fields'] = array_values(array_unique(array_filter($missingFields)));
        $_SESSION['registration_form_data'] = $_POST;
        header('Location: ' . $redirectBack);
        exit;
    }
}

if (!function_exists('workshopNormalizeMobile')) {
    function workshopNormalizeMobile(string $mobile): string
    {
        return substr(preg_replace('/\D/', '', $mobile), -10);
    }
}

if (!function_exists('workshopFindAccountByMobile')) {
    function workshopFindAccountByMobile(mysqli $conn, string $mobile): ?array
    {
        if (!isMultiCourseSystemInstalled($conn)) {
            return null;
        }
        $mobile = workshopNormalizeMobile($mobile);
        if (strlen($mobile) !== 10) {
            return null;
        }
        $stmt = $conn->prepare(
            "SELECT * FROM student_accounts
             WHERE REPLACE(REPLACE(mobile,' ',''),'-','') = ?
             ORDER BY id ASC LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $mobile);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('workshopIsMobileEnrolledInCourse')) {
    function workshopIsMobileEnrolledInCourse(mysqli $conn, string $mobile, int $courseId): bool
    {
        $mobile = workshopNormalizeMobile($mobile);
        if (strlen($mobile) !== 10 || $courseId <= 0) {
            return false;
        }
        $stmt = $conn->prepare(
            "SELECT id FROM students
             WHERE course_id = ?
             AND REPLACE(REPLACE(mobile,' ',''),'-','') = ?
             AND LOWER(status) NOT IN ('rejected','inactive')
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('is', $courseId, $mobile);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return !empty($row);
    }
}

if (!function_exists('getWorkshopClassStandardOptions')) {
    function getWorkshopClassStandardOptions(): array
    {
        return [
            'School (Class 1–10)' => [
                '1st' => '1st Standard',
                '2nd' => '2nd Standard',
                '3rd' => '3rd Standard',
                '4th' => '4th Standard',
                '5th' => '5th Standard',
                '6th' => '6th Standard',
                '7th' => '7th Standard',
                '8th' => '8th Standard',
                '9th' => '9th Standard',
                '10th' => '10th Standard',
            ],
            'Higher education' => [
                '11th' => '11th Standard',
                '12th' => '12th Standard',
                'Diploma' => 'Diploma',
                'Graduation' => 'Graduation',
                'PG' => 'Post Graduation',
                'Other' => 'Other',
            ],
        ];
    }
}

if (!function_exists('workshopGetAllowedClassStandards')) {
    function workshopGetAllowedClassStandards(): array
    {
        $all = [];
        foreach (getWorkshopClassStandardOptions() as $group) {
            foreach ($group as $value => $label) {
                $all[] = $value;
            }
        }
        return $all;
    }
}

if (!function_exists('getWorkshopShortFormTitle')) {
    function getWorkshopShortFormTitle(): string
    {
        return 'Workshop and Awareness Program';
    }
}

if (!function_exists('workshopProgramLabelFromCourse')) {
    function workshopProgramLabelFromCourse(array $course): string
    {
        return getWorkshopShortFormTitle();
    }
}

if (!function_exists('workshopValidateAgeForClass')) {
    /**
     * Optional soft check only — not used to block registration (workshops allow all ages).
     */
    function workshopValidateAgeForClass(string $classStandard, int $age): ?string
    {
        return null;
    }
}

if (!function_exists('workshopUploadPassportPhoto')) {
    function workshopUploadPassportPhoto(array $file, string $safeStudentId, string $redirectBack): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            workshopRedirectWithErrors($redirectBack, ['Student photo is required.'], ['passport_photo']);
        }
        $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed, true)) {
            workshopRedirectWithErrors($redirectBack, ['Photo must be JPG or PNG.'], ['passport_photo']);
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            workshopRedirectWithErrors($redirectBack, ['Photo must be 5MB or smaller.'], ['passport_photo']);
        }
        $imageInfo = @getimagesize($file['tmp_name']);
        if (!$imageInfo || ($imageInfo[0] ?? 0) < 180 || ($imageInfo[1] ?? 0) < 180) {
            workshopRedirectWithErrors(
                $redirectBack,
                ['Photo is too small or unreadable. Upload a clear passport photo (min 180×180 px).'],
                ['passport_photo']
            );
        }
        $uploadDir = dirname(__DIR__) . '/student/uploads/students/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            workshopRedirectWithErrors($redirectBack, ['Unable to create upload folder.'], ['passport_photo']);
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $ext = 'jpg';
        }
        $fn = $safeStudentId . '_' . time() . '_workshop_photo.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fn)) {
            workshopRedirectWithErrors($redirectBack, ['Failed to save photo.'], ['passport_photo']);
        }
        return 'student/uploads/students/' . $fn;
    }
}

if (!function_exists('workshopUploadAadharCard')) {
    function workshopUploadAadharCard(array $file, string $safeStudentId, string $redirectBack): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
            || empty($file['tmp_name'])
            || !is_uploaded_file($file['tmp_name'] ?? '')) {
            return '';
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            workshopRedirectWithErrors($redirectBack, ['Aadhar card upload failed. Please try again or leave it blank.'], ['aadhar_card']);
        }
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed, true)) {
            workshopRedirectWithErrors($redirectBack, ['Aadhar card must be JPG, PNG, or PDF.'], ['aadhar_card']);
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
            workshopRedirectWithErrors($redirectBack, ['Aadhar card must be JPG, PNG, or PDF.'], ['aadhar_card']);
        }
        $max = ($ext === 'pdf') ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
        if (($file['size'] ?? 0) > $max) {
            workshopRedirectWithErrors($redirectBack, ['Aadhar card file is too large.'], ['aadhar_card']);
        }
        $uploadDir = dirname(__DIR__) . '/student/uploads/aadhar/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            workshopRedirectWithErrors($redirectBack, ['Unable to create upload folder.'], ['aadhar_card']);
        }
        $fn = $safeStudentId . '_' . time() . '_aadhar.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fn)) {
            workshopRedirectWithErrors($redirectBack, ['Failed to save Aadhar card.'], ['aadhar_card']);
        }
        return 'student/uploads/aadhar/' . $fn;
    }
}

if (!function_exists('workshopAdminListCourses')) {
    /**
     * @return list<array<string,mixed>>
     */
    function workshopAdminListCourses(mysqli $conn): array
    {
        ensureWorkshopRegistrationSchema($conn);
        $out = [];
        $r = $conn->query('SELECT id, course_name, course_code, training_center, registration_form, course_type, category FROM courses ORDER BY course_name ASC');
        if (!$r) {
            return $out;
        }
        while ($row = $r->fetch_assoc()) {
            if (workshopCourseUsesShortForm($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }
}

if (!function_exists('workshopAdminCourseIds')) {
    /**
     * @return list<int>
     */
    function workshopAdminCourseIds(mysqli $conn): array
    {
        $ids = [];
        foreach (workshopAdminListCourses($conn) as $c) {
            $ids[] = (int) $c['id'];
        }
        return $ids;
    }
}

if (!function_exists('workshopAdminStoreOptionalFile')) {
    /**
     * @return array{ok:bool,path:string,error:string}
     */
    function workshopAdminStoreOptionalFile(array $file, string $subdir, string $safeId, string $suffix, array $exts, int $maxBytes): array
    {
        $empty = ['ok' => true, 'path' => '', 'error' => ''];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
            return $empty;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'path' => '', 'error' => 'File upload failed.'];
        }
        $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $exts, true)) {
            return ['ok' => false, 'path' => '', 'error' => 'Allowed types: ' . implode(', ', $exts) . '.'];
        }
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'path' => '', 'error' => 'File is too large.'];
        }
        $dir = dirname(__DIR__) . '/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'path' => '', 'error' => 'Unable to create upload folder.'];
        }
        $fn = $safeId . '_' . time() . '_' . $suffix . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $fn)) {
            return ['ok' => false, 'path' => '', 'error' => 'Failed to save file.'];
        }
        return ['ok' => true, 'path' => $subdir . $fn, 'error' => ''];
    }
}

if (!function_exists('workshopAdminCreateParticipant')) {
    /**
     * Office-desk workshop registration (same student record as the public short form).
     *
     * @return array{success:bool,message:string,student_id:string,password:string}
     */
    function workshopAdminCreateParticipant(mysqli $conn, array $post, array $files, string $adminUser): array
    {
        $fail = static function (string $msg): array {
            return ['success' => false, 'message' => $msg, 'student_id' => '', 'password' => ''];
        };

        require_once __DIR__ . '/multi_course_helper.php';
        require_once __DIR__ . '/student_id_helper.php';
        require_once __DIR__ . '/email_helper.php';
        require_once __DIR__ . '/state_city_registration.php';

        ensureWorkshopRegistrationSchema($conn);

        $courseId = (int) ($post['course_id'] ?? 0);
        $name = trim((string) ($post['name'] ?? ''));
        $classStandard = trim((string) ($post['class_standard'] ?? ''));
        $fatherName = trim((string) ($post['father_name'] ?? ''));
        $motherName = trim((string) ($post['mother_name'] ?? ''));
        $dob = trim((string) ($post['dob'] ?? ''));
        $gender = trim((string) ($post['gender'] ?? ''));
        $mobile = workshopNormalizeMobile((string) ($post['mobile'] ?? ''));
        $email = trim((string) ($post['email'] ?? ''));
        $schoolName = trim((string) ($post['school_name'] ?? ''));
        $address = trim((string) ($post['address'] ?? ''));
        $state = function_exists('normalizeStateName') ? normalizeStateName(trim((string) ($post['state'] ?? ''))) : trim((string) ($post['state'] ?? ''));
        $city = trim((string) ($post['city'] ?? ''));
        $pincode = trim((string) ($post['pincode'] ?? ''));
        $category = trim((string) ($post['category'] ?? 'General'));
        $aadhar = function_exists('normalizeAadhar') ? normalizeAadhar((string) ($post['aadhar'] ?? '')) : preg_replace('/\D/', '', (string) ($post['aadhar'] ?? ''));
        $approveNow = !empty($post['approve_now']);
        $sendEmail = !empty($post['send_email']);
        $status = $approveNow ? 'active' : 'pending';

        if ($courseId < 1) {
            return $fail('Select a workshop / awareness course.');
        }
        $stmt = $conn->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return $fail('Could not load the course.');
        }
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $courseRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$courseRow || !workshopCourseUsesShortForm($courseRow)) {
            return $fail('That course is not a workshop / awareness program. Set Registration form to Workshop on the course.');
        }
        if ($name === '') {
            return $fail('Student full name is required.');
        }
        if (!in_array($classStandard, workshopGetAllowedClassStandards(), true)) {
            return $fail('Select a valid class / level.');
        }
        if ($fatherName === '') {
            return $fail("Father's name is required.");
        }
        if ($motherName === '') {
            return $fail("Mother's name is required.");
        }
        if ($dob === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || $dob > date('Y-m-d')) {
            return $fail('Date of birth is required and cannot be in the future.');
        }
        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            return $fail('Gender is required.');
        }
        if (strlen($mobile) !== 10) {
            return $fail('Valid 10-digit parent mobile is required.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $fail('Valid email is required.');
        }
        if ($schoolName === '') {
            return $fail('School / college name is required.');
        }
        if ($address === '') {
            return $fail('Address is required.');
        }
        if ($state === '') {
            return $fail('State is required.');
        }
        if ($city === '' || $city === 'manual_input') {
            return $fail('City / district is required.');
        }
        if (!preg_match('/^\d{6}$/', $pincode)) {
            return $fail('Valid 6-digit PIN is required.');
        }
        if (!in_array($category, ['General', 'OBC', 'SC', 'ST', 'EWS'], true)) {
            return $fail('Category is required.');
        }
        if (strlen($aadhar) !== 12) {
            return $fail('Aadhar number is required (12 digits).');
        }
        if (workshopIsMobileEnrolledInCourse($conn, $mobile, $courseId)) {
            return $fail('This mobile number is already registered for this workshop.');
        }

        $age = (int) (new DateTime($dob))->diff(new DateTime())->y;
        $courseName = (string) $courseRow['course_name'];
        $trainingCenter = trim((string) ($courseRow['training_center'] ?? '')) ?: 'NIELIT BHUBANESWAR';
        $schemeId = null;

        $isReturning = false;
        $existingAccount = null;
        if ($aadhar !== '' && function_exists('findAccountByAadhar')) {
            $existingAccount = findAccountByAadhar($conn, $aadhar);
            if ($existingAccount && function_exists('isAadharEnrolledInCourseScheme')
                && isAadharEnrolledInCourseScheme($conn, $aadhar, $courseId, $schemeId)) {
                return $fail('This Aadhar is already registered for this program.');
            }
        }
        if (!$existingAccount) {
            $existingAccount = workshopFindAccountByMobile($conn, $mobile);
        }

        if ($existingAccount) {
            $isReturning = true;
            $studentId = (string) $existingAccount['student_id'];
            if ($email === '' && !empty($existingAccount['email'])) {
                $email = (string) $existingAccount['email'];
            }
        } elseif (isMultiCourseSystemInstalled($conn)) {
            $studentId = getNextGlobalStudentID($conn);
        } else {
            $studentId = getNextStudentID($courseId, $conn);
        }
        if ($studentId === null || $studentId === '') {
            return $fail('Could not generate a student ID.');
        }

        $safeStudentId = str_replace(['/', '\\', ' '], '-', $studentId);
        $photo = workshopAdminStoreOptionalFile(
            $files['passport_photo'] ?? [],
            'student/uploads/students/',
            $safeStudentId,
            'workshop_photo',
            ['jpg', 'jpeg', 'png'],
            5 * 1024 * 1024
        );
        if (!$photo['ok']) {
            return $fail($photo['error'] ?: 'Photo upload failed.');
        }
        if ($photo['path'] === '') {
            return $fail('Passport photo is required.');
        }
        $aadharFile = workshopAdminStoreOptionalFile(
            $files['aadhar_card'] ?? [],
            'student/uploads/aadhar/',
            $safeStudentId,
            'aadhar',
            ['jpg', 'jpeg', 'png', 'pdf'],
            10 * 1024 * 1024
        );
        if (!$aadharFile['ok']) {
            return $fail($aadharFile['error'] ?: 'Aadhar upload failed.');
        }
        if ($aadharFile['path'] === '') {
            return $fail('Aadhar card upload is required.');
        }

        $plainPassword = '';
        if ($isReturning && !empty($existingAccount['password'])) {
            $hashedPassword = $existingAccount['password'];
        } else {
            $plainPassword = bin2hex(random_bytes(8));
            $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        }

        $educationData = json_encode([
            'workshop' => true,
            'class_standard' => $classStandard,
            'school_name' => $schoolName,
            'entered_by_admin' => $adminUser,
        ]);

        $hasSchemeCol = function_exists('hasSchemeEnrollmentColumns') && hasSchemeEnrollmentColumns($conn);
        $hasClassCol = true;
        $classColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'class_standard'");
        if (!$classColCheck || $classColCheck->num_rows === 0) {
            $hasClassCol = false;
        }

        $schemeColSql = $hasSchemeCol ? ', scheme_id' : '';
        $schemeValSql = $hasSchemeCol ? ', ?' : '';
        $classColSql = $hasClassCol ? ', class_standard' : '';
        $classValSql = $hasClassCol ? ', ?' : '';

        $statusSql = $approveNow ? 'active' : 'pending';
        $sql = "INSERT INTO students (
            course, course_id, training_center, name, father_name, mother_name,
            dob, age, mobile, aadhar, gender, religion, marital_status,
            category, nationality, email, position,
            state, city, pincode, address, college_name, education_details,
            passport_photo, aadhar_card_doc, student_id, password,
            status{$schemeColSql}{$classColSql}, registration_date
        ) VALUES (
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,
            '{$statusSql}'{$schemeValSql}{$classValSql}, NOW()
        )";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $fail('Database error: ' . $conn->error);
        }

        $religion = 'Other';
        $maritalStatus = 'Single';
        $nationality = 'Indian';
        $position = 'Student';
        $passportPath = $photo['path'];
        $aadharPath = $aadharFile['path'];

        $bindTypes = 'ssisss' . 'issssss' . 'ssss' . 'ssssss' . 'ssss';
        $bindArgs = [
            $courseName, $courseId, $trainingCenter, $name, $fatherName,
            $motherName, $dob, $age, $mobile, $aadhar, $gender, $religion, $maritalStatus,
            $category, $nationality, $email, $position,
            $state, $city, $pincode, $address, $schoolName, $educationData,
            $passportPath, $aadharPath, $studentId, $hashedPassword,
        ];
        if ($hasSchemeCol) {
            $bindTypes .= 'i';
            $bindArgs[] = $schemeId;
        }
        if ($hasClassCol) {
            $bindTypes .= 's';
            $bindArgs[] = $classStandard;
        }

        $stmt->bind_param($bindTypes, ...$bindArgs);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return $fail('Could not save the workshop record: ' . $err);
        }
        $stmt->close();
        $studentRecordId = (int) $conn->insert_id;

        if ($studentRecordId > 0 && is_file(__DIR__ . '/nielit_registration_helper.php')) {
            require_once __DIR__ . '/nielit_registration_helper.php';
            if (function_exists('syncNielitRegistrationNoDefault')) {
                syncNielitRegistrationNoDefault($conn, $studentRecordId, null);
            }
        }

        if (isMultiCourseSystemInstalled($conn) && $studentRecordId > 0) {
            $accountId = null;
            if ($isReturning && !empty($existingAccount['id'])) {
                $accountId = (int) $existingAccount['id'];
            } else {
                $accountAadhar = $aadhar !== ''
                    ? $aadhar
                    : ('NOAADHAR/' . preg_replace('/[^A-Za-z0-9\/]/', '', $studentId));
                $accountId = createStudentAccount($conn, [
                    'student_id' => $studentId,
                    'aadhar' => $accountAadhar,
                    'name' => $name,
                    'email' => $email,
                    'mobile' => $mobile,
                    'password' => $hashedPassword,
                    'dob' => $dob,
                    'gender' => $gender,
                ]);
                if (!$accountId && $aadhar !== '') {
                    $refetch = findAccountByAadhar($conn, $aadhar);
                    $accountId = !empty($refetch['id']) ? (int) $refetch['id'] : null;
                }
                if (!$accountId) {
                    $refetch = workshopFindAccountByMobile($conn, $mobile);
                    $accountId = !empty($refetch['id']) ? (int) $refetch['id'] : null;
                }
            }
            if ($accountId) {
                linkStudentRecordToAccount($conn, $studentRecordId, $accountId);
                createStudentEnrollment($conn, $accountId, $courseId, $studentRecordId, $status, $schemeId);
            }
        }

        if ($sendEmail && !$isReturning && $plainPassword !== '') {
            dispatchRegistrationEmailAsync($email, $name, $studentId, $plainPassword, $courseName, $trainingCenter);
        }

        if (is_file(__DIR__ . '/activity_logger.php')) {
            require_once __DIR__ . '/activity_logger.php';
            logActivity($conn, [
                'action' => 'workshop_record_create',
                'description' => 'Admin entered workshop participant "' . $name . '" (' . $studentId . ') for "' . $courseName . '".',
                'entity_type' => 'student',
                'entity_id' => $studentId,
                'entity_name' => $name,
            ]);
        }

        $msg = 'Workshop record saved. Student ID: ' . $studentId . '. Status: ' . $status . '.';
        if ($plainPassword !== '') {
            $msg .= ' Password: ' . $plainPassword . '.';
        } else {
            $msg .= ' Existing student account — they can log in with their current password.';
        }
        return ['success' => true, 'message' => $msg, 'student_id' => $studentId, 'password' => $plainPassword];
    }
}
