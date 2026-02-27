# Test Auto Registration System

## Quick Test Guide

### Test 1: Register New Student

1. **Go to registration page:**
   ```
   http://localhost/student/register.php?course_id=1
   ```

2. **Fill the form:**
   - Name: Test Student
   - Email: your-email@example.com
   - Mobile: 9876543210
   - Fill other required fields

3. **Submit form**

4. **Expected Results:**
   - ✅ Redirected to success page
   - ✅ Student ID shown: `NIELIT/2026/XXX/0001`
   - ✅ Password shown: 16-character random string
   - ✅ Email sent to your address
   - ✅ Copy buttons work
   - ✅ Course and center displayed

---

### Test 2: Check Email

1. **Check your inbox** (and spam folder)

2. **Email should contain:**
   - ✅ Subject: "Registration Successful - NIELIT Bhubaneswar"
   - ✅ Student ID
   - ✅ Password
   - ✅ Course name
   - ✅ Training center
   - ✅ Login link button
   - ✅ Professional HTML design

---

### Test 3: Verify Database

```sql
-- Check student record
SELECT student_id, password, email, course, training_center 
FROM students 
ORDER BY id DESC 
LIMIT 1;

-- Expected:
-- student_id: NIELIT/2026/PPI/0001 (or similar)
-- password: $2y$10$... (hashed)
-- email: your-email@example.com
-- course: Course name
-- training_center: Center name
```

---

### Test 4: Sequential ID Generation

1. **Register 3 students for same course**

2. **Expected Student IDs:**
   ```
   NIELIT/2026/PPI/0001
   NIELIT/2026/PPI/0002
   NIELIT/2026/PPI/0003
   ```

3. **Register student for different course**

4. **Expected Student ID:**
   ```
   NIELIT/2026/DBC15/0001  (starts from 0001 for new course)
   ```

---

### Test 5: Course Locking

1. **Access with course_id parameter:**
   ```
   http://localhost/student/register.php?course_id=1
   ```
   - ✅ Course field is READ-ONLY
   - ✅ Training center is READ-ONLY
   - ✅ Blue background on locked fields
   - ✅ Lock icon (🔒) displayed

2. **Access without parameter:**
   ```
   http://localhost/student/register.php
   ```
   - ✅ Course field is EDITABLE
   - ✅ Training center is EDITABLE
   - ✅ Normal dropdown behavior

---

### Test 6: Login with Credentials

1. **Go to login page:**
   ```
   http://localhost/student/login.php
   ```

2. **Enter credentials:**
   - Student ID: (from success page)
   - Password: (from success page)

3. **Expected:**
   - ✅ Login successful
   - ✅ Redirected to student portal

---

## Test Email Configuration

Create a test file: `test_email.php`

```php
<?php
require_once 'includes/email_helper.php';

$result = testEmailConfiguration('your-email@example.com');

if ($result['success']) {
    echo "✅ Email sent successfully!";
} else {
    echo "❌ Error: " . $result['message'];
}
?>
```

Run: `http://localhost/test_email.php`

---

## Common Issues & Solutions

### Issue 1: Email Not Sent
**Solution:**
- Check SMTP credentials in `config/email.php`
- Verify port 587 is not blocked
- Check spam folder
- Test with: `test_email.php`

### Issue 2: Student ID Not Generated
**Solution:**
- Ensure course has `course_abbreviation` set
- Check database connection
- Verify `students` table exists
- Review error logs

### Issue 3: Course Not Locked
**Solution:**
- Verify URL has `?course_id=X`
- Check JavaScript is enabled
- Clear browser cache
- Check browser console for errors

---

## Database Setup

Ensure these columns exist:

```sql
-- Students table
ALTER TABLE students ADD COLUMN IF NOT EXISTS student_id VARCHAR(50);
ALTER TABLE students ADD COLUMN IF NOT EXISTS password VARCHAR(255);
ALTER TABLE students ADD COLUMN IF NOT EXISTS course_id INT;

-- Courses table
ALTER TABLE courses ADD COLUMN IF NOT EXISTS course_abbreviation VARCHAR(10);

-- Add index for performance
CREATE INDEX idx_student_id ON students(student_id);
CREATE INDEX idx_course_id ON students(course_id);
```

---

## Success Criteria

✅ All tests pass  
✅ Email received within 1 minute  
✅ Student ID format correct  
✅ Password is random and secure  
✅ Course locking works  
✅ Database records correct  
✅ Success page displays properly  
✅ Login works with credentials  

---

**Ready to test!** 🚀
