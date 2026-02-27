# ✅ QR Code System - Testing Checklist

## Pre-Deployment Testing Guide

Use this checklist to verify the QR code system is working correctly before going live.

---

## 🗄️ Database Setup

### Step 1: Run SQL Update

- [ ] Open phpMyAdmin or MySQL command line
- [ ] Select `nielit_bhubaneswar` database
- [ ] Run `database_qr_system_update.sql`
- [ ] Verify no errors in execution
- [ ] Check columns were added:
  ```sql
  DESCRIBE courses;
  ```
- [ ] Confirm you see:
  - `qr_code_path` VARCHAR(255)
  - `qr_generated_at` DATETIME

**Expected Result:** ✅ Columns added successfully

---

## 📁 Directory Setup

### Step 2: Verify QR Codes Directory

- [ ] Navigate to `assets/qr_codes/` folder
- [ ] Check directory exists
- [ ] Verify write permissions (777 or 755)
- [ ] Test by creating a test file:
  ```bash
  touch assets/qr_codes/test.txt
  ```
- [ ] Delete test file if successful

**Expected Result:** ✅ Directory is writable

---

## 🔧 Library Verification

### Step 3: Test phpqrcode Library

- [ ] Open browser
- [ ] Go to: `http://localhost/public_html/test_qrcode.php`
- [ ] Check for success message
- [ ] Verify QR code image displays
- [ ] Scan QR code with phone
- [ ] Confirm link opens correctly

**Expected Result:** ✅ QR code generates and scans successfully

---

## 👨‍💼 Admin Panel Testing

### Step 4: Login to Admin Panel

- [ ] Go to: `http://localhost/public_html/admin/login.php`
- [ ] Enter admin credentials
- [ ] Verify successful login
- [ ] Check dashboard loads

**Expected Result:** ✅ Admin login successful

### Step 5: Access Course Management

- [ ] Click "Manage Courses" in sidebar
- [ ] Verify page loads without errors
- [ ] Check courses table displays
- [ ] Confirm "QR Code" column is visible
- [ ] Look for yellow "Generate" buttons

**Expected Result:** ✅ Course management page loads with QR column

---

## 🎨 QR Code Generation Testing

### Step 6: Generate First QR Code

- [ ] Find a course without QR code (yellow button)
- [ ] Click "Generate" button
- [ ] Watch for loading spinner
- [ ] Wait for success message
- [ ] Page should reload automatically
- [ ] Verify button changed to green "View" and blue "Download"

**Expected Result:** ✅ QR code generated successfully

**If Failed:**
- Check browser console for errors
- Verify `admin/generate_qr.php` exists
- Check file permissions on `assets/qr_codes/`
- Review PHP error logs

### Step 7: View QR Code

- [ ] Click green "View" button
- [ ] Modal popup should open
- [ ] Verify QR code image displays
- [ ] Check course name in modal title
- [ ] Confirm "Download" button is present
- [ ] Confirm "Regenerate" button is present

**Expected Result:** ✅ QR code displays in modal

### Step 8: Download QR Code

**Method 1: Quick Download**
- [ ] Click blue "Download" button in table
- [ ] File should download immediately
- [ ] Check Downloads folder
- [ ] Verify filename format: `qr_[CODE]_[ID].png`
- [ ] Open PNG file
- [ ] Confirm QR code is visible

**Method 2: Modal Download**
- [ ] Click green "View" button
- [ ] In modal, click "Download QR Code"
- [ ] File should download
- [ ] Verify same file as Method 1

**Expected Result:** ✅ QR code downloads successfully

---

## 📱 QR Code Scanning Testing

### Step 9: Test QR Code Scanning

- [ ] Open downloaded QR code on computer screen
- [ ] Open phone camera app
- [ ] Point camera at QR code
- [ ] Wait for link notification
- [ ] Tap notification
- [ ] Browser should open registration page
- [ ] Verify course is pre-selected
- [ ] Check all form fields are visible

**Expected Result:** ✅ QR code scans and opens registration page

