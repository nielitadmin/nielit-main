<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/student_id_helper.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';
require_once __DIR__ . '/../includes/email_helper.php';

// ============================================================
// HELPERS
// ============================================================
function validateUploadedDocument($file, $docCategory) {
    $allowedTypes      = ['image/jpeg','image/jpg','application/pdf','image/png'];
    $allowedExtensions = ['jpg','jpeg','pdf','png'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $codes = [0=>'OK',1=>'Exceeds php.ini limit',2=>'Exceeds form limit',3=>'Partial upload',4=>'No file',6=>'No tmp dir',7=>'Write fail',8=>'Extension blocked'];
        return ['valid'=>false,'message'=>'Upload error: '.($codes[$file['error']] ?? 'Code '.$file['error'])];
    }
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mimeType, $allowedTypes))
        return ['valid'=>false,'message'=>"Invalid file type: $mimeType"];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions))
        return ['valid'=>false,'message'=>"Invalid extension: .$ext"];
    $max = ($ext === 'pdf') ? 10*1024*1024 : 5*1024*1024;
    if ($file['size'] > $max)
        return ['valid'=>false,'message'=>"Too large: ".round($file['size']/1024/1024,2)."MB (max ".($max/1024/1024)."MB)"];
    $content = file_get_contents($file['tmp_name'], false, null, 0, 1024);
    if (strpos($content, '<?php') !== false || strpos($content, '#!/') !== false)
        return ['valid'=>false,'message'=>'Invalid file content'];
    return ['valid'=>true];
}

function handleCategorizedUpload($file, $docCategory, $student_id) {
    $v = validateUploadedDocument($file, $docCategory);
    if (!$v['valid']) return ['success'=>false,'error'=>$v['message']];

    $subdirs = ['aadhar'=>'aadhar','caste'=>'caste_certificates','tenth'=>'marksheets/10th','twelfth'=>'marksheets/12th','graduation'=>'marksheets/graduation','other'=>'other'];
    $subdir  = $subdirs[$docCategory] ?? 'other';
    $dir     = __DIR__ . '/uploads/' . $subdir . '/';

    if (!is_dir($dir) && !mkdir($dir, 0755, true))
        return ['success'=>false,'error'=>"Cannot create directory: $dir"];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // FIX: Replace slashes in student_id for use in filename only
    $safe_id  = str_replace(['/', '\\', ' '], '-', $student_id);
    $filename = $safe_id . '_' . time() . '_' . $docCategory . '.' . $ext;
    $dest     = $dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $path = 'student/uploads/'.$subdir.'/'.$filename;
        
        // Verify the file exists at the returned path
        if (!file_exists(__DIR__ . '/../' . $path)) {
            error_log("Path verification failed: File saved to $dest but path $path doesn't resolve correctly");
            return ['success'=>false,'error'=>'File upload verification failed'];
        }
        
        return ['success'=>true,'path'=>$path];
    }

    return ['success'=>false,'error'=>"move_uploaded_file failed. Dest: $dest | Writable: ".(is_writable($dir)?'YES':'NO')];
}

function validateThumbImpressionUpload($file) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    $allowedExtensions = ['jpg', 'jpeg', 'png'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'Upload error: ' . $file['error']];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes, true)) {
        return ['valid' => false, 'message' => "Invalid file type: $mimeType"];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        return ['valid' => false, 'message' => "Invalid extension: .$ext"];
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return ['valid' => false, 'message' => 'Thumb impression must be 2MB or smaller'];
    }

    return ['valid' => true];
}

function handleThumbImpressionUpload($file, $student_id) {
    $validation = validateThumbImpressionUpload($file);
    if (!$validation['valid']) {
        return ['success' => false, 'error' => $validation['message']];
    }

    $dir = __DIR__ . '/uploads/students/thumb_impression/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['success' => false, 'error' => "Cannot create directory: $dir"];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safe_id = str_replace(['/', '\\', ' '], '-', $student_id);
    $filename = $safe_id . '_' . time() . '_thumb.' . $ext;
    $dest = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'error' => 'Failed to save thumb impression image'];
    }

    return ['success' => true, 'path' => 'student/uploads/students/thumb_impression/' . $filename];
}

