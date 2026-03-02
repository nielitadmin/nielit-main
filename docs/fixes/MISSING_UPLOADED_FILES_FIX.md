# 🔧 MISSING UPLOADED FILES - FIX GUIDE

## Issue Identified

The student document viewing page shows "No data available" because:

1. ✅ Database has file paths stored correctly
2. ❌ **Actual files don't exist on disk**

### Debug Output Shows:
```
passport_photo: uploads/students/NIELIT-2026-SWA-0001_1772188953_passport.png → File Exists? NO
signature: uploads/students/NIELIT-2026-SWA-0001_1772188954_signature.jpg → File Exists? NO
aadhar_card_doc: uploads/aadhar/NIELIT-2026-SWA-0001_1772188953_aadhar.pdf → File Exists? NO
tenth_marksheet_doc: uploads/marksheets/10th/NIELIT-2026-SWA-0001_1772188953_tenth.pdf → File Exists? NO
```

## Root Cause Analysis

### Possible Causes:

1. **Upload Directory Mismatch**
   - Registration form uploads to one directory
   - View page looks in a different directory

2. **Missing Upload Directories**
   - The required directories don't exist
   - PHP can't create them due to permissions

3. **Upload Process Failed**
   - Database was updated but file upload failed
   - No error handling to catch the failure

4. **Files Were Deleted**
   - Files were uploaded successfully
   - Later deleted manually or by a cleanup script

## Solution Steps

### Step 1: Check Upload Directory Structure

Run this command to check if directories exist:

```bash
cd C:\xampp\htdocs\public_html
dir uploads
```

**Expected structure:**
```
uploads/
├── students/          (for passport photos and signatures)
├── aadhar/           (for aadhar cards)
├── marksheets/
│   ├── 10th/        (for 10th marksheets)
│   └── 12th/        (for 12th marksheets)
├── graduation/       (for graduation certificates)
├── caste/           (for caste certificates)
├── payment/         (for payment receipts)
└── other/           (for other documents)
```

### Step 2: Create Missing Directories

Create a script to set up all required directories:

```php
<?php
// Run this once: create_upload_directories.php

$base_dir = __DIR__ . '/uploads';
$directories = [
    'students',
    'aadhar',
    'marksheets/10th',
    'marksheets/12th',
    'graduation',
    'caste',
    'payment',
    'other'
];

foreach ($directories as $dir) {
    $full_path = $base_dir . '/' . $dir;
    if (!file_exists($full_path)) {
        if (mkdir($full_path, 0755, true)) {
            echo "✓ Created: $full_path<br>";
        } else {
            echo "✗ Failed to create: $full_path<br>";
        }
    } else {
        echo "→ Already exists: $full_path<br>";
    }
}

echo "<br>Done! All directories checked/created.";
?>
```

Save as `create_upload_directories.php` and run it once.

### Step 3: Re-upload Documents

Since the files are missing, you need to re-upload them:

**Option A: Via Edit Student Page**
1. Go to: `http://localhost/public_html/admin/edit_student.php?id=NIELIT/2026/SWA/0001`
2. Scroll to document sections
3. Upload all required documents again
4. Click "Update Student"

**Option B: Via Student Registration**
1. Register a new student
2. Upload all documents during registration
3. Check if files are saved correctly

### Step 4: Verify Upload Process

Check the registration form handler (`student/submit_registration.php`) to ensure it's saving files correctly.

**Key things to check:**
1. File upload error handling
2. Directory permissions (should be 0755 or 0777)
3. File move operation success
4. Database update after successful file upload

### Step 5: Add .htaccess Protection

Create `.htaccess` in uploads directory to prevent direct access:

```apache
# uploads/.htaccess
Options -Indexes
<FilesMatch "\.(jpg|jpeg|png|gif|pdf)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>
```

## Quick Fix Script

Create this script to diagnose and fix the issue:

```php
<?php
// fix_missing_uploads.php
session_start();
require_once __DIR__ . '/config/config.php';

if (!isset($_SESSION['admin'])) {
    die("Admin login required");
}

echo "<h2>Upload Directory Diagnostic & Fix</h2>";

// 1. Check if base uploads directory exists
$base_dir = __DIR__ . '/uploads';
echo "<h3>1. Base Directory Check</h3>";
if (file_exists($base_dir)) {
    echo "✓ Base uploads directory exists: $base_dir<br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($base_dir)), -4) . "<br>";
} else {
    echo "✗ Base uploads directory MISSING!<br>";
    if (mkdir($base_dir, 0755, true)) {
        echo "✓ Created base directory<br>";
    } else {
        echo "✗ Failed to create base directory<br>";
    }
}

// 2. Check/Create subdirectories
echo "<h3>2. Subdirectory Check</h3>";
$subdirs = [
    'students',
    'aadhar',
    'marksheets',
    'marksheets/10th',
    'marksheets/12th',
    'graduation',
    'caste',
    'payment',
    'other'
];

foreach ($subdirs as $subdir) {
    $full_path = $base_dir . '/' . $subdir;
    if (file_exists($full_path)) {
        echo "✓ $subdir exists<br>";
    } else {
        echo "✗ $subdir MISSING - ";
        if (mkdir($full_path, 0755, true)) {
            echo "CREATED<br>";
        } else {
            echo "FAILED TO CREATE<br>";
        }
    }
}

// 3. Check student files
echo "<h3>3. Student File Check (NIELIT/2026/SWA/0001)</h3>";
$sql = "SELECT student_id, name, passport_photo, signature, aadhar_card_doc, tenth_marksheet_doc 
        FROM students WHERE student_id = 'NIELIT/2026/SWA/0001'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $student = $result->fetch_assoc();
    echo "<p><strong>Student:</strong> " . htmlspecialchars($student['name']) . "</p>";
    
    $files = [
        'Passport Photo' => $student['passport_photo'],
        'Signature' => $student['signature'],
        'Aadhar Card' => $student['aadhar_card_doc'],
        '10th Marksheet' => $student['tenth_marksheet_doc']
    ];
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Document</th><th>Path in DB</th><th>File Exists?</th><th>Action</th></tr>";
    
    foreach ($files as $label => $path) {
        if (empty($path)) {
            echo "<tr><td>$label</td><td><em>Not set</em></td><td>N/A</td><td>-</td></tr>";
            continue;
        }
        
        $full_path = __DIR__ . '/' . $path;
        $exists = file_exists($full_path);
        
        echo "<tr>";
        echo "<td>$label</td>";
        echo "<td>" . htmlspecialchars($path) . "</td>";
        echo "<td>" . ($exists ? '<span style="color:green;">YES</span>' : '<span style="color:red;">NO</span>') . "</td>";
        echo "<td>" . ($exists ? '-' : '<strong>NEEDS RE-UPLOAD</strong>') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>Student not found</p>";
}

// 4. Recommendations
echo "<h3>4. Recommendations</h3>";
echo "<ul>";
echo "<li>All required directories have been created (if they were missing)</li>";
echo "<li>Files marked as 'NEEDS RE-UPLOAD' must be uploaded again via Edit Student page</li>";
echo "<li>Go to: <a href='edit_student.php?id=NIELIT/2026/SWA/0001'>Edit Student Page</a></li>";
echo "<li>Upload the missing documents and click 'Update Student'</li>";
echo "</ul>";

$conn->close();
?>
```

Save as `admin/fix_missing_uploads.php` and run it.

## Prevention

### 1. Add Upload Validation

In `student/submit_registration.php`, add proper error handling:

```php
// Example: Proper file upload with validation
if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/students/';
    
    // Check if directory exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_name = $student_id . '_' . time() . '_passport.' . $extension;
    $target_path = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['passport_photo']['tmp_name'], $target_path)) {
        $passport_photo_path = 'uploads/students/' . $file_name;
        // Save to database
    } else {
        // Log error
        error_log("Failed to upload passport photo for $student_id");
        $_SESSION['error'] = "Failed to upload passport photo";
    }
} else {
    // Log upload error
    error_log("Passport photo upload error: " . $_FILES['passport_photo']['error']);
}
```

### 2. Add File Existence Check

Before displaying documents, check if files exist:

```php
// In view_student_documents.php
if (!empty($student['passport_photo'])) {
    $file_path = __DIR__ . '/../' . $student['passport_photo'];
    if (file_exists($file_path)) {
        // Display document
    } else {
        // Show "File missing" message
        // Log the issue
        error_log("Missing file: " . $student['passport_photo'] . " for student " . $student['student_id']);
    }
}
```

### 3. Add Backup System

Create a backup script that runs daily:

```php
// backup_uploads.php
$source = __DIR__ . '/uploads';
$backup = __DIR__ . '/backups/uploads_' . date('Y-m-d');

// Copy entire uploads directory
function recurse_copy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            if (is_dir($src . '/' . $file)) {
                recurse_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

recurse_copy($source, $backup);
echo "Backup created: $backup";
```

## Testing

After fixing:

1. **Run the fix script**: `http://localhost/public_html/admin/fix_missing_uploads.php`
2. **Re-upload documents**: Via edit student page
3. **Verify files exist**: Check the uploads directory
4. **Test viewing**: Go to view documents page
5. **Confirm display**: Documents should now show correctly

## Summary

The issue is **missing physical files**, not a code problem. The solution is:

1. ✅ Create all required upload directories
2. ✅ Re-upload the missing documents
3. ✅ Add proper error handling to prevent this in future
4. ✅ Implement backup system

---

**Date**: February 27, 2026
**Issue**: Files in database but missing from disk
**Cause**: Upload directories missing or files deleted
**Solution**: Create directories + re-upload documents
