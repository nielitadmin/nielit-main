<?php
session_start();

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include the database connection
require_once __DIR__ . '/../config/config.php';

// ============================================================
// DOCUMENT UPLOAD HELPER FUNCTIONS (from submit_registration.php)
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
    if (!$v['valid']) {
        error_log("Document upload validation failed for student $student_id, category $docCategory: {$v['message']}");
        return ['success'=>false,'error'=>$v['message']];
    }

    $subdirs = ['aadhar'=>'aadhar','caste'=>'caste_certificates','tenth'=>'marksheets/10th','twelfth'=>'marksheets/12th','graduation'=>'marksheets/graduation','other'=>'other'];
    $subdir  = $subdirs[$docCategory] ?? 'other';
    $dir     = __DIR__ . '/../student/uploads/' . $subdir . '/';

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        error_log("Failed to create directory for student $student_id, category $docCategory: $dir");
        return ['success'=>false,'error'=>"Cannot create directory: $dir"];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Replace slashes in student_id for use in filename only
    $safe_id  = str_replace(['/', '\\', ' '], '-', $student_id);
    $filename = $safe_id . '_' . time() . '_' . $docCategory . '.' . $ext;
    $dest     = $dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        // Construct the path that will be stored in the database
        $path = 'student/uploads/'.$subdir.'/'.$filename;
        
        // Verify the file exists at the returned path (as it will be checked from viewing page)
        // The viewing page uses: file_exists(__DIR__ . '/../' . $path)
        // Since we're in admin/, __DIR__ . '/../' . $path resolves to student/uploads/...
        if (!file_exists(__DIR__ . '/../' . $path)) {
            error_log("Path verification failed for student $student_id, category $docCategory: File saved to $dest but path $path doesn't resolve correctly");
            return ['success'=>false,'error'=>'File upload verification failed'];
        }
        
        error_log("Document uploaded successfully for student $student_id, category $docCategory: $path");
        return ['success'=>true,'path'=>$path];
    }

    error_log("move_uploaded_file failed for student $student_id, category $docCategory: Dest=$dest, Writable=".(is_writable($dir)?'YES':'NO'));
    return ['success'=>false,'error'=>"move_uploaded_file failed. Dest: $dest | Writable: ".(is_writable($dir)?'YES':'NO')];
}

// Check if the database connection is successful
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Redirect if admin not logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");
    exit();
}

// Get student ID from URL
if (isset($_GET['id'])) {
    $student_id = $_GET['id'];
    
    // Get filter parameters to preserve them
    $filter_course = isset($_GET['filter_course']) ? $_GET['filter_course'] : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

    // Fetch student info
    $sql = "SELECT * FROM students WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
    } else {
        $_SESSION['message'] = "Student not found!";
        $_SESSION['message_type'] = "danger";
        header("Location: students.php");
        exit();
    }
    
    // Fetch education details from separate table
    $sql_education = "SELECT * FROM education_details WHERE student_id = ? ORDER BY id ASC";
    $stmt_education = $conn->prepare($sql_education);
    if ($stmt_education) {
        $stmt_education->bind_param("s", $student_id);
        $stmt_education->execute();
        $education_result = $stmt_education->get_result();
        $education_records = [];
        while ($row = $education_result->fetch_assoc()) {
            $education_records[] = $row;
        }
        $stmt_education->close();
    }
} else {
    header("Location: students.php");
    exit();
}

