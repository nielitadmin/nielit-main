# URGENT: Debug Form Submission Issue

## Current Status
All fixes have been applied but form still redirects to step 1. We need to identify the EXACT error.

## 🔍 STEP 1: Check Browser Console (MOST IMPORTANT)

1. Open the registration form: `http://localhost/student/register.php?course=DBC24`
2. Press `F12` to open Developer Tools
3. Go to the **Console** tab
4. Fill in the form with ALL required fields
5. Upload ALL 4 mandatory documents:
   - Aadhar Card
   - 10th Marksheet
   - Passport Photo
   - Signature
6. Click **Submit Registration**
7. **LOOK FOR**:
   - `=== FORM SUBMISSION STARTED ===`
   - `✓ ALL VALIDATIONS PASSED`
   - `=== FORM WILL BE SUBMITTED ===`
   - `Form submission proceeding to server...`

### What to Check:
- **If you see validation errors in console** → JavaScript is blocking submission (expected behavior)
- **If you see "ALL VALIDATIONS PASSED"** → Form is submitting to server, check PHP error log next

---

## 🔍 STEP 2: Check for Toast Error Message

After the form redirects back to step 1:
1. **Look at the TOP of the page** for a red toast notification
2. The error message will appear as a red popup in the top-right corner
3. **Take a screenshot** of the error message if you see one

---

## 🔍 STEP 3: Check PHP Error Log

**Location**: `C:\xampp\php\logs\php_error_log`

1. Open this file in Notepad
2. Scroll to the **BOTTOM** (most recent errors)
3. Look for errors with today's timestamp
4. **Copy the last 20-30 lines** and send them to me

### What to Look For:
```
[27-Feb-2026 ...] PHP Warning: ...
[27-Feb-2026 ...] PHP Fatal error: ...
[27-Feb-2026 ...] PREPARE FAILED: ...
[27-Feb-2026 ...] INSERT FAILED: ...
```

---

## 🔍 STEP 4: Check Apache Error Log (if PHP log is empty)

**Location**: `C:\xampp\apache\logs\error.log`

1. Open this file in Notepad
2. Scroll to the **BOTTOM**
3. Look for errors with today's timestamp
4. **Copy the last 20-30 lines**

---

## 🔍 STEP 5: Test with Minimal Data

Try submitting with ONLY these fields filled:
- **Name**: Test User
- **Mobile**: 9876543210
- **Email**: test@example.com
- **Date of Birth**: 2000-01-01
- **Aadhar**: 123456789012
- **Pincode**: 751024
- **Father Name**: Test Father
- **Mother Name**: Test Mother
- **Gender**: Male
- **Marital Status**: Single
- **Category**: General
- **Nationality**: Indian
- **State**: Odisha
- **City**: Bhubaneswar
- **Address**: Test Address
- **Position**: Student

Upload 4 documents (any small image/PDF files for testing).

---

## 🔍 STEP 6: Check Database Connection

Run this test script to verify database is working:

**Create file**: `test_db_connection.php` in root folder

```php
<?php
require_once 'config/config.php';

echo "<h2>Database Connection Test</h2>";

// Test connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
echo "✅ Database connected successfully<br>";

// Test students table exists
$result = $conn->query("SHOW TABLES LIKE 'students'");
if ($result->num_rows > 0) {
    echo "✅ Students table exists<br>";
} else {
    echo "❌ Students table NOT found<br>";
}

// Test courses table
$result = $conn->query("SELECT * FROM courses WHERE course_code = 'DBC24' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $course = $result->fetch_assoc();
    echo "✅ Course DBC24 found: " . $course['course_name'] . "<br>";
    echo "   - Course ID: " . $course['id'] . "<br>";
    echo "   - Course Code: " . $course['course_code'] . "<br>";
} else {
    echo "❌ Course DBC24 NOT found<br>";
}

// Test document columns exist
$result = $conn->query("DESCRIBE students");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

$required_columns = [
    'aadhar_card_doc',
    'caste_certificate_doc',
    'tenth_marksheet_doc',
    'twelfth_marksheet_doc',
    'graduation_certificate_doc',
    'other_documents_doc'
];

echo "<br><strong>Document Columns Check:</strong><br>";
foreach ($required_columns as $col) {
    if (in_array($col, $columns)) {
        echo "✅ $col exists<br>";
    } else {
        echo "❌ $col MISSING<br>";
    }
}

echo "<br><strong>All Columns in students table:</strong><br>";
echo "<pre>" . implode("\n", $columns) . "</pre>";
?>
```

Then visit: `http://localhost/test_db_connection.php`

---

## 📋 What to Send Me

Please provide:

1. **Browser Console Output** (screenshot or copy-paste)
2. **Toast Error Message** (if any appears)
3. **PHP Error Log** (last 20-30 lines from `C:\xampp\php\logs\php_error_log`)
4. **Database Test Results** (output from `test_db_connection.php`)

With this information, I can identify the EXACT issue and provide a targeted fix.

---

## Common Issues & Quick Fixes

### Issue: "Course not found" error
**Fix**: Make sure you're using a valid course code in the URL
```
http://localhost/student/register.php?course=DBC24
```

### Issue: "Database error" in toast
**Fix**: Check PHP error log for the specific SQL error

### Issue: Form submits but no data in database
**Fix**: Check if INSERT statement is failing (look for "INSERT FAILED" in PHP error log)

### Issue: "Permission denied" for file uploads
**Fix**: Make sure `uploads/` folder exists and is writable
```bash
mkdir uploads
chmod 755 uploads
```

---

**Next Steps**: Run through these debugging steps and send me the results. I'll provide an immediate fix based on what we find.