function normalizeStateName($state) {
    $state = trim((string)$state);

    $stateMap = [
        'AN' => 'Andaman and Nicobar Islands',
        'AP' => 'Andhra Pradesh',
        'AR' => 'Arunachal Pradesh',
        'AS' => 'Assam',
        'BR' => 'Bihar',
        'CH' => 'Chandigarh',
        'CT' => 'Chhattisgarh',
        'DN' => 'Dadra and Nagar Haveli and Daman and Diu',
        'DL' => 'Delhi',
        'GA' => 'Goa',
        'GJ' => 'Gujarat',
        'HR' => 'Haryana',
        'HP' => 'Himachal Pradesh',
        'JK' => 'Jammu and Kashmir',
        'JH' => 'Jharkhand',
        'KA' => 'Karnataka',
        'KL' => 'Kerala',
        'LD' => 'Lakshadweep',
        'MP' => 'Madhya Pradesh',
        'MH' => 'Maharashtra',
        'MN' => 'Manipur',
        'ML' => 'Meghalaya',
        'MZ' => 'Mizoram',
        'NL' => 'Nagaland',
        'OD' => 'Odisha',
        'OR' => 'Odisha',
        'PB' => 'Punjab',
        'PY' => 'Puducherry',
        'RJ' => 'Rajasthan',
        'SK' => 'Sikkim',
        'TN' => 'Tamil Nadu',
        'TG' => 'Telangana',
        'TR' => 'Tripura',
        'UP' => 'Uttar Pradesh',
        'UT' => 'Uttarakhand',
        'WB' => 'West Bengal'
    ];

    $upperState = strtoupper($state);
    return $stateMap[$upperState] ?? $state;
}

function registrationFieldLabels() {
    return [
        'name' => 'Full Name',
        'father_name' => "Father's Name",
        'mother_name' => "Mother's Name",
        'dob' => 'Date of Birth',
        'gender' => 'Gender',
        'marital_status' => 'Marital Status',
        'mobile' => 'Mobile Number',
        'email' => 'Email Address',
        'aadhar' => 'Aadhar Number',
        'nationality' => 'Nationality',
        'religion' => 'Religion',
        'category' => 'Category',
        'position' => 'Position/Occupation',
        'address' => 'Address',
        'state' => 'State',
        'city' => 'City/District',
        'pincode' => 'Pincode',
        'scheme_id' => 'Scheme / Project',
        'utr_number' => 'UTR/Transaction ID',
        'payment_date' => 'Payment Date',
        'payment_receipt' => 'Payment Receipt',
        'passport_photo' => 'Passport Photo',
        'signature' => 'Signature',
        'aadhar_card' => 'Aadhar Card Document',
        'tenth_marksheet' => '10th Marksheet/Certificate',
    ];
}

function registrationStoreFormData(array $post) {
    $stored = [];
    foreach ($post as $key => $value) {
        if ($key === 'registration_token') {
            continue;
        }
        if (is_array($value)) {
            $stored[$key] = array_map(static function ($item) {
                return is_string($item) ? $item : $item;
            }, $value);
        } elseif (is_string($value) || is_numeric($value)) {
            $stored[$key] = (string) $value;
        }
    }
    return $stored;
}

function registrationRedirectWithErrors($redirectBack, array $errors, array $missingFields = []) {
    $errors = array_values(array_filter($errors));
    $missingFields = array_values(array_unique(array_filter($missingFields)));

    $_SESSION['registration_errors'] = $errors;
    $_SESSION['registration_missing_fields'] = $missingFields;
    $_SESSION['registration_form_data'] = registrationStoreFormData($_POST);

    if (count($errors) === 1) {
        $_SESSION['error'] = $errors[0];
    } elseif (count($errors) > 1) {
        $_SESSION['error'] = 'Please correct the ' . count($errors) . ' issue(s) listed below and submit again.';
    } else {
        $_SESSION['error'] = 'Please review the form and try again.';
    }

    header('Location: ' . $redirectBack);
    exit();
}

function registrationFileUploadError($field) {
    if (!isset($_FILES[$field])) {
        return 'missing';
    }
    $code = $_FILES[$field]['error'];
    if ($code === UPLOAD_ERR_OK) {
        return '';
    }
    $codes = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file selected',
    ];
    return $codes[$code] ?? 'Upload error code ' . $code;
}