**Test on Multiple Devices:**
- [ ] iPhone (iOS)
- [ ] Android phone
- [ ] Tablet
- [ ] Different QR scanner apps

---

## 🔄 Regeneration Testing

### Step 10: Test QR Code Regeneration

- [ ] Click green "View" button
- [ ] In modal, click yellow "Regenerate" button
- [ ] Confirm regeneration in popup
- [ ] Wait for process to complete
- [ ] Page should reload
- [ ] Download new QR code
- [ ] Compare with old QR code (should be different image)
- [ ] Scan new QR code
- [ ] Verify link still works (same URL)

**Expected Result:** ✅ QR code regenerates successfully

---

## 🔗 Registration Link Testing

### Step 11: Test Copy Link Function

- [ ] Find "Registration Link" column
- [ ] Click "Copy" button (📋 icon)
- [ ] Button should show checkmark briefly
- [ ] Open new browser tab
- [ ] Paste link in address bar
- [ ] Press Enter
- [ ] Registration page should open
- [ ] Verify course is pre-selected

**Expected Result:** ✅ Link copies and works correctly

### Step 12: Test Open Link Function

- [ ] Click "Open" button (🔗 icon)
- [ ] New tab should open
- [ ] Registration page should load
- [ ] Course should be pre-selected
- [ ] All form sections should be visible

**Expected Result:** ✅ Link opens in new tab successfully

---

## 📝 Registration Form Testing

### Step 13: Test Registration Page

- [ ] Open registration link
- [ ] Verify all 8 sections are visible:
  1. Course Selection
  2. Personal Information
  3. Contact Information
  4. Additional Details
  5. Address Details
  6. Academic Details
  7. Payment Details
  8. Document Upload
- [ ] Check course is pre-selected
- [ ] Test form validation (try submitting empty)
- [ ] Fill in all required fields
- [ ] Test file uploads
- [ ] Submit form (if submission handler exists)

**Expected Result:** ✅ Registration form works correctly

---

## 🔍 Error Handling Testing

### Step 14: Test Error Scenarios

**Test 1: Generate QR for Invalid Course**
- [ ] Manually call: `admin/generate_qr.php?course_id=99999`
- [ ] Should return error JSON
- [ ] Error message should be clear

**Test 2: Generate QR Without Login**
- [ ] Logout from admin panel
- [ ] Try to access `admin/generate_qr.php`
- [ ] Should return unauthorized error

**Test 3: View Non-Existent QR Code**
- [ ] Delete a QR code file manually
- [ ] Try to view it in admin panel
- [ ] Should handle gracefully

**Expected Result:** ✅ Errors handled properly

---

## 🎯 Bulk Operations Testing

### Step 15: Generate Multiple QR Codes

- [ ] Go through all courses
- [ ] Generate QR code for each course without one
- [ ] Verify all generate successfully
- [ ] Download all QR codes
- [ ] Organize in a folder
- [ ] Test scanning random QR codes

**Expected Result:** ✅ All QR codes generate successfully

---

## 📊 Database Verification

### Step 16: Check Database Updates

Run these SQL queries:

```sql
-- Check QR codes were saved
SELECT id, course_name, course_code, qr_code_path, qr_generated_at
FROM courses
WHERE qr_code_path IS NOT NULL;

-- Count courses with QR codes
SELECT COUNT(*) as total_qr_codes
FROM courses
WHERE qr_code_path IS NOT NULL;

-- Check registration links
SELECT id, course_name, registration_link
FROM courses
WHERE registration_link IS NOT NULL;
```

- [ ] Verify QR paths are correct
- [ ] Check timestamps are recent
- [ ] Confirm registration links are valid

**Expected Result:** ✅ Database updated correctly

---

## 🖼️ File System Verification

### Step 17: Check Generated Files

- [ ] Navigate to `assets/qr_codes/` folder
- [ ] List all PNG files
- [ ] Verify file naming convention: `qr_[CODE]_[ID].png`
- [ ] Check file sizes (should be 2-5 KB)
- [ ] Open random files to verify they're valid PNGs
- [ ] Confirm no corrupted files

