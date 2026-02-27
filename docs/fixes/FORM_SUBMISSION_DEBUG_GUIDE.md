# Form Submission Debug Guide

## Issue
Form is not submitting to database - redirects back to page 1 without confirmation.

## Debugging Steps

### Step 1: Run Diagnostic Test
1. Open browser and navigate to: `http://localhost/your-project/test_form_submission.php`
2. Check all 6 tests pass:
   - ✓ Database columns exist
   - ✓ Upload directories exist (or will be created)
   - ✓ PHP upload configuration is correct
   - ✓ Sample course exists
   - ✓ Error log location identified
   - ✓ No session errors

### Step 2: Open Browser Developer Tools
1. Press `F12` to open Developer Tools
2. Go to **Console** tab
3. Keep it open while testing

### Step 3: Fill Out Registration Form
1. Click the test URL from diagnostic page (or navigate to a course registration link)
2. Fill out the form with test data:
   - **Name:** Test Student
   - **Mobile:** 9876543210 (10 digits)
   - **Aadhar:** 123456789012 (12 digits)
   - **Email:** test@example.com
   - **Pincode:** 751001 (6 digits)
   - **DOB:** Any valid date
   - Fill other required fields

### Step 4: Upload Required Documents
**MANDATORY (must upload these 3):**
1. **Aadhar Card** - Upload any JPG or PDF file
2. **Passport Photo** - Upload any image file
3. **Signature** - Upload any image file

**OPTIONAL (can skip these):**
- 10th Marksheet
- 12th Marksheet
- Caste Certificate
- Graduation Certificate
- Other Documents

### Step 5: Submit Form and Check Console
1. Click "Submit Registration" button
2. **Watch the Console** - you should see detailed logs:

```
=== FORM SUBMISSION STARTED ===
course_id field: <input>
course_id value: 1
Form data being submitted:
  course_id: 1
  name: Test Student
  mobile: 9876543210
  ...
=== STARTING VALIDATION ===
Validating mobile: 9876543210
✓ Mobile validation passed
Validating aadhar: 123456789012
✓ Aadhar validation passed
...
=== STARTING DOCUMENT VALIDATION ===
Checking aadhar_card: <input>
  - Has files: 1
  - First file: aadhar.pdf
  ✓ Aadhar Card validation passed
=== VALIDATING PASSPORT PHOTO & SIGNATURE ===
✓ Passport photo present
✓ Signature present
...
✓ ALL VALIDATIONS PASSED
=== FORM WILL BE SUBMITTED ===
Form submission proceeding to server...
```

### Step 6: Identify Where It Fails

#### If Console Shows Validation Error:
Look for lines like:
```
✗ VALIDATION FAILED: Invalid mobile number
```
or
```
✗ VALIDATION FAILED: Aadhar Card is missing
```

**Fix:** Upload the missing document or fix the invalid field.

#### If Console Shows "ALL VALIDATIONS PASSED":
The problem is on the server side (PHP).

**Check PHP Error Log:**
1. Open: `C:\xampp\apache\logs\error.log`
2. Look for recent entries with:
   - `=== REGISTRATION FORM SUBMISSION ===`
   - `=== DOCUMENT UPLOAD RESULTS ===`
   - `=== DATABASE INSERT SUCCESSFUL ===` or `=== DATABASE INSERT FAILED ===`

### Step 7: Common Issues and Solutions

#### Issue 1: "course_id value: FIELD NOT FOUND"
**Problem:** Hidden course_id field is missing
**Solution:** Make sure you're accessing the form through a course registration link (e.g., `student/register.php?course=sas`)

#### Issue 2: "Aadhar Card is required"
**Problem:** Aadhar card file not uploaded
**Solution:** Upload a JPG or PDF file for Aadhar Card

#### Issue 3: "Passport photo is required"
**Problem:** Passport photo not uploaded
**Solution:** Upload an image file for passport photo

#### Issue 4: "Signature is required"
**Problem:** Signature not uploaded
**Solution:** Upload an image file for signature

#### Issue 5: Form submits but no database entry
**Check PHP Error Log for:**
- SQL errors (column mismatch, data type errors)
- File upload errors (permission denied, directory not found)
- Validation errors (empty required fields)

### Step 8: Check Database
After successful submission, verify in phpMyAdmin:
```sql
SELECT * FROM students ORDER BY id DESC LIMIT 1;
```

Check if:
- New record exists
- `status` = 'pending'
- `aadhar_card_doc` has a path like `uploads/aadhar/STUDENT_ID_timestamp_aadhar.pdf`
- `passport_photo` has a path
- `signature` has a path

### Step 9: Check Admin Panel
1. Login to admin panel
2. Go to Students page
3. Check "Pending Approval" count
4. Should see the new student in pending list

## Current Validation Requirements

### Mandatory Fields (Form will NOT submit without these):
1. **Aadhar Card** (categorized document)
2. **Passport Photo** (legacy document)
3. **Signature** (legacy document)
4. Name, Mobile (10 digits), Email, Aadhar Number (12 digits)
5. DOB, Pincode (6 digits)

### Optional Fields (Can be left empty):
1. 10th Marksheet
2. 12th Marksheet
3. Caste Certificate
4. Graduation Certificate
5. Other Documents

## Debug Checklist

- [ ] Ran `test_form_submission.php` - all tests pass
- [ ] Opened browser console (F12)
- [ ] Filled form with valid test data
- [ ] Uploaded Aadhar Card, Passport Photo, Signature
- [ ] Clicked Submit and watched console logs
- [ ] Identified where validation fails (if it does)
- [ ] Checked PHP error log at `C:\xampp\apache\logs\error.log`
- [ ] Verified database entry was created
- [ ] Checked admin panel for pending student

## Need More Help?

If form still doesn't work after following all steps:

1. **Copy the ENTIRE console log** from browser
2. **Copy the relevant PHP error log entries** (last 50 lines)
3. **Take a screenshot** of the form with filled data
4. Share all three with the developer

## Files Modified
- `student/register.php` - Added extensive console logging
- `submit_registration.php` - Has error_log debugging
- `test_form_submission.php` - New diagnostic script
