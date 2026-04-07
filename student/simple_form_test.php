<?php
/**
 * Simple Form Test - No JavaScript
 * NIELIT Bhubaneswar - Basic HTML Form Test
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';

echo "<h2>🔧 Simple Form Test (No JavaScript)</h2>";

// Clear any existing session messages
unset($_SESSION['error'], $_SESSION['success']);

echo "<p>This is a basic HTML form without any JavaScript validation to test if the issue is client-side.</p>";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Registration Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin: 10px 0; }
        label { display: inline-block; width: 200px; font-weight: bold; }
        input, select, textarea { width: 250px; padding: 5px; }
        .submit-btn { background: #007bff; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
        .section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; }
    </style>
</head>
<body>

<div class="section">
    <h3>📝 Basic Registration Form (No JavaScript)</h3>
    <form method="POST" action="submit_registration.php" enctype="multipart/form-data">
        
        <!-- Hidden course ID -->
        <input type="hidden" name="course_id" value="65">
        
        <h4>Personal Information</h4>
        <div class="form-group">
            <label>Name:</label>
            <input type="text" name="name" value="Simple Test Student" required>
        </div>
        
        <div class="form-group">
            <label>Father's Name:</label>
            <input type="text" name="father_name" value="Test Father" required>
        </div>
        
        <div class="form-group">
            <label>Mother's Name:</label>
            <input type="text" name="mother_name" value="Test Mother" required>
        </div>
        
        <div class="form-group">
            <label>Date of Birth:</label>
            <input type="date" name="dob" value="1995-01-01" required>
        </div>
        
        <div class="form-group">
            <label>Mobile:</label>
            <input type="tel" name="mobile" value="9876543210" required>
        </div>
        
        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" value="simple.test@example.com" required>
        </div>
        
        <div class="form-group">
            <label>Aadhar Number:</label>
            <input type="text" name="aadhar" value="123456789012" required>
        </div>
        
        <div class="form-group">
            <label>Gender:</label>
            <select name="gender" required>
                <option value="Male" selected>Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Religion:</label>
            <select name="religion" required>
                <option value="Hindu" selected>Hindu</option>
                <option value="Muslim">Muslim</option>
                <option value="Christian">Christian</option>
                <option value="Sikh">Sikh</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Marital Status:</label>
            <select name="marital_status" required>
                <option value="Single" selected>Single</option>
                <option value="Married">Married</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Category:</label>
            <select name="category" required>
                <option value="General" selected>General</option>
                <option value="OBC">OBC</option>
                <option value="SC">SC</option>
                <option value="ST">ST</option>
                <option value="EWS">EWS</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Position:</label>
            <input type="text" name="position" value="Student" required>
        </div>
        
        <div class="form-group">
            <label>Nationality:</label>
            <input type="text" name="nationality" value="Indian" required>
        </div>
        
        <h4>Address Information</h4>
        <div class="form-group">
            <label>State:</label>
            <input type="text" name="state" value="Odisha" required>
        </div>
        
        <div class="form-group">
            <label>City:</label>
            <input type="text" name="city" value="Bhubaneswar" required>
        </div>
        
        <div class="form-group">
            <label>Pincode:</label>
            <input type="text" name="pincode" value="751001" required>
        </div>
        
        <div class="form-group">
            <label>Address:</label>
            <textarea name="address" required>Simple Test Address, Bhubaneswar, Odisha</textarea>
        </div>
        
        <div class="form-group">
            <label>Training Center:</label>
            <input type="text" name="training_center" value="NIELIT Bhubaneswar" required>
        </div>
        
        <div class="form-group">
            <label>College Name:</label>
            <input type="text" name="college_name" value="Simple Test College">
        </div>
        
        <h4>File Uploads (Use Small Files)</h4>
        <div style="background: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 5px;">
            <strong>⚠️ Use very small files (under 1MB each) for testing</strong>
        </div>
        
        <div class="form-group">
            <label>Passport Photo:</label>
            <input type="file" name="passport_photo" accept=".jpg,.jpeg,.png" required>
        </div>
        
        <div class="form-group">
            <label>Signature:</label>
            <input type="file" name="signature" accept=".jpg,.jpeg,.png" required>
        </div>
        
        <div class="form-group">
            <label>Aadhar Card:</label>
            <input type="file" name="aadhar_card" accept=".jpg,.jpeg,.png,.pdf" required>
        </div>
        
        <div class="form-group">
            <label>10th Marksheet:</label>
            <input type="file" name="tenth_marksheet" accept=".jpg,.jpeg,.png,.pdf" required>
        </div>
        
        <h4>Educational Qualifications</h4>
        <input type="hidden" name="exam_passed[]" value="10th">
        <input type="hidden" name="exam_name[]" value="CBSE">
        <input type="hidden" name="year_of_passing[]" value="2010">
        <input type="hidden" name="institute_name[]" value="Simple Test School">
        <input type="hidden" name="stream[]" value="Science">
        <input type="hidden" name="percentage[]" value="85">
        
        <div style="background: #e8f5e8; padding: 10px; margin: 10px 0; border-radius: 5px;">
            ✅ Educational qualification added: 10th - CBSE - 2010 - Simple Test School - Science - 85%
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <button type="submit" class="submit-btn">🚀 Submit Simple Test</button>
        </div>
        
    </form>
</div>

<div style="background: #d1ecf1; padding: 15px; margin: 20px 0; border-radius: 5px;">
    <h4>🎯 What This Test Does:</h4>
    <ul>
        <li>✅ No JavaScript validation - pure HTML form</li>
        <li>✅ All required fields pre-filled with test data</li>
        <li>✅ Submits directly to submit_registration.php</li>
        <li>✅ Uses the same course ID (65) as the main form</li>
    </ul>
    
    <h4>📋 Instructions:</h4>
    <ol>
        <li>Prepare 4 small files (under 1MB each): passport photo, signature, aadhar, 10th marksheet</li>
        <li>Upload the files in the form above</li>
        <li>Click "Submit Simple Test"</li>
        <li>Watch what happens - should redirect to success page</li>
    </ol>
    
    <h4>🔍 If It Still Redirects Back:</h4>
    <p>The issue is server-side (file size, database, permissions, etc.)</p>
    <p>If this works but the main form doesn't, the issue is JavaScript-related</p>
</div>

</body>
</html>