// ============================================================
// MAIN
// ============================================================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . APP_URL . "/public/courses.php");
    exit();
}

error_log("=== REGISTRATION SUBMISSION ===");
error_log("course_id raw: " . ($_POST['course_id'] ?? 'NOT SET'));

// ----------------------------------------------------------
// 1. Collect fields
// ----------------------------------------------------------
$course_id        = intval($_POST['course_id'] ?? 0);
$scheme_id        = normalizeEnrollmentSchemeId($_POST['scheme_id'] ?? null);
$training_center  = trim($_POST['training_center']  ?? '');
$name             = trim($_POST['name']              ?? '');
$father_name      = trim($_POST['father_name']       ?? '');
$mother_name      = trim($_POST['mother_name']       ?? '');
$dob              = trim($_POST['dob']               ?? '');
$mobile           = trim($_POST['mobile']            ?? '');
$aadhar           = trim($_POST['aadhar']            ?? '');
$gender           = trim($_POST['gender']            ?? '');
$religion         = trim($_POST['religion']          ?? '');
$marital_status   = trim($_POST['marital_status']    ?? '');
$student_category = trim($_POST['category']          ?? '');
$pwd_status       = trim($_POST['pwd_status']        ?? 'No');
$position         = trim($_POST['position']          ?? '');
$nationality      = trim($_POST['nationality']       ?? '');
$email            = trim($_POST['email']             ?? '');
$state            = trim($_POST['state']             ?? '');
$city             = trim($_POST['city']              ?? '');
$pincode          = trim($_POST['pincode']           ?? '');
$address          = trim($_POST['address']           ?? '');
$college_name     = trim($_POST['college_name']      ?? '');
$utr_number       = trim($_POST['utr_number']        ?? '');
$payment_date     = trim($_POST['payment_date']      ?? '');
$distinguishing_marks = trim($_POST['distinguishing_marks'] ?? '') ?: null;
$apaar_id         = trim($_POST['apaar_id'] ?? '') ?: null;

$payment_date_db = !empty($payment_date) ? date('Y-m-d H:i:s', strtotime($payment_date)) : null;

// Store a readable state name in the database even if the form posts ISO2 code.
$state = normalizeStateName($state);

// Sanitize enum values to match DB definitions
if (!in_array($gender,           ['Male','Female','Other']))                       $gender = 'Male';
if (!in_array($religion,         ['Hindu','Muslim','Christian','Sikh','Other']))   $religion = 'Other';
if (!in_array($student_category, ['General','OBC','SC','ST','EWS']))               $student_category = 'General';

$exam_passed     = $_POST['exam_passed']     ?? [];
$exam_name_arr   = $_POST['exam_name']       ?? [];
$year_of_passing = $_POST['year_of_passing'] ?? [];
$institute_name  = $_POST['institute_name']  ?? [];
$stream          = $_POST['stream']          ?? [];
$percentage      = $_POST['percentage']      ?? [];
$age = !empty($dob) ? (int)(new DateTime($dob))->diff(new DateTime())->y : 0;

// ----------------------------------------------------------
// 2. Validate course_id
// ----------------------------------------------------------
if ($course_id <= 0) {
    $_SESSION['error'] = "Invalid course. Please use a valid registration link.";
    $token = trim($_POST['registration_token'] ?? '');
    if ($token !== '') {
        header('Location: ' . APP_URL . '/student/register.php?token=' . rawurlencode($token));
    } else {
        header('Location: ' . APP_URL . '/public/courses.php');
    }
    exit();
}

// ----------------------------------------------------------
// 3. Fetch course details
// ----------------------------------------------------------
$feeColumn = '0 AS fees';
$feeColumnCheck = $conn->query("SHOW COLUMNS FROM courses LIKE 'fees'");
if ($feeColumnCheck && $feeColumnCheck->num_rows > 0) {
    $feeColumn = 'fees';
} else {
    $trainingFeeColumnCheck = $conn->query("SHOW COLUMNS FROM courses LIKE 'training_fees'");
    if ($trainingFeeColumnCheck && $trainingFeeColumnCheck->num_rows > 0) {
        $feeColumn = 'training_fees AS fees';
    }
}

