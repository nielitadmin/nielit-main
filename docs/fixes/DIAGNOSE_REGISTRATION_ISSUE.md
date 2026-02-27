# 🔍 Diagnose Registration Issue

## Current Problem
Form submits but redirects to `courses.php` without saving data.

## Possible Causes

### 1. course_id Field Not Being Sent
The hidden `course_id` field might not be in the form data.

### 2. JavaScript Validation Failing
JavaScript might be preventing submission.

### 3. Form Data Being Lost
Data might be lost during multi-step navigation.

---

## 🧪 Diagnostic Steps

### Step 1: Test Simple Form
Open: `http://localhost/public_html/test_registration_submit.html`

This tests if the basic form submission works.

**Expected Result:**
- Shows all form data
- course_id = 1
- All validations pass

### Step 2: Check Browser Console
1. Open registration form: `http://localhost/public_html/student/register.php?course=ol`
2. Press F12 to open Developer Tools
3. Go to "Console" tab
4. Fill the form and click Submit
5. Look for any JavaScript errors

**What to Look For:**
- Red error messages
- "Uncaught" errors
- Network tab shows POST request to `submit_registration.php`

### Step 3: Check Error Logs
After submitting the form, check:

**Option A: Check PHP Error Log**
```
http://localhost/public_html/check_error_log_location.php
```

**Option B: Check Apache Log**
```
http://localhost/public_html/view_apache_log.php
```

**What to Look For:**
```
=== REGISTRATION FORM SUBMISSION ===
RAW course_id from POST: [value or NOT SET]
POST array keys: [list of fields]
Parsed course_id: [number]
course_id validation: [WILL FAIL or WILL PASS]
```

### Step 4: Use Debug Form Data
Temporarily change form action in `student/register.php`:

**Find line 1222:**
```php
<form method="POST" action="<?php echo APP_URL; ?>/submit_registration.php"
```

**Change to:**
```php
<form method="POST" action="<?php echo APP_URL; ?>/debug_form_data.php"
```

Then submit the form and see exactly what data is being sent.

---

## 🎯 Quick Fixes to Try

### Fix 1: Ensure course_id is Set
Check if the hidden field has a value:

1. Open: `http://localhost/public_html/student/register.php?course=ol`
2. Right-click on the page → "View Page Source"
3. Search for: `name="course_id"`
4. Should see: `<input type="hidden" name="course_id" value="1">`

If value is empty, the course lookup failed.

### Fix 2: Check JavaScript Console
1. Open form with F12 Developer Tools
2. Go to Console tab
3. Type: `document.querySelector('input[name="course_id"]').value`
4. Press Enter
5. Should show: `"1"` (or another number)

If it shows empty or null, the field is missing.

### Fix 3: Disable JavaScript Validation Temporarily
In `student/register.php`, find the form submit handler (around line 2110) and add at the top:

```javascript
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    console.log('Form submitting...');
    console.log('course_id:', document.querySelector('input[name="course_id"]').value);
    // Rest of validation...
```

This will log the course_id value before validation.

---

## 📊 Common Issues & Solutions

### Issue 1: course_id is Empty String
**Symptom:** `intval('')` returns `0`, validation fails
**Solution:** Check if `$course_details['id']` exists in register.php

### Issue 2: Form Submits to Wrong URL
**Symptom:** Network tab shows wrong URL
**Solution:** Check `APP_URL` in `config/app.php`

### Issue 3: JavaScript Prevents Submission
**Symptom:** Form doesn't submit at all
**Solution:** Check console for validation errors

### Issue 4: POST Data Not Reaching PHP
**Symptom:** `$_POST` is empty
**Solution:** Check `php.ini` settings:
- `post_max_size`
- `upload_max_filesize`
- `max_file_uploads`

---

## 🔧 Manual Debug Method

Add this at the very top of `submit_registration.php` (line 18):

```php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // EMERGENCY DEBUG - Remove after testing
    echo "<h2>DEBUG: Form Data Received</h2>";
    echo "<pre>";
    echo "course_id: " . ($_POST['course_id'] ?? 'NOT SET') . "\n";
    echo "name: " . ($_POST['name'] ?? 'NOT SET') . "\n";
    echo "email: " . ($_POST['email'] ?? 'NOT SET') . "\n";
    echo "\nFull POST data:\n";
    print_r($_POST);
    echo "</pre>";
    exit(); // Stop here to see the output
}
```

This will show you exactly what data is being received.

---

## 🎯 Most Likely Cause

Based on the symptoms (redirects to courses.php), the most likely cause is:

**The `course_id` field is empty or 0**

This triggers the validation at line 99-103 in `submit_registration.php`:

```php
if (empty($course_id) || $course_id <= 0) {
    $_SESSION['error'] = "Please select a valid course...";
    header("Location: " . APP_URL . "/public/courses.php");
    exit();
}
```

---

## ✅ Next Steps

1. **First:** Check browser console (F12) for JavaScript errors
2. **Second:** Use `test_registration_submit.html` to test basic submission
3. **Third:** Check error logs to see what's being received
4. **Fourth:** Use `debug_form_data.php` to see exact POST data

Once we know what data is (or isn't) being sent, we can fix it!

---

## 📞 Quick Test Commands

```bash
# Test simple form
http://localhost/public_html/test_registration_submit.html

# Check error logs
http://localhost/public_html/check_error_log_location.php

# Debug form data
# (Change form action to debug_form_data.php first)
http://localhost/public_html/student/register.php?course=ol
```

---

**Let's find out what's happening!** 🔍
