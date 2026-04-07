<?php
// Fixed registration form - simplified validation
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';

// LINK-ONLY ACCESS: Require course_id parameter
$selected_course_id = $_GET['course_id'] ?? $_GET['course'] ?? '';

if (empty($selected_course_id)) {
    $_SESSION['error'] = 'Invalid access! Registration is only available through course registration links.';
    header('Location: ' . APP_URL . '/public/courses.php');
    exit();
}

// Fetch course details
$course_details = null;
$stmt = false;

if (is_numeric($selected_course_id)) {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $selected_course_id);
    }
} else {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE (course_code = ? OR course_abbreviation = ?)");
    if ($stmt) {
        $stmt->bind_param("ss", $selected_course_id, $selected_course_id);
    }
}

if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $conn->error;
    header('Location: ' . APP_URL . '/public/courses.php');
    exit();
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Invalid or inactive course. Please select a valid course from the courses page.';
    header('Location: ' . APP_URL . '/public/courses.php');
    exit();
}

$course_details = $result->fetch_assoc();

if (!isset($course_details['link_published']) || $course_details['link_published'] != 1) {
    $_SESSION['error'] = 'Registration for this course is currently closed. Please contact the administration for more information.';
    header('Location: ' . APP_URL . '/public/courses.php');
    exit();
}

