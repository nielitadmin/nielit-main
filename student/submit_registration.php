<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/student_id_helper.php';
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
$distinguishing_marks = trim($_POST['distinguishing_marks'] ?? '') ?: null;
$apaar_id         = trim($_POST['apaar_id'] ?? '') ?: null;

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
    header("Location: " . APP_URL . "/public/courses.php");
    exit();
}

// ----------------------------------------------------------
// 3. Fetch course details
// ----------------------------------------------------------
$s = $conn->prepare("SELECT course_name, course_code FROM courses WHERE id = ?");
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

// FIXED: Detect which form was used and redirect appropriately
$redirectBack = APP_URL . "/student/register.php?course=" . urlencode($course_code); // Default fallback

// Check HTTP_REFERER to determine which form was used
if (isset($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
    error_log("REDIRECT FIX: HTTP_REFERER = " . $referer);
    
    if (strpos($referer, 'register_fixed.php') !== false) {
        $redirectBack = APP_URL . "/student/register_fixed.php?course=" . urlencode($course_code);
        error_log("REDIRECT FIX: Detected register_fixed.php, redirecting to: " . $redirectBack);
    } elseif (strpos($referer, 'test_registration_simple.php') !== false) {
        $redirectBack = APP_URL . "/student/test_registration_simple.php";
        error_log("REDIRECT FIX: Detected test_registration_simple.php, redirecting to: " . $redirectBack);
    } else {
        error_log("REDIRECT FIX: Using default redirect to: " . $redirectBack);
    }
    // If referer contains register.php or no specific match, use default
} else {
    error_log("REDIRECT FIX: No HTTP_REFERER found, using default redirect: " . $redirectBack);
}

// ----------------------------------------------------------
// 4. Required field validation
// ----------------------------------------------------------
$required = ['Name'=>$name,'DOB'=>$dob,'Mobile'=>$mobile,'Email'=>$email,'State'=>$state,'City'=>$city,'Pincode'=>$pincode,'Address'=>$address,'Position'=>$position,'Father Name'=>$father_name,'Mother Name'=>$mother_name,'Gender'=>$gender,'Marital Status'=>$marital_status,'Category'=>$student_category,'Nationality'=>$nationality];
foreach ($required as $label => $val) {
    if (empty($val)) {
        $_SESSION['error'] = "$label is required and cannot be empty.";
        error_log("Validation failed: $label is empty");
        header("Location: " . $redirectBack);
        exit();
    }
}

// ----------------------------------------------------------
// 5. Generate student ID
// ----------------------------------------------------------
$student_id = getNextStudentID($course_id, $conn);
if ($student_id === null) {
    $_SESSION['error'] = "Error generating student ID. Ensure the course has an abbreviation set.";
    header("Location: " . $redirectBack);
    exit();
}
error_log("Student ID: $student_id");

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

// ----------------------------------------------------------
// 7. Upload passport photo (mandatory)
// ----------------------------------------------------------
if (!isset($_FILES['passport_photo']) || $_FILES['passport_photo']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "Passport photo is required. Upload error code: " . ($_FILES['passport_photo']['error'] ?? 'missing');
    header("Location: " . $redirectBack);
    exit();
}
$v = validateUploadedDocument($_FILES['passport_photo'], 'passport');
if (!$v['valid']) {
    $_SESSION['error'] = "Passport photo invalid: " . $v['message'];
    header("Location: " . $redirectBack);
    exit();
}
$ext = strtolower(pathinfo($_FILES['passport_photo']['name'], PATHINFO_EXTENSION));
$fn  = $safe_student_id . '_' . time() . '_passport.' . $ext;
if (!move_uploaded_file($_FILES['passport_photo']['tmp_name'], $uploadDir . $fn)) {
    $_SESSION['error'] = "Failed to save passport photo. Check folder permissions on: $uploadDir";
    header("Location: " . $redirectBack);
    exit();
}
$passport_photo_path = 'student/uploads/students/' . $fn;

// ----------------------------------------------------------
// 8. Upload signature (mandatory)
// ----------------------------------------------------------
if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "Signature is required. Upload error code: " . ($_FILES['signature']['error'] ?? 'missing');
    header("Location: " . $redirectBack);
    exit();
}
$v = validateUploadedDocument($_FILES['signature'], 'signature');
if (!$v['valid']) {
    $_SESSION['error'] = "Signature invalid: " . $v['message'];
    header("Location: " . $redirectBack);
    exit();
}
$ext = strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION));
$fn  = $safe_student_id . '_' . (time()+1) . '_signature.' . $ext;
if (!move_uploaded_file($_FILES['signature']['tmp_name'], $uploadDir . $fn)) {
    $_SESSION['error'] = "Failed to save signature. Check folder permissions on: $uploadDir";
    header("Location: " . $redirectBack);
    exit();
}
$signature_path = 'student/uploads/students/' . $fn;

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
    $msg = "Document upload errors:<br>";
    foreach ($uploadErrors as $f => $e)
        $msg .= "- " . ucwords(str_replace('_',' ',$f)) . ": $e<br>";
    $_SESSION['error'] = $msg;
    // Rollback uploaded files
    foreach (array_merge($uploadedDocs, array_filter([$passport_photo_path,$signature_path,$payment_receipt_path])) as $p) {
        $abs = __DIR__ . '/' . $p;
        if (!empty($p) && file_exists($abs)) unlink($abs);
    }
    header("Location: " . $redirectBack);
    exit();
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
$password        = bin2hex(random_bytes(8));
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
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
$sql = "INSERT INTO students (
    course, course_id, training_center, name, father_name, mother_name,
    dob, age, mobile, aadhar, apaar_id, gender, religion, marital_status,
    category, pwd_status, distinguishing_marks, position, nationality, email,
    state, city, pincode, address, college_name, education_details,
    passport_photo, signature, payment_receipt, utr_number,
    student_id, password,
    aadhar_card_doc, caste_certificate_doc, tenth_marksheet_doc,
    twelfth_marksheet_doc, graduation_certificate_doc, other_documents_doc,
    status, registration_date
) VALUES (
    ?,?,?,?,?,?, ?,?,?,?,?,?,?,?,
    ?,?,?,?,?,?, ?,?,?,?,?,?,
    ?,?,?,?, ?,?,
    ?,?,?, ?,?,?,
    'pending', NOW()
)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("PREPARE FAILED: " . $conn->error);
    $_SESSION['error'] = "Database error: " . $conn->error;
    header("Location: " . $redirectBack);
    exit();
}

