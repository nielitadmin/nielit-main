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
                            <label class="form-label">Training Center</label>
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
                                        echo '<td><input type="text" class="form-control form-control-sm" name="exam_passed[]" value="' . htmlspecialchars($edu['exam_passed'] ?? '') . '"></td>';
                                        echo '<td><input type="text" class="form-control form-control-sm" name="exam_name[]" value="' . htmlspecialchars($edu['exam_name'] ?? '') . '"></td>';
                                        echo '<td><input type="text" class="form-control form-control-sm" name="year_of_passing[]" value="' . htmlspecialchars($edu['year_of_passing'] ?? '') . '"></td>';
                                        echo '<td><input type="text" class="form-control form-control-sm" name="institute_name[]" value="' . htmlspecialchars($edu['institute_name'] ?? '') . '"></td>';
                                        echo '<td><input type="text" class="form-control form-control-sm" name="stream[]" value="' . htmlspecialchars($edu['stream'] ?? '') . '"></td>';
                                        echo '<td><input type="text" class="form-control form-control-sm" name="percentage[]" value="' . htmlspecialchars($edu['percentage'] ?? '') . '"></td>';
                                        echo '<td><button type="button" class="btn btn-sm btn-danger" onclick="removeEducationRow(this)"><i class="fas fa-trash"></i></button></td>';
                                        echo '</tr>';
                                    }
                                }
                                
                                // If no education data, show one empty row
                                if ($row_count == 0) {
                                    echo '<tr>';
                                    echo '<td>1</td>';
                                    echo '<td><input type="text" class="form-control form-control-sm" name="exam_passed[]" placeholder="10th/12th"></td>';
                                    echo '<td><input type="text" class="form-control form-control-sm" name="exam_name[]" placeholder="High School"></td>';
                                    echo '<td><input type="text" class="form-control form-control-sm" name="year_of_passing[]" placeholder="2020"></td>';
                                    echo '<td><input type="text" class="form-control form-control-sm" name="institute_name[]" placeholder="Board/University"></td>';
                                    echo '<td><input type="text" class="form-control form-control-sm" name="stream[]" placeholder="Science/Arts"></td>';
                                    echo '<td><input type="text" class="form-control form-control-sm" name="percentage[]" placeholder="85%"></td>';
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
function addEducationRow() {
    const table = document.getElementById('educationTable').getElementsByTagName('tbody')[0];
    const rowCount = table.rows.length + 1;
    const newRow = table.insertRow();
    
    newRow.innerHTML = `
        <td>${rowCount}</td>
        <td><input type="text" class="form-control form-control-sm" name="exam_passed[]" placeholder="10th/12th"></td>
        <td><input type="text" class="form-control form-control-sm" name="exam_name[]" placeholder="High School"></td>
        <td><input type="text" class="form-control form-control-sm" name="year_of_passing[]" placeholder="2020"></td>
        <td><input type="text" class="form-control form-control-sm" name="institute_name[]" placeholder="Board/University"></td>
        <td><input type="text" class="form-control form-control-sm" name="stream[]" placeholder="Science/Arts"></td>
        <td><input type="text" class="form-control form-control-sm" name="percentage[]" placeholder="85%"></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeEducationRow(this)"><i class="fas fa-trash"></i></button></td>
    `;
    
    // Update serial numbers
    updateSerialNumbers();
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
</script>