$paymentColSql = "'optional' AS payment_details_required";
$paymentColCheck = $conn->query("SHOW COLUMNS FROM courses LIKE 'payment_details_required'");
if ($paymentColCheck && $paymentColCheck->num_rows > 0) {
    $paymentColSql = 'payment_details_required';
}

$s = $conn->prepare("SELECT course_name, course_code, registration_token, {$paymentColSql}, $feeColumn FROM courses WHERE id = ?");
if (!$s) {
    $_SESSION['error'] = "Database error loading course details: " . $conn->error;
    header("Location: " . APP_URL . "/public/courses.php");
    exit();
}
$s->bind_param("i", $course_id);
$s->execute();
$cr = $s->get_result();
if ($cr->num_rows === 0) {
    $_SESSION['error'] = "Course not found. Please use a valid registration link.";
    header("Location: " . APP_URL . "/public/courses.php");
    exit();
}
$cRow         = $cr->fetch_assoc();
$course_name  = $cRow['course_name'];
$course_code  = $cRow['course_code'];
$course_fee   = isset($cRow['fees']) ? (float)$cRow['fees'] : 0.00;

$registration_token = trim($_POST['registration_token'] ?? '');
if ($registration_token === '') {
    $registration_token = trim($cRow['registration_token'] ?? '');
}

if ($registration_token !== '') {
    $redirectBack = APP_URL . '/student/register.php?token=' . rawurlencode($registration_token);
} else {
    $redirectBack = APP_URL . '/public/courses.php';
}

