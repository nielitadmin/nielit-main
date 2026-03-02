# Missing Uploaded Files Bugfix Design

## Overview

The student document viewing system fails to display uploaded documents despite having valid file paths stored in the database. The root cause is a critical variable assignment bug in `admin/edit_student.php` where `$field = $r['path']` overwrites the field name variable instead of updating the document path variable. This causes the database UPDATE statement to receive field names (like "student/uploads/aadhar/...") instead of actual path values, resulting in physical files being saved to disk but incorrect paths stored in the database. Additionally, the student registration process in `student/submit_registration.php` has a path mismatch issue where files are saved to `student/uploads/` but paths are stored as `uploads/`, causing file_exists() checks to fail.

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - when documents are uploaded via admin edit or student registration
- **Property (P)**: The desired behavior - both physical files AND correct database paths must be saved
- **Preservation**: Existing file validation, path format conventions, and document viewing logic that must remain unchanged
- **handleCategorizedUpload**: The function in both `admin/edit_student.php` and `student/submit_registration.php` that processes document uploads
- **Variable Variable Bug**: The use of `$field = $r['path']` instead of `$$field = $r['path']` in edit_student.php, causing field name corruption
- **Path Mismatch Bug**: The discrepancy between save location (`student/uploads/`) and stored path (`uploads/`) in submit_registration.php

## Bug Details

### Fault Condition

The bug manifests when an administrator uploads documents via the edit student page OR when a student uploads documents during registration. In the admin case, the `handleCategorizedUpload` function successfully saves physical files to disk, but a variable assignment bug causes the database UPDATE to store field names instead of file paths. In the student registration case, files are saved to `student/uploads/` but paths are stored as `uploads/`, causing file_exists() checks to fail.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type DocumentUploadEvent
  OUTPUT: boolean
  
  RETURN (input.source == 'admin_edit_student' OR input.source == 'student_registration')
         AND input.file.error == UPLOAD_ERR_OK
         AND input.file.size <= maxSize
         AND input.file.type IN allowedTypes
         AND correspondingDocumentField(input.category) EXISTS
