# 🔍 DEBUG: Form Submission Issue

## Problem
Form submits but redirects to `courses.php` without saving data to database.

## Root Cause Analysis
The issue is likely one of these validation failures:
1. **course_id is empty or 0** - Most likely cause
2. **Date of Birth (DOB) is empty**
3. **Name, Mobile, or Email is empty**

## Debugging Steps Added

### 1. Enhanced Error Logging
Added detailed logging to `submit_registration.php`:
- Logs all POST data when form is submitted
- Logs the parsed course_id value
- Logs which validation check is failing
- Shows detailed error messages in session

### 2. Check PHP Error Logs

**Location of error logs:**
- XAMPP: `C:\xampp\php\logs\php_error_log`
- Or check: `C:\xampp\apache\logs\error.log`

**How to check:**
1. Open the error log file in a text editor
2. Look for lines starting with `=== REGISTRATION FORM SUBMISSION ===`
3. Check what data is being received
4. Look for "Validation failed" messages

### 3. Test the Form

**Step-by-step testing:**

1. **Access the registration page:**
   ```
   http://localhost/public_html/student/register.php?course=sas
   ```

2. **Fill out the form completely:**
   - Level 1: Fill all personal information fields
   - Level 2: Fill all contact and address fields
   - Level 3: Fill academic details (optional) and upload documents

3. **Submit the form**

4. **Check what happens:**
   - If redirected to courses.php → Check error message in toast notification
   - Check PHP error log for detailed debugging info

### 4. Common Issues & Solutions

#### Issue 1: course_id is 0 or empty
**Symptom:** Error message says "Course ID received: 0"

**Solution:** The hidden field `course_id` in the form is not being set correctly.

**Check in browser DevTools:**
1. Open browser DevTools (F12)
2. Go to Elements/Inspector tab
3. Search for: `<input type="hidden" name="course_id"`
4. Check if it has a value attribute with a number

**If value is empty or 0:**
- The course lookup in `student/register.php` is failing
- Check if the course exists in database with that code

#### Issue 2: DOB is empty
**Symptom:** Error message says "Date of Birth is required"

**Solution:** Make sure you select a date in the DOB field before submitting

#### Issue 3: Name/Mobile/Email is empty
**Symptom:** Error message shows which field is empty

**Solution:** Fill all required fields marked with red asterisk (*)

### 5. Browser Console Check

**Open browser console (F12 → Console tab) and check:**
1. Are there any JavaScript errors?
2. Is the form validation working?
3. Are all form fields being submitted?

**To see form data being submitted:**
Add this to browser console before submitting:
```javascript
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    const formData = new FormData(this);
    console.log('Form Data:');
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }
});
```

### 6. Quick Fix: Verify course_id Hidden Field

**Check the HTML source:**
1. Right-click on the page → View Page Source
2. Search for: `name="course_id"`
3. You should see something like:
   ```html
   <input type="hidden" name="course_id" value="123">
   ```

**If the value is empty or missing:**
The issue is in `student/register.php` where it sets:
```php
<input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_details['id']); ?>">
```

This means `$course_details['id']` is empty.

### 7. Database Check

**Verify the course exists:**
```sql
SELECT id, course_name, course_code, course_abbreviation 
FROM courses 
WHERE course_code = 'sas' OR course_abbreviation = 'sas';
```

**Expected result:**
- Should return at least one row
- The `id` column should have a number (e.g., 1, 2, 3, etc.)

**If no results:**
- The course doesn't exist with that code
- You need to add it or use a different course code

### 8. Test with Direct course_id

**Try accessing with numeric ID instead:**
```
http://localhost/public_html/student/register.php?course_id=1
```

Replace `1` with an actual course ID from your database.

**If this works:**
- The issue is with course code lookup
- Check if the course has a `course_code` or `course_abbreviation` set

## Next Steps

1. **Submit the form** and check the error message
2. **Check PHP error log** for detailed debugging info
3. **Report back** with:
   - The exact error message shown
   - Any errors from PHP error log
   - The value of course_id hidden field (from View Source)

## Expected Error Log Output

When you submit the form, you should see something like this in the error log:

```
=== REGISTRATION FORM SUBMISSION ===
POST Data: Array
(
    [course_id] => 1
    [training_center] => NIELIT Bhubaneswar
    [name] => John Doe
    [father_name] => Father Name
    ...
)
FILES Data: Array
(
    [0] => documents
    [1] => passport_photo
    [2] => signature
    [3] => payment_receipt
)
Parsed course_id: 1
```

**If course_id is 0:**
```
Parsed course_id: 0
Validation failed: Invalid course_id = 0 | POST course_id = NOT SET
```

This tells us the hidden field is not being submitted.

## Files Modified
- `submit_registration.php` - Added debugging logs and better error messages

## Status
✅ Debugging code added
⏳ Waiting for test results