// Check HTTP_REFERER for alternate registration forms (legacy)
if (isset($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
    error_log("REDIRECT: HTTP_REFERER = " . $referer);

    if (strpos($referer, 'register_fixed.php') !== false) {
        $redirectBack = APP_URL . '/student/register_fixed.php?course=' . urlencode($course_code);
    } elseif (strpos($referer, 'test_registration_simple.php') !== false) {
        $redirectBack = APP_URL . '/student/test_registration_simple.php';
    }
}

// ----------------------------------------------------------
// 4. Validate all required fields (collect every issue, then redirect once)
// ----------------------------------------------------------
$validationErrors = [];
$missingFields = [];
$labels = registrationFieldLabels();

$requiredMap = [
    'name' => $name,
    'father_name' => $father_name,
    'mother_name' => $mother_name,
    'dob' => $dob,
    'gender' => $gender,
    'marital_status' => $marital_status,
    'mobile' => $mobile,
    'email' => $email,
    'nationality' => $nationality,
    'religion' => $religion,
    'category' => $student_category,
    'position' => $position,
    'address' => $address,
    'state' => $state,
    'city' => $city,
    'pincode' => $pincode,
];

foreach ($requiredMap as $field => $val) {
    if ($val === '' || $val === null) {
        $missingFields[] = $field;
        $validationErrors[] = ($labels[$field] ?? ucwords(str_replace('_', ' ', $field))) . ' is required.';
    }
}

$aadhar = normalizeAadhar($aadhar);
if ($aadhar === '' || strlen($aadhar) !== 12) {
    $missingFields[] = 'aadhar';
    $validationErrors[] = 'Aadhar number must be exactly 12 digits.';
}

$course_schemes = getSchemesForCourse($conn, $course_id);
if (!empty($course_schemes) && $scheme_id === null) {
    $missingFields[] = 'scheme_id';
    $validationErrors[] = 'Please select a scheme/project for this course.';
}

$paymentRequired = (($cRow['payment_details_required'] ?? 'optional') === 'required');
if ($paymentRequired) {
    if ($utr_number === '') {
        $missingFields[] = 'utr_number';
        $validationErrors[] = 'UTR/Transaction ID is required.';
    }
    if ($payment_date === '') {
        $missingFields[] = 'payment_date';
        $validationErrors[] = 'Payment date is required.';
    }
    $receiptErr = registrationFileUploadError('payment_receipt');
    if ($receiptErr !== '') {
        $missingFields[] = 'payment_receipt';
        $validationErrors[] = 'Payment receipt is required' . ($receiptErr !== 'missing' ? ' (' . $receiptErr . ')' : '') . '.';
    }
}

foreach (['passport_photo' => 'Passport photo', 'signature' => 'Signature'] as $fileField => $fileLabel) {
    $fileErr = registrationFileUploadError($fileField);
    if ($fileErr !== '') {
        $missingFields[] = $fileField;
        $validationErrors[] = $fileLabel . ' is required' . ($fileErr !== 'missing' ? ' (' . $fileErr . ')' : '') . '.';
    }
}

foreach (['aadhar_card' => 'Aadhar card document', 'tenth_marksheet' => '10th marksheet/certificate'] as $fileField => $fileLabel) {
    $fileErr = registrationFileUploadError($fileField);
    if ($fileErr !== '') {
        $missingFields[] = $fileField;
        $validationErrors[] = $fileLabel . ' is required' . ($fileErr !== 'missing' ? ' (' . $fileErr . ')' : '') . '.';
    }
}

if (!empty($validationErrors)) {
    registrationRedirectWithErrors($redirectBack, $validationErrors, $missingFields);
}

// ----------------------------------------------------------
// 5. Aadhar duplicate check + Student ID
// ----------------------------------------------------------
if ($scheme_id !== null && !validateSchemeForCourse($conn, $course_id, $scheme_id)) {
    registrationRedirectWithErrors($redirectBack, ['Invalid scheme/project selected for this course.'], ['scheme_id']);
}
if (isAadharEnrolledInCourseScheme($conn, $aadhar, $course_id, $scheme_id)) {
    registrationRedirectWithErrors(
        $redirectBack,
        ['This Aadhar is already registered for this course and scheme/project. Select a different project or contact admin.'],
        ['aadhar']
    );
}

$is_returning_student = false;
$existing_account = findAccountByAadhar($conn, $aadhar);

if ($existing_account) {
    $is_returning_student = true;
    $student_id = $existing_account['student_id'];
    error_log("Returning student - reusing ID: $student_id");
} elseif (isMultiCourseSystemInstalled($conn)) {
    $student_id = getNextGlobalStudentID($conn);
    if ($student_id === null) {
        registrationRedirectWithErrors($redirectBack, ['Error generating student ID. Please try again or contact support.'], []);
    }
    error_log("New student - global ID: $student_id");
} else {
    $student_id = getNextStudentID($course_id, $conn);
    if ($student_id === null) {
        registrationRedirectWithErrors($redirectBack, ['Error generating student ID. Ensure the course has an abbreviation set.'], []);
    }
    error_log("New student - course ID: $student_id");
}

// FIX: Safe version of student_id for use in filenames (replaces / \ with -)
$safe_student_id = str_replace(['/', '\\', ' '], '-', $student_id);

// ----------------------------------------------------------
// 6. Create upload directory
// ----------------------------------------------------------
$uploadDir = __DIR__ . '/uploads/students/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$passport_photo_path  = '';
$signature_path       = '';
$payment_receipt_path = '';
$left_thumb_impression_path = null;

// ----------------------------------------------------------
// 7. Upload passport photo (mandatory)
// ----------------------------------------------------------
if (!isset($_FILES['passport_photo']) || $_FILES['passport_photo']['error'] !== UPLOAD_ERR_OK) {
    registrationRedirectWithErrors(
        $redirectBack,
        ['Passport photo is required. Upload error code: ' . ($_FILES['passport_photo']['error'] ?? 'missing')],
        ['passport_photo']
    );
}
$v = validateUploadedDocument($_FILES['passport_photo'], 'passport');
if (!$v['valid']) {
    registrationRedirectWithErrors($redirectBack, ['Passport photo invalid: ' . $v['message']], ['passport_photo']);
}
$ext = strtolower(pathinfo($_FILES['passport_photo']['name'], PATHINFO_EXTENSION));
$fn  = $safe_student_id . '_' . time() . '_passport.' . $ext;
if (!move_uploaded_file($_FILES['passport_photo']['tmp_name'], $uploadDir . $fn)) {
    registrationRedirectWithErrors($redirectBack, ['Failed to save passport photo. Check folder permissions.'], ['passport_photo']);
}
$passport_photo_path = 'student/uploads/students/' . $fn;

// ----------------------------------------------------------
// 8. Upload signature (mandatory)
// ----------------------------------------------------------
if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
    registrationRedirectWithErrors(
        $redirectBack,
        ['Signature is required. Upload error code: ' . ($_FILES['signature']['error'] ?? 'missing')],
        ['signature']
    );
}
$v = validateUploadedDocument($_FILES['signature'], 'signature');
if (!$v['valid']) {
    registrationRedirectWithErrors($redirectBack, ['Signature invalid: ' . $v['message']], ['signature']);
}
$ext = strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION));
$fn  = $safe_student_id . '_' . (time()+1) . '_signature.' . $ext;
if (!move_uploaded_file($_FILES['signature']['tmp_name'], $uploadDir . $fn)) {
    registrationRedirectWithErrors($redirectBack, ['Failed to save signature. Check folder permissions.'], ['signature']);
}
$signature_path = 'student/uploads/students/' . $fn;

