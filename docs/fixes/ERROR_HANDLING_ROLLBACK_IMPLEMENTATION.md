# Error Handling and Rollback Mechanism Implementation

## Overview

This document describes the error handling and rollback mechanism implemented to prevent orphaned files when database operations fail during document uploads.

## Problem Addressed

**Bug Condition**: When file uploads succeed but database operations fail, physical files remain on disk without corresponding database entries, creating orphaned files.

**Requirements**: 1.5, 2.5

## Implementation Details

### 1. Admin Edit Student (`admin/edit_student.php`)

#### Success Path
- Logs successful database updates with student ID
- Logs which document fields were updated
- Provides clear success message to user

#### Failure Path (Database Update Fails)
When `$stmt->execute()` fails:

1. **Error Logging**
   ```php
   error_log("Database update failed for student $student_id: " . $conn->error);
   ```

2. **Rollback Mechanism**
   - Iterates through all successfully uploaded documents in `$uploadedDocs`
   - Deletes each physical file from disk
   - Logs each deletion attempt (success or failure)
   ```php
   foreach ($uploadedDocs as $field => $path) {
       $abs = __DIR__ . '/../' . $path;
       if (!empty($path) && file_exists($abs)) {
           if (unlink($abs)) {
               error_log("Rollback: Deleted orphaned file $path");
           } else {
               error_log("Rollback: Failed to delete orphaned file $path");
           }
       }
   }
   ```

3. **User-Friendly Error Message**
   ```php
   $_SESSION['message'] = "Database update failed: " . htmlspecialchars($conn->error) . 
                          ". Any uploaded files have been removed.";
   $_SESSION['message_type'] = "danger";
   ```

### 2. Student Registration (`student/submit_registration.php`)

#### Success Path
- Logs successful insert with student ID
- Logs all uploaded file paths (passport, signature, categorized documents)
- Sends confirmation email

#### Failure Path (Database Insert Fails)
When `$stmt->execute()` fails:

1. **Error Logging**
   ```php
   error_log("INSERT FAILED for student $student_id: " . $stmt->error . " (errno=" . $stmt->errno . ")");
   error_log("Rolling back all uploaded files for student $student_id due to database failure");
   ```

2. **Rollback Mechanism**
   - Collects all uploaded files (passport, signature, payment receipt, categorized documents)
   - Deletes each physical file from disk
   - Logs each deletion attempt
   ```php
   $allUploadedFiles = array_merge(
       array_filter([$passport_photo_path, $signature_path, $payment_receipt_path]),
       array_values($uploadedDocs)
   );
   
   foreach ($allUploadedFiles as $path) {
       $abs = __DIR__ . '/' . $path;
       if (!empty($path) && file_exists($abs)) {
           if (unlink($abs)) {
               error_log("Rollback: Deleted orphaned file $path");
           } else {
               error_log("Rollback: Failed to delete orphaned file $path");
           }
       }
   }
   ```

3. **User-Friendly Error Message**
   ```php
   $_SESSION['error'] = "Registration failed due to database error. Please try again. " .
                        "If the problem persists, contact support.";
   ```

## Logging Strategy

### Success Logging
- **Admin Edit**: Logs student ID and updated document fields
- **Registration**: Logs student ID, passport path, signature path, and categorized document fields

### Failure Logging
- **Database Errors**: Logs full error message and error number
- **Rollback Actions**: Logs each file deletion attempt with path
- **Rollback Failures**: Logs if file deletion fails (for debugging)

### Example Log Output

**Successful Upload:**
```
Student database update successful for student_id: NIELIT/2026/SWA/0001
Documents updated for student NIELIT/2026/SWA/0001: aadhar_card_doc, tenth_marksheet_doc
```

**Failed Upload with Rollback:**
```
Database update failed for student NIELIT/2026/SWA/0001: Duplicate entry 'test@example.com' for key 'email'
Rolling back uploaded documents for student NIELIT/2026/SWA/0001 due to database failure
Rollback: Deleted orphaned file student/uploads/aadhar/NIELIT-2026-SWA-0001_1234567890_aadhar.pdf
Rollback: Deleted orphaned file student/uploads/marksheets/10th/NIELIT-2026-SWA-0001_1234567891_tenth.pdf
```

## Error Messages

### Admin Interface
- **Success**: "Student information updated successfully!"
- **Upload Validation Failure**: "Document upload errors: [detailed list]"
- **Database Failure**: "Database update failed: [error details]. Any uploaded files have been removed."

### Student Registration
- **Success**: "Registration successful! Student ID: [ID], Password: [password]..."
- **Upload Validation Failure**: "Document upload errors: [detailed list]"
- **Database Failure**: "Registration failed due to database error. Please try again. If the problem persists, contact support."

## Preservation of Existing Behavior

The implementation preserves all existing error handling:
- File size validation errors
- File type validation errors
- Upload validation errors
- Required field validation
- All existing success paths

## Testing

Run the verification test:
```bash
php tests/test_error_handling_rollback.php
```

This test verifies:
1. Rollback mechanism exists in both files
2. Error logging is comprehensive
3. Success logging includes file paths
4. User-friendly error messages are present
5. Existing error handling is preserved

## Benefits

1. **No Orphaned Files**: Physical files are automatically deleted when database operations fail
2. **Clear Error Messages**: Users receive actionable error messages without technical jargon
3. **Debugging Support**: Comprehensive logging helps administrators diagnose issues
4. **Data Consistency**: Database and filesystem remain synchronized
5. **User Experience**: Failed operations are handled gracefully with clear feedback

## Requirements Satisfied

- **Requirement 1.5**: File upload failures now provide error messages and logging
- **Requirement 2.5**: Clear error messages and detailed logs for debugging
- **Bug Condition**: Orphaned files are prevented through automatic rollback
- **Preservation**: Existing error handling for validation failures remains unchanged
