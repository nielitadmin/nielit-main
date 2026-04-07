<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Get FDCP-2026 course
$stmt = $conn->prepare("SELECT * FROM courses WHERE course_code = ? OR course_abbreviation = ?");
$course_code = 'FDCP-2026';
$stmt->bind_param("ss", $course_code, $course_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Course FDCP-2026 not found");
}

$course = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Registration Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 300px; padding: 8px; border: 1px solid #ccc; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .error { color: red; background: #ffe6e6; padding: 10px; margin: 10px 0; }
        .success { color: green; background: #e6ffe6; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Simple Registration Test - FDCP-2026</h1>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <p><strong>Course:</strong> <?php echo htmlspecialchars($course['course_name']); ?></p>
    
    <form method="POST" action="submit_registration.php" enctype="multipart/form-data">
        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
        
        <div class="form-group">
            <label>Training Center:</label>
            <input type="text" name="training_center" value="NIELIT BHUBANESWAR" readonly>
        </div>
        
        <div class="form-group">
            <label>Full Name *:</label>
            <input type="text" name="name" required>
        </div>
        
        <div class="form-group">
            <label>Father's Name *:</label>
            <input type="text" name="father_name" required>
        </div>
        
        <div class="form-group">
            <label>Mother's Name *:</label>
            <input type="text" name="mother_name" required>
        </div>
        
        <div class="form-group">
            <label>Date of Birth *:</label>
            <input type="date" name="dob" required>
        </div>
        
        <div class="form-group">
            <label>Mobile Number *:</label>
            <input type="tel" name="mobile" pattern="[0-9]{10}" required>
        </div>
        
        <div class="form-group">
            <label>Email *:</label>
            <input type="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label>Aadhar Number *:</label>
            <input type="text" name="aadhar" pattern="[0-9]{12}" required>
        </div>
        
        <div class="form-group">
            <label>Gender *:</label>
            <select name="gender" required>
                <option value="">Select</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Religion *:</label>
            <select name="religion" required>
                <option value="">Select</option>
                <option value="Hindu">Hindu</option>
                <option value="Muslim">Muslim</option>
                <option value="Christian">Christian</option>
                <option value="Sikh">Sikh</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Marital Status *:</label>
            <select name="marital_status" required>
                <option value="">Select</option>
                <option value="Single">Single</option>
                <option value="Married">Married</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Category *:</label>
            <select name="category" required>
                <option value="">Select</option>
                <option value="General">General</option>
                <option value="OBC">OBC</option>
                <option value="SC">SC</option>
                <option value="ST">ST</option>
                <option value="EWS">EWS</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Position *:</label>
            <select name="position" required>
                <option value="">Select</option>
                <option value="Student">Student</option>
                <option value="Researcher">Researcher</option>
                <option value="Faculty">Faculty</option>
                <option value="Industrial">Industrial</option>
                <option value="Services Holder">Services Holder</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Nationality *:</label>
            <select name="nationality" required>
                <option value="Indian">Indian</option>
                <option value="Foreign">Foreign</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>State *:</label>
            <input type="text" name="state" required>
        </div>
        
        <div class="form-group">
            <label>City *:</label>
            <input type="text" name="city" required>
        </div>
        
        <div class="form-group">
            <label>Pincode *:</label>
            <input type="text" name="pincode" pattern="[0-9]{6}" required>
        </div>
        
        <div class="form-group">
            <label>Address *:</label>
            <textarea name="address" required></textarea>
        </div>
        
        <div class="form-group">
            <label>Passport Photo * (JPG/PNG, max 5MB):</label>
            <input type="file" name="passport_photo" accept="image/*" required>
        </div>
        
        <div class="form-group">
            <label>Signature * (JPG/PNG, max 5MB):</label>
            <input type="file" name="signature" accept="image/*" required>
        </div>
        
        <div class="form-group">
            <label>Aadhar Card * (JPG/PDF, max 5MB):</label>
            <input type="file" name="aadhar_card" accept=".jpg,.jpeg,.pdf" required>
        </div>
        
        <div class="form-group">
            <label>10th Marksheet * (JPG/PDF, max 5MB):</label>
            <input type="file" name="tenth_marksheet" accept=".jpg,.jpeg,.pdf" required>
        </div>
        
        <!-- Hidden fields for education details -->
        <input type="hidden" name="exam_passed[]" value="Matriculation">
        <input type="hidden" name="exam_name[]" value="CBSE">
        <input type="hidden" name="year_of_passing[]" value="2010">
        <input type="hidden" name="institute_name[]" value="Test School">
        <input type="hidden" name="stream[]" value="General">
        <input type="hidden" name="percentage[]" value="85">
        
        <button type="submit">Submit Registration</button>
    </form>
    
    <hr>
    <p><strong>Debug Links:</strong></p>
    <ul>
        <li><a href="debug_registration_issue.php">Debug Registration Issue</a></li>
        <li><a href="check_students_table_schema.php">Check Students Table Schema</a></li>
        <li><a href="test_registration_fix.php">Test Registration Fix</a></li>
    </ul>
</body>
</html>