// ----------------------------------------------------------
// 9A. Upload left hand thumb impression (optional)
// ----------------------------------------------------------
if (isset($_FILES['left_thumb_impression']) && $_FILES['left_thumb_impression']['error'] === UPLOAD_ERR_OK) {
    $r = handleThumbImpressionUpload($_FILES['left_thumb_impression'], $student_id);
    if ($r['success']) {
        $left_thumb_impression_path = $r['path'];
    } else {
        registrationRedirectWithErrors($redirectBack, ['Thumb impression invalid: ' . $r['error']], ['left_thumb_impression']);
    }
}

// ----------------------------------------------------------
// 9. Upload payment receipt (optional)
// ----------------------------------------------------------
if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['payment_receipt']['name'], PATHINFO_EXTENSION));
    $fn  = $safe_student_id . '_' . (time()+2) . '_receipt.' . $ext;
    if (move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $uploadDir . $fn))
        $payment_receipt_path = 'student/uploads/students/' . $fn;
}

// ----------------------------------------------------------
// 10. Categorized document uploads
// ----------------------------------------------------------
$docCats = [
    'aadhar_card'            => 'aadhar',
    'caste_certificate'      => 'caste',
    'tenth_marksheet'        => 'tenth',
    'twelfth_marksheet'      => 'twelfth',
    'graduation_certificate' => 'graduation',
    'other_documents'        => 'other'
];
$uploadedDocs = [];
$uploadErrors = [];

foreach ($docCats as $field => $cat) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $r = handleCategorizedUpload($_FILES[$field], $cat, $student_id);
        if ($r['success']) $uploadedDocs[$field] = $r['path'];
        else               $uploadErrors[$field] = $r['error'];
    } elseif (in_array($field, ['aadhar_card','tenth_marksheet'])) {
        $code = $_FILES[$field]['error'] ?? 4;
        if ($code !== UPLOAD_ERR_OK) {
            $uploadErrors[$field] = "Required document missing (error code: $code)";
        }
    }
}

if (!empty($uploadErrors)) {
    $docErrors = [];
    $docMissing = [];
    foreach ($uploadErrors as $f => $e) {
        $docMissing[] = $f;
        $docErrors[] = (registrationFieldLabels()[$f] ?? ucwords(str_replace('_', ' ', $f))) . ': ' . $e;
    }
    foreach (array_merge($uploadedDocs, array_filter([$passport_photo_path, $signature_path, $left_thumb_impression_path, $payment_receipt_path])) as $p) {
        $abs = __DIR__ . '/' . $p;
        if (!empty($p) && file_exists($abs)) {
            unlink($abs);
        }
    }
    registrationRedirectWithErrors($redirectBack, $docErrors, $docMissing);
}

$aadhar_card_path            = $uploadedDocs['aadhar_card']            ?? '';
$caste_certificate_path      = $uploadedDocs['caste_certificate']      ?? '';
$tenth_marksheet_path        = $uploadedDocs['tenth_marksheet']        ?? '';
$twelfth_marksheet_path      = $uploadedDocs['twelfth_marksheet']      ?? '';
$graduation_certificate_path = $uploadedDocs['graduation_certificate'] ?? '';
$other_documents_path        = $uploadedDocs['other_documents']        ?? '';

