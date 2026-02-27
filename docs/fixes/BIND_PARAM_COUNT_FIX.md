# Bind Param Count Fix - Edit Student

## Error

```
Warning: mysqli_stmt::bind_param(): Number of elements in type definition string doesn't match number of bind variables in C:\xampp\htdocs\public_html\admin\edit_student.php on line 343
```

## Root Cause

The `bind_param` call had **38 "s"** characters in the type string, but only **37 variables** were being bound.

## Parameter Count Breakdown

### UPDATE SQL Statement (37 placeholders):

1. **Personal Info (7)**: name, father_name, mother_name, dob, age, mobile, email
2. **Course Info (8)**: course, status, address, city, state, pincode, aadhar, apaar_id
3. **Additional Personal (8)**: gender, religion, marital_status, category, pwd_status, distinguishing_marks, position, nationality
4. **Institution Info (3)**: college_name, utr_number, training_center
5. **Legacy Documents (4)**: passport_photo, signature, documents, payment_receipt
6. **Educational Docs (3)**: aadhar_card_doc, tenth_marksheet_doc, twelfth_marksheet_doc
7. **Additional Docs (3)**: caste_certificate_doc, graduation_certificate_doc, other_documents_doc
8. **WHERE Clause (1)**: student_id

**Total: 37 parameters**

## Fix Applied

Changed the bind_param type string from:
```php
"ssssssssssssssssssssssssssssssssssssss"  // 38 s's - WRONG
```

To:
```php
"sssssssssssssssssssssssssssssssssssss"   // 37 s's - CORRECT
```

## Verification

- Type string length: 37 characters
- Variable count: 37 variables
- Match: ✅ CORRECT

## Testing

The edit student page should now work without errors when updating student information.

**Test steps:**
1. Go to admin/students.php
2. Click "Edit" on any student
3. Make changes to any field
4. Click "Update Student"
5. Verify the update succeeds without errors

---

**Status**: ✅ FIXED
**Date**: February 27, 2026
**File**: admin/edit_student.php
**Line**: 343
