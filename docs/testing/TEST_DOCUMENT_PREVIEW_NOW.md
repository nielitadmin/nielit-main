# 🧪 Test Document Preview - Quick Guide

## ⚡ Quick Test (5 minutes)

### Step 1: Test Registration Form
```
1. Open: http://your-site.com/student/register.php?course=DBC-2026
2. Fill basic info (name, DOB, etc.)
3. Navigate to "Academic & Documents" section
4. Upload passport photo
   ✅ VERIFY: Image preview appears immediately
   ✅ VERIFY: File name and size shown
   ✅ VERIFY: Remove button visible
5. Upload signature
   ✅ VERIFY: Signature preview appears
   ✅ VERIFY: Can see actual signature image
6. Submit form
   ✅ VERIFY: Success message with student ID
```

### Step 2: Test Admin Panel
```
1. Login to admin panel
2. Go to: Students → View All Students
3. Click on newly registered student
   ✅ VERIFY: Passport photo displays
   ✅ VERIFY: Signature displays
   ✅ VERIFY: No 404 errors in console
4. Click "Edit Student"
   ✅ VERIFY: Images show in edit form
   ✅ VERIFY: Download buttons work
```

---

## 📋 Detailed Test Cases

### Test Case 1: Passport Photo Preview
**Steps:**
1. Go to registration form
2. Click "Choose File" for Passport Photo
3. Select a JPG image (< 5MB)

**Expected Results:**
- ✅ Image preview appears within 1 second
- ✅ Preview shows actual photo (200x200px max)
- ✅ File name displayed below preview
- ✅ File size displayed (e.g., "125.5 KB")
- ✅ Remove button visible and functional
- ✅ Green checkmark icon shown

**Screenshot Location:**
```
┌─────────────────┐
│                 │
│  [Photo Image]  │
│   200x200px     │
│                 │
└─────────────────┘
✓ photo.jpg
  125.5 KB
  [Remove]
```

---

### Test Case 2: Signature Preview
**Steps:**
1. Go to registration form
2. Click "Choose File" for Signature
3. Select a PNG image (< 2MB)

**Expected Results:**
- ✅ Signature preview appears immediately
- ✅ Can verify signature is clear
- ✅ File details shown
- ✅ Remove button works

---

### Test Case 3: PDF Document Upload
**Steps:**
1. Go to registration form
2. Upload Aadhar card as PDF
3. Check preview

**Expected Results:**
- ✅ PDF icon shown (not image preview)
- ✅ File name and size displayed
- ✅ Remove button works
- ✅ No error messages

---

### Test Case 4: File Size Validation
**Steps:**
1. Try to upload 10MB photo
2. Check error handling

**Expected Results:**
- ✅ Error message shown
- ✅ File cleared automatically
- ✅ Toast notification appears
- ✅ Can select different file

---

### Test Case 5: Invalid File Type
**Steps:**
1. Try to upload .exe or .txt file
2. Check validation

**Expected Results:**
- ✅ Error message: "Invalid file type"
- ✅ File cleared
- ✅ Can select valid file

---

### Test Case 6: Remove and Re-upload
**Steps:**
1. Upload passport photo
2. Click Remove button
3. Upload different photo

**Expected Results:**
- ✅ First preview disappears
- ✅ File input cleared
- ✅ Can select new file
- ✅ New preview appears

---

### Test Case 7: Mobile Responsiveness
**Steps:**
1. Open registration on mobile device
2. Upload passport photo
3. Check layout

**Expected Results:**
- ✅ Preview displays correctly
- ✅ Buttons are touch-friendly
- ✅ Text is readable
- ✅ Layout doesn't break

---

### Test Case 8: Admin Panel Display
**Steps:**
1. Register new student
2. Login to admin panel
3. View student details

**Expected Results:**
- ✅ Passport photo displays
- ✅ Signature displays
- ✅ Images load without 404 errors
- ✅ Download buttons work

---

### Test Case 9: Edit Student Form
**Steps:**
1. Admin panel → Edit Student
2. Check existing images
3. Upload new image

**Expected Results:**
- ✅ Current images display
- ✅ Can upload new images
- ✅ Preview shows for new uploads
- ✅ Save works correctly

---

### Test Case 10: Database Path Verification
**Steps:**
1. Register new student
2. Check database