$selected_course = $course_details['course_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - NIELIT Bhubaneswar (Fixed)</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #0d47a1;
            --secondary-blue: #1565c0;
            --accent-gold: #ffc107;
            --light-bg: #f8f9fa;
            --text-dark: #212529;
            --text-muted: #6c757d;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-dark);
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }

        .top-bar {
            background-color: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 8px 0;
            font-size: 0.85rem;
        }
        
        .gov-logos img {
            height: 45px;
            width: auto;
        }

        .ministry-text {
            font-weight: 600;
            color: var(--text-dark);
            line-height: 1.2;
        }

        .navbar {
            background-color: var(--primary-blue);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            padding: 0.5rem 1rem;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: #fff !important;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--accent-gold) !important;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .form-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }

        .page-title {
            text-align: center;
            margin-bottom: 30px;
        }

        .page-title h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #0d47a1;
            margin-bottom: 10px;
        }

        .course-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border: 2px solid #90caf9;
        }

        .course-info h5 {
            color: #0d47a1;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #0d47a1;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e3f2fd;
        }

        .form-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .required-mark {
            color: #dc2626;
            margin-left: 3px;
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.1);
            outline: none;
        }

        .btn-register {
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            color: white;
            padding: 16px 48px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(13, 71, 161, 0.3);
            width: 100%;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(13, 71, 161, 0.4);
        }

        .alert {
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="top-bar">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex align-items-center justify-content-md-start justify-content-center text-header-group">
                <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo" class="me-3" style="height: 50px;">
                <div>
                    <div class="fw-bold text-primary d-none d-sm-block">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर</div>
                    <div class="fw-bold text-dark">National Institute of Electronics & Information Technology, Bhubaneswar</div>
                </div>
            </div>
            <div class="col-md-4 d-flex justify-content-md-end justify-content-center gov-logos">
                <div class="text-end me-3 d-none d-lg-block">
                    <small class="d-block fw-bold text-secondary">Ministry of Electronics & IT</small>
                    <small class="d-block text-secondary">Government of India</small>
                </div>
                <img src="<?php echo APP_URL; ?>/assets/images/National-Emblem.png" alt="Gov India" style="height: 50px;">
            </div>
        </div>
    </div>
</div>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo APP_URL; ?>/index.php">
            <i class="fas fa-university me-2"></i> NIELIT
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/public/courses.php">Courses</a></li>
                <li class="nav-item"><a class="nav-link active" href="#">Registration</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/student/login.php">Student Portal</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/public/contact.php">Contact</a></li>
            </ul>
        </div>
    </div>
</nav>

<?php
// Display session messages
if (isset($_SESSION['success'])) {
    echo '<div class="container"><div class="alert alert-success">' . $_SESSION['success'] . '</div></div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="container"><div class="alert alert-danger">' . $_SESSION['error'] . '</div></div>';
    unset($_SESSION['error']);
}
?>

<div class="container">
    <div class="form-card">
        <div class="page-title">
            <h1><i class="fas fa-user-graduate"></i> Student Registration (Fixed)</h1>
            <p>Simplified registration form with basic validation</p>
        </div>

        <!-- Course Info -->
        <div class="course-info">
            <h5><i class="fas fa-graduation-cap me-2"></i>Selected Course</h5>
            <strong>Course:</strong> <?php echo htmlspecialchars($course_details['course_name']); ?><br>
            <strong>Code:</strong> <?php echo htmlspecialchars($course_details['course_code']); ?><br>
            <strong>Training Centre:</strong> <?php echo htmlspecialchars($course_details['training_center'] ?? 'NIELIT BHUBANESWAR'); ?>
        </div>

        <div class="alert alert-warning">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Note:</strong> This is a simplified version of the registration form with basic validation only.
        </div>

        <form method="POST" action="<?php echo APP_URL; ?>/student/submit_registration.php" enctype="multipart/form-data" id="registrationForm">
            <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_details['id']); ?>">
            <input type="hidden" name="training_center" value="<?php echo htmlspecialchars($course_details['training_center'] ?? 'NIELIT BHUBANESWAR'); ?>">

            <!-- Personal Information -->
            <div class="form-section">
                <h3 class="section-title">Personal Information</h3>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Full Name <span class="required-mark">*</span></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Father's Name <span class="required-mark">*</span></label>
                        <input type="text" class="form-control" name="father_name" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mother's Name <span class="required-mark">*</span></label>
                        <input type="text" class="form-control" name="mother_name" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Date of Birth <span class="required-mark">*</span></label>
                        <input type="date" class="form-control" name="dob" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Gender <span class="required-mark">*</span></label>
                        <select class="form-select" name="gender" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Marital Status <span class="required-mark">*</span></label>
                        <select class="form-select" name="marital_status" required>
                            <option value="">Select</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="form-section">
                <h3 class="section-title">Contact Information</h3>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mobile Number <span class="required-mark">*</span></label>
                        <input type="tel" class="form-control" name="mobile" pattern="[0-9]{10}" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email Address <span class="required-mark">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Aadhar Number <span class="required-mark">*</span></label>
                        <input type="text" class="form-control" name="aadhar" pattern="[0-9]{12}" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nationality <span class="required-mark">*</span></label>
                        <select class="form-select" name="nationality" required>
                            <option value="Indian" selected>Indian</option>
                            <option value="Foreign">Foreign</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Additional Details -->
            <div class="form-section">
                <h3 class="section-title">Additional Details</h3>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Religion <span class="required-mark">*</span></label>
                        <select class="form-select" name="religion" required>
                            <option value="">Select</option>
                            <option value="Hindu">Hindu</option>
                            <option value="Muslim">Muslim</option>
                            <option value="Christian">Christian</option>
                            <option value="Sikh">Sikh</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Category <span class="required-mark">*</span></label>
                        <select class="form-select" name="category" required>
                            <option value="">Select</option>
                            <option value="General">General</option>
                            <option value="OBC">OBC</option>
                            <option value="SC">SC</option>
                            <option value="ST">ST</option>
                            <option value="EWS">EWS</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">PWD Status</label>
                        <select class="form-select" name="pwd_status">
                            <option value="No" selected>No</option>
                            <option value="Yes">Yes</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Position <span class="required-mark">*</span></label>
                        <select class="form-select" name="position" required>
                            <option value="">Select</option>
                            <option value="Student">Student</option>
                            <option value="Researcher">Researcher</option>
                            <option value="Faculty">Faculty</option>
                            <option value="Industrial">Industrial</option>
                            <option value="Services Holder">Services Holder</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="form-section">
                <h3 class="section-title">Address Details</h3>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Address <span class="required-mark">*</span></label>
                        <textarea class="form-control" name="address" rows="2" required></textarea>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">State <span class="required-mark">*</span></label>
                        <input type="text" class="form-control" name="state" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">City <span class="required-mark">*</span></label>
                        <input type="text" class="form-control" name="city" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Pincode <span class="required-mark">*</span></label>
                        <input type="text" class="form-control" name="pincode" pattern="[0-9]{6}" required>
                    </div>
                </div>
            </div>

            <!-- Document Uploads -->
            <div class="form-section">
                <h3 class="section-title">Document Uploads</h3>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Upload clear, readable documents. Accepted formats: JPG, PNG, PDF. Max size: 5MB for images, 10MB for PDFs.
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Passport Photo <span class="required-mark">*</span></label>
                        <input type="file" class="form-control" name="passport_photo" accept="image/*" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Signature <span class="required-mark">*</span></label>
                        <input type="file" class="form-control" name="signature" accept="image/*" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Aadhar Card <span class="required-mark">*</span></label>
                        <input type="file" class="form-control" name="aadhar_card" accept=".jpg,.jpeg,.pdf" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">10th Marksheet <span class="required-mark">*</span></label>
                        <input type="file" class="form-control" name="tenth_marksheet" accept=".jpg,.jpeg,.pdf" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">12th Marksheet (Optional)</label>
                        <input type="file" class="form-control" name="twelfth_marksheet" accept=".jpg,.jpeg,.pdf">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Other Documents (Optional)</label>
                        <input type="file" class="form-control" name="other_documents" accept=".jpg,.jpeg,.pdf">
                    </div>
                </div>
            </div>

            <!-- Hidden Education Fields -->
            <input type="hidden" name="exam_passed[]" value="Matriculation">
            <input type="hidden" name="exam_name[]" value="CBSE">
            <input type="hidden" name="year_of_passing[]" value="2020">
            <input type="hidden" name="institute_name[]" value="School">
            <input type="hidden" name="stream[]" value="General">
            <input type="hidden" name="percentage[]" value="85">

            <!-- Submit Button -->
            <div class="text-center mt-4">
                <button type="submit" class="btn-register">
                    <i class="fas fa-paper-plane me-2"></i>Submit Registration
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- SIMPLIFIED JAVASCRIPT - NO COMPLEX VALIDATION -->
<script>
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    console.log('Form submitted with simplified validation');
    
    // Basic validation only
    const requiredFields = ['name', 'father_name', 'mother_name', 'dob', 'gender', 'marital_status', 'mobile', 'email', 'aadhar', 'nationality', 'religion', 'category', 'position', 'state', 'city', 'pincode', 'address'];
    
    for (let field of requiredFields) {
        const input = document.querySelector(`[name="${field}"]`);
        if (!input || !input.value.trim()) {
            alert(`Please fill in the ${field.replace('_', ' ')} field.`);
            e.preventDefault();
            return false;
        }
    }
    
    // Check required files
    const requiredFiles = ['passport_photo', 'signature', 'aadhar_card', 'tenth_marksheet'];
    for (let file of requiredFiles) {
        const input = document.querySelector(`[name="${file}"]`);
        if (!input || !input.files[0]) {
            alert(`Please upload ${file.replace('_', ' ')}.`);
            e.preventDefault();
            return false;
        }
    }
    
    console.log('All validations passed, submitting form...');
    // Form will submit normally
});
</script>

</body>
</html>
<?php $conn->close(); ?>