// ----------------------------------------------------------
// 11. Password & education data
// ----------------------------------------------------------
$password = null;
if ($is_returning_student && !empty($existing_account['password'])) {
    $hashed_password = $existing_account['password'];
} else {
    $password        = bin2hex(random_bytes(8));
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
}
$education_data  = json_encode([
    'exam_passed'     => $exam_passed,
    'exam_name'       => $exam_name_arr,
    'year_of_passing' => $year_of_passing,
    'institute_name'  => $institute_name,
    'stream'          => $stream,
    'percentage'      => $percentage
]);

// ----------------------------------------------------------
// 12. INSERT into students table - FIXED PARAMETER MISMATCH
// Updated to match actual database schema with all required fields
// ----------------------------------------------------------
$hasSchemeCol = hasSchemeEnrollmentColumns($conn);
$schemeColSql = $hasSchemeCol ? ', scheme_id' : '';
$schemeValSql = $hasSchemeCol ? ', ?' : '';

$sql = "INSERT INTO students (
    course, course_id, training_center, name, father_name, mother_name,
    dob, age, mobile, aadhar, apaar_id, gender, religion, marital_status,
    category, pwd_status, distinguishing_marks, position, nationality, email,
    state, city, pincode, address, college_name, education_details,
    passport_photo, signature, left_thumb_impression, payment_receipt, utr_number, payment_date,
    student_id, password,
    aadhar_card_doc, caste_certificate_doc, tenth_marksheet_doc,
    twelfth_marksheet_doc, graduation_certificate_doc, other_documents_doc,
    status{$schemeColSql}, registration_date
) VALUES (
    ?,?,?,?,?,?,?,?,?,?,
    ?,?,?,?,?,?,?,?,?,?,
    ?,?,?,?,?,?,?,?,?,?,
    ?,?,?,?,?,?,?,?,?,?,
    'pending'{$schemeValSql}, NOW()
)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("PREPARE FAILED: " . $conn->error);
    registrationRedirectWithErrors($redirectBack, ['Database error: ' . $conn->error], []);
}

$bindTypes = 'si' . str_repeat('s', 5) . 'i' . str_repeat('s', 32);
if ($hasSchemeCol) {
    $bindTypes .= 'i';
}
$bindArgs = [
    $course_name, $course_id, $training_center, $name, $father_name,
    $mother_name, $dob, $age, $mobile, $aadhar, $apaar_id, $gender,
    $religion, $marital_status, $student_category, $pwd_status,
    $distinguishing_marks, $position, $nationality, $email,
    $state, $city, $pincode, $address, $college_name, $education_data,
    $passport_photo_path, $signature_path, $left_thumb_impression_path, $payment_receipt_path, $utr_number, $payment_date_db,
    $student_id, $hashed_password,
    $aadhar_card_path, $caste_certificate_path, $tenth_marksheet_path,
    $twelfth_marksheet_path, $graduation_certificate_path, $other_documents_path,
];
if ($hasSchemeCol) {
    $bindArgs[] = $scheme_id;
}
$stmt->bind_param($bindTypes, ...$bindArgs);

if (!$stmt->execute()) {
    error_log("INSERT FAILED for student $student_id: " . $stmt->error . " (errno=" . $stmt->errno . ")");
    error_log("SQL: " . $sql);
    error_log("Parameters: course_name=$course_name, course_id=$course_id, training_center=$training_center, name=$name");
    
    // Rollback: Delete all uploaded files
    error_log("Rolling back all uploaded files for student $student_id due to database failure");
    
    $allUploadedFiles = array_merge(
        array_filter([$passport_photo_path, $signature_path, $left_thumb_impression_path, $payment_receipt_path]),
        array_values($uploadedDocs)
    );
    
    foreach ($allUploadedFiles as $path) {
        $abs = __DIR__ . '/' . $path;
        if (!empty($path) && file_exists($abs)) {
            if (unlink($abs)) {
                error_log("Rollback: Deleted orphaned file $path");
            } else {
                error_log("Rollback: Failed to delete orphaned file $path");
            }
        }
    }
    
    registrationRedirectWithErrors(
        $redirectBack,
        ['Registration failed due to database error. Please try again or contact support. Error: ' . $stmt->error],
        []
    );
}

