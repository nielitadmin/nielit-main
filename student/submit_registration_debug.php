<?php
// =============================================================
// DEBUG VERSION OF submit_registration.php
// Place in /student/ folder
// Access via test_submit.html
// Shows ALL errors on screen instead of redirecting
// DELETE AFTER DEBUGGING
// =============================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/student_id_helper.php';

echo "<style>body{font-family:monospace;padding:20px;} .ok{color:green;font-weight:bold;} .fail{color:red;font-weight:bold;} .warn{color:orange;font-weight:bold;} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;overflow:auto;}</style>";
echo "<h2>🔍 Registration Debug Output</h2>";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo "<p class='fail'>❌ Not a POST request. Use test_submit.html to submit.</p>";
    exit();
}

echo "<h3>Step 1: POST Data Received</h3><pre>";
foreach ($_POST as $k => $v) {
    if (is_array($v)) {
        echo htmlspecialchars($k) . " = [" . implode(', ', array_map('htmlspecialchars', $v)) . "]\n";
    } else {
        echo htmlspecialchars($k) . " = " . htmlspecialchars($v) . "\n";
    }
}
echo "</pre>";

echo "<h3>Step 2: FILES Data</h3><pre>";
foreach ($_FILES as $k => $f) {
    echo htmlspecialchars($k) . ":\n";
    echo "  name: " . htmlspecialchars($f['name']) . "\n";
    echo "  size: " . $f['size'] . " bytes\n";
    echo "  error: " . $f['error'] . " (" . ($f['error'] === 0 ? 'OK' : 'ERROR') . ")\n";
    echo "  tmp_name: " . $f['tmp_name'] . "\n";
}
echo "</pre>";

echo "<h3>Step 3: Collecting Variables</h3>";
$course_id        = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
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
$apaar_id = trim($_POST['apaar_id'] ?? '') ?: null;

$age = 0;
if (!empty($dob)) {
    $age = (int)(new DateTime($dob))->diff(new DateTime())->y;
}

echo "<pre>";
echo "course_id = $course_id\n";
echo "name = $name\n";
echo "gender = $gender\n";
echo "religion = $religion\n";
echo "category = $student_category\n";
echo "age (calculated) = $age\n";
echo "</pre>";

echo "<h3>Step 4: DB Connection</h3>";
if ($conn) {
    echo "<p class='ok'>✅ DB Connected</p>";
} else {
    echo "<p class='fail'>❌ DB Connection FAILED: " . mysqli_connect_error() . "</p>";
    exit();
}

echo "<h3>Step 5: Course Lookup</h3>";
$stmt = $conn->prepare("SELECT course_name, course_abbreviation, course_code FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$courseResult = $stmt->get_result();
if ($courseResult->num_rows === 0) {
    echo "<p class='fail'>❌ Course ID $course_id NOT FOUND in database!</p>";
    exit();
}
$courseRow   = $courseResult->fetch_assoc();
$course_name = $courseRow['course_name'];
$course_code = $courseRow['course_code'];
echo "<p class='ok'>✅ Course found: $course_name (code: $course_code)</p>";

echo "<h3>Step 6: Generate Student ID</h3>";
$student_id = getNextStudentID($course_id, $conn);
if ($student_id === null) {
    echo "<p class='fail'>❌ Student ID generation FAILED. Course abbreviation may be missing.</p>";
    exit();
}
echo "<p class='ok'>✅ Student ID generated: $student_id</p>";

echo "<h3>Step 7: File Uploads</h3>";
$uploadStudentDir = __DIR__ . '/uploads/students/';
if (!is_dir($uploadStudentDir)) {
    mkdir($uploadStudentDir, 0755, true);
    echo "<p class='warn'>⚠️ Created directory: $uploadStudentDir</p>";
}
echo "<p>Upload dir: $uploadStudentDir</p>";
echo "<p>Writable: " . (is_writable($uploadStudentDir) ? "<span class='ok'>YES</span>" : "<span class='fail'>NO - PERMISSION ERROR!</span>") . "</p>";

$passport_photo_path = '';
$signature_path      = '';

if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['passport_photo']['name'], PATHINFO_EXTENSION));
    $fn  = $student_id . '_' . time() . '_passport.' . $ext;
    $target = $uploadStudentDir . $fn;
    if (move_uploaded_file($_FILES['passport_photo']['tmp_name'], $target)) {
        $passport_photo_path = 'uploads/students/' . $fn;
        echo "<p class='ok'>✅ Passport photo saved: $passport_photo_path</p>";
    } else {
        echo "<p class='fail'>❌ FAILED to move passport photo to: $target</p>";
        echo "<p>Check folder permissions on: $uploadStudentDir</p>";
    }
} else {
    $errCode = $_FILES['passport_photo']['error'] ?? 'NOT SET';
    echo "<p class='fail'>❌ Passport photo not uploaded. Error code: $errCode</p>";
}

if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION));
    $fn  = $student_id . '_' . (time()+1) . '_signature.' . $ext;
    $target = $uploadStudentDir . $fn;
    if (move_uploaded_file($_FILES['signature']['tmp_name'], $target)) {
        $signature_path = 'uploads/students/' . $fn;
        echo "<p class='ok'>✅ Signature saved: $signature_path</p>";
    } else {
        echo "<p class='fail'>❌ FAILED to move signature to: $target</p>";
    }
} else {
    $errCode = $_FILES['signature']['error'] ?? 'NOT SET';
    echo "<p class='fail'>❌ Signature not uploaded. Error code: $errCode</p>";
}

