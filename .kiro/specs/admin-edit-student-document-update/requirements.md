# Requirements Document

## Introduction

This document specifies the requirements for updating the admin panel's edit_student.php page to support the new categorized document upload system. The registration form (student/register.php) and document viewing page (admin/view_student_documents.php) have already been updated with a categorized document structure that includes 6 new database columns for different document types. The edit_student.php page currently only supports the legacy document structure and needs to be updated to allow admins to edit and upload the new categorized documents.

## Glossary

- **Admin_Panel**: The administrative interface at admin/edit_student.php used by administrators to edit student information
- **Categorized_Document_System**: The new document upload structure that organizes documents into specific categories (Aadhar, marksheets, certificates, etc.)
- **Legacy_Documents**: The original document fields (passport_photo, signature, documents, payment_receipt)
- **Document_Category**: A specific type of document (e.g., aadhar_card_doc, tenth_marksheet_doc)
- **File_Upload_Handler**: The handleCategorizedUpload function from submit_registration.php that validates and processes document uploads
- **Document_Preview**: Visual display of currently uploaded documents with download/view options
- **Database_Column**: A field in the students table that stores the file path for a document
- **File_Storage_Path**: The directory location where uploaded documents are stored (e.g., student/uploads/aadhar/)
- **Mandatory_Document**: A document that must be uploaded (passport_photo, signature, aadhar_card_doc, tenth_marksheet_doc)
- **Optional_Document**: A document that may be uploaded but is not required (twelfth_marksheet_doc, caste_certificate_doc, graduation_certificate_doc, other_documents_doc, payment_receipt)

## Requirements

### Requirement 1: Display Current Categorized Documents

**User Story:** As an administrator, I want to see all currently uploaded categorized documents for a student, so that I know which documents exist before making changes.

#### Acceptance Criteria

1. WHEN the edit student page loads, THE Admin_Panel SHALL display preview sections for all 6 new Document_Category types
2. FOR EACH Document_Category, THE Admin_Panel SHALL show the upload status (uploaded or not uploaded)
3. WHEN a document exists for a Document_Category, THE Admin_Panel SHALL display a preview image for image files
4. WHEN a document exists for a Document_Category AND the file is a PDF, THE Admin_Panel SHALL display a PDF icon with view and download buttons
5. WHEN a document does not exist for a Document_Category, THE Admin_Panel SHALL display a "not uploaded" indicator
6. THE Admin_Panel SHALL organize document display sections to match the registration form structure (Photo & Signature, Identity Proof, Educational Qualifications, Additional Documents, Payment Information)

### Requirement 2: Add File Upload Fields for Categorized Documents

**User Story:** As an administrator, I want to upload or replace categorized documents for a student, so that I can update their document records.

#### Acceptance Criteria

1. THE Admin_Panel SHALL provide file upload input fields for aadhar_card_doc
2. THE Admin_Panel SHALL provide file upload input fields for tenth_marksheet_doc
3. THE Admin_Panel SHALL provide file upload input fields for twelfth_marksheet_doc
4. THE Admin_Panel SHALL provide file upload input fields for caste_certificate_doc
5. THE Admin_Panel SHALL provide file upload input fields for graduation_certificate_doc
6. THE Admin_Panel SHALL provide file upload input fields for other_documents_doc
7. WHEN an upload field is displayed, THE Admin_Panel SHALL show helper text indicating accepted file types and size limits
8. THE Admin_Panel SHALL mark aadhar_card_doc and tenth_marksheet_doc upload fields as mandatory
9. THE Admin_Panel SHALL mark twelfth_marksheet_doc, caste_certificate_doc, graduation_certificate_doc, and other_documents_doc upload fields as optional

### Requirement 3: Validate Uploaded Documents

**User Story:** As an administrator, I want uploaded documents to be validated, so that only valid files are accepted and stored.

#### Acceptance Criteria

1. WHEN a file is uploaded for any Document_Category, THE File_Upload_Handler SHALL validate the file type is in the allowed list (JPG, JPEG, PNG, PDF)
2. WHEN a file is uploaded for any Document_Category, THE File_Upload_Handler SHALL validate the file size does not exceed 5MB for images
3. WHEN a file is uploaded for any Document_Category, THE File_Upload_Handler SHALL validate the file size does not exceed 10MB for PDFs
4. WHEN a file fails validation, THE Admin_Panel SHALL display an error message describing the validation failure
5. WHEN a file passes validation, THE File_Upload_Handler SHALL proceed with the upload process
6. THE File_Upload_Handler SHALL check file content for malicious code (PHP tags, shell scripts)
7. IF malicious content is detected, THEN THE File_Upload_Handler SHALL reject the upload and return an error

### Requirement 4: Process Categorized Document Uploads

**User Story:** As an administrator, I want uploaded documents to be saved to the correct storage locations, so that they are organized properly in the file system.

#### Acceptance Criteria

1. WHEN aadhar_card_doc is uploaded, THE File_Upload_Handler SHALL save the file to student/uploads/aadhar/
2. WHEN tenth_marksheet_doc is uploaded, THE File_Upload_Handler SHALL save the file to student/uploads/marksheets/10th/
3. WHEN twelfth_marksheet_doc is uploaded, THE File_Upload_Handler SHALL save the file to student/uploads/marksheets/12th/
4. WHEN caste_certificate_doc is uploaded, THE File_Upload_Handler SHALL save the file to student/uploads/caste_certificates/
5. WHEN graduation_certificate_doc is uploaded, THE File_Upload_Handler SHALL save the file to student/uploads/marksheets/graduation/
6. WHEN other_documents_doc is uploaded, THE File_Upload_Handler SHALL save the file to student/uploads/other/
7. WHEN a file is saved, THE File_Upload_Handler SHALL generate a unique filename using the pattern: {student_id}_{timestamp}_{category}.{extension}
8. IF the target directory does not exist, THEN THE File_Upload_Handler SHALL create the directory with 0755 permissions
9. WHEN a file upload succeeds, THE File_Upload_Handler SHALL return the relative file path for database storage