END FUNCTION
```

### Examples

**Admin Edit Bug:**
- Upload Aadhar card via edit_student.php for student "NIELIT/2026/SWA/0001"
- Expected: `aadhar_card_doc` column = "student/uploads/aadhar/NIELIT-2026-SWA-0001_1234567890_aadhar.pdf"
- Actual: `aadhar_card_doc` column = "student/uploads/aadhar/NIELIT-2026-SWA-0001_1234567890_aadhar.pdf" (field name, not path)
- Physical file: EXISTS at correct location
- Result: "No data available" displayed because database has wrong value

**Student Registration Bug:**
- Upload 10th marksheet during registration for new student
- Expected: File saved to `student/uploads/marksheets/10th/` AND path stored as `student/uploads/marksheets/10th/filename.pdf`
- Actual: File saved to `student/uploads/marksheets/10th/` BUT path stored as `uploads/marksheets/10th/filename.pdf`
- Result: file_exists(__DIR__ . '/../uploads/marksheets/10th/filename.pdf') returns FALSE

**Edge Cases:**
- Multiple documents uploaded simultaneously - all fail due to same bug
- Documents with special characters in student_id - handled correctly by str_replace() but still fail due to path bug
- Large files near size limit - validation works but storage fails due to path bug

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- File validation logic (size limits, MIME types, content scanning) must continue to work exactly as before
- Path format convention `uploads/{category}/{student_id}_{timestamp}_{filename}` must remain unchanged
- Directory creation with `mkdir($dir, 0755, true)` must continue to work
- The `file_exists()` checks in `view_student_documents.php` must continue to use the same logic
- The debug script `admin/debug_student_documents.php` must continue to accurately report file status
- All existing document categories (aadhar, caste, tenth, twelfth, graduation, other) must continue to be supported

**Scope:**
All inputs that do NOT involve document uploads should be completely unaffected by this fix. This includes:
- Text field updates (name, email, mobile, etc.)
- Education details updates
- Status changes
- Non-document form submissions

## Hypothesized Root Cause

Based on the code analysis, the root causes are:

1. **Variable Assignment Bug in edit_student.php (Line 300)**: The code uses `$field = $r['path']` instead of `$$field = $r['path']`. This overwrites the loop variable `$field` (which contains the field name like "aadhar_card_doc") with the file path string. When the database UPDATE executes, it binds the corrupted field name instead of the actual path value.

2. **Path Mismatch in submit_registration.php**: The `handleCategorizedUpload` function saves files to `__DIR__ . '/uploads/' . $subdir . '/'` (which resolves to `student/uploads/...`) but returns the path as `'uploads/'.$subdir.'/'.$filename` (missing the `student/` prefix). This causes file_exists() checks to fail because the viewing page looks for files at the wrong location.

3. **Missing Error Handling**: When the upload succeeds but the database update fails (due to the variable bug), there's no rollback mechanism to delete the orphaned physical files.

4. **Silent Failure**: The bug doesn't produce visible errors because move_uploaded_file() succeeds and the database UPDATE executes without SQL errors (it just stores wrong values).

## Correctness Properties

Property 1: Fault Condition - Document Upload Saves Both File and Correct Path

_For any_ document upload where the bug condition holds (valid file uploaded via admin edit or student registration), the fixed system SHALL save the physical file to disk AND store the correct file path in the database, enabling successful file_exists() checks and document viewing.

**Validates: Requirements 2.1, 2.2, 2.3, 2.4**

Property 2: Preservation - Non-Upload Operations Unchanged

_For any_ form submission that does NOT involve document uploads (text field updates, education details, status changes), the fixed code SHALL produce exactly the same behavior as the original code, preserving all existing functionality for non-upload operations.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File 1**: `admin/edit_student.php`

**Function**: Document upload processing loop (around line 295-305)

**Specific Changes**:
1. **Fix Variable Variable Assignment**: Change `$field = $r['path']` to `$$field = $r['path']` on line 300
   - This ensures the document path variable (e.g., `$aadhar_card_doc`) is updated, not the field name variable
   - The double dollar sign creates a variable variable, using the value of `$field` as the variable name

2. **Add Path Validation**: After successful upload, verify the path is stored correctly
   - Add assertion: `if (empty($$field)) { /* rollback */ }`
   - Ensures the variable variable assignment worked

**File 2**: `student/submit_registration.php`

**Function**: `handleCategorizedUpload` (line 39-58)

**Specific Changes**:
1. **Fix Path Prefix**: Change the return statement from `'uploads/'.$subdir.'/'.$filename` to `'student/uploads/'.$subdir.'/'.$filename`
   - This matches the actual save location with the stored path
   - Ensures file_exists() checks work correctly

2. **Standardize Path Format**: Ensure consistency between save location and returned path
   - Both should use the same base directory reference

3. **Add Path Verification**: Before returning success, verify the file exists at the returned path
   - Add check: `if (!file_exists(__DIR__ . '/../' . $path)) { /* error */ }`

**File 3**: `admin/edit_student.php` (handleCategorizedUpload function)

**Function**: `handleCategorizedUpload` (line 39-58)

**Specific Changes**:
1. **Fix Path Prefix**: Change the return statement from `'student/uploads/'.$subdir.'/'.$filename` to match the actual directory structure
   - Currently returns `'student/uploads/'` but saves to `__DIR__ . '/../student/uploads/'`
   - Need to ensure the returned path works with file_exists(__DIR__ . '/../' . $path)

2. **Add Logging**: Add error_log() statements for debugging upload failures
   - Log successful uploads with paths
   - Log failures with detailed error information

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Fault Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Write tests that upload documents via both admin edit and student registration, then check both the database values and physical file existence. Run these tests on the UNFIXED code to observe failures and understand the root cause.

**Test Cases**:
1. **Admin Edit Aadhar Upload Test**: Upload Aadhar card via edit_student.php, check database value (will show field name instead of path on unfixed code)
2. **Admin Edit Multiple Documents Test**: Upload 3 documents simultaneously, verify all database fields are corrupted (will fail on unfixed code)
3. **Student Registration 10th Marksheet Test**: Upload 10th marksheet during registration, verify file_exists() check (will fail on unfixed code)
4. **Path Mismatch Test**: Check if physical file exists but file_exists() returns false due to path mismatch (will fail on unfixed code)

**Expected Counterexamples**:
- Database columns contain field names like "aadhar_card_doc" instead of paths like "student/uploads/aadhar/..."
- Physical files exist in `student/uploads/` but file_exists() checks fail
- Document viewing page shows "No data available" despite files being on disk
- Possible causes: variable assignment bug, path prefix mismatch, missing error handling

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed function produces the expected behavior.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  result := handleDocumentUpload_fixed(input)
  ASSERT result.physicalFileExists == TRUE
  ASSERT result.databasePathCorrect == TRUE
  ASSERT file_exists(result.storedPath) == TRUE
END FOR
```

**Test Cases**:
1. Upload single document via admin edit - verify both file and path
2. Upload multiple documents via admin edit - verify all files and paths
3. Upload documents via student registration - verify file_exists() works
4. Upload documents with special characters in student_id - verify path sanitization still works
5. Upload maximum size files - verify no corruption occurs

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed function produces the same result as the original function.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT handleFormSubmission_original(input) = handleFormSubmission_fixed(input)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases that manual unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-upload inputs

**Test Plan**: Observe behavior on UNFIXED code first for non-upload operations, then write property-based tests capturing that behavior.

**Test Cases**:
1. **Text Field Update Preservation**: Update student name, email, mobile without uploading documents - verify database updates work
2. **Education Details Preservation**: Add/update education records without documents - verify no regression
3. **Status Change Preservation**: Change student status without documents - verify workflow continues
4. **Empty File Upload Preservation**: Submit form with empty file inputs - verify no errors occur

### Unit Tests

- Test variable variable assignment with mock data
- Test path prefix consistency between save and return
- Test file_exists() checks with correct paths
- Test rollback mechanism when upload succeeds but database fails
- Test error logging for debugging

### Property-Based Tests

- Generate random document uploads with various file types and sizes - verify all succeed
- Generate random student IDs with special characters - verify path sanitization works
- Generate random combinations of document categories - verify all paths are correct
- Test that all non-upload form submissions continue to work across many scenarios

### Integration Tests

- Test full admin edit flow with document uploads in each category
- Test full student registration flow with all required documents
- Test document viewing page displays all uploaded documents correctly
- Test debug script accurately reports file status after uploads
- Test that multiple admins can upload documents simultaneously without conflicts