// On update submission
if (isset($_POST['update_student'])) {
    // Collect student info
    $name = $_POST['name'] ?? '';
    $father_name = $_POST['father_name'] ?? '';
    $mother_name = $_POST['mother_name'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $age = $_POST['age'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    $email = $_POST['email'] ?? '';
    $course = $_POST['course'] ?? '';
    $status = $_POST['status'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $pincode = $_POST['pincode'] ?? '';
    $aadhar = $_POST['aadhar'] ?? '';
    $apaar_id = isset($_POST['apaar_id']) ? trim($_POST['apaar_id']) : NULL;
    
    // Sanitize APAAR ID
    if ($apaar_id !== NULL && $apaar_id !== '') {
        $apaar_id = htmlspecialchars($apaar_id, ENT_QUOTES, 'UTF-8');
    } else {
        $apaar_id = NULL;
    }
    
    $gender = $_POST['gender'] ?? '';
    $religion = $_POST['religion'] ?? '';
    $marital_status = $_POST['marital_status'] ?? '';
    $category = $_POST['category'] ?? '';
    $pwd_status = $_POST['pwd_status'] ?? 'No';
    $distinguishing_marks = isset($_POST['distinguishing_marks']) ? trim($_POST['distinguishing_marks']) : NULL;
    
    // Sanitize distinguishing marks
    if ($distinguishing_marks !== NULL && $distinguishing_marks !== '') {
        $distinguishing_marks = htmlspecialchars($distinguishing_marks, ENT_QUOTES, 'UTF-8');
    } else {
        $distinguishing_marks = NULL;
    }
    
    $position = $_POST['position'] ?? '';
    $nationality = $_POST['nationality'] ?? '';
    $college_name = $_POST['college_name'] ?? '';
    $utr_number = $_POST['utr_number'] ?? '';
    $training_center = $_POST['training_center'] ?? '';

    // Educational details (arrays)
    $exam_passed = $_POST['exam_passed'] ?? [];
    $exam_name = $_POST['exam_name'] ?? [];
    $year_of_passing = $_POST['year_of_passing'] ?? [];
    $institute_name = $_POST['institute_name'] ?? [];
    $stream = $_POST['stream'] ?? [];
    $percentage = $_POST['percentage'] ?? [];

    // Validate required fields
    if (empty($name) || empty($mobile) || empty($email)) {
        $_SESSION['message'] = "Please fill in all required fields.";
        $_SESSION['message_type'] = "danger";
        header("Location: edit_student.php?id=$student_id");
        exit();
    }

    // File uploads fallback
    $passport_photo = $student['passport_photo'];
    $signature = $student['signature'];
    $documents = $student['documents'];
    $payment_receipt = $student['payment_receipt'] ?? '';

    // Validate and upload passport photo
    if (!empty($_FILES['passport_photo']['name'])) {
        $filename = uniqid() . '_' . basename($_FILES['passport_photo']['name']);
        $target_path = __DIR__ . '/../uploads/' . $filename;
        $passport_photo = 'uploads/' . $filename;
        
        if ($_FILES['passport_photo']['size'] > 5 * 1024 * 1024) {
            $_SESSION['message'] = "Passport photo file size is too large!";
            $_SESSION['message_type'] = "danger";
        } elseif (in_array($_FILES['passport_photo']['type'], ['image/jpeg', 'image/png', 'image/jpg'])) {
            if (!move_uploaded_file($_FILES['passport_photo']['tmp_name'], $target_path)) {
                $_SESSION['message'] = "Error uploading passport photo.";
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Invalid passport photo file type.";
            $_SESSION['message_type'] = "danger";
        }
    }

    // Validate and upload signature
    if (!empty($_FILES['signature']['name'])) {
        $filename = uniqid() . '_' . basename($_FILES['signature']['name']);
        $target_path = __DIR__ . '/../uploads/' . $filename;
        $signature = 'uploads/' . $filename;
        
        if ($_FILES['signature']['size'] > 2 * 1024 * 1024) {
            $_SESSION['message'] = "Signature file size is too large!";
            $_SESSION['message_type'] = "danger";
        } elseif (in_array($_FILES['signature']['type'], ['image/jpeg', 'image/png', 'image/jpg'])) {
            if (!move_uploaded_file($_FILES['signature']['tmp_name'], $target_path)) {
                $_SESSION['message'] = "Error uploading signature.";
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Invalid signature file type.";
            $_SESSION['message_type'] = "danger";
        }
    }

    // Validate and upload documents
    if (!empty($_FILES['documents']['name'])) {
        $filename = uniqid() . '_' . basename($_FILES['documents']['name']);
        $target_path = __DIR__ . '/../uploads/' . $filename;
        $documents = 'uploads/' . $filename;
        
        if ($_FILES['documents']['size'] > 10 * 1024 * 1024) {
            $_SESSION['message'] = "Document file size is too large!";
            $_SESSION['message_type'] = "danger";
        } elseif (in_array($_FILES['documents']['type'], ['application/pdf'])) {
            if (!move_uploaded_file($_FILES['documents']['tmp_name'], $target_path)) {
                $_SESSION['message'] = "Error uploading documents.";
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Invalid document file type.";
            $_SESSION['message_type'] = "danger";
        }
    }

    // Validate and upload payment receipt
    if (!empty($_FILES['payment_receipt']['name'])) {
        $filename = uniqid() . '_' . basename($_FILES['payment_receipt']['name']);
        $target_path = __DIR__ . '/../uploads/' . $filename;
        $payment_receipt = 'uploads/' . $filename;
        
        if ($_FILES['payment_receipt']['size'] > 5 * 1024 * 1024) {
            $_SESSION['message'] = "Payment receipt file size is too large!";
            $_SESSION['message_type'] = "danger";
        } elseif (in_array($_FILES['payment_receipt']['type'], ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'])) {
            if (!move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $target_path)) {
                $_SESSION['message'] = "Error uploading payment receipt.";
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Invalid payment receipt file type.";
            $_SESSION['message_type'] = "danger";
        }
    }

    // ============================================================
    // CATEGORIZED DOCUMENT UPLOADS (NEW SYSTEM)
    // ============================================================
    
    // Initialize document paths with existing values
    $aadhar_card_doc = $student['aadhar_card_doc'] ?? '';
    $tenth_marksheet_doc = $student['tenth_marksheet_doc'] ?? '';
    $twelfth_marksheet_doc = $student['twelfth_marksheet_doc'] ?? '';
    $caste_certificate_doc = $student['caste_certificate_doc'] ?? '';
    $graduation_certificate_doc = $student['graduation_certificate_doc'] ?? '';
    $other_documents_doc = $student['other_documents_doc'] ?? '';
    
    // Document category mapping
    $docCats = [
        'aadhar_card_doc' => 'aadhar',
        'tenth_marksheet_doc' => 'tenth',
        'twelfth_marksheet_doc' => 'twelfth',
        'caste_certificate_doc' => 'caste',
        'graduation_certificate_doc' => 'graduation',
        'other_documents_doc' => 'other'
    ];
    
    $uploadedDocs = [];
    $uploadErrors = [];
    
    // Process each categorized document
    foreach ($docCats as $field => $cat) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $r = handleCategorizedUpload($_FILES[$field], $cat, $student_id);
            if ($r['success']) {
                $uploadedDocs[$field] = $r['path'];
                // Update the variable with new path using variable variable
                $$field = $r['path'];
                
                // Validate that the variable variable assignment worked
                if (empty($$field)) {
                    $uploadErrors[$field] = "Failed to assign path to variable";
                    error_log("Variable variable assignment failed for field: $field");
                }
            } else {
                $uploadErrors[$field] = $r['error'];
            }
        }
    }
    
    // Handle upload errors
    if (!empty($uploadErrors)) {
        $msg = "Document upload errors:<br>";
        foreach ($uploadErrors as $f => $e) {
            $msg .= "- " . ucwords(str_replace('_', ' ', $f)) . ": $e<br>";
        }
        $_SESSION['message'] = $msg;
        $_SESSION['message_type'] = "danger";
        
        // Rollback: Delete any successfully uploaded files
        foreach ($uploadedDocs as $path) {
            $abs = __DIR__ . '/../' . $path;
            if (!empty($path) && file_exists($abs)) {
                unlink($abs);
            }
        }
        
        header("Location: edit_student.php?id=$student_id");
        exit();
    }

    // Update student table
    $update_sql = "UPDATE students SET 
        name=?, father_name=?, mother_name=?, dob=?, age=?, mobile=?, email=?, 
        course=?, status=?, address=?, city=?, state=?, pincode=?, aadhar=?, apaar_id=?,
        gender=?, religion=?, marital_status=?, category=?, pwd_status=?, distinguishing_marks=?, position=?, nationality=?, 
        college_name=?, utr_number=?, training_center=?,
        passport_photo=?, signature=?, documents=?, payment_receipt=?,
        aadhar_card_doc=?, tenth_marksheet_doc=?, twelfth_marksheet_doc=?,
        caste_certificate_doc=?, graduation_certificate_doc=?, other_documents_doc=?
        WHERE student_id=?";
    
    $stmt = $conn->prepare($update_sql);
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("sssssssssssssssssssssssssssssssssssss", 
        $name, $father_name, $mother_name, $dob, $age, $mobile, $email,
        $course, $status, $address, $city, $state, $pincode, $aadhar, $apaar_id,
        $gender, $religion, $marital_status, $category, $pwd_status, $distinguishing_marks, $position, $nationality,
        $college_name, $utr_number, $training_center,
        $passport_photo, $signature, $documents, $payment_receipt,
        $aadhar_card_doc, $tenth_marksheet_doc, $twelfth_marksheet_doc,
        $caste_certificate_doc, $graduation_certificate_doc, $other_documents_doc,
        $student_id);

    if ($stmt->execute()) {
        // Log successful database update
        error_log("Student database update successful for student_id: $student_id");
        if (!empty($uploadedDocs)) {
            error_log("Documents updated for student $student_id: " . implode(', ', array_keys($uploadedDocs)));
        }
        
        // Delete existing education records for this student
        $delete_education = "DELETE FROM education_details WHERE student_id = ?";
        $stmt_delete = $conn->prepare($delete_education);
        if ($stmt_delete) {
            $stmt_delete->bind_param("s", $student_id);
            $stmt_delete->execute();
            $stmt_delete->close();
        }
        
        // Insert new education records
        if (!empty($exam_passed) && is_array($exam_passed)) {
            $insert_education = "INSERT INTO education_details (student_id, exam_passed, exam_name, year_of_passing, institute_name, stream, percentage) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($insert_education);
            
            if ($stmt_insert) {
                for ($i = 0; $i < count($exam_passed); $i++) {
                    $ep = $exam_passed[$i] ?? '';
                    $en = $exam_name[$i] ?? '';
                    $yop = $year_of_passing[$i] ?? '';
                    $in = $institute_name[$i] ?? '';
                    $st = $stream[$i] ?? '';
                    $per = $percentage[$i] ?? '';
                    
                    // Only insert if at least one field has data
                    if (!empty($ep) || !empty($en) || !empty($yop) || !empty($in) || !empty($st) || !empty($per)) {
                        $stmt_insert->bind_param("sssssss", $student_id, $ep, $en, $yop, $in, $st, $per);
                        $stmt_insert->execute();
                    }
                }
                $stmt_insert->close();
            }
        }
        
        $_SESSION['message'] = "Student information updated successfully!";
        $_SESSION['message_type'] = "success";
        
        // Build return URL with filters
        $return_url = 'students.php';
        $return_params = [];
        if (!empty($filter_course) && $filter_course != 'All') {
            $return_params[] = 'filter_course=' . urlencode($filter_course);
        }
        if (!empty($start_date)) {
            $return_params[] = 'start_date=' . urlencode($start_date);
        }
        if (!empty($end_date)) {
            $return_params[] = 'end_date=' . urlencode($end_date);
        }
        if (!empty($return_params)) {
            $return_url .= '?' . implode('&', $return_params);
        }
        
        header("Location: $return_url");
        exit();
    } else {
        // Database update failed - rollback uploaded files
        error_log("Database update failed for student $student_id: " . $conn->error);
        
        // Delete any successfully uploaded files from this request
        if (!empty($uploadedDocs)) {
            error_log("Rolling back uploaded documents for student $student_id due to database failure");
            foreach ($uploadedDocs as $field => $path) {
                $abs = __DIR__ . '/../' . $path;
                if (!empty($path) && file_exists($abs)) {
                    if (unlink($abs)) {
                        error_log("Rollback: Deleted orphaned file $path");
                    } else {
                        error_log("Rollback: Failed to delete orphaned file $path");
                    }
                }
            }
        }
        
        $_SESSION['message'] = "Database update failed: " . htmlspecialchars($conn->error) . ". Any uploaded files have been removed.";
        $_SESSION['message_type'] = "danger";
        
        header("Location: edit_student.php?id=$student_id");
        exit();
    }
}

// Fetch available courses
$sql_courses = "SELECT * FROM courses ORDER BY course_name";
$courses_result = $conn->query($sql_courses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .photo-preview {
            text-align: center;
            padding: 20px;
            background: var(--light);
            border-radius: var(--radius-md);
            margin-bottom: 16px;
        }
        
        .photo-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            margin-bottom: 12px;
            object-fit: cover;
        }
        
        .file-info {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 8px;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 2px solid var(--light);
        }
        
        /* Custom input styling for "Other" selections */
        .custom-other-input {
            background: linear-gradient(135deg, #fff7ed 0%, #fef3c7 100%) !important;
            border: 2px solid #f59e0b !important;
            font-style: italic;
        }
        
        .custom-other-input:focus {
            border-color: #d97706 !important;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1) !important;
            background: #ffffff !important;
            font-style: normal;
        }
        
        .custom-other-input::placeholder {
            color: #92400e;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .form-grid, .form-grid-3 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-logo">
            <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo">
            <h5>NIELIT Admin</h5>
            <small>Bhubaneswar</small>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="students.php" class="nav-link active">
                    <i class="fas fa-users"></i> Students
                </a>
            </div>
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/manage_batches.php" class="nav-link">
                    <i class="fas fa-layer-group"></i> Batches
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php" class="nav-link">
                    <i class="fas fa-project-diagram"></i> Schemes/Projects
                </a>
            </div>
            
            <div class="nav-divider"></div>
            <div class="nav-section-title">System Settings</div>
            
            <div class="nav-item">
                <a href="manage_centres.php" class="nav-link">
                    <i class="fas fa-building"></i> Training Centres
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_themes.php" class="nav-link">
                    <i class="fas fa-palette"></i> Themes
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_homepage.php" class="nav-link">
                    <i class="fas fa-home"></i> Homepage Content
                </a>
            </div>
            
            <div class="nav-divider"></div>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/approve_students.php" class="nav-link">
                    <i class="fas fa-user-check"></i> Approve Students
                </a>
            </div>
            <div class="nav-item">
                <a href="add_admin.php" class="nav-link">
                    <i class="fas fa-user-shield"></i> Add Admin
                </a>
            </div>
            <div class="nav-item">
                <a href="reset_password.php" class="nav-link">
                    <i class="fas fa-key"></i> Reset Password
                </a>
            </div>
            
            <div class="nav-divider"></div>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/index.php" class="nav-link">
                    <i class="fas fa-globe"></i> View Website
                </a>
            </div>
            <div class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-user-edit"></i> Edit Student</h4>
                <small>Student ID: <?php echo htmlspecialchars($student_id); ?></small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role">Administrator</span>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="admin-main">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'success'; ?>">
                    <i class="fas fa-<?php echo (isset($_SESSION['message_type']) && $_SESSION['message_type'] == 'danger') ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                    <?php echo $_SESSION['message']; ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <!-- Personal Information Section -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-user"></i> Personal Information
                    </h5>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Father's Name</label>
                            <input type="text" name="father_name" class="form-control" value="<?php echo htmlspecialchars($student['father_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mother's Name</label>
                            <input type="text" name="mother_name" class="form-control" value="<?php echo htmlspecialchars($student['mother_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($student['dob']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-control" value="<?php echo htmlspecialchars($student['age']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-control">
                                <option value="Male" <?php echo ($student['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($student['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($student['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Aadhar Number</label>
                            <input type="text" name="aadhar" class="form-control" value="<?php echo htmlspecialchars($student['aadhar']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">APAAR ID</label>
                            <input type="text" name="apaar_id" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['apaar_id'] ?? ''); ?>" 
                                   maxlength="50" placeholder="Enter APAAR ID (optional)">
                            <small class="text-muted">Optional: Automated Permanent Academic Account Registry ID</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Religion</label>
                            <input type="text" name="religion" class="form-control" value="<?php echo htmlspecialchars($student['religion']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Marital Status</label>
                            <select name="marital_status" class="form-control">
                                <option value="Single" <?php echo ($student['marital_status'] == 'Single') ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo ($student['marital_status'] == 'Married') ? 'selected' : ''; ?>>Married</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-control">
                                <option value="General" <?php echo ($student['category'] == 'General') ? 'selected' : ''; ?>>General</option>
                                <option value="OBC" <?php echo ($student['category'] == 'OBC') ? 'selected' : ''; ?>>OBC</option>
                                <option value="SC" <?php echo ($student['category'] == 'SC') ? 'selected' : ''; ?>>SC</option>
                                <option value="ST" <?php echo ($student['category'] == 'ST') ? 'selected' : ''; ?>>ST</option>
                                <option value="EWS" <?php echo ($student['category'] == 'EWS') ? 'selected' : ''; ?>>EWS</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Persons with Disabilities</label>
                            <select name="pwd_status" class="form-control">
                                <option value="No" <?php echo (empty($student['pwd_status']) || $student['pwd_status'] == 'No') ? 'selected' : ''; ?>>No</option>
                                <option value="Yes" <?php echo (!empty($student['pwd_status']) && $student['pwd_status'] == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Distinguishing Marks</label>
                            <input type="text" name="distinguishing_marks" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['distinguishing_marks'] ?? ''); ?>" 
                                   maxlength="255" placeholder="e.g., Birthmark on left arm">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nationality</label>
                            <input type="text" name="nationality" class="form-control" value="<?php echo htmlspecialchars($student['nationality']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" class="form-control" value="<?php echo htmlspecialchars($student['position']); ?>">
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-address-book"></i> Contact Information
                    </h5>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Mobile Number *</label>
                            <input type="tel" name="mobile" class="form-control" value="<?php echo htmlspecialchars($student['mobile']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($student['address']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($student['city']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="<?php echo htmlspecialchars($student['state']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Pincode</label>
                            <input type="text" name="pincode" class="form-control" value="<?php echo htmlspecialchars($student['pincode']); ?>">
                        </div>
                    </div>
                </div>

                <!-- Course Information Section -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-graduation-cap"></i> Course Information
                    </h5>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Course</label>
                            <select name="course" class="form-control">
                                <?php
                                if ($courses_result && $courses_result->num_rows > 0) {
                                    while ($course = $courses_result->fetch_assoc()) {
                                        $selected = ($student['course'] == $course['course_name']) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($course['course_name']) . "' $selected>" . htmlspecialchars($course['course_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="Active" <?php echo ($student['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($student['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Completed" <?php echo ($student['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">College Name</label>
                            <input type="text" name="college_name" class="form-control" value="<?php echo htmlspecialchars($student['college_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Training Centre</label>
                            <input type="text" name="training_center" class="form-control" value="<?php echo htmlspecialchars($student['training_center'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Educational Qualifications Section -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-book-open"></i> Educational Qualifications
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="educationTable">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">Sl.</th>
                                    <th style="width: 15%;">Exam Passed</th>
                                    <th style="width: 15%;">Exam Name</th>
                                    <th style="width: 12%;">Year</th>
                                    <th style="width: 23%;">Institute/Board</th>
                                    <th style="width: 15%;">Stream</th>
                                    <th style="width: 10%;">%/CGPA</th>
                                    <th style="width: 5%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Display education records from database table
                                $row_count = 0;
                                
                                if (!empty($education_records) && is_array($education_records)) {
                                    foreach ($education_records as $edu) {
                                        $row_count++;
                                        echo '<tr>';
                                        echo '<td>' . $row_count . '</td>';
                                        
                                        // Exam Passed dropdown
                                        echo '<td>';
                                        echo '<select class="form-select form-select-sm" name="exam_passed[]" required onchange="updateExamName(this)">';
                                        echo '<option value="">Select Level</option>';
                                        $exam_levels = [
                                            'Primary' => 'Primary (5th/8th)',
                                            'Matriculation' => 'Matriculation (10th)',
                                            'Intermediate' => 'Intermediate (+2/12th)',
                                            'ITI' => 'ITI',
                                            'Diploma' => 'Diploma',
                                            'Graduation' => 'Graduation',
                                            'Post Graduation' => 'Post Graduation',
                                            'PhD' => 'PhD/Doctorate',
                                            'Other' => 'Other'
                                        ];
                                        foreach ($exam_levels as $value => $label) {
                                            $selected = ($edu['exam_passed'] == $value) ? 'selected' : '';
                                            echo '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
                                        }
                                        echo '</select>';
                                        echo '</td>';
                                        
                                        // Exam Name dropdown (will be populated by JavaScript)
                                        echo '<td>';
                                        echo '<select class="form-select form-select-sm" name="exam_name[]" required>';
                                        echo '<option value="' . htmlspecialchars($edu['exam_name'] ?? '') . '">' . htmlspecialchars($edu['exam_name'] ?? 'Select Exam') . '</option>';
                                        echo '</select>';
                                        echo '</td>';
                                        
                                        // Year dropdown
                                        echo '<td>';
                                        echo '<select class="form-select form-select-sm" name="year_of_passing[]" required>';
                                        echo '<option value="">Year</option>';
                                        $current_year = date('Y');
                                        for ($year = $current_year + 1; $year >= 1990; $year--) {
                                            $selected = ($edu['year_of_passing'] == $year) ? 'selected' : '';
                                            echo '<option value="' . $year . '" ' . $selected . '>' . $year . '</option>';
                                        }
                                        echo '</select>';
                                        echo '</td>';
                                        
                                        echo '<td><input type="text" class="form-control form-control-sm" name="institute_name[]" value="' . htmlspecialchars($edu['institute_name'] ?? '') . '" placeholder="Board/University" required></td>';
                                        
                                        // Stream dropdown
                                        echo '<td>';
                                        echo '<select class="form-select form-select-sm" name="stream[]" required onchange="handleStreamOther(this)">';
                                        echo '<option value="">Select Stream</option>';
                                        
                                        // General Streams
                                        $general_streams = ['Science', 'Commerce', 'Arts/Humanities', 'General', 'Vocational'];
                                        foreach ($general_streams as $stream) {
                                            $selected = ($edu['stream'] == $stream) ? 'selected' : '';
                                            echo '<option value="' . $stream . '" ' . $selected . '>' . $stream . '</option>';
                                        }
                                        
                                        // Engineering & Technology Streams
                                        echo '<optgroup label="Engineering & Technology">';
                                        $engineering_streams = [
                                            'Computer Science Engineering', 'Information Technology', 'Electronics & Communication Engineering',
                                            'Electrical Engineering', 'Mechanical Engineering', 'Civil Engineering', 'Chemical Engineering',
                                            'Aerospace Engineering', 'Automobile Engineering', 'Biomedical Engineering', 'Biotechnology Engineering',
                                            'Environmental Engineering', 'Industrial Engineering', 'Instrumentation Engineering', 'Marine Engineering',
                                            'Mining Engineering', 'Petroleum Engineering', 'Production Engineering', 'Textile Engineering',
                                            'Agricultural Engineering', 'Food Technology', 'Metallurgical Engineering', 'Materials Science Engineering',
                                            'Robotics Engineering', 'Artificial Intelligence & Machine Learning', 'Data Science Engineering',
                                            'Cyber Security Engineering', 'Software Engineering', 'Network Engineering', 'Embedded Systems',
                                            'VLSI Design', 'Nanotechnology', 'Renewable Energy Engineering'
                                        ];
                                        foreach ($engineering_streams as $stream) {
                                            $selected = ($edu['stream'] == $stream) ? 'selected' : '';
                                            echo '<option value="' . $stream . '" ' . $selected . '>' . $stream . '</option>';
                                        }
                                        echo '</optgroup>';
                                        
                                        // Computer Applications
                                        echo '<optgroup label="Computer Applications">';
                                        $computer_streams = [
                                            'Computer Applications', 'Information Systems', 'Computer Science', 'Software Development',
                                            'Web Development', 'Mobile App Development', 'Database Management', 'System Administration'
                                        ];
                                        foreach ($computer_streams as $stream) {
                                            $selected = ($edu['stream'] == $stream) ? 'selected' : '';
                                            echo '<option value="' . $stream . '" ' . $selected . '>' . $stream . '</option>';
                                        }
                                        echo '</optgroup>';
                                        
                                        // Management & Business
                                        echo '<optgroup label="Management & Business">';
                                        $management_streams = [
                                            'Management', 'Business Administration', 'Marketing', 'Finance', 'Human Resources',
                                            'Operations Management', 'International Business', 'Entrepreneurship'
                                        ];
                                        foreach ($management_streams as $stream) {
                                            $selected = ($edu['stream'] == $stream) ? 'selected' : '';
                                            echo '<option value="' . $stream . '" ' . $selected . '>' . $stream . '</option>';
                                        }
                                        echo '</optgroup>';
                                        
                                        // Pure Sciences
                                        echo '<optgroup label="Pure Sciences">';
                                        $science_streams = [
                                            'Physics', 'Chemistry', 'Mathematics', 'Biology', 'Biotechnology', 'Microbiology',
                                            'Biochemistry', 'Environmental Science', 'Statistics', 'Applied Mathematics'
                                        ];
                                        foreach ($science_streams as $stream) {
                                            $selected = ($edu['stream'] == $stream) ? 'selected' : '';
                                            echo '<option value="' . $stream . '" ' . $selected . '>' . $stream . '</option>';
                                        }
                                        echo '</optgroup>';
                                        
                                        echo '<option value="Other">Other</option>';
                                        echo '</select>';
                                        echo '</td>';
                                        
                                        echo '<td><input type="text" class="form-control form-control-sm" name="percentage[]" value="' . htmlspecialchars($edu['percentage'] ?? '') . '" placeholder="85%" required></td>';
                                        echo '<td><button type="button" class="btn btn-sm btn-danger" onclick="removeEducationRow(this)"><i class="fas fa-trash"></i></button></td>';
                                        echo '</tr>';
                                    }
                                }
                                
                                // If no education data, show one empty row
                                if ($row_count == 0) {
                                    echo '<tr>';
                                    echo '<td>1</td>';
                                    echo '<td>';
                                    echo '<select class="form-select form-select-sm" name="exam_passed[]" required onchange="updateExamName(this)">';
                                    echo '<option value="">Select Level</option>';
                                    echo '<option value="Primary">Primary (5th/8th)</option>';
                                    echo '<option value="Matriculation">Matriculation (10th)</option>';
                                    echo '<option value="Intermediate">Intermediate (+2/12th)</option>';
                                    echo '<option value="ITI">ITI</option>';
                                    echo '<option value="Diploma">Diploma</option>';
                                    echo '<option value="Graduation">Graduation</option>';
                                    echo '<option value="Post Graduation">Post Graduation</option>';
                                    echo '<option value="PhD">PhD/Doctorate</option>';
                                    echo '<option value="Other">Other</option>';
                                    echo '</select>';
                                    echo '</td>';
                                    echo '<td>';
                                    echo '<select class="form-select form-select-sm" name="exam_name[]" required>';
                                    echo '<option value="">Select Exam</option>';
                                    echo '</select>';
                                    echo '</td>';
                                    echo '<td>';
                                    echo '<select class="form-select form-select-sm" name="year_of_passing[]" required>';
                                    echo '<option value="">Year</option>';
                                    $current_year = date('Y');
                                    for ($year = $current_year + 1; $year >= 1990; $year--) {
                                        echo '<option value="' . $year . '">' . $year . '</option>';
                                    }
                                    echo '</select>';
                                    echo '</td>';
                                    echo '<td><input type="text" class="form-control form-control-sm" name="institute_name[]" placeholder="Board/University" required></td>';
                                    echo '<td>';
                                    echo '<select class="form-select form-select-sm" name="stream[]" required onchange="handleStreamOther(this)">';
                                    echo '<option value="">Select Stream</option>';
                                    echo '<option value="Science">Science</option>';
                                    echo '<option value="Commerce">Commerce</option>';
                                    echo '<option value="Arts/Humanities">Arts/Humanities</option>';
                                    echo '<option value="General">General</option>';
                                    echo '<option value="Vocational">Vocational</option>';
                                    echo '<optgroup label="Engineering & Technology">';
                                    echo '<option value="Computer Science Engineering">Computer Science Engineering</option>';
                                    echo '<option value="Information Technology">Information Technology</option>';
                                    echo '<option value="Electronics & Communication Engineering">Electronics & Communication Engineering</option>';
                                    echo '<option value="Electrical Engineering">Electrical Engineering</option>';
                                    echo '<option value="Mechanical Engineering">Mechanical Engineering</option>';
                                    echo '<option value="Civil Engineering">Civil Engineering</option>';
                                    echo '<option value="Chemical Engineering">Chemical Engineering</option>';
                                    echo '<option value="Aerospace Engineering">Aerospace Engineering</option>';
                                    echo '<option value="Automobile Engineering">Automobile Engineering</option>';
                                    echo '<option value="Biomedical Engineering">Biomedical Engineering</option>';
                                    echo '<option value="Biotechnology Engineering">Biotechnology Engineering</option>';
                                    echo '<option value="Environmental Engineering">Environmental Engineering</option>';
                                    echo '<option value="Industrial Engineering">Industrial Engineering</option>';
                                    echo '<option value="Instrumentation Engineering">Instrumentation Engineering</option>';
                                    echo '<option value="Marine Engineering">Marine Engineering</option>';
                                    echo '<option value="Mining Engineering">Mining Engineering</option>';
                                    echo '<option value="Petroleum Engineering">Petroleum Engineering</option>';
                                    echo '<option value="Production Engineering">Production Engineering</option>';
                                    echo '<option value="Textile Engineering">Textile Engineering</option>';
                                    echo '<option value="Agricultural Engineering">Agricultural Engineering</option>';
                                    echo '<option value="Food Technology">Food Technology</option>';
                                    echo '<option value="Metallurgical Engineering">Metallurgical Engineering</option>';
                                    echo '<option value="Materials Science Engineering">Materials Science Engineering</option>';
                                    echo '<option value="Robotics Engineering">Robotics Engineering</option>';
                                    echo '<option value="Artificial Intelligence & Machine Learning">Artificial Intelligence & Machine Learning</option>';
                                    echo '<option value="Data Science Engineering">Data Science Engineering</option>';
                                    echo '<option value="Cyber Security Engineering">Cyber Security Engineering</option>';
                                    echo '<option value="Software Engineering">Software Engineering</option>';
                                    echo '<option value="Network Engineering">Network Engineering</option>';
                                    echo '<option value="Embedded Systems">Embedded Systems</option>';
                                    echo '<option value="VLSI Design">VLSI Design</option>';
                                    echo '<option value="Nanotechnology">Nanotechnology</option>';
                                    echo '<option value="Renewable Energy Engineering">Renewable Energy Engineering</option>';
                                    echo '</optgroup>';
                                    echo '<optgroup label="Computer Applications">';
                                    echo '<option value="Computer Applications">Computer Applications</option>';
                                    echo '<option value="Information Systems">Information Systems</option>';
                                    echo '<option value="Computer Science">Computer Science</option>';
                                    echo '<option value="Software Development">Software Development</option>';
                                    echo '<option value="Web Development">Web Development</option>';
                                    echo '<option value="Mobile App Development">Mobile App Development</option>';
                                    echo '<option value="Database Management">Database Management</option>';
                                    echo '<option value="System Administration">System Administration</option>';
                                    echo '</optgroup>';
                                    echo '<optgroup label="Management & Business">';
                                    echo '<option value="Management">Management</option>';
                                    echo '<option value="Business Administration">Business Administration</option>';
                                    echo '<option value="Marketing">Marketing</option>';
                                    echo '<option value="Finance">Finance</option>';
                                    echo '<option value="Human Resources">Human Resources</option>';
                                    echo '<option value="Operations Management">Operations Management</option>';
                                    echo '<option value="International Business">International Business</option>';
                                    echo '<option value="Entrepreneurship">Entrepreneurship</option>';
                                    echo '</optgroup>';
                                    echo '<optgroup label="Pure Sciences">';
                                    echo '<option value="Physics">Physics</option>';
                                    echo '<option value="Chemistry">Chemistry</option>';
                                    echo '<option value="Mathematics">Mathematics</option>';
                                    echo '<option value="Biology">Biology</option>';
                                    echo '<option value="Biotechnology">Biotechnology</option>';
                                    echo '<option value="Microbiology">Microbiology</option>';
                                    echo '<option value="Biochemistry">Biochemistry</option>';
                                    echo '<option value="Environmental Science">Environmental Science</option>';
                                    echo '<option value="Statistics">Statistics</option>';
                                    echo '<option value="Applied Mathematics">Applied Mathematics</option>';
                                    echo '</optgroup>';
                                    echo '<option value="Other">Other</option>';
                                    echo '</select>';
                                    echo '</td>';
                                    echo '<td><input type="text" class="form-control form-control-sm" name="percentage[]" placeholder="85%" required></td>';
                                    echo '<td><button type="button" class="btn btn-sm btn-danger" onclick="removeEducationRow(this)"><i class="fas fa-trash"></i></button></td>';
                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-success" onclick="addEducationRow()">
                            <i class="fas fa-plus me-2"></i>Add More
                        </button>
                    </div>
                </div>

                <!-- Payment Information Section -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-credit-card"></i> Payment Information
                    </h5>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">UTR Number</label>
                            <input type="text" name="utr_number" class="form-control" value="<?php echo htmlspecialchars($student['utr_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Receipt</label>
                            <?php if (!empty($student['payment_receipt'])): ?>
                                <div class="file-info mb-2">
                                    <a href="<?php echo APP_URL . '/' . $student['payment_receipt']; ?>" target="_blank" class="btn btn-sm btn-info">
                                        <i class="fas fa-download"></i> View Current Receipt
                                    </a>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="payment_receipt" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="file-info">Upload new receipt (JPG, PNG, or PDF, max 5MB)</small>
                        </div>
                    </div>
                </div>

                <!-- Photo & Signature Section -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-camera"></i> Photo & Signature
                        <span class="badge bg-danger ms-2">Required</span>
                    </h5>
                    <div class="form-grid-3">
                        <!-- Passport Photo -->
                        <div class="form-group">
                            <label class="form-label">Passport Photo *</label>
                            <?php if (!empty($student['passport_photo'])): ?>
                                <div class="photo-preview">
                                    <img src="<?php echo APP_URL . '/' . $student['passport_photo']; ?>" alt="Passport Photo">
                                    <a href="<?php echo APP_URL . '/' . $student['passport_photo']; ?>" download class="btn btn-sm btn-primary">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="passport_photo" class="form-control" accept=".jpg,.jpeg,.png">
                            <small class="file-info">Upload new photo (JPG/PNG, max 5MB)</small>
                        </div>

                        <!-- Signature -->
                        <div class="form-group">
                            <label class="form-label">Signature *</label>
                            <?php if (!empty($student['signature'])): ?>
                                <div class="photo-preview">
                                    <img src="<?php echo APP_URL . '/' . $student['signature']; ?>" alt="Signature">
                                    <a href="<?php echo APP_URL . '/' . $student['signature']; ?>" download class="btn btn-sm btn-primary">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="signature" class="form-control" accept=".jpg,.jpeg,.png">
                            <small class="file-info">Upload new signature (JPG/PNG, max 2MB)</small>
                        </div>

                        <!-- Legacy Documents (kept for backward compatibility) -->
                        <div class="form-group">
                            <label class="form-label">Legacy Documents (PDF)</label>
                            <?php if (!empty($student['documents'])): ?>
                                <div class="photo-preview">
                                    <i class="fas fa-file-pdf" style="font-size: 48px; color: #dc3545;"></i>
                                    <br>
                                    <a href="<?php echo APP_URL . '/' . $student['documents']; ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
                                        <i class="fas fa-eye"></i> View PDF
                                    </a>
                                    <a href="<?php echo APP_URL . '/' . $student['documents']; ?>" download class="btn btn-sm btn-success mt-2">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="documents" class="form-control" accept=".pdf">
                            <small class="file-info">Upload new documents (PDF, max 10MB)</small>
                        </div>
                    </div>
                </div>

                <!-- Identity Proof Section -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-id-card"></i> Identity Proof
                        <span class="badge bg-danger ms-2">Required</span>
                    </h5>
                    <div class="form-grid">
                        <!-- Aadhar Card -->
                        <div class="form-group">
                            <label class="form-label">Aadhar Card *</label>
                            <?php if (!empty($student['aadhar_card_doc'])): ?>
                                <div class="photo-preview">
                                    <?php 
                                    $ext = pathinfo($student['aadhar_card_doc'], PATHINFO_EXTENSION);
                                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])): 
                                    ?>
                                        <img src="<?php echo APP_URL . '/' . $student['aadhar_card_doc']; ?>" alt="Aadhar Card">
                                    <?php else: ?>
                                        <i class="fas fa-file-pdf" style="font-size: 48px; color: #dc3545;"></i>
                                        <br>
                                    <?php endif; ?>
                                    <a href="<?php echo APP_URL . '/' . $student['aadhar_card_doc']; ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="<?php echo APP_URL . '/' . $student['aadhar_card_doc']; ?>" download class="btn btn-sm btn-success mt-2">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Not uploaded
                                </div>
                            <?php endif; ?>
                            <input type="file" name="aadhar_card_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="file-info">Upload new Aadhar card (JPG/PNG/PDF, max 5MB for images, 10MB for PDF)</small>
                        </div>
                    </div>
                </div>

                <!-- Educational Qualifications Section -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-graduation-cap"></i> Educational Qualifications Documents
                    </h5>
                    <div class="form-grid-3">
                        <!-- 10th Marksheet -->
                        <div class="form-group">
                            <label class="form-label">10th Marksheet/Certificate *</label>
                            <?php if (!empty($student['tenth_marksheet_doc'])): ?>
                                <div class="photo-preview">
                                    <?php 
                                    $ext = pathinfo($student['tenth_marksheet_doc'], PATHINFO_EXTENSION);
                                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])): 
                                    ?>
                                        <img src="<?php echo APP_URL . '/' . $student['tenth_marksheet_doc']; ?>" alt="10th Marksheet">
                                    <?php else: ?>
                                        <i class="fas fa-file-pdf" style="font-size: 48px; color: #dc3545;"></i>
                                        <br>
                                    <?php endif; ?>
                                    <a href="<?php echo APP_URL . '/' . $student['tenth_marksheet_doc']; ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="<?php echo APP_URL . '/' . $student['tenth_marksheet_doc']; ?>" download class="btn btn-sm btn-success mt-2">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Not uploaded
                                </div>
                            <?php endif; ?>
                            <input type="file" name="tenth_marksheet_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="file-info">Upload new 10th marksheet (JPG/PNG/PDF, max 5MB for images, 10MB for PDF)</small>
                        </div>

                        <!-- 12th Marksheet -->
                        <div class="form-group">
                            <label class="form-label">12th Marksheet/Diploma</label>
                            <?php if (!empty($student['twelfth_marksheet_doc'])): ?>
                                <div class="photo-preview">
                                    <?php 
                                    $ext = pathinfo($student['twelfth_marksheet_doc'], PATHINFO_EXTENSION);
                                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])): 
                                    ?>
                                        <img src="<?php echo APP_URL . '/' . $student['twelfth_marksheet_doc']; ?>" alt="12th Marksheet">
                                    <?php else: ?>
                                        <i class="fas fa-file-pdf" style="font-size: 48px; color: #dc3545;"></i>
                                        <br>
                                    <?php endif; ?>
                                    <a href="<?php echo APP_URL . '/' . $student['twelfth_marksheet_doc']; ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="<?php echo APP_URL . '/' . $student['twelfth_marksheet_doc']; ?>" download class="btn btn-sm btn-success mt-2">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Not uploaded (Optional)
                                </div>
                            <?php endif; ?>
                            <input type="file" name="twelfth_marksheet_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="file-info">Upload new 12th marksheet (JPG/PNG/PDF, max 5MB for images, 10MB for PDF)</small>
                        </div>

                        <!-- Graduation Certificate -->
                        <div class="form-group">
                            <label class="form-label">Graduation Certificate</label>
                            <?php if (!empty($student['graduation_certificate_doc'])): ?>
                                <div class="photo-preview">
                                    <?php 
                                    $ext = pathinfo($student['graduation_certificate_doc'], PATHINFO_EXTENSION);
                                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])): 
                                    ?>
                                        <img src="<?php echo APP_URL . '/' . $student['graduation_certificate_doc']; ?>" alt="Graduation Certificate">
                                    <?php else: ?>
                                        <i class="fas fa-file-pdf" style="font-size: 48px; color: #dc3545;"></i>
                                        <br>
                                    <?php endif; ?>
                                    <a href="<?php echo APP_URL . '/' . $student['graduation_certificate_doc']; ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="<?php echo APP_URL . '/' . $student['graduation_certificate_doc']; ?>" download class="btn btn-sm btn-success mt-2">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Not uploaded (Optional)
                                </div>
                            <?php endif; ?>
                            <input type="file" name="graduation_certificate_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="file-info">Upload new graduation certificate (JPG/PNG/PDF, max 5MB for images, 10MB for PDF)</small>
                        </div>
                    </div>
                </div>

                <!-- Additional Documents Section -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-folder-open"></i> Additional Documents
                        <span class="badge bg-info ms-2">Optional</span>
                    </h5>
                    <div class="form-grid">
                        <!-- Caste Certificate -->
                        <div class="form-group">
                            <label class="form-label">Caste Certificate</label>
                            <?php if (!empty($student['caste_certificate_doc'])): ?>
                                <div class="photo-preview">
                                    <?php 
                                    $ext = pathinfo($student['caste_certificate_doc'], PATHINFO_EXTENSION);
                                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])): 
                                    ?>
                                        <img src="<?php echo APP_URL . '/' . $student['caste_certificate_doc']; ?>" alt="Caste Certificate">
                                    <?php else: ?>
                                        <i class="fas fa-file-pdf" style="font-size: 48px; color: #dc3545;"></i>
                                        <br>
                                    <?php endif; ?>
                                    <a href="<?php echo APP_URL . '/' . $student['caste_certificate_doc']; ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="<?php echo APP_URL . '/' . $student['caste_certificate_doc']; ?>" download class="btn btn-sm btn-success mt-2">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Not uploaded (Optional)
                                </div>
                            <?php endif; ?>
                            <input type="file" name="caste_certificate_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="file-info">Upload new caste certificate (JPG/PNG/PDF, max 5MB for images, 10MB for PDF)</small>
                        </div>

                        <!-- Other Documents -->
                        <div class="form-group">
                            <label class="form-label">Other Supporting Documents</label>
                            <?php if (!empty($student['other_documents_doc'])): ?>
                                <div class="photo-preview">
                                    <?php 
                                    $ext = pathinfo($student['other_documents_doc'], PATHINFO_EXTENSION);
                                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])): 
                                    ?>
                                        <img src="<?php echo APP_URL . '/' . $student['other_documents_doc']; ?>" alt="Other Documents">
                                    <?php else: ?>
                                        <i class="fas fa-file-pdf" style="font-size: 48px; color: #dc3545;"></i>
                                        <br>
                                    <?php endif; ?>
                                    <a href="<?php echo APP_URL . '/' . $student['other_documents_doc']; ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="<?php echo APP_URL . '/' . $student['other_documents_doc']; ?>" download class="btn btn-sm btn-success mt-2">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Not uploaded (Optional)
                                </div>
                            <?php endif; ?>
                            <input type="file" name="other_documents_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="file-info">Upload new supporting documents (JPG/PNG/PDF, max 5MB for images, 10MB for PDF)</small>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="students.php<?php 
                        $params = [];
                        if (!empty($filter_course) && $filter_course != 'All') $params[] = 'filter_course=' . urlencode($filter_course);
                        if (!empty($start_date)) $params[] = 'start_date=' . urlencode($start_date);
                        if (!empty($end_date)) $params[] = 'end_date=' . urlencode($end_date);
                        echo !empty($params) ? '?' . implode('&', $params) : '';
                    ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <a href="download_student_form.php?id=<?php echo urlencode($student_id); ?>" class="btn btn-success" target="_blank">
                        <i class="fas fa-download"></i> Download Form
                    </a>
                    <button type="submit" name="update_student" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Student
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

