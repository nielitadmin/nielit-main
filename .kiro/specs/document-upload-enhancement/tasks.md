# Implementation Plan: Document Upload Enhancement

## Overview

This implementation plan breaks down the document upload enhancement feature into discrete, actionable tasks. The approach follows a phased implementation strategy: database schema first, then backend processing, followed by frontend updates, and finally admin interface enhancements. Each task builds incrementally to ensure the system remains functional throughout development.

## Tasks

- [x] 1. Database Schema and Migration
  - Create migration script to add six new document category columns to students table
  - Add indexes for frequently queried document columns
  - Test migration on development database
  - Create rollback script for safe deployment
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 2. File Storage Infrastructure
  - [x] 2.1 Create organized directory structure for categorized documents
    - Create subdirectories: uploads/aadhar/, uploads/caste_certificates/, uploads/marksheets/10th/, uploads/marksheets/12th/, uploads/marksheets/graduation/, uploads/other/
    - Set appropriate permissions (0755) for all directories
    - Add .htaccess files for security if needed
    - _Requirements: 11.1, 11.4_
  
  - [ ]* 2.2 Write property test for directory creation
    - **Property 6: Unique Filename Generation**
    - **Validates: Requirements 5.2, 11.2**
  
  - [x] 2.3 Implement file upload handler function
    - Create handleCategorizedUpload() function in submit_registration.php
    - Implement unique filename generation using student_id + timestamp + category
    - Implement file move operations with error handling
    - Return relative paths for database storage
    - _Requirements: 5.2, 5.3, 11.2, 11.3_
  
  - [ ]* 2.4 Write property test for file upload handler
    - **Property 4: File Storage Path Consistency**
    - **Validates: Requirements 3.2, 5.3, 11.1**

- [ ] 3. Validation Service Implementation
  - [x] 3.1 Implement server-side validation function
    - Create validateUploadedDocument() function
    - Validate MIME types using finfo_file()
    - Validate file extensions
    - Validate file sizes (5MB for images, 10MB for PDFs)
    - Implement security checks for executable content
    - _Requirements: 2.1, 2.2, 2.5, 5.1, 5.6, 12.1, 12.4_
  
  - [ ]* 3.2 Write property test for file type validation
    - **Property 1: File Type Validation Consistency**
    - **Validates: Requirements 2.1, 2.2, 2.4, 2.5**
  
  - [ ]* 3.3 Write property test for file size validation
    - **Property 7: File Size Limit Enforcement**
    - **Validates: Requirements 2.6, 5.6, 10.2**
  
  - [ ]* 3.4 Write property test for security validation
    - **Property 8: Security Validation**
    - **Validates: Requirements 12.1, 12.4**

- [ ] 4. Update Registration Form (student/register.php)
  - [x] 4.1 Add HTML structure for categorized document upload fields
    - Create document upload section with six categorized fields
    - Add visual indicators for mandatory vs optional fields
    - Add file format hints below each upload field
    - Implement responsive grid layout for document fields
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 4.1, 4.2, 4.3, 4.4_
  
  - [x] 4.2 Add CSS styling for document categories
    - Style mandatory document sections with red accent
    - Style optional document sections with blue accent
    - Add badges for "Required" and "Optional" labels
    - Implement hover effects and visual feedback
    - _Requirements: 1.3, 4.2_
  
  - [x] 4.3 Implement client-side validation JavaScript
    - Create validateDocumentUpload() function
    - Validate file extensions (.jpg, .jpeg, .pdf)
    - Validate file sizes before submission
    - Display inline error messages for invalid files
    - Prevent form submission if validation fails
    - _Requirements: 2.1, 2.4, 4.6, 10.1_
  
  - [ ]* 4.4 Write unit tests for client-side validation
    - Test valid file types pass validation
    - Test invalid file types fail validation
    - Test file size limits
    - Test error message display

