# Bugfix Requirements Document

## Introduction

The student document viewing system fails to display uploaded documents despite having valid file paths stored in the database. The root cause is that physical files are not being saved to disk during the upload process, resulting in "No data available" being displayed on the admin document viewing page (`admin/view_student_documents.php`). This affects both student registration uploads and admin edit operations, preventing administrators from viewing any student documents.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a student uploads documents during registration THEN the system stores file paths in the database but the physical files are not saved to disk

1.2 WHEN an administrator uploads documents via the edit student page THEN the system stores file paths in the database but the physical files are not saved to disk

1.3 WHEN the admin views student documents and the physical files don't exist THEN the system displays "No data available" for all documents

1.4 WHEN the upload process attempts to save files to non-existent directories THEN the system fails silently without creating the required directory structure

1.5 WHEN file upload failures occur THEN the system does not provide error messages or logging to indicate the failure

### Expected Behavior (Correct)

2.1 WHEN a student uploads documents during registration THEN the system SHALL save both the file path to the database AND the physical file to disk

2.2 WHEN an administrator uploads documents via the edit student page THEN the system SHALL save both the file path to the database AND the physical file to disk

2.3 WHEN the admin views student documents and the physical files exist THEN the system SHALL display the uploaded documents correctly

2.4 WHEN the upload process attempts to save files THEN the system SHALL ensure the target directory exists with proper permissions (creating it if necessary)

2.5 WHEN file upload failures occur THEN the system SHALL provide clear error messages and log the failure for debugging

### Unchanged Behavior (Regression Prevention)

3.1 WHEN the database stores file paths THEN the system SHALL CONTINUE TO use the same path format: `uploads/{category}/{student_id}_{timestamp}_{filename}`

3.2 WHEN the admin views student documents with valid files THEN the system SHALL CONTINUE TO use `file_exists()` checks before displaying documents

3.3 WHEN file paths are stored in the database THEN the system SHALL CONTINUE TO store them in the appropriate document columns (passport_path, aadhar_path, etc.)

3.4 WHEN the debug script (`admin/debug_student_documents.php`) is run THEN the system SHALL CONTINUE TO accurately report the status of database entries vs physical files

3.5 WHEN documents are uploaded THEN the system SHALL CONTINUE TO support all existing document categories (students, aadhar, marksheets/10th, marksheets/12th, etc.)