// FIXED: Corrected parameter count and types to match SQL statement
$stmt->bind_param(
    "sisssssissssssssssssssssssssssssssssss",
    $course_name, $course_id, $training_center, $name, $father_name,
    $mother_name, $dob, $age, $mobile, $aadhar, $apaar_id, $gender,
    $religion, $marital_status, $student_category, $pwd_status,
    $distinguishing_marks, $position, $nationality, $email,
    $state, $city, $pincode, $address, $college_name, $education_data,
    $passport_photo_path, $signature_path, $payment_receipt_path, $utr_number,
    $student_id, $hashed_password,
    $aadhar_card_path, $caste_certificate_path, $tenth_marksheet_path,
    $twelfth_marksheet_path, $graduation_certificate_path, $other_documents_path
);

if (!$stmt->execute()) {
    error_log("INSERT FAILED for student $student_id: " . $stmt->error . " (errno=" . $stmt->errno . ")");
    error_log("SQL: " . $sql);
    error_log("Parameters: course_name=$course_name, course_id=$course_id, training_center=$training_center, name=$name");
    
    // Rollback: Delete all uploaded files
    error_log("Rolling back all uploaded files for student $student_id due to database failure");
    
    $allUploadedFiles = array_merge(
        array_filter([$passport_photo_path, $signature_path, $payment_receipt_path]),
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
    
    $_SESSION['error'] = "Registration failed due to database error. Please try again. If the problem persists, contact support. Error: " . $stmt->error;
    header("Location: " . $redirectBack);
    exit();
}

error_log("INSERT SUCCESS: $student_id with documents: passport=$passport_photo_path, signature=$signature_path" . 
    (!empty($uploadedDocs) ? ", categorized_docs=" . implode(',', array_keys($uploadedDocs)) : ""));

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
$email_sent = sendRegistrationEmail($email, $name, $student_id, $password, $course_name, $training_center);

$_SESSION['success'] = $email_sent
    ? "Registration successful! Student ID: <strong>$student_id</strong>, Password: <strong>$password</strong>. Email sent to <strong>$email</strong>.<br><strong>Note:</strong> Account pending admin approval."
    : "Registration successful! Student ID: <strong>$student_id</strong>, Password: <strong>$password</strong>. Save these credentials.<br><strong>Note:</strong> Account pending admin approval.";
$_SESSION['student_id']       = $student_id;
$_SESSION['student_password'] = $password;
$_SESSION['student_email']    = $email;
$_SESSION['course_name']      = $course_name;
$_SESSION['training_center']  = $training_center;

header("Location: " . APP_URL . "/student/registration_success.php");
exit();
?>