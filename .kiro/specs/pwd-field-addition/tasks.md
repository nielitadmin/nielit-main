# Tasks: Add PWD and Distinguishing Marks Fields

## Task Breakdown

### Phase 1: Database Setup
- [ ] 1. Create database migration script
  - [ ] 1.1 Write ALTER TABLE statement for pwd_status column
  - [ ] 1.2 Write ALTER TABLE statement for distinguishing_marks column
  - [ ] 1.3 Add default values and constraints
  - [ ] 1.4 Test migration on development database

### Phase 2: Registration Form Integration
- [ ] 2. Update student registration form (student/register.php)
  - [ ] 2.1 Add PWD status field to Level 1 Personal Information section
  - [ ] 2.2 Add Distinguishing Marks field to Level 1 Personal Information section
  - [ ] 2.3 Position both fields after Category field
  - [ ] 2.4 Add dropdown with Yes/No options for PWD
  - [ ] 2.5 Add text input with maxlength="255" for Distinguishing Marks
  - [ ] 2.6 Add helper text for both optional fields
  - [ ] 2.7 Apply consistent styling

- [ ] 3. Update form submission handler (submit_registration.php)
  - [ ] 3.1 Capture pwd_status from POST data
  - [ ] 3.2 Add default value handling ('No' if not provided)
  - [ ] 3.3 Capture distinguishing_marks from POST data
  - [ ] 3.4 Sanitize distinguishing_marks with htmlspecialchars()
  - [ ] 3.5 Update INSERT statement to include both fields
  - [ ] 3.6 Update bind_param type string for both fields
  - [ ] 3.7 Test registration with PWD: Yes and marks filled
  - [ ] 3.8 Test registration with PWD: No and marks empty
  - [ ] 3.9 Test XSS prevention in distinguishing_marks

### Phase 3: Admin View Integration
- [ ] 4. Update view student documents page (admin/view_student_documents.php)
  - [ ] 4.1 Add PWD status row to Personal Information table
  - [ ] 4.2 Add Distinguishing Marks to same row or adjacent row
  - [ ] 4.3 Position after Category field
  - [ ] 4.4 Add badge styling with icon for PWD
  - [ ] 4.5 Add plain text display for Distinguishing Marks
  - [ ] 4.6 Handle NULL values gracefully for both fields
  - [ ] 4.7 Test display with PWD: Yes and marks filled
  - [ ] 4.8 Test display with PWD: No and marks empty

- [ ] 5. Update students list page (admin/students.php)
  - [ ] 5.1 Add PWD filter dropdown (optional)
  - [ ] 5.2 Update query to support PWD filtering
  - [ ] 5.3 Add PWD column to table (optional)
  - [ ] 5.4 Test filtering by PWD status

### Phase 4: Admin Edit Integration
- [ ] 6. Update edit student form (admin/edit_student.php)
  - [ ] 6.1 Add PWD status field to form
  - [ ] 6.2 Add Distinguishing Marks field to form
  - [ ] 6.3 Pre-select current PWD status
  - [ ] 6.4 Pre-fill current distinguishing marks
  - [ ] 6.5 Update UPDATE query to include both fields
  - [ ] 6.6 Update bind_param for both fields
  - [ ] 6.7 Add sanitization for distinguishing_marks
  - [ ] 6.8 Test editing PWD status from No to Yes
  - [ ] 6.9 Test editing distinguishing marks
  - [ ] 6.10 Test XSS prevention in distinguishing_marks

### Phase 5: PDF Form Integration
- [ ] 7. Update PDF download form (admin/download_student_form.php)
  - [ ] 7.1 Add PWD status to Personal Information section
  - [ ] 7.2 Add Distinguishing Marks to Personal Information section
  - [ ] 7.3 Add bilingual labels (English/Hindi) for both fields
  - [ ] 7.4 Position after Category field
  - [ ] 7.5 Handle NULL values for both fields
  - [ ] 7.6 Test PDF generation with PWD: Yes and marks filled
  - [ ] 7.7 Test PDF generation with PWD: No and marks empty

### Phase 6: Admission Order Integration
- [ ] 8. Update admission order generation (batch_module/admin/generate_admission_order_ajax.php)
  - [ ] 8.1 Add PWD counting logic
  - [ ] 8.2 Count PWD students by gender
  - [ ] 8.3 Add PWD summary display section
  - [ ] 8.4 Position after category summary table
  - [ ] 8.5 Add wheelchair icon and styling
  - [ ] 8.6 Include distinguishing_marks in student data (if needed)
  - [ ] 8.7 Test with batch containing PWD students and distinguishing marks
  - [ ] 8.8 Test with batch containing no PWD students

### Phase 7: Testing & Validation
- [ ] 9. Comprehensive testing
  - [ ] 9.1 Test complete registration flow with PWD: Yes and marks filled
  - [ ] 9.2 Test complete registration flow with PWD: No and marks empty
  - [ ] 9.3 Test admin view of PWD students with distinguishing marks
  - [ ] 9.4 Test admin edit of both PWD status and distinguishing marks
  - [ ] 9.5 Test PDF generation with both fields
  - [ ] 9.6 Test admission order PWD counts
  - [ ] 9.7 Test backward compatibility (NULL values for both fields)
  - [ ] 9.8 Test filter preservation in admin views
  - [ ] 9.9 Test XSS prevention in distinguishing marks
  - [ ] 9.10 Test character limit enforcement (255 chars)

### Phase 8: Documentation
- [ ] 10. Create documentation
  - [ ] 10.1 Create deployment guide
  - [ ] 10.2 Create testing checklist
  - [ ] 10.3 Document database changes
  - [ ] 10.4 Create before/after comparison
  - [ ] 10.5 Update user guide (if exists)

## Task Dependencies

```
1 (Database) → 2 (Registration Form) → 3 (Form Submission)
                                     ↓
4 (View Documents) ← 6 (Edit Form) ← 3
                                     ↓
7 (PDF Form) ← 3
                                     ↓
8 (Admission Order) ← 3
                                     ↓
9 (Testing) ← All above
                                     ↓
10 (Documentation) ← 9
```

## Estimated Time
- Phase 1: 20 minutes
- Phase 2: 45 minutes
- Phase 3: 40 minutes
- Phase 4: 30 minutes
- Phase 5: 35 minutes
- Phase 6: 35 minutes
- Phase 7: 60 minutes
- Phase 8: 35 minutes

**Total: ~4.5 hours**

## Success Criteria
- ✅ Database columns added successfully for both fields
- ✅ Registration form captures PWD status and distinguishing marks
- ✅ Both fields saved to database correctly
- ✅ Both fields displayed in all admin views
- ✅ Both fields editable by admin
- ✅ Both fields included in PDF
- ✅ Admission orders show PWD counts
- ✅ Distinguishing marks properly sanitized (XSS prevention)
- ✅ All tests pass
- ✅ No data loss or corruption
- ✅ Backward compatibility maintained