- [ ] 5. Update Form Submission Handler (submit_registration.php)
  - [x] 5.1 Process categorized document uploads
    - Loop through all six document categories
    - Call handleCategorizedUpload() for each uploaded file
    - Collect file paths for database insertion
    - Handle optional fields (allow empty uploads)
    - _Requirements: 5.1, 5.2, 5.3, 5.4_
  
  - [ ] 5.2 Update database INSERT statement
    - Add six new document columns to INSERT query
    - Bind parameters for all document paths
    - Leave legacy "documents" column as NULL for new records
    - _Requirements: 3.1, 3.4, 5.4_
  
  - [ ] 5.3 Implement error handling and rollback
    - If any file upload fails, delete all uploaded files
    - Display specific error messages for each failed upload
    - Preserve form data in session for re-display
    - Log errors for debugging
    - _Requirements: 5.5, 10.1, 10.2, 10.3, 10.6_
  
  - [ ]* 5.4 Write property test for database column mapping
    - **Property 3: Database Column Mapping Integrity**
    - **Validates: Requirements 3.1, 3.4, 5.4**
  
  - [ ]* 5.5 Write property test for mandatory document enforcement
    - **Property 2: Mandatory Document Enforcement**
    - **Validates: Requirements 1.3, 4.6**
  
  - [ ]* 5.6 Write integration test for complete registration flow
    - Test end-to-end registration with all documents
    - Verify database record created correctly
    - Verify files stored in correct directories
    - Test with missing optional documents

- [ ] 6. Checkpoint - Test Registration Flow
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Update Admin Students List (admin/students.php)
  - [ ] 7.1 Add document status indicators to student list
    - Query new document columns in SELECT statement
    - Display badge/icon for each document category
    - Show green checkmark for uploaded documents
    - Show red X for missing mandatory documents
    - Add tooltip showing document category names
    - _Requirements: 6.1, 6.2, 6.4_
  
  - [ ] 7.2 Add document completion filter
    - Add filter dropdown for "All Documents Submitted" / "Missing Documents"
    - Implement SQL WHERE clause for filtering
    - Preserve filter state in URL parameters
    - _Requirements: 6.5_
  
  - [ ]* 7.3 Write unit tests for document status display
    - Test status indicators for complete records
    - Test status indicators for incomplete records
    - Test status indicators for legacy records

- [ ] 8. Update Admin Edit Student Page (admin/edit_student.php)
  - [ ] 8.1 Add categorized document upload fields
    - Create six separate upload fields with labels
    - Display current filename or "Not Uploaded" for each category
    - Add "View" button for existing documents
    - Add "Delete" button for existing documents
    - _Requirements: 7.1, 7.2, 7.4_
  
  - [ ] 8.2 Implement selective document replacement logic
    - Check if new file uploaded for each category
    - Only update database column if new file provided
    - Preserve existing document paths for unchanged categories
    - Delete old file when replacing with new file
    - _Requirements: 7.3, 7.5_
  
  - [ ] 8.3 Add validation for admin uploads
    - Reuse validateUploadedDocument() function
    - Display validation errors specific to each category
    - Prevent save if validation fails
    - _Requirements: 2.1, 2.2, 2.5_
  
  - [ ]* 8.4 Write property test for admin edit preservation
    - **Property 9: Admin Edit Preservation**
    - **Validates: Requirements 7.5**
  
  - [ ]* 8.5 Write integration test for admin edit flow
    - Test editing student without changing documents
    - Test replacing specific documents
    - Test replacing all documents
    - Verify only modified documents updated

- [ ] 9. Update Document Viewing Page (admin/view_student_documents.php)
  - [ ] 9.1 Restructure document display by category
    - Create separate card/section for each document category
    - Display category headers with icons
    - Show upload status (Uploaded/Not Uploaded) for each
    - _Requirements: 8.1, 8.4_
  
  - [ ] 9.2 Implement document preview and download
    - Display image preview for JPG/JPEG files
    - Display PDF icon for PDF files
    - Add "View Full Size" button for images
    - Add "Download" button for all documents
    - _Requirements: 8.2, 8.3_
  
  - [ ] 9.3 Add document metadata display
    - Show upload date for each document
    - Show file size for each document
    - Show file format (JPG/PDF) for each document
    - _Requirements: 8.5_
  
  - [ ]* 9.4 Write unit tests for document display
    - Test display with all documents uploaded
    - Test display with some documents missing
    - Test display with legacy records
    - Test image preview rendering
    - Test PDF icon rendering

