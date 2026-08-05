<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/course_public_display.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/workshop_registration_helper.php';
require_once __DIR__ . '/../includes/state_city_registration.php';

ensureWorkshopRegistrationSchema($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/public/courses.php');
    exit;
}

$registration_token = trim($_POST['registration_token'] ?? '');
$redirectBack = $registration_token !== ''
    ? APP_URL . '/student/register_workshop.php?token=' . rawurlencode($registration_token)
    : APP_URL . '/public/courses.php';

$course_id = (int)($_POST['course_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$class_standard = trim($_POST['class_standard'] ?? '');
$father_name = trim($_POST['father_name'] ?? '');
$mother_name = trim($_POST['mother_name'] ?? '');
$dob = trim($_POST['dob'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$mobile = workshopNormalizeMobile(trim($_POST['mobile'] ?? ''));
$email = trim($_POST['email'] ?? '');
$school_name = trim($_POST['school_name'] ?? '');
$address = trim($_POST['address'] ?? '');
$state = normalizeStateName(trim($_POST['state'] ?? ''));
$city = trim($_POST['city'] ?? '');
$pincode = trim($_POST['pincode'] ?? '');
$category = trim($_POST['category'] ?? '');
$aadhar = normalizeAadhar(trim($_POST['aadhar'] ?? ''));
$training_center = trim($_POST['training_center'] ?? 'NIELIT BHUBANESWAR');

$errors = [];
$missing = [];

if ($course_id <= 0) {
    $errors[] = 'Course information is missing.';
    $missing[] = 'course_id';
}
if ($name === '') {
    $errors[] = 'Student name is required.';
    $missing[] = 'name';
}
if (!in_array($class_standard, workshopGetAllowedClassStandards(), true)) {
    $errors[] = 'Please select a valid class or education level.';
    $missing[] = 'class_standard';
}
if ($father_name === '' && $mother_name === '') {
    $errors[] = 'Enter at least father or mother / guardian name.';
    $missing[] = 'father_name';
}
if ($dob === '') {
    $errors[] = 'Date of birth is required.';
    $missing[] = 'dob';
}
if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
    $errors[] = 'Gender is required.';
    $missing[] = 'gender';
}
if (strlen($mobile) !== 10) {
    $errors[] = 'Valid 10-digit mobile number is required.';
    $missing[] = 'mobile';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required for registration confirmation.';
    $missing[] = 'email';
}
if ($school_name === '') {
    $errors[] = 'School name is required.';
    $missing[] = 'school_name';
}
if ($address === '') {
    $errors[] = 'Address is required.';
    $missing[] = 'address';
}
if ($state === '') {
    $errors[] = 'State is required.';
    $missing[] = 'state';
}
if ($city === '' || $city === 'manual_input') {
    $errors[] = 'City / District is required.';
    $missing[] = 'city';
}
if ($pincode === '' || !preg_match('/^\d{6}$/', $pincode)) {
    $errors[] = 'Valid 6-digit pincode is required.';
    $missing[] = 'pincode';
}
if ($category !== '' && !in_array($category, ['General', 'OBC', 'SC', 'ST', 'EWS'], true)) {
    $category = 'General';
}
if ($category === '') {
    $category = 'General';
}
if ($aadhar !== '' && strlen($aadhar) !== 12) {
    $errors[] = 'Aadhar number must be exactly 12 digits if provided.';
    $missing[] = 'aadhar';
}

$age = $dob !== '' ? (int)(new DateTime($dob))->diff(new DateTime())->y : 0;

if (!empty($errors)) {
    workshopRedirectWithErrors($redirectBack, $errors, $missing);
}

$courseStmt = $conn->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
$courseStmt->bind_param('i', $course_id);
$courseStmt->execute();
$courseRow = $courseStmt->get_result()->fetch_assoc();
$courseStmt->close();

if (!$courseRow || !workshopCourseUsesShortForm($courseRow)) {
    workshopRedirectWithErrors($redirectBack, ['This course does not use workshop registration.'], []);
}
if (!workshopRegistrationIsOpen($courseRow)) {
    setCoursesPageNotice('Registration for this workshop is closed.');
    header('Location: ' . APP_URL . '/public/courses.php');
    exit;
}

$course_name = (string)$courseRow['course_name'];
$scheme_id = null;

if (workshopIsMobileEnrolledInCourse($conn, $mobile, $course_id)) {
    workshopRedirectWithErrors(
        $redirectBack,
        ['This mobile number is already registered for this workshop. Contact admin if you need help.'],
        ['mobile']
    );
}

$is_returning_student = false;
$existing_account = null;
if ($aadhar !== '') {
    $existing_account = findAccountByAadhar($conn, $aadhar);
    if ($existing_account && isAadharEnrolledInCourseScheme($conn, $aadhar, $course_id, $scheme_id)) {
        workshopRedirectWithErrors($redirectBack, ['This Aadhar is already registered for this program.'], ['aadhar']);
    }
}
if (!$existing_account) {
    $existing_account = workshopFindAccountByMobile($conn, $mobile);
}

if ($existing_account) {
    $is_returning_student = true;
    $student_id = (string)$existing_account['student_id'];
    if ($email === '' && !empty($existing_account['email'])) {
        $email = (string)$existing_account['email'];
    }
} elseif (isMultiCourseSystemInstalled($conn)) {
    $student_id = getNextGlobalStudentID($conn);
} else {
    $student_id = getNextStudentID($course_id, $conn);
}

if ($student_id === null || $student_id === '') {
    workshopRedirectWithErrors($redirectBack, ['Could not generate student ID. Contact admin.'], []);
}

$safe_student_id = str_replace(['/', '\\', ' '], '-', $student_id);
$passport_photo_path = workshopUploadPassportPhoto($_FILES['passport_photo'] ?? [], $safe_student_id, $redirectBack);
$aadhar_card_path = workshopUploadAadharCard($_FILES['aadhar_card'] ?? [], $safe_student_id, $redirectBack);

$password = null;
if ($is_returning_student && !empty($existing_account['password'])) {
    $hashed_password = $existing_account['password'];
} else {
    $password = bin2hex(random_bytes(8));
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
}

$aadhar = $aadhar;
$education_data = json_encode([
    'workshop' => true,
    'class_standard' => $class_standard,
    'school_name' => $school_name,
]);

$hasSchemeCol = hasSchemeEnrollmentColumns($conn);
$hasClassCol = true;
$classColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'class_standard'");
if (!$classColCheck || $classColCheck->num_rows === 0) {
    $hasClassCol = false;
}

$schemeColSql = $hasSchemeCol ? ', scheme_id' : '';
$schemeValSql = $hasSchemeCol ? ', ?' : '';
$classColSql = $hasClassCol ? ', class_standard' : '';
$classValSql = $hasClassCol ? ', ?' : '';

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
    'pending'{$schemeValSql}{$classValSql}, NOW()
)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    workshopRedirectWithErrors($redirectBack, ['Database error: ' . $conn->error], []);
}

$religion = 'Other';
$marital_status = 'Single';
$nationality = 'Indian';
$position = 'Student';
$signature_path = '';
$empty = '';

$bindTypes = 'ssisss' . 'issssss' . 'ssss' . 'ssssss' . 'ssss';
$bindArgs = [
    $course_name, $course_id, $training_center, $name, $father_name,
    $mother_name, $dob, $age, $mobile, $aadhar, $gender, $religion, $marital_status,
    $category, $nationality, $email, $position,
    $state, $city, $pincode, $address, $school_name, $education_data,
    $passport_photo_path, $aadhar_card_path, $student_id, $hashed_password,
];
if ($hasSchemeCol) {
    $bindTypes .= 'i';
    $bindArgs[] = $scheme_id;
}
if ($hasClassCol) {
    $bindTypes .= 's';
    $bindArgs[] = $class_standard;
}

$stmt->bind_param($bindTypes, ...$bindArgs);
if (!$stmt->execute()) {
    $absPhoto = __DIR__ . '/../' . $passport_photo_path;
    if (is_file($absPhoto)) {
        unlink($absPhoto);
    }
    if ($aadhar_card_path !== '') {
        $absAadhar = __DIR__ . '/../' . $aadhar_card_path;
        if (is_file($absAadhar)) {
            unlink($absAadhar);
        }
    }
    workshopRedirectWithErrors($redirectBack, ['Registration failed: ' . $stmt->error], []);
}
$stmt->close();

$student_record_id = (int)$conn->insert_id;

if ($student_record_id > 0 && file_exists(__DIR__ . '/../includes/nielit_registration_helper.php')) {
    require_once __DIR__ . '/../includes/nielit_registration_helper.php';
    syncNielitRegistrationNoDefault($conn, $student_record_id, null);
}

if (isMultiCourseSystemInstalled($conn) && $student_record_id > 0) {
    $account_id = null;
    if ($is_returning_student && !empty($existing_account['id'])) {
        $account_id = (int)$existing_account['id'];
    } else {
        // student_accounts.aadhar is UNIQUE — use a non-colliding placeholder when Aadhar is skipped.
        $accountAadhar = $aadhar !== ''
            ? $aadhar
            : ('NOAADHAR/' . preg_replace('/[^A-Za-z0-9\/]/', '', $student_id));
        $account_id = createStudentAccount($conn, [
            'student_id' => $student_id,
            'aadhar' => $accountAadhar,
            'name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'password' => $hashed_password,
            'dob' => $dob,
            'gender' => $gender,
        ]);
        if (!$account_id && $aadhar !== '') {
            $refetch = findAccountByAadhar($conn, $aadhar);
            $account_id = !empty($refetch['id']) ? (int)$refetch['id'] : null;
        }
        if (!$account_id) {
            $refetch = workshopFindAccountByMobile($conn, $mobile);
            $account_id = !empty($refetch['id']) ? (int)$refetch['id'] : null;
        }
    }
    if ($account_id) {
        linkStudentRecordToAccount($conn, $student_record_id, $account_id);
        createStudentEnrollment($conn, $account_id, $course_id, $student_record_id, 'pending', $scheme_id);
    }
}

if ($is_returning_student) {
    $_SESSION['success'] = "Workshop registration submitted for <strong>$name</strong>. Student ID: <strong>$student_id</strong>. Log in to your dashboard with your existing password. Enrollment will show <strong>Active</strong> after admin verifies your documents.";
    $_SESSION['registration_email_sent'] = false;
    $_SESSION['registration_email_queued'] = false;
} else {
    $email_queued = dispatchRegistrationEmailAsync($email, $name, $student_id, $password, $course_name, $training_center);
    $_SESSION['success'] = "Workshop registration successful! Student ID: <strong>$student_id</strong>, Password: <strong>$password</strong>. You can log in to your student dashboard now. Status will change to <strong>Active</strong> after admin verifies your documents.";
    if ($email_queued) {
        $_SESSION['success'] .= " Confirmation email is being sent to <strong>$email</strong>.";
    } else {
        $_SESSION['success'] .= " Please save your credentials shown on the next screen.";
    }
    $_SESSION['registration_email_sent'] = false;
    $_SESSION['registration_email_queued'] = $email_queued;

    if (file_exists(__DIR__ . '/../includes/activity_logger.php')) {
        require_once __DIR__ . '/../includes/activity_logger.php';
        logActivity($conn, [
            'actor_type' => 'student',
            'actor_id' => (string) $student_id,
            'actor_name' => $name,
            'action' => 'student_register',
            'entity_type' => 'course',
            'entity_id' => isset($course_id) ? (string) $course_id : null,
            'entity_name' => $course_name,
            'description' => 'Student "' . $name . '" (' . $student_id . ') registered for workshop/course "' . $course_name . '".',
            'details' => ['email' => $email, 'form' => 'workshop'],
        ]);
    }
}

$_SESSION['student_id'] = $student_id;
$_SESSION['student_password'] = $password ?? '';
$_SESSION['student_email'] = $email;
$_SESSION['course_name'] = $course_name;
$_SESSION['training_center'] = $training_center;
$_SESSION['is_returning_student'] = $is_returning_student;

finalizeRegistrationRedirect(APP_URL . '/student/registration_success.php');