**SQL Query:**
```sql
SELECT student_id, passport_photo, signature 
FROM students 
ORDER BY id DESC 
LIMIT 1;
```

**Expected Results:**
```
student_id: DBC-2026/001
passport_photo: student/uploads/students/DBC-2026-001_1234567890_passport.jpg
signature: student/uploads/students/DBC-2026-001_1234567891_signature.jpg
```

**Verify:**
- ✅ Paths start with `student/`
- ✅ Paths include `uploads/students/`
- ✅ Filenames include student ID
- ✅ Filenames include timestamp

---

## 🐛 Common Issues & Solutions

### Issue 1: Preview Not Showing
**Symptoms:**
- File selected but no preview appears
- Only file name shown

**Solutions:**
1. Check browser console for errors
2. Verify file is an image (JPG/PNG)
3. Check file size (< 5MB)
4. Try different browser
5. Clear browser cache

---

### Issue 2: Images Not Displaying in Admin
**Symptoms:**
- 404 errors in console
- Broken image icons

**Solutions:**
1. Check database paths:
   ```sql
   SELECT passport_photo FROM students WHERE id = X;
   ```
2. Verify path starts with `student/`
3. Check file exists:
   ```bash
   ls -la student/uploads/students/
   ```
4. Check file permissions:
   ```bash
   chmod 755 student/uploads/students/
   ```

---

### Issue 3: File Upload Fails
**Symptoms:**
- Error message on submit
- Files not saved

**Solutions:**
1. Check directory permissions:
   ```bash
   chmod 755 student/uploads/students/
   ```
2. Check disk space:
   ```bash
   df -h
   ```
3. Check PHP upload limits:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   ```

---

## 📊 Test Results Template

```
Date: _______________
Tester: _______________
Browser: _______________

[ ] Test Case 1: Passport Photo Preview
[ ] Test Case 2: Signature Preview
[ ] Test Case 3: PDF Document Upload
[ ] Test Case 4: File Size Validation
[ ] Test Case 5: Invalid File Type
[ ] Test Case 6: Remove and Re-upload
[ ] Test Case 7: Mobile Responsiveness
[ ] Test Case 8: Admin Panel Display
[ ] Test Case 9: Edit Student Form
[ ] Test Case 10: Database Path Verification

Issues Found:
_________________________________
_________________________________
_________________________________

Overall Status: [ ] PASS  [ ] FAIL
```

---

## 🎯 Success Criteria

### Must Pass (Critical)
- ✅ Passport photo preview shows
- ✅ Signature preview shows
- ✅ Images display in admin panel
- ✅ No 404 errors
- ✅ File paths correct in database

### Should Pass (Important)
- ✅ Remove button works
- ✅ File size validation works
- ✅ Mobile responsive
- ✅ Error messages clear

### Nice to Have
- ✅ Fast preview (< 1 second)
- ✅ Professional appearance
- ✅ Smooth animations

---

## 🚀 Quick Commands

### Check File Permissions
```bash
ls -la student/uploads/students/
```

### Check Database Paths
```sql
SELECT student_id, passport_photo, signature 
FROM students 
WHERE passport_photo LIKE 'student/%' 
ORDER BY id DESC 
LIMIT 5;
```

### Check Recent Uploads
```bash
ls -lt student/uploads/students/ | head -10
```

### Clear Test Data
```sql
DELETE FROM students WHERE student_id LIKE 'TEST%';
```

---

## 📞 Support

### If Tests Fail
1. Check documentation:
   - `docs/fixes/START_HERE_DOCUMENT_PREVIEW_FIX.md`
   - `docs/fixes/REGISTRATION_DOCUMENT_PREVIEW_COMPLETE.md`

2. Check browser console (F12)
3. Check server error logs
4. Verify file permissions
5. Check database paths

### Contact
- Check GitHub issues
- Review documentation
- Test on different browser

---

## ✅ Final Checklist

Before marking as complete:

- [ ] All 10 test cases pass
- [ ] No console errors
- [ ] Images display in admin panel
- [ ] Mobile works correctly
- [ ] Database paths correct
- [ ] File permissions set
- [ ] Documentation reviewed
- [ ] Stakeholders notified

---

**Status:** Ready for Testing  
**Priority:** High  
**Estimated Time:** 15-30 minutes  
**Last Updated:** 2025-03-02
