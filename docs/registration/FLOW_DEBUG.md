# 🔄 Registration Flow & Debugging

## Complete Registration Flow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. USER CLICKS REGISTRATION LINK                            │
│    http://localhost/public_html/student/register.php?course=sas │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. student/register.php LOADS                               │
│    • Checks if course parameter exists                      │
│    • Looks up course in database                            │
│    • If found: Shows registration form                      │
│    • If not found: Redirects to courses.php                 │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. FORM DISPLAYS WITH LOCKED COURSE                         │
│    • Course info card shows selected course                 │
│    • Hidden field: <input name="course_id" value="1">       │
│    • User fills out 3 levels of information                 │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. USER CLICKS "COMPLETE REGISTRATION"                      │
│    • JavaScript validates all fields                        │
│    • Checks file uploads (3 required)                       │
│    • If valid: Form submits to submit_registration.php      │
│    • If invalid: Shows error toast                          │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. submit_registration.php RECEIVES DATA                    │
│    • Logs all POST data (NEW!)                              │
│    • Validates course_id (must be > 0)                      │
│    • Validates DOB (must not be empty)                      │
│    • Validates name, mobile, email (must not be empty)      │
└─────────────────────────────────────────────────────────────┘
                            ↓
                    ┌───────┴───────┐
                    │               │
            ❌ VALIDATION FAILS  ✅ VALIDATION PASSES
                    │               │
                    ↓               ↓
    ┌───────────────────────┐   ┌───────────────────────┐
    │ Redirects to:         │   │ Continues processing: │
    │ • courses.php         │   │ • Looks up course     │
    │   (if course_id = 0)  │   │ • Generates student ID│
    │ • register.php        │   │ • Hashes password     │
    │   (if other fields)   │   │ • Inserts to database │
    │                       │   │ • Sends email         │
    │ Shows error message   │   │ • Redirects to success│
    └───────────────────────┘   └───────────────────────┘
                                            ↓
                            ┌───────────────────────────┐
                            │ 6. registration_success.php│
                            │    • Shows student ID      │
                            │    • Shows password        │
                            │    • Shows success message │
                            └───────────────────────────┘
```

## Where Things Can Go Wrong

### ❌ Point 2: Course Lookup Fails
**Symptom:** Redirects to courses.php immediately
**Cause:** Course doesn't exist with that code
**Check:**
```sql
SELECT * FROM courses WHERE course_code = 'sas' OR course_abbreviation = 'sas';
```
**Fix:** Use a valid course code or add the course to database

### ❌ Point 3: Hidden Field Not Set
**Symptom:** Form loads but course_id is empty
**Cause:** `$course_details['id']` is empty
**Check:** View page source, search for `name="course_id"`
**Fix:** Ensure course lookup in step 2 succeeded

### ❌ Point 4: JavaScript Validation Fails
**Symptom:** Form doesn't submit, shows error toast
**Cause:** Missing required files or invalid field format
**Check:** Browser console for JavaScript errors
**Fix:** Upload all 3 required files, fix field formats

### ❌ Point 5: Server Validation Fails
**Symptom:** Form submits but redirects to courses.php
**Cause:** One of the server-side validations failed
**Check:** PHP error log for validation failure message
**Fix:** Based on which validation failed

## Debugging Checkpoints

### Checkpoint 1: Course Lookup
**File:** `student/register.php` (lines 15-60)
**What to check:**
```php
// This should find the course
$stmt = $conn->prepare("SELECT * FROM courses WHERE ...");
$result = $stmt->get_result();

// This should be > 0
if ($result->num_rows === 0) {
    // PROBLEM: Course not found
}
```

**Test:**
```
http://localhost/public_html/student/register.php?course=sas
```
- ✅ Should show registration form with course info card
- ❌ If redirects to courses.php → Course doesn't exist

### Checkpoint 2: Hidden Field Value
**File:** `student/register.php` (around line 1400)
**What to check:**
```html
<input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_details['id']); ?>">
```

**Test:** View page source
- ✅ Should see: `<input type="hidden" name="course_id" value="1">`
- ❌ If value is empty → Course lookup failed

### Checkpoint 3: Form Submission
**File:** `submit_registration.php` (line 18)
**What to check:**
```php
$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
error_log("Parsed course_id: " . $course_id);
```

**Test:** Check PHP error log after submitting
- ✅ Should see: `Parsed course_id: 1` (or other number)
- ❌ If see: `Parsed course_id: 0` → Hidden field not submitted

### Checkpoint 4: Validation
**File:** `submit_registration.php` (lines 100-120)
**What to check:**
```php
if (empty($course_id) || $course_id <= 0) {
    // This is where it fails if course_id is 0
    error_log("Validation failed: Invalid course_id");
}
```

**Test:** Check PHP error log
- ✅ Should NOT see validation failed messages
- ❌ If see validation failed → That's the problem

## Quick Debug Commands

### 1. Check if course exists
```sql
SELECT id, course_name, course_code, course_abbreviation 
FROM courses 
WHERE course_code = 'sas' OR course_abbreviation = 'sas';
```

### 2. Check PHP error log
```bash
# Windows
type C:\xampp\php\logs\php_error_log | findstr "REGISTRATION"

# Or open in notepad
notepad C:\xampp\php\logs\php_error_log
```

### 3. Check form data in browser
```javascript
// Paste in browser console before submitting
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    const formData = new FormData(this);
    console.log('course_id:', formData.get('course_id'));
    console.log('name:', formData.get('name'));
    console.log('email:', formData.get('email'));
});
```

## Test Sequence

### 1. Test Course Lookup
```
http://localhost/public_html/student/register.php?course=sas
```
**Expected:** Registration form loads with course info card
**If fails:** Course doesn't exist, check database

### 2. Test Hidden Field
**Action:** View page source, search for `name="course_id"`
**Expected:** `<input type="hidden" name="course_id" value="1">`
**If fails:** Course lookup didn't work

### 3. Test Basic Submission
```
http://localhost/public_html/test_form_submission.php
```
**Expected:** Success message after submitting
**If fails:** Database or configuration issue

### 4. Test Full Registration
**Action:** Fill out all 3 levels, upload files, submit
**Expected:** Redirect to registration_success.php
**If fails:** Check error log for validation failure

## Common Error Messages

### "Please select a valid course. Course ID received: 0"
**Meaning:** course_id is 0 or empty
**Location:** submit_registration.php line 102
**Fix:** Check hidden field value in page source

### "Date of Birth is required"
**Meaning:** DOB field is empty
**Location:** submit_registration.php line 107
**Fix:** Make sure you selected a date

### "Name, Mobile, and Email are required fields"
**Meaning:** One of these fields is empty
**Location:** submit_registration.php line 112
**Fix:** Fill all required fields

### "Course not found"
**Meaning:** Course ID doesn't exist in database
**Location:** submit_registration.php line 180
**Fix:** Check if course exists in database

## Files to Check

1. **student/register.php** - Form display and course lookup
2. **submit_registration.php** - Form processing and validation
3. **registration_success.php** - Success page
4. **PHP error log** - Detailed error messages
5. **Browser console** - JavaScript errors

## Status Indicators

✅ **Working:** Form submits → Success page → Data in database
⚠️ **Partial:** Form loads but submission fails
❌ **Broken:** Can't access registration page

---

**Current Status:** ⚠️ Partial - Form loads but submission redirects to courses.php

**Next Action:** Run test form to identify exact failure point