// Categorized docs
$docDir = __DIR__ . '/uploads/aadhar/';
if (!is_dir($docDir)) mkdir($docDir, 0755, true);
$aadhar_card_path = '';
if (isset($_FILES['aadhar_card']) && $_FILES['aadhar_card']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['aadhar_card']['name'], PATHINFO_EXTENSION));
    $fn  = $student_id . '_' . time() . '_aadhar.' . $ext;
    if (move_uploaded_file($_FILES['aadhar_card']['tmp_name'], $docDir . $fn)) {
        $aadhar_card_path = 'uploads/aadhar/' . $fn;
        echo "<p class='ok'>✅ Aadhar card saved: $aadhar_card_path</p>";
    } else {
        echo "<p class='fail'>❌ FAILED to move aadhar card</p>";
    }
} else {
    echo "<p class='fail'>❌ Aadhar card not uploaded. Error: " . ($_FILES['aadhar_card']['error'] ?? 'NOT SET') . "</p>";
}

$tenthDir = __DIR__ . '/uploads/marksheets/10th/';
if (!is_dir($tenthDir)) mkdir($tenthDir, 0755, true);
$tenth_marksheet_path = '';
if (isset($_FILES['tenth_marksheet']) && $_FILES['tenth_marksheet']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['tenth_marksheet']['name'], PATHINFO_EXTENSION));
    $fn  = $student_id . '_' . time() . '_tenth.' . $ext;
    if (move_uploaded_file($_FILES['tenth_marksheet']['tmp_name'], $tenthDir . $fn)) {
        $tenth_marksheet_path = 'uploads/marksheets/10th/' . $fn;
        echo "<p class='ok'>✅ 10th marksheet saved: $tenth_marksheet_path</p>";
    } else {
        echo "<p class='fail'>❌ FAILED to move 10th marksheet</p>";
    }
} else {
    echo "<p class='fail'>❌ 10th marksheet not uploaded. Error: " . ($_FILES['tenth_marksheet']['error'] ?? 'NOT SET') . "</p>";
}

echo "<h3>Step 8: Prepare INSERT</h3>";
$password        = 'TestPass123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$education_data  = json_encode(['exam_passed' => ['10th']]);

$caste_certificate_path = $twelfth_marksheet_path = $graduation_certificate_path = $other_documents_path = $payment_receipt_path = $utr_number = '';

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
    ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?,
    ?, ?,
    ?, ?, ?,
    ?, ?, ?,
    'pending', NOW()
)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "<p class='fail'>❌ PREPARE FAILED: " . $conn->error . "</p>";
    echo "<p>This means a column name in the INSERT doesn't exist in your DB!</p>";
    exit();
}
echo "<p class='ok'>✅ Prepare OK</p>";

echo "<h3>Step 9: bind_param</h3>";
$typeStr = "sisssssissssssssssssssssssssssssssssss";
echo "<p>Type string: <code>$typeStr</code> (length: " . strlen($typeStr) . ")</p>";

$stmt->bind_param(
    $typeStr,
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
echo "<p class='ok'>✅ bind_param OK</p>";

echo "<h3>Step 10: Execute INSERT</h3>";
if ($stmt->execute()) {
    echo "<p class='ok'>✅✅✅ INSERT SUCCEEDED! Student ID: $student_id</p>";
    echo "<p class='ok'>Password would be: $password</p>";
    echo "<p style='color:blue;font-weight:bold;'>🎉 The INSERT works! Your real submit_registration.php should also work now.</p>";
    echo "<p>Check your students table in phpMyAdmin to see the new row.</p>";
    
    // Clean up test record
    $conn->query("DELETE FROM students WHERE student_id = '$student_id'");
    echo "<p class='warn'>⚠️ Test record deleted from DB.</p>";
} else {
    echo "<p class='fail'>❌ EXECUTE FAILED!</p>";
    echo "<p class='fail'>Error: " . $stmt->error . "</p>";
    echo "<p class='fail'>Error Number: " . $stmt->errno . "</p>";
    echo "<br><h3>🔍 Most Common Causes:</h3>";
    echo "<ul>";
    echo "<li><strong>Error 1292/1265</strong>: Enum value mismatch - run fix_enums.sql in phpMyAdmin</li>";
    echo "<li><strong>Error 1048</strong>: Column cannot be null - a required field is empty</li>";
    echo "<li><strong>Error 1406</strong>: Data too long for column</li>";
    echo "<li><strong>Error 1062</strong>: Duplicate entry - student_id already exists</li>";
    echo "</ul>";
    
    echo "<h3>📋 Values being inserted:</h3><pre>";
    $vals = [
        'course_name' => $course_name,
        'course_id' => $course_id,
        'training_center' => $training_center,
        'name' => $name,
        'gender' => $gender,
        'religion' => $religion,
        'category' => $student_category,
        'age' => $age,
        'passport_photo_path' => $passport_photo_path,
        'signature_path' => $signature_path,
        'aadhar_card_path' => $aadhar_card_path,
        'tenth_marksheet_path' => $tenth_marksheet_path,
    ];
    foreach ($vals as $k => $v) {
        echo htmlspecialchars("$k = '$v'") . "\n";
    }
    echo "</pre>";
}

echo "<hr><p style='color:red;font-weight:bold;'>DELETE submit_registration_debug.php AND test_submit.html AFTER DEBUGGING!</p>";
$conn->close();
?>