</body>
</html>

<?php
$conn->close();
?>
<script>
// Add education row
// Add education row with enhanced dropdowns
function addEducationRow() {
    const table = document.getElementById('educationTable').getElementsByTagName('tbody')[0];
    const rowCount = table.rows.length + 1;
    const newRow = table.insertRow();
    
    // Generate year options
    const currentYear = new Date().getFullYear();
    let yearOptions = '<option value="">Year</option>';
    for (let year = currentYear + 1; year >= 1990; year--) {
        yearOptions += `<option value="${year}">${year}</option>`;
    }
    
    newRow.innerHTML = `
        <td>${rowCount}</td>
        <td>
            <select class="form-select form-select-sm" name="exam_passed[]" required onchange="updateExamName(this)">
                <option value="">Select Level</option>
                <option value="Primary">Primary (5th/8th)</option>
                <option value="Matriculation">Matriculation (10th)</option>
                <option value="Intermediate">Intermediate (+2/12th)</option>
                <option value="ITI">ITI</option>
                <option value="Diploma">Diploma</option>
                <option value="Graduation">Graduation</option>
                <option value="Post Graduation">Post Graduation</option>
                <option value="PhD">PhD/Doctorate</option>
                <option value="Other">Other</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="exam_name[]" required>
                <option value="">Select Exam</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="year_of_passing[]" required>
                ${yearOptions}
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm" name="institute_name[]" placeholder="Board/University" required></td>
        <td>
            <select class="form-select form-select-sm" name="stream[]" required onchange="handleStreamOther(this)">
                <option value="">Select Stream</option>
                <!-- General Streams -->
                <option value="Science">Science</option>
                <option value="Commerce">Commerce</option>
                <option value="Arts/Humanities">Arts/Humanities</option>
                <option value="General">General</option>
                <option value="Vocational">Vocational</option>
                
                <!-- Engineering & Technology Streams -->
                <optgroup label="Engineering & Technology">
                    <option value="Computer Science Engineering">Computer Science Engineering</option>
                    <option value="Information Technology">Information Technology</option>
                    <option value="Electronics & Communication Engineering">Electronics & Communication Engineering</option>
                    <option value="Electrical Engineering">Electrical Engineering</option>
                    <option value="Mechanical Engineering">Mechanical Engineering</option>
                    <option value="Civil Engineering">Civil Engineering</option>
                    <option value="Chemical Engineering">Chemical Engineering</option>
                    <option value="Aerospace Engineering">Aerospace Engineering</option>
                    <option value="Automobile Engineering">Automobile Engineering</option>
                    <option value="Biomedical Engineering">Biomedical Engineering</option>
                    <option value="Biotechnology Engineering">Biotechnology Engineering</option>
                    <option value="Environmental Engineering">Environmental Engineering</option>
                    <option value="Industrial Engineering">Industrial Engineering</option>
                    <option value="Instrumentation Engineering">Instrumentation Engineering</option>
                    <option value="Marine Engineering">Marine Engineering</option>
                    <option value="Mining Engineering">Mining Engineering</option>
                    <option value="Petroleum Engineering">Petroleum Engineering</option>
                    <option value="Production Engineering">Production Engineering</option>
                    <option value="Textile Engineering">Textile Engineering</option>
                    <option value="Agricultural Engineering">Agricultural Engineering</option>
                    <option value="Food Technology">Food Technology</option>
                    <option value="Metallurgical Engineering">Metallurgical Engineering</option>
                    <option value="Materials Science Engineering">Materials Science Engineering</option>
                    <option value="Robotics Engineering">Robotics Engineering</option>
                    <option value="Artificial Intelligence & Machine Learning">Artificial Intelligence & Machine Learning</option>
                    <option value="Data Science Engineering">Data Science Engineering</option>
                    <option value="Cyber Security Engineering">Cyber Security Engineering</option>
                    <option value="Software Engineering">Software Engineering</option>
                    <option value="Network Engineering">Network Engineering</option>
                    <option value="Embedded Systems">Embedded Systems</option>
                    <option value="VLSI Design">VLSI Design</option>
                    <option value="Nanotechnology">Nanotechnology</option>
                    <option value="Renewable Energy Engineering">Renewable Energy Engineering</option>
                </optgroup>
                
                <!-- Computer Applications -->
                <optgroup label="Computer Applications">
                    <option value="Computer Applications">Computer Applications</option>
                    <option value="Information Systems">Information Systems</option>
                    <option value="Computer Science">Computer Science</option>
                    <option value="Software Development">Software Development</option>
                    <option value="Web Development">Web Development</option>
                    <option value="Mobile App Development">Mobile App Development</option>
                    <option value="Database Management">Database Management</option>
                    <option value="System Administration">System Administration</option>
                </optgroup>
                
                <!-- Management & Business -->
                <optgroup label="Management & Business">
                    <option value="Management">Management</option>
                    <option value="Business Administration">Business Administration</option>
                    <option value="Marketing">Marketing</option>
                    <option value="Finance">Finance</option>
                    <option value="Human Resources">Human Resources</option>
                    <option value="Operations Management">Operations Management</option>
                    <option value="International Business">International Business</option>
                    <option value="Entrepreneurship">Entrepreneurship</option>
                </optgroup>
                
                <!-- Pure Sciences -->
                <optgroup label="Pure Sciences">
                    <option value="Physics">Physics</option>
                    <option value="Chemistry">Chemistry</option>
                    <option value="Mathematics">Mathematics</option>
                    <option value="Biology">Biology</option>
                    <option value="Biotechnology">Biotechnology</option>
                    <option value="Microbiology">Microbiology</option>
                    <option value="Biochemistry">Biochemistry</option>
                    <option value="Environmental Science">Environmental Science</option>
                    <option value="Statistics">Statistics</option>
                    <option value="Applied Mathematics">Applied Mathematics</option>
                </optgroup>
                
                <!-- Other Specializations -->
                <option value="Other">Other</option>
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm" name="percentage[]" placeholder="85%" required></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeEducationRow(this)"><i class="fas fa-trash"></i></button></td>
    `;
    
    // Update serial numbers
    updateSerialNumbers();
}

