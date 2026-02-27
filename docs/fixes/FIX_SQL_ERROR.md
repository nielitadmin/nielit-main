# 🔧 FIX SQL ERROR - Line 148

## ❌ ERROR
```
Fatal error: Call to a member function bind_param() on bool
in submit_registration.php:148
```

## 🔍 CAUSE
The SQL `prepare()` statement is returning `false`, which means there's a column mismatch between the SQL query and the actual database table structure.

## ✅ FIXES APPLIED

### 1. Added Error Checking
Added a check after `prepare()` to show the actual database error:

```php
// Check if prepare was successful
if (!$stmt) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    header("Location: student/register.php?course_id=" . $course_id);
    exit();
}
```

### 2. Created Database Check Script
Created `check_students_table.php` to verify table structure.

## 🧪 NEXT STEPS

### Step 1: Check Table Structure
Open in browser:
```
http://localhost/public_html/check_students_table.php
```

This will show:
- All columns in the students table
- Which required columns are missing
- SQL commands to add missing columns

### Step 2: Run the Check
The script will display:
- ✓ Green checkmarks for existing columns
- ✗ Red X marks for missing columns
- SQL ALTER TABLE commands to fix missing columns

### Step 3: Add Missing Columns
If any columns are missing, run the displayed SQL commands in phpMyAdmin:

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select database: `nielit_bhubaneswar`
3. Click "SQL" tab
4. Copy and paste the ALTER TABLE commands
5. Click "Go"

### Step 4: Test Again
After adding missing columns, try submitting the registration form again.

## 📋 EXPECTED COLUMNS

The students table should have these columns:

```
- id (primary key, auto_increment)
- course (VARCHAR)
- course_id (INT)
- training_center (VARCHAR)
- name (VARCHAR)
- father_name (VARCHAR)
- mother_name (VARCHAR)
- dob (DATE)
- age (INT)
- mobile (VARCHAR)
- aadhar (VARCHAR)
- gender (VARCHAR)
- religion (VARCHAR)
- marital_status (VARCHAR)
- category (VARCHAR)
- position (VARCHAR)
- nationality (VARCHAR)
- email (VARCHAR)
- state (VARCHAR)
- city (VARCHAR)
- pincode (VARCHAR)
- address (TEXT)
- college_name (VARCHAR)
- education_details (TEXT)
- documents (VARCHAR)
- passport_photo (VARCHAR)
- signature (VARCHAR)
- payment_receipt (VARCHAR)
- utr_number (VARCHAR)
- student_id (VARCHAR)
- password (VARCHAR)
- registration_date (DATETIME)
```

## 🚀 QUICK FIX

If you want to quickly create the table with all required columns, run this SQL:

```sql
-- Check if table exists and drop it (CAUTION: This deletes all data!)
-- DROP TABLE IF EXISTS students;

-- Create students table with all required columns
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course VARCHAR(255) NOT NULL,
    course_id INT NOT NULL,
    training_center VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    father_name VARCHAR(255) NOT NULL,
    mother_name VARCHAR(255) NOT NULL,
    dob DATE NOT NULL,
    age INT,
    mobile VARCHAR(20) NOT NULL,
    aadhar VARCHAR(20) NOT NULL,
    gender VARCHAR(20) NOT NULL,
    religion VARCHAR(50),
    marital_status VARCHAR(20),
    category VARCHAR(50),
    position VARCHAR(100),
    nationality VARCHAR(50) DEFAULT 'Indian',
    email VARCHAR(255) NOT NULL UNIQUE,
    state VARCHAR(100),
    city VARCHAR(100),
    pincode VARCHAR(10),
    address TEXT,
    college_name VARCHAR(255),
    education_details TEXT,
    documents VARCHAR(255),
    passport_photo VARCHAR(255),
    signature VARCHAR(255),
    payment_receipt VARCHAR(255),
    utr_number VARCHAR(100),
    student_id VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_course_id (course_id),
    INDEX idx_student_id (student_id),
    INDEX idx_email (email),
    INDEX idx_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## ⚠️ IMPORTANT

Before running any SQL that drops or recreates the table:
1. **Backup your database first!**
2. Export existing student data if any
3. Only drop/recreate if you're okay losing existing data

## 📞 TROUBLESHOOTING

### Error Still Persists?
1. Check `check_students_table.php` output
2. Verify all columns exist
3. Check column data types match
4. Ensure database connection is working
5. Check PHP error logs for more details

### Can't Access phpMyAdmin?
Run SQL commands using PHP:
```php
<?php
require_once 'config/config.php';

$sql = "ALTER TABLE students ADD COLUMN missing_column VARCHAR(255)";
if ($conn->query($sql)) {
    echo "Column added successfully";
} else {
    echo "Error: " . $conn->error;
}
?>
```

## ✅ VERIFICATION

After fixing, you should see:
1. No more "bind_param() on bool" error
2. Form submits successfully
3. Student record created in database
4. Email sent with credentials
5. Redirect to success page

---

**Run the check script now:**
```
http://localhost/public_html/check_students_table.php
```