- [ ] 10. Implement Backward Compatibility
  - [ ] 10.1 Add legacy document fallback logic
    - In view pages, check if new columns are NULL
    - If NULL, fall back to legacy "documents" column
    - Display legacy documents with migration indicator
    - _Requirements: 9.1, 9.2_
  
  - [ ] 10.2 Create data migration helper script
    - Create migrations/migrate_legacy_documents.php
    - Provide UI for admins to migrate individual records
    - Add bulk migration option for all legacy records
    - Log migration operations
    - _Requirements: 9.3, 9.5_
  
  - [ ]* 10.3 Write property test for backward compatibility
    - **Property 5: Backward Compatibility Preservation**
    - **Validates: Requirements 9.1, 9.2, 9.4**
  
  - [ ]* 10.4 Write integration test for legacy record handling
    - Create test records with legacy format
    - Verify legacy documents display correctly
    - Test editing legacy records
    - Test migration from legacy to categorized

- [ ] 11. Checkpoint - Test Admin Interfaces
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 12. Error Handling and User Feedback
  - [ ] 12.1 Implement comprehensive error messages
    - Create error message templates for each validation type
    - Include specific details (file size, allowed formats, category)
    - Display errors in user-friendly language
    - _Requirements: 10.1, 10.2, 10.3_
  
  - [ ]* 12.2 Write property test for error message specificity
    - **Property 10: Error Message Specificity**
    - **Validates: Requirements 2.6, 10.1, 10.2, 10.3**
  
  - [ ] 12.3 Implement form data preservation on error
    - Store form data in session on validation failure
    - Pre-fill form fields with previous values
    - Clear session data after successful submission
    - _Requirements: 10.5_
  
  - [ ]* 12.4 Write unit tests for error handling
    - Test error display for invalid file types
    - Test error display for oversized files
    - Test error display for security violations
    - Test form data preservation

- [ ] 13. Security Enhancements
  - [ ] 13.1 Implement access control for document viewing
    - Verify user is admin or document owner before serving files
    - Create secure document serving script
    - Log all document access attempts
    - _Requirements: 12.2, 12.3, 12.5_
  
  - [ ] 13.2 Add filename sanitization
    - Remove special characters from uploaded filenames
    - Prevent directory traversal attempts
    - Validate file paths before file operations
    - _Requirements: 12.4_
  
  - [ ]* 13.3 Write security tests
    - Test directory traversal prevention
    - Test executable file rejection
    - Test access control enforcement
    - Test filename sanitization

- [ ] 14. Documentation and Deployment
  - [ ] 14.1 Create deployment guide
    - Document database migration steps
    - Document directory creation steps
    - Document permission requirements
    - Include rollback procedures
  
  - [ ] 14.2 Create user documentation
    - Document for students: how to upload documents
    - Document for admins: how to view/manage documents
    - Document for admins: how to migrate legacy records
    - Include screenshots and examples
  
  - [ ] 14.3 Update system documentation
    - Update database schema documentation
    - Update API/function documentation
    - Update file structure documentation

- [ ] 15. Final Testing and Validation
  - [ ] 15.1 Perform end-to-end testing
    - Test complete student registration flow
    - Test all admin interface operations
    - Test backward compatibility scenarios
    - Test error handling scenarios
  
  - [ ] 15.2 Perform cross-browser testing
    - Test on Chrome, Firefox, Safari, Edge
    - Test on mobile devices
    - Verify file upload works on all platforms
  
  - [ ] 15.3 Perform load testing
    - Test concurrent uploads from multiple users
    - Test with large files near size limits
    - Monitor server resource usage
  
  - [ ] 15.4 Security audit
    - Review all file upload code for vulnerabilities
    - Test with malicious file uploads
    - Verify access controls work correctly

- [ ] 16. Final Checkpoint - Complete System Validation
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional property-based and integration tests that can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key milestones
- Property tests validate universal correctness properties across many generated inputs
- Unit tests validate specific examples and edge cases
- Integration tests validate end-to-end workflows
- The implementation follows a bottom-up approach: infrastructure → backend → frontend → admin interfaces
- Backward compatibility is maintained throughout to ensure existing records remain functional
- Security is integrated at every layer rather than added as an afterthought