### Requirement 5: Update Database with Document Paths

**User Story:** As an administrator, I want document file paths to be saved in the database, so that the system can retrieve and display the documents later.

#### Acceptance Criteria

1. WHEN the update form is submitted, THE Admin_Panel SHALL execute an UPDATE SQL query on the students table
2. THE UPDATE SQL query SHALL include the aadhar_card_doc Database_Column
3. THE UPDATE SQL query SHALL include the tenth_marksheet_doc Database_Column
4. THE UPDATE SQL query SHALL include the twelfth_marksheet_doc Database_Column
5. THE UPDATE SQL query SHALL include the caste_certificate_doc Database_Column
6. THE UPDATE SQL query SHALL include the graduation_certificate_doc Database_Column
7. THE UPDATE SQL query SHALL include the other_documents_doc Database_Column
8. WHEN a new document is uploaded for a Document_Category, THE Admin_Panel SHALL update the corresponding Database_Column with the new file path
9. WHEN no new document is uploaded for a Document_Category, THE Admin_Panel SHALL preserve the existing Database_Column value
10. THE UPDATE SQL query SHALL use prepared statements with parameter binding to prevent SQL injection

### Requirement 6: Maintain Legacy Document Support

**User Story:** As an administrator, I want to continue editing legacy documents, so that existing functionality is not broken.

#### Acceptance Criteria

1. THE Admin_Panel SHALL continue to support uploading passport_photo to student/uploads/students/
2. THE Admin_Panel SHALL continue to support uploading signature to student/uploads/students/
3. THE Admin_Panel SHALL continue to support uploading payment_receipt to student/uploads/students/
4. THE Admin_Panel SHALL continue to support uploading the legacy documents field to student/uploads/students/
5. THE UPDATE SQL query SHALL include all Legacy_Documents Database_Column fields
6. WHEN the form is submitted, THE Admin_Panel SHALL process both Legacy_Documents and categorized documents

### Requirement 7: Organize Document Upload Interface

**User Story:** As an administrator, I want the document upload interface to be well-organized, so that I can easily find and upload the correct document type.

#### Acceptance Criteria

1. THE Admin_Panel SHALL group passport_photo and signature in a "Photo & Signature" section
2. THE Admin_Panel SHALL group aadhar_card_doc in an "Identity Proof" section
3. THE Admin_Panel SHALL group tenth_marksheet_doc, twelfth_marksheet_doc, and graduation_certificate_doc in an "Educational Qualifications" section
4. THE Admin_Panel SHALL group caste_certificate_doc and other_documents_doc in an "Additional Documents" section
5. THE Admin_Panel SHALL group payment_receipt in a "Payment Information" section
6. EACH section SHALL have a clear heading with an icon
7. MANDATORY sections SHALL display a visual indicator (badge or label) showing they are required
8. OPTIONAL sections SHALL display a visual indicator showing they are optional

### Requirement 8: Handle File Upload Errors

**User Story:** As an administrator, I want to see clear error messages when document uploads fail, so that I can correct the issue and try again.

#### Acceptance Criteria

1. WHEN any document upload fails, THE Admin_Panel SHALL display an error message to the administrator
2. THE error message SHALL specify which Document_Category failed to upload
3. THE error message SHALL describe the reason for the failure (file too large, invalid type, etc.)
4. WHEN multiple documents fail to upload, THE Admin_Panel SHALL display all error messages
5. WHEN a document upload fails, THE Admin_Panel SHALL NOT update the database record
6. WHEN a document upload fails, THE Admin_Panel SHALL preserve all existing document paths in the database
7. IF any uploaded files were saved before an error occurred, THEN THE Admin_Panel SHALL delete those files to maintain consistency

### Requirement 9: Preserve Existing Documents

**User Story:** As an administrator, I want existing documents to remain unchanged when I don't upload new ones, so that I can update only specific documents without affecting others.

#### Acceptance Criteria

1. WHEN the form is submitted WITHOUT a new file for a Document_Category, THE Admin_Panel SHALL retain the existing file path in the Database_Column
2. WHEN the form is submitted WITH a new file for a Document_Category, THE Admin_Panel SHALL replace the Database_Column value with the new file path
3. THE Admin_Panel SHALL NOT delete existing document files when new files are uploaded
4. WHEN retrieving student data for editing, THE Admin_Panel SHALL load all existing document paths from the database
5. THE Document_Preview SHALL display the current document for each Document_Category based on the database values

### Requirement 10: Reuse Existing Upload Logic

**User Story:** As a developer, I want to reuse the validated upload logic from submit_registration.php, so that document handling is consistent across the system.

#### Acceptance Criteria

1. THE Admin_Panel SHALL use the validateUploadedDocument function from submit_registration.php for file validation
2. THE Admin_Panel SHALL use the handleCategorizedUpload function from submit_registration.php for processing categorized documents
3. THE Admin_Panel SHALL use the same file type validation rules as submit_registration.php
4. THE Admin_Panel SHALL use the same file size limits as submit_registration.php
5. THE Admin_Panel SHALL use the same File_Storage_Path structure as submit_registration.php
6. THE Admin_Panel SHALL use the same filename generation pattern as submit_registration.php
