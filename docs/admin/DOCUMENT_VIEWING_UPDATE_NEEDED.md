# Admin Panel Document Viewing - Update Required

## Current Situation

The registration form (`student/register.php`) has been updated with a new categorized document upload system, but the admin panel (`admin/view_student_documents.php`) still uses the old document structure.

## Registration Form Document Structure (NEW)

### Mandatory Documents (4 Required)
1. **Aadhar Card** → Stored in: `aadhar_card_doc` column
2. **10th Marksheet/Certificate** → Stored in: `tenth_marksheet_doc` column
3. **Passport Photo** → Stored in: `passport_photo` column (legacy)
4. **Signature** → Stored in: `signature` column (legacy)

### Optional Documents
5. **12th Marksheet/Diploma** → Stored in: `twelfth_marksheet_doc` column
6. **Caste Certificate** → Stored in: `caste_certificate_doc` column
7. **Graduation Certificate** → Stored in: `graduation_certificate_doc` column
8. **Other Documents** → Stored in: `other_documents_doc` column
9. **Payment Receipt** → Stored in: `payment_receipt` column (legacy)

### File Upload Paths
- **Categorized documents**: `student/uploads/{category}/` 
  - Aadhar: `student/uploads/aadhar/`
  - 10th: `student/uploads/marksheets/10th/`
  - 12th: `student/uploads/marksheets/12th/`
  - Caste: `student/uploads/caste_certificates/`
  - Graduation: `student/uploads/marksheets/graduation/`
  - Other: `student/uploads/other/`
- **Legacy documents**: `student/uploads/students/`
  - Passport photo, signature, payment receipt

## Admin Panel Current Structure (OLD)

The `admin/view_student_documents.php` file currently displays:
1. **Passport Photo** → `passport_photo` column ✅ (correct)
2. **Signature** → `signature` column ✅ (correct)
3. **Educational Documents** → `documents` column ❌ (OLD - no longer used)
4. **Payment Receipt** → `payment_receipt` column ✅ (correct)

## What Needs to Be Updated

### 1. Remove Old "Educational Documents" Section
The single "Educational Documents" card that reads from the `documents` column needs to be removed since this column is no longer used.

### 2. Add New Categorized Document Cards

Replace the old "Educational Documents" card with individual cards for each new document type:

#### Identity Proof Section
- **Aadhar Card** → Read from `aadhar_card_doc` column

#### Educational Qualifications Section
- **10th Marksheet/Certificate** → Read from `tenth_marksheet_doc` column
- **12th Marksheet/Diploma Certificate** → Read from `twelfth_marksheet_doc` column (optional)
- **Graduation Certificate** → Read from `graduation_certificate_doc` column (optional)

#### Additional Documents Section
- **Caste Certificate** → Read from `caste_certificate_doc` column (optional)
- **Other Supporting Documents** → Read from `other_documents_doc` column (optional)

### 3. Update File Path Resolution

The admin panel needs to check for files in the new categorized paths:
- `../student/uploads/aadhar/` for Aadhar cards
- `../student/uploads/marksheets/10th/` for 10th marksheets
- `../student/uploads/marksheets/12th/` for 12th marksheets
- `../student/uploads/caste_certificates/` for caste certificates
- `../student/uploads/marksheets/graduation/` for graduation certificates
- `../student/uploads/other/` for other documents
- `../student/uploads/students/` for passport photo, signature, payment receipt (legacy)

### 4. Visual Organization

Group the documents in the admin panel to match the registration form structure:

```
┌─────────────────────────────────────────────────────────┐
│ Photo & Signature (Mandatory)                           │
│ - Passport Photo                                        │
│ - Signature                                             │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Identity Proof (Mandatory)                              │
│ - Aadhar Card                                           │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Educational Qualifications                              │
│ - 10th Marksheet/Certificate (Mandatory)                │
│ - 12th Marksheet/Diploma (Optional)                     │
│ - Graduation Certificate (Optional)                     │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Additional Documents (Optional)                         │
│ - Caste Certificate                                     │
│ - Other Supporting Documents                            │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Payment Information (Optional)                          │
│ - Payment Receipt                                       │
└─────────────────────────────────────────────────────────┘
```

## Database Columns Reference

### Current Columns in `students` Table
```sql
-- Legacy columns (still in use)
passport_photo VARCHAR(255)
signature VARCHAR(255)
payment_receipt VARCHAR(255)
documents TEXT  -- ❌ NO LONGER USED

-- New categorized document columns
aadhar_card_doc VARCHAR(255)
caste_certificate_doc VARCHAR(255)
tenth_marksheet_doc VARCHAR(255)
twelfth_marksheet_doc VARCHAR(255)
graduation_certificate_doc VARCHAR(255)
other_documents_doc VARCHAR(255)
```

## Implementation Priority

### High Priority (Mandatory Documents)
1. Add Aadhar Card display
2. Add 10th Marksheet display
3. Remove old "Educational Documents" section

### Medium Priority (Optional Documents)
4. Add 12th Marksheet display
5. Add Caste Certificate display
6. Add Graduation Certificate display
7. Add Other Documents display

### Low Priority (Enhancement)
8. Add visual grouping/sections
9. Add document status badges (Mandatory/Optional)
10. Add document validation status indicators

## Testing Checklist

After updating the admin panel:

- [ ] Verify Aadhar Card displays correctly
- [ ] Verify 10th Marksheet displays correctly
- [ ] Verify 12th Marksheet displays correctly (when uploaded)
- [ ] Verify Caste Certificate displays correctly (when uploaded)
- [ ] Verify Graduation Certificate displays correctly (when uploaded)
- [ ] Verify Other Documents displays correctly (when uploaded)
- [ ] Verify Passport Photo still displays correctly
- [ ] Verify Signature still displays correctly
- [ ] Verify Payment Receipt still displays correctly
- [ ] Verify file paths resolve correctly for all document types
- [ ] Verify "View Full Size" links work for all documents
- [ ] Verify "Download" links work for PDF documents
- [ ] Verify "Not Uploaded" status shows for missing optional documents
- [ ] Verify document preview images display correctly
- [ ] Test with students who have all documents uploaded
- [ ] Test with students who have only mandatory documents uploaded
- [ ] Test with students who have mixed document uploads

## Files That Need Updates

1. **admin/view_student_documents.php** - Main document viewing page
2. **admin/students.php** - May need updates if it shows document counts/status
3. **admin/edit_student.php** - May need updates if it allows document editing
4. **admin/download_student_form.php** - May need updates to include new documents in PDF

## Backward Compatibility

The system should handle both old and new document structures:
- Old students may have data in the `documents` column
- New students will have data in the categorized columns
- The admin panel should check both and display whichever is available

## Summary

The registration form now uses a modern categorized document upload system with 6 new database columns. The admin panel needs to be updated to display these new document categories instead of the old single "Educational Documents" field. This will provide better organization and clarity for administrators reviewing student applications.

---

**Status**: Documentation Complete - Ready for Implementation
**Date**: February 27, 2026
**Priority**: High - Admin panel is out of sync with registration form