error_log("INSERT SUCCESS: $student_id with documents: passport=$passport_photo_path, signature=$signature_path" . 
    (!empty($uploadedDocs) ? ", categorized_docs=" . implode(',', array_keys($uploadedDocs)) : ""));

$student_record_id = (int)$conn->insert_id;

if (isMultiCourseSystemInstalled($conn) && $student_record_id > 0) {
    $account_id = null;
    if ($is_returning_student) {
        if (!empty($existing_account['id'])) {
            $account_id = (int)$existing_account['id'];
        } else {
            $account_id = createStudentAccount($conn, [
                'student_id' => $student_id,
                'aadhar' => $aadhar,
                'name' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'password' => $hashed_password,
                'dob' => $dob,
                'gender' => $gender,
            ]);
            if (!$account_id) {
                $refetch = findAccountByAadhar($conn, $aadhar);
                $account_id = !empty($refetch['id']) ? (int)$refetch['id'] : null;
            }
        }
    } else {
        $account_id = createStudentAccount($conn, [
            'student_id' => $student_id,
            'aadhar' => $aadhar,
            'name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'password' => $hashed_password,
            'dob' => $dob,
            'gender' => $gender,
        ]);
    }

    if ($account_id) {
        linkStudentRecordToAccount($conn, $student_record_id, $account_id);
        createStudentEnrollment($conn, $account_id, $course_id, $student_record_id, 'pending', $scheme_id);
    } else {
        error_log("WARN: Multi-course account/enrollment not created for $student_id");
    }
}

// ----------------------------------------------------------
// 13. Insert education details into separate table
// ----------------------------------------------------------
if (!empty($exam_passed) && is_array($exam_passed)) {
    $edu_stmt = $conn->prepare("INSERT INTO education_details (student_id, exam_passed, exam_name, year_of_passing, institute_name, stream, percentage) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    if ($edu_stmt) {
        for ($i = 0; $i < count($exam_passed); $i++) {
            $ep = $exam_passed[$i] ?? '';
            $en = $exam_name_arr[$i] ?? '';
            $yp = $year_of_passing[$i] ?? '';
            $in = $institute_name[$i] ?? '';
            $st = $stream[$i] ?? '';
            $pc = $percentage[$i] ?? '';
            
            // Only insert if at least exam_passed is provided
            if (!empty($ep)) {
                $edu_stmt->bind_param("sssssss", $student_id, $ep, $en, $yp, $in, $st, $pc);
                if (!$edu_stmt->execute()) {
                    error_log("Failed to insert education detail for $student_id: " . $edu_stmt->error);
                } else {
                    error_log("Inserted education record for $student_id: $ep");
                }
            }
        }
        $edu_stmt->close();
    } else {
        error_log("Failed to prepare education_details statement: " . $conn->error);
    }
}

// ----------------------------------------------------------
// 14. Set session and redirect to success page
// ----------------------------------------------------------
if ($is_returning_student) {
    $email_sent = false;
    $_SESSION['success'] = "Additional course enrollment submitted! Your Student ID remains <strong>$student_id</strong>. Use your <strong>existing password</strong> to login after approval.<br><strong>Course:</strong> $course_name<br><strong>Note:</strong> Pending admin approval.";
} else {
    $email_sent = sendRegistrationEmail($email, $name, $student_id, $password, $course_name, $training_center);
    $_SESSION['success'] = $email_sent
        ? "Registration successful! Student ID: <strong>$student_id</strong>, Password: <strong>$password</strong>. Email sent to <strong>$email</strong>.<br><strong>Note:</strong> Account pending admin approval."
        : "Registration successful! Student ID: <strong>$student_id</strong>, Password: <strong>$password</strong>. Save these credentials.<br><strong>Note:</strong> Account pending admin approval.";
}
$_SESSION['student_id']            = $student_id;
$_SESSION['student_password']      = $password ?? '';
$_SESSION['student_email']         = $email;
$_SESSION['course_name']           = $course_name;
$_SESSION['training_center']       = $training_center;
$_SESSION['is_returning_student']  = $is_returning_student;
$_SESSION['registration_email_sent'] = !empty($email_sent);

header("Location: " . APP_URL . "/student/registration_success.php");
exit();
?>