// Update exam name options based on exam passed selection
function updateExamName(examPassedSelect) {
    const row = examPassedSelect.closest('tr');
    const examNameSelect = row.querySelector('select[name="exam_name[]"]');
    const selectedLevel = examPassedSelect.value;
    
    // Clear existing options
    examNameSelect.innerHTML = '<option value="">Select Exam</option>';
    
    // Handle "Other" selection for Exam Passed
    if (selectedLevel === 'Other') {
        // Replace dropdown with text input for custom entry
        const customInput = document.createElement('input');
        customInput.type = 'text';
        customInput.className = 'form-control form-control-sm custom-other-input';
        customInput.name = 'exam_passed[]';
        customInput.placeholder = 'Enter custom qualification level';
        customInput.required = true;
        customInput.value = '';
        
        // Replace the select with input
        examPassedSelect.parentNode.replaceChild(customInput, examPassedSelect);
        
        // Also replace exam name dropdown with text input
        const examNameInput = document.createElement('input');
        examNameInput.type = 'text';
        examNameInput.className = 'form-control form-control-sm custom-other-input';
        examNameInput.name = 'exam_name[]';
        examNameInput.placeholder = 'Enter exam/qualification name';
        examNameInput.required = true;
        
        examNameSelect.parentNode.replaceChild(examNameInput, examNameSelect);
        
        return;
    }
    
    // Define exam name options for each level
    const examOptions = {
        'Primary': [
            'Primary School Certificate',
            '5th Standard',
            '8th Standard',
            'Elementary Education'
        ],
        'Matriculation': [
            'Secondary School Certificate (SSC)',
            'High School Certificate (HSC)',
            'Board of Secondary Education',
            'CBSE Class 10',
            'ICSE Class 10',
            'State Board 10th'
        ],
        'Intermediate': [
            'Higher Secondary Certificate',
            'Intermediate Certificate',
            'CBSE Class 12',
            'ICSE Class 12',
            'State Board 12th',
            'Pre-University Course (PUC)',
            'Higher Secondary Education'
        ],
        'ITI': [
            'Industrial Training Institute',
            'National Council for Vocational Training (NCVT)',
            'State Council for Vocational Training (SCVT)',
            'Craftsman Training Scheme (CTS)',
            'Apprenticeship Training Scheme (ATS)'
        ],
        'Diploma': [
            'Diploma in Engineering',
            'Polytechnic Diploma',
            'Technical Diploma',
            'Professional Diploma',
            'Vocational Diploma'
        ],
        'Graduation': [
            'Bachelor of Technology (B.Tech)',
            'Bachelor of Engineering (B.E.)',
            'Bachelor of Science (B.Sc)',
            'Bachelor of Arts (B.A.)',
            'Bachelor of Commerce (B.Com)',
            'Bachelor of Computer Applications (BCA)',
            'Bachelor of Business Administration (BBA)',
            'Bachelor of Fine Arts (BFA)',
            'Other Bachelor Degree'
        ],
        'Post Graduation': [
            'Master of Technology (M.Tech)',
            'Master of Engineering (M.E.)',
            'Master of Science (M.Sc)',
            'Master of Arts (M.A.)',
            'Master of Commerce (M.Com)',
            'Master of Computer Applications (MCA)',
            'Master of Business Administration (MBA)',
            'Master of Fine Arts (MFA)',
            'Other Master Degree'
        ],
        'PhD': [
            'Doctor of Philosophy (Ph.D)',
            'Doctor of Science (D.Sc)',
            'Doctor of Literature (D.Litt)',
            'Doctor of Engineering (D.Eng)',
            'Other Doctorate Degree'
        ]
    };
    
    // Populate exam name options
    if (examOptions[selectedLevel]) {
        examOptions[selectedLevel].forEach(examName => {
            const option = document.createElement('option');
            option.value = examName;
            option.textContent = examName;
            examNameSelect.appendChild(option);
        });
    }
    
    // Add "Other" option at the end for exam name
    const otherOption = document.createElement('option');
    otherOption.value = 'Other';
    otherOption.textContent = 'Other (Specify custom)';
    examNameSelect.appendChild(otherOption);
    
    // Add event listener for exam name "Other" selection
    examNameSelect.addEventListener('change', function() {
        handleExamNameOther(this);
    });
}

