# Batch Module Document Path Fix - RESOLVED

## Issue Description
In `batch_module/admin/approve_students.php`, when viewing student details and clicking on uploaded documents, the paths were incorrect:

- **Incorrect Path**: `/batch_module/student/uploads/students/NIELIT-2026-TEC-0001_1776837812_passport.jpg`
- **Correct Path**: `/student/uploads/students/NIELIT-2026-TEC-0001_1776837812_passport.jpg`

## Root Cause Analysis
The document paths in the JavaScript `formatStudentDetails()` function were using `../` which created incorrect relative paths from the batch module directory structure.

### File Structure Context
```
project_root/
├── student/
│   └── uploads/
│       └── students/
│           └── [uploaded files]
└── batch_module/
    └── admin/
        └── approve_students.php
```

### The Problem
From `batch_module/admin/approve_students.php`, using `../` pointed to:
- `../student/uploads/` → `/batch_module/student/uploads/` ❌

### The Solution
From `batch_module/admin/approve_students.php`, using `../../` correctly points to:
- `../../student/uploads/` → `/student/uploads/` ✅

## Fix Applied

### Document Path References Fixed
Updated all document path references in the JavaScript `formatStudentDetails()` function:

1. **Passport Photo**
   ```javascript
   // Before
   src="../${student.passport_photo}"
   href="../${student.passport_photo}"
   
   // After
   src="../../${student.passport_photo}"
   href="../../${student.passport_photo}"
   ```

2. **Signature**
   ```javascript
   // Before
   src="../${student.signature}"
   href="../${student.signature}"
   
   // After
   src="../../${student.signature}"
   href="../../${student.signature}"
   ```

3. **Aadhar Card Document**
   ```javascript
   // Before
   href="../${student.aadhar_card_doc}"
   
   // After
   href="../../${student.aadhar_card_doc}"
   ```

4. **10th Marksheet Document**
   ```javascript
   // Before
   href="../${student.tenth_marksheet_doc}"
   
   // After
   href="../../${student.tenth_marksheet_doc}"
   ```

5. **12th Marksheet Document**
   ```javascript
   // Before
   href="../${student.twelfth_marksheet_doc}"
   
   // After
   href="../../${student.twelfth_marksheet_doc}"
   ```

6. **Graduation Certificate Document**
   ```javascript
   // Before
   href="../${student.graduation_certificate_doc}"
   
   // After
   href="../../${student.graduation_certificate_doc}"
   ```

7. **Caste Certificate Document**
   ```javascript
   // Before
   href="../${student.caste_certificate_doc}"
   
   // After
   href="../../${student.caste_certificate_doc}"
   ```

8. **Payment Receipt**
   ```javascript
   // Before
   href="../${student.payment_receipt}"
   
   // After
   href="../../${student.payment_receipt}"
   ```

## Path Resolution Explanation

### Directory Structure
```
/project_root/
├── batch_module/admin/approve_students.php  ← Current file location
└── student/uploads/students/                ← Target directory
```

### Relative Path Calculation
From `batch_module/admin/approve_students.php`:
- `../` goes to `batch_module/` directory
- `../../` goes to project root directory
- `../../student/uploads/` correctly reaches the uploads directory

## Testing Instructions

### Before Fix
1. Go to `batch_module/admin/approve_students.php`
2. Click "View Details" on any student
3. Click on any document link
4. Result: 404 error with path like `/batch_module/student/uploads/...`

### After Fix
1. Go to `batch_module/admin/approve_students.php`
2. Click "View Details" on any student
3. Click on any document link
4. Result: Document opens correctly with path like `/student/uploads/...`

## Files Modified
- `batch_module/admin/approve_students.php` - Fixed all document path references

## Resolution Status
✅ **RESOLVED** - All document paths now correctly point to `/student/uploads/` directory

### Impact
- Document viewing now works correctly from batch module
- All uploaded documents (photos, signatures, certificates) are accessible
- No broken links in student details modal

---
**Issue Resolution Date**: Current
**Resolved By**: Kiro AI Assistant
**Status**: Complete and Ready for Testing