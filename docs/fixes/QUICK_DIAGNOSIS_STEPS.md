# ⚡ Quick Diagnosis - 3 Steps

## Problem
Form redirects to `courses.php` without saving.

## Quick Diagnosis (5 Minutes)

### Step 1: Check Browser Console (2 min)
1. Open: `http://localhost/public_html/student/register.php?course=ol`
2. Press **F12** (Developer Tools)
3. Click **Console** tab
4. Fill form and click Submit
5. Look for **red errors**

**What to check:**
- Any JavaScript errors?
- Does form actually submit?

---

### Step 2: Check What Data is Sent (2 min)
1. Open: `http://localhost/public_html/test_registration_submit.html`
2. Click **Submit Test Form**
3. See if validation passes

**Expected:**
- ✅ All validations pass
- ✅ course_id = 1
- ✅ All fields present

**If this works but real form doesn't:**
- Problem is in the actual registration form
- Hidden field might be missing or empty

---

### Step 3: Check Error Logs (1 min)
1. Submit the real registration form
2. Open: `http://localhost/public_html/check_error_log_location.php`
3. Look for recent errors

**What to look for:**
```
RAW course_id from POST: [value]
course_id validation: WILL FAIL or WILL PASS
```

---

## 🎯 Most Likely Issues

### Issue A: Hidden Field is Empty
**Check:**
1. Open registration form
2. Right-click → View Page Source
3. Search for: `name="course_id"`
4. Check if `value="1"` or `value=""`

**If empty:**
- Course lookup failed
- `$course_details['id']` is not set

### Issue B: JavaScript Validation Fails
**Check:**
- Browser console shows errors
- Form doesn't submit at all
- Validation messages appear

**Fix:**
- Check all required fields are filled
- Check file uploads are valid
- Check validation rules

### Issue C: POST Data Not Received
**Check:**
- `$_POST` is empty in logs
- File size too large
- PHP settings issue

**Fix:**
- Check `php.ini` settings
- Reduce file sizes
- Check `post_max_size`

---

## 🔧 Quick Fix to Try

Add this to the TOP of `submit_registration.php` (after line 17):

```php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // QUICK DEBUG
    file_put_contents('debug_post.txt', print_r($_POST, true));
    file_put_contents('debug_files.txt', print_r($_FILES, true));
    
    // Continue with normal code...
}
```

Then check files:
- `debug_post.txt` - Shows POST data
- `debug_files.txt` - Shows uploaded files

---

## ✅ Action Plan

1. **Do Step 1** - Check browser console
2. **Do Step 2** - Test simple form
3. **Do Step 3** - Check error logs
4. **Report back** what you find

Based on what you see, we'll know exactly what to fix!

---

## 📞 Quick Links

| Tool | URL |
|------|-----|
| Registration Form | `http://localhost/public_html/student/register.php?course=ol` |
| Test Form | `http://localhost/public_html/test_registration_submit.html` |
| Error Log Checker | `http://localhost/public_html/check_error_log_location.php` |
| Apache Log Viewer | `http://localhost/public_html/view_apache_log.php` |
| Debug Form Data | `http://localhost/public_html/debug_form_data.php` |

---

**Start with Step 1 - Check browser console!** 🔍
