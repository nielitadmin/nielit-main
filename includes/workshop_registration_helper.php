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