**Expected Result:** ✅ All QR code files are valid

---

## 🔒 Security Testing

### Step 18: Test Security Measures

**Test 1: Session Validation**
- [ ] Logout from admin panel
- [ ] Try to access `admin/generate_qr.php` directly
- [ ] Should be denied

**Test 2: SQL Injection**
- [ ] Try: `admin/generate_qr.php?course_id=1' OR '1'='1`
- [ ] Should be handled safely

**Test 3: XSS Prevention**
- [ ] Create course with name: `<script>alert('XSS')</script>`
- [ ] Generate QR code
- [ ] View in admin panel
- [ ] Script should not execute

**Expected Result:** ✅ Security measures working

---

## 📱 Mobile Responsiveness Testing

### Step 19: Test on Mobile Devices

**Admin Panel:**
- [ ] Open admin panel on mobile
- [ ] Navigate to Manage Courses
- [ ] Verify table is scrollable
- [ ] Test QR generation on mobile
- [ ] Check modal displays correctly

**Registration Page:**
- [ ] Open registration link on mobile
- [ ] Verify all sections are readable
- [ ] Test form inputs on mobile
- [ ] Check file upload works
- [ ] Test form submission

**Expected Result:** ✅ Mobile-friendly interface

---

## 🌐 Browser Compatibility Testing

### Step 20: Test on Different Browsers

Test on:
- [ ] Google Chrome
- [ ] Mozilla Firefox
- [ ] Microsoft Edge
- [ ] Safari (if available)

For each browser:
- [ ] Login to admin panel
- [ ] Generate QR code
- [ ] View QR code modal
- [ ] Download QR code
- [ ] Copy registration link
- [ ] Open registration page

**Expected Result:** ✅ Works on all browsers

---

## 📈 Performance Testing

### Step 21: Test Performance

**Generation Speed:**
- [ ] Time QR code generation (should be < 2 seconds)
- [ ] Generate multiple QR codes in succession
- [ ] Check server doesn't slow down

**Page Load Speed:**
- [ ] Measure course management page load time
- [ ] Check modal opens quickly
- [ ] Verify downloads start immediately

**Expected Result:** ✅ Acceptable performance

---

## 📋 Final Verification

### Step 22: Complete System Check

- [ ] All database columns added
- [ ] QR codes directory is writable
- [ ] phpqrcode library works
- [ ] Admin can login
- [ ] Course management page loads
- [ ] QR codes generate successfully
- [ ] QR codes can be viewed
- [ ] QR codes can be downloaded
- [ ] QR codes scan correctly
- [ ] Registration links work
- [ ] Registration page loads
- [ ] Form validation works
- [ ] Error handling works
- [ ] Security measures in place
- [ ] Mobile-friendly
- [ ] Browser-compatible
- [ ] Performance acceptable

**Expected Result:** ✅ All checks passed

---

## 🎉 Sign-Off

### Testing Completed By:

**Name:** _________________
**Date:** _________________
**Time:** _________________

### Test Results:

- [ ] All tests passed
- [ ] Some tests failed (list below)
- [ ] Ready for production
- [ ] Needs fixes before deployment

### Issues Found:

1. _________________________________
2. _________________________________
3. _________________________________

### Notes:

_________________________________
_________________________________
_________________________________

---

## 📞 Support

If any tests fail:

1. Check documentation files
2. Review error logs
3. Verify file permissions
4. Check database connection
5. Contact development team

**Documentation Files:**
- `QR_CODE_SYSTEM_READY.md`
- `QR_CODE_INTEGRATION_COMPLETE.md`
- `ADMIN_QR_CODE_GUIDE.md`
- `QR_SYSTEM_IMPLEMENTATION_SUMMARY.md`

---

**Checklist Version:** 1.0.0
**Last Updated:** February 11, 2026
**For:** NIELIT Bhubaneswar QR Code System