// Handle "Other" selection in exam name dropdown
function handleExamNameOther(examNameSelect) {
    if (examNameSelect.value === 'Other') {
        // Replace dropdown with text input for custom entry
        const customInput = document.createElement('input');
        customInput.type = 'text';
        customInput.className = 'form-control form-control-sm custom-other-input';
        customInput.name = 'exam_name[]';
        customInput.placeholder = 'Enter custom exam/qualification name';
        customInput.required = true;
        customInput.value = '';
        
        // Replace the select with input
        examNameSelect.parentNode.replaceChild(customInput, examNameSelect);
    }
}

// Handle "Other" selection in stream dropdown
function handleStreamOther(streamSelect) {
    if (streamSelect.value === 'Other') {
        // Replace dropdown with text input for custom entry
        const customInput = document.createElement('input');
        customInput.type = 'text';
        customInput.className = 'form-control form-control-sm custom-other-input';
        customInput.name = 'stream[]';
        customInput.placeholder = 'Enter custom stream/specialization';
        customInput.required = true;
        customInput.value = '';
        
        // Replace the select with input
        streamSelect.parentNode.replaceChild(customInput, streamSelect);
    }
}

// Remove education row
function removeEducationRow(button) {
    const table = document.getElementById('educationTable').getElementsByTagName('tbody')[0];
    if (table.rows.length > 1) {
        const row = button.parentNode.parentNode;
        row.parentNode.removeChild(row);
        updateSerialNumbers();
    } else {
        alert('At least one education entry is required.');
    }
}

// Update serial numbers
function updateSerialNumbers() {
    const table = document.getElementById('educationTable').getElementsByTagName('tbody')[0];
    for (let i = 0; i < table.rows.length; i++) {
        table.rows[i].cells[0].innerHTML = i + 1;
    }
}

// Initialize existing dropdowns on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to existing exam passed selects
    const examPassedSelects = document.querySelectorAll('select[name="exam_passed[]"]');
    examPassedSelects.forEach(select => {
        // Trigger updateExamName for existing rows with values
        if (select.value) {
            updateExamName(select);
        }
    });
    
    // Add event listeners to existing exam name selects
    const examNameSelects = document.querySelectorAll('select[name="exam_name[]"]');
    examNameSelects.forEach(select => {
        select.addEventListener('change', function() {
            handleExamNameOther(this);
        });
    });
    
    // Add event listeners to existing stream selects
    const streamSelects = document.querySelectorAll('select[name="stream[]"]');
    streamSelects.forEach(select => {
        select.addEventListener('change', function() {
            handleStreamOther(this);
        });
    });
});
</script>
