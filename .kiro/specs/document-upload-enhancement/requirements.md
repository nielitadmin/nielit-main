# Requirements Document

## Introduction

This document specifies the requirements for enhancing the student registration form's document upload system. The current system uses a single "documents" field for all file uploads, which lacks organization and clarity. The enhancement will restructure the document upload section into clearly labeled, organized categories that are easy to understand for students, with proper file format validation and backward compatibility with existing records.

## Glossary

- **System**: The student registration and management system
- **Document_Upload_Module**: The component responsible for handling document uploads during registration
- **Validation_Service**: The component that validates file types and sizes
- **Database_Schema**: The structure of the students table and related document storage
- **Admin_Interface**: The administrative pages for viewing and managing student documents
- **Student_Registration_Form**: The web form at student/register.php where students submit their information
- **Legacy_Records**: Existing student records with documents stored in the old single-field format

## Requirements

### Requirement 1: Document Category Structure

**User Story:** As a student, I want to see clearly labeled document upload fields organized by category, so that I know exactly which documents to upload for each requirement.

#### Acceptance Criteria

1. THE Document_Upload_Module SHALL provide separate upload fields for Aadhar Card, Caste Certificate, 10th Marksheet/Certificate, 12th Marksheet/Diploma Certificate, Graduation Certificate, and Other Documents
2. WHEN displaying upload fields, THE Student_Registration_Form SHALL group identity documents (Aadhar) separately from educational documents (10th, 12th, Graduation) and supporting documents (Caste Certificate, Other Documents)
3. THE Student_Registration_Form SHALL display visual indicators distinguishing mandatory fields (Aadhar Card, 10th Marksheet) from optional fields (Caste Certificate, Graduation Certificate, Other Documents)
4. WHEN a student views the registration form, THE System SHALL display file format requirements (JPG/JPEG/PDF) near each upload field
5. THE Student_Registration_Form SHALL display clear labels for each document category that match common terminology used in educational institutions

### Requirement 2: File Format Validation

**User Story:** As a system administrator, I want strict file format validation on both client and server sides, so that only valid document formats are accepted and stored.

#### Acceptance Criteria

1. WHEN a student selects a file for upload, THE Validation_Service SHALL verify the file extension is JPG, JPEG, or PDF
2. WHEN a file is uploaded, THE Validation_Service SHALL verify the MIME type matches the allowed types (image/jpeg, image/jpg, application/pdf)
3. IF an invalid file format is selected, THEN THE System SHALL prevent form submission and display an error message specifying the allowed formats
4. THE Validation_Service SHALL perform client-side validation before form submission to provide immediate feedback
5. THE Validation_Service SHALL perform server-side validation during form processing to ensure security
6. WHEN validation fails, THE System SHALL display error messages that clearly identify which document field has the invalid format

### Requirement 3: Database Schema Enhancement

**User Story:** As a database administrator, I want a structured database schema for categorized documents, so that document storage is organized and queryable.

#### Acceptance Criteria

1. THE Database_Schema SHALL include separate columns for aadhar_card_doc, caste_certificate_doc, tenth_marksheet_doc, twelfth_marksheet_doc, graduation_certificate_doc, and other_documents_doc
2. WHEN storing document paths, THE System SHALL store the relative file path from the application root directory
3. THE Database_Schema SHALL maintain the existing "documents" column for backward compatibility with legacy records
4. WHEN a new student registers, THE System SHALL populate the new categorized document columns and leave the legacy "documents" column NULL
5. THE Database_Schema SHALL allow NULL values for optional document fields (caste_certificate_doc, graduation_certificate_doc, other_documents_doc)

### Requirement 4: Registration Form Updates

**User Story:** As a student, I want an intuitive document upload interface in the registration form, so that I can easily upload my documents without confusion.

#### Acceptance Criteria

1. WHEN the registration form loads, THE Student_Registration_Form SHALL display six distinct upload fields with clear category labels
2. THE Student_Registration_Form SHALL display a red asterisk (*) next to mandatory document fields (Aadhar Card, 10th Marksheet)
3. WHEN a student hovers over an upload field, THE System SHALL display a tooltip or helper text explaining the document requirement
4. THE Student_Registration_Form SHALL display file format hints (e.g., "Accepted: JPG, JPEG, PDF") below each upload field
5. WHEN a file is selected, THE System SHALL display the selected filename to confirm the selection
6. IF a mandatory document field is empty on submission, THEN THE System SHALL prevent submission and highlight the missing field

### Requirement 5: Form Submission Handler Updates

**User Story:** As a developer, I want the form submission handler to process categorized documents correctly, so that documents are stored in their respective database columns.

#### Acceptance Criteria

1. WHEN processing a registration submission, THE System SHALL validate each uploaded document against its allowed file types
2. WHEN saving uploaded files, THE System SHALL generate unique filenames using timestamps and original filenames to prevent collisions
3. THE System SHALL store uploaded files in the uploads/ directory with appropriate subdirectories for organization
4. WHEN inserting student records, THE System SHALL populate the appropriate document column based on the upload field
5. IF any file upload fails, THEN THE System SHALL rollback the entire registration and display a descriptive error message
6. THE System SHALL validate file sizes with maximum limits (5MB for images, 10MB for PDFs)

### Requirement 6: Admin Student View Updates

**User Story:** As an administrator, I want to see categorized documents in the student list view, so that I can quickly identify which documents each student has submitted.

#### Acceptance Criteria

1. WHEN viewing the students list, THE Admin_Interface SHALL display document status indicators for each category
2. THE Admin_Interface SHALL use visual icons or badges to show which document categories have been uploaded
3. WHEN an administrator clicks on a student's document status, THE System SHALL navigate to the detailed document view page
4. THE Admin_Interface SHALL display a summary count of uploaded documents vs required documents for each student
5. THE Admin_Interface SHALL support filtering students by document completion status (all documents submitted, missing documents)

### Requirement 7: Admin Edit Student Page Updates

**User Story:** As an administrator, I want to edit and replace individual document categories, so that I can update specific documents without affecting others.

#### Acceptance Criteria

1. WHEN editing a student record, THE Admin_Interface SHALL display separate upload fields for each document category
2. THE Admin_Interface SHALL show the current filename or "Not Uploaded" status for each document category
3. WHEN an administrator uploads a new document for a category, THE System SHALL replace only that specific document
4. THE Admin_Interface SHALL provide "View" and "Delete" buttons for each uploaded document
5. WHEN saving edits, THE System SHALL preserve existing documents for categories where no new file was uploaded

### Requirement 8: Document Viewing Page Updates

**User Story:** As an administrator, I want to view all categorized documents for a student on a single page, so that I can review their complete documentation easily.

#### Acceptance Criteria

1. WHEN viewing student documents, THE Admin_Interface SHALL display documents organized by category with clear section headers
2. THE Admin_Interface SHALL display document previews for image files (JPG/JPEG) and PDF icons for PDF files
3. THE Admin_Interface SHALL provide "View Full Size" and "Download" buttons for each document
4. WHEN a document category has no uploaded file, THE System SHALL display a "Not Uploaded" indicator with an appropriate icon
5. THE Admin_Interface SHALL display document upload dates and file sizes for each uploaded document

### Requirement 9: Backward Compatibility

**User Story:** As a system administrator, I want existing student records to remain accessible and functional, so that the system upgrade does not disrupt current operations.

#### Acceptance Criteria

1. WHEN displaying documents for legacy records, THE System SHALL check the legacy "documents" column if new categorized columns are NULL
2. THE Admin_Interface SHALL display a migration indicator for legacy records showing they use the old document format
3. THE System SHALL allow administrators to manually migrate legacy documents to categorized fields through the edit interface
4. WHEN editing a legacy record, THE System SHALL preserve the legacy "documents" field until all categories are populated
5. THE System SHALL provide a data migration script to assist in bulk migration of legacy documents

### Requirement 10: Error Handling and User Feedback

**User Story:** As a student, I want clear error messages when document uploads fail, so that I understand what went wrong and how to fix it.

#### Acceptance Criteria

1. WHEN a file upload fails due to invalid format, THE System SHALL display an error message specifying the allowed formats for that document category
2. WHEN a file upload fails due to size limits, THE System SHALL display an error message showing the maximum allowed size
3. IF multiple validation errors occur, THEN THE System SHALL display all errors in a consolidated, easy-to-read format
4. THE System SHALL display success messages confirming which documents were uploaded successfully
5. WHEN server-side validation fails, THE System SHALL preserve all form data and pre-fill the form so students don't lose their entered information

### Requirement 11: File Storage Organization

**User Story:** As a system administrator, I want uploaded documents organized in a logical directory structure, so that file management and backups are easier.

#### Acceptance Criteria

1. THE System SHALL store uploaded documents in subdirectories organized by document category (uploads/aadhar/, uploads/marksheets/, etc.)
2. WHEN storing files, THE System SHALL generate unique filenames using student_id, timestamp, and document category
3. THE System SHALL maintain file extension integrity to preserve file type information
4. THE System SHALL create necessary subdirectories automatically if they don't exist
5. THE System SHALL log file upload operations for audit purposes

### Requirement 12: Security and Access Control

**User Story:** As a security administrator, I want document uploads to be secure and access-controlled, so that student documents are protected from unauthorized access.

#### Acceptance Criteria

1. THE System SHALL validate that uploaded files are not executable scripts or malicious files
2. THE System SHALL store uploaded files outside the web root or with appropriate access restrictions
3. WHEN serving document files, THE System SHALL verify the requesting user has appropriate permissions (admin or document owner)
4. THE System SHALL sanitize filenames to prevent directory traversal attacks
5. THE System SHALL log all document access attempts for security auditing
