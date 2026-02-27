# Implementation Plan: Admin Edit Student Document Update

## Overview

This implementation plan updates the `admin/edit_student.php` page to support the new categorized document upload system. The feature adds support for 6 new document categories (Aadhar card, 10th marksheet, 12th marksheet, caste certificate, graduation certificate, and other documents) while maintaining backward compatibility with legacy document fields. The implementation reuses validated upload logic from `student/submit_registration.php` to ensure consistency across the system.

## Tasks

- [ ] 1. Include upload helper functions from submit_registration.php
  - Add require_once statement to include student/submit_registration.php at the top of edit_student.php
  - Ensure validateUploadedDocument() and handleCategorizedUpload() functions are accessible
  - _Requirements: 10.1, 10.2_

- [ ] 2. Add document preview sections for categorized documents
  - [ ] 2.1 Create document preview component for Aadhar card
    - Display current aadhar_card_doc with image preview or PDF icon
    - Add view and download buttons for existing document
    - Show "not uploaded" indicator if document doesn't exist
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_
  
  - [ ] 2.2 Create document preview components for educational documents
    - Display previews for tenth_marksheet_doc, twelfth_marksheet_doc, graduation_certificate_doc
    - Add view and download buttons for each existing document
    - Show "not uploaded" indicator for missing documents
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_
  
  - [ ] 2.3 Create document preview components for additional documents
    - Display previews for caste_certificate_doc and other_documents_doc
    - Add view and download buttons for each existing document
    - Show "not uploaded" indicator for missing documents
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [ ] 3. Add file upload input fields for categorized documents
  - [ ] 3.1 Add upload field for Aadhar card in Identity Proof section
    - Create file input with name="aadhar_card_doc"
    - Add accept attribute for .jpg,.jpeg,.png,.pdf
    - Add helper text showing file type and size limits
    - Mark as mandatory with asterisk
    - _Requirements: 2.1, 2.7, 2.8_
  
  - [ ] 3.2 Add upload fields for educational documents in Educational Qualifications section
    - Create file inputs for tenth_marksheet_doc (mandatory), twelfth_marksheet_doc (optional), graduation_certificate_doc (optional)
    - Add accept attributes and helper text for each
    - Mark tenth_marksheet_doc as mandatory
    - _Requirements: 2.2, 2.3, 2.5, 2.7, 2.8, 2.9_
  
  - [ ] 3.3 Add upload fields for additional documents in Additional Documents section
    - Create file inputs for caste_certificate_doc and other_documents_doc
    - Add accept attributes and helper text for each
    - Mark both as optional
    - _Requirements: 2.4, 2.6, 2.7, 2.9_

- [ ] 4. Organize UI into logical sections
  - [ ] 4.1 Create Photo & Signature section
    - Group passport_photo and signature fields
    - Add section heading with icon
    - Add "Required" badge to section
    - _Requirements: 7.1, 7.6, 7.7_
  
  - [ ] 4.2 Create Identity Proof section
    - Group aadhar_card_doc field
    - Add section heading with icon
    - Add "Required" badge to section
    - _Requirements: 7.2, 7.6, 7.7_
  
  - [ ] 4.3 Create Educational Qualifications section
    - Group tenth_marksheet_doc, twelfth_marksheet_doc, graduation_certificate_doc fields
    - Add section heading with icon
    - Add "Required" badge for mandatory documents
    - _Requirements: 7.3, 7.6, 7.7, 7.8_
  
  - [ ] 4.4 Create Additional Documents section
    - Group caste_certificate_doc and other_documents_doc fields
    - Add section heading with icon
    - Add "Optional" badge to section
    - _Requirements: 7.4, 7.6, 7.8_
  
  - [ ] 4.5 Update Payment Information section
    - Keep payment_receipt field in existing section
    - Add "Optional" badge to section
    - _Requirements: 7.5, 7.6, 7.8_

- [ ] 5. Update form processing to handle categorized document uploads
  - [ ] 5.1 Initialize document paths with existing values
    - Load current document paths from student record for all 6 new categories
    - Store in variables to preserve existing values if no new upload
    - _Requirements: 9.1, 9.4_
  
  - [ ] 5.2 Process aadhar_card_doc upload
    - Check if file uploaded for aadhar_card_doc
    - Call handleCategorizedUpload with 'aadhar' category
    - Store result path or error message
    - _Requirements: 3.1, 3.2, 3.3, 4.1, 4.7, 10.2, 10.5_
  
  - [ ] 5.3 Process tenth_marksheet_doc upload
    - Check if file uploaded for tenth_marksheet_doc
    - Call handleCategorizedUpload with 'tenth' category
    - Store result path or error message
    - _Requirements: 3.1, 3.2, 3.3, 4.2, 4.7, 10.2, 10.5_
  
  - [ ] 5.4 Process twelfth_marksheet_doc upload
    - Check if file uploaded for twelfth_marksheet_doc
    - Call handleCategorizedUpload with 'twelfth' category
    - Store result path or error message
    - _Requirements: 3.1, 3.2, 3.3, 4.3, 4.7, 10.2, 10.5_
  
  - [ ] 5.5 Process caste_certificate_doc upload
    - Check if file uploaded for caste_certificate_doc
    - Call handleCategorizedUpload with 'caste' category
    - Store result path or error message
    - _Requirements: 3.1, 3.2, 3.3, 4.4, 4.7, 10.2, 10.5_
  
  - [ ] 5.6 Process graduation_certificate_doc upload
    - Check if file uploaded for graduation_certificate_doc
    - Call handleCategorizedUpload with 'graduation' category
    - Store result path or error message
    - _Requirements: 3.1, 3.2, 3.3, 4.5, 4.7, 10.2, 10.5_
  
  - [ ] 5.7 Process other_documents_doc upload
    - Check if file uploaded for other_documents_doc
    - Call handleCategorizedUpload with 'other' category
    - Store result path or error message
    - _Requirements: 3.1, 3.2, 3.3, 4.6, 4.7, 10.2, 10.5_

- [ ] 6. Add comprehensive error handling
  - [ ] 6.1 Collect all upload errors
    - Create errors array to store all validation and upload failures
    - Add specific error messages with document category names
    - _Requirements: 8.1, 8.2, 8.3, 8.4_
  
  - [ ] 6.2 Implement rollback on error
    - If any errors exist, delete all successfully uploaded files
    - Do not update database if any upload fails
    - Preserve all existing document paths
    - _Requirements: 8.5, 8.6, 8.7_
  
  - [ ] 6.3 Display error messages to admin
    - Show all collected error messages in alert
    - Include document category and failure reason for each error
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

- [ ] 7. Update database UPDATE query
  - [ ] 7.1 Add new document columns to UPDATE statement
    - Add aadhar_card_doc, tenth_marksheet_doc, twelfth_marksheet_doc columns
    - Add caste_certificate_doc, graduation_certificate_doc, other_documents_doc columns
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7_
  
  - [ ] 7.2 Update bind_param with new document values
    - Add 6 new string parameters for categorized documents
    - Ensure parameter order matches UPDATE statement
    - Use prepared statements to prevent SQL injection
    - _Requirements: 5.8, 5.9, 5.10_
  
  - [ ] 7.3 Preserve existing document paths when no new upload
    - Use existing path value if no new file uploaded for a category
    - Only update path when new file successfully uploaded
    - _Requirements: 9.1, 9.2, 9.5_

- [ ] 8. Maintain legacy document support
  - [ ] 8.1 Verify legacy document upload processing
    - Ensure passport_photo, signature, documents, payment_receipt still work
    - Verify file paths use student/uploads/students/ directory
    - Test that legacy and new documents can be uploaded together
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [ ] 9. Checkpoint - Test document upload functionality
  - Test uploading new categorized documents for a student
  - Test updating only some documents while preserving others
  - Test error handling with invalid file types and sizes
  - Verify database updates correctly with new document paths
  - Ensure all tests pass, ask the user if questions arise

- [ ] 10. Add CSS styling for document sections
  - [ ] 10.1 Add styles for document preview components
    - Style image previews with proper sizing and borders
    - Style PDF preview icons and buttons
    - Style "not uploaded" indicators
    - _Requirements: 1.3, 1.4, 1.5_
  
  - [ ] 10.2 Add styles for section badges
    - Style "Required" badges with appropriate color
    - Style "Optional" badges with different color
    - Position badges next to section headings
    - _Requirements: 7.7, 7.8_
  
  - [ ] 10.3 Add responsive styles for document sections
    - Ensure document previews work on mobile devices
    - Make upload fields stack properly on small screens
    - _Requirements: 7.6_

- [ ] 11. Final checkpoint - Complete testing
  - Test complete workflow: view existing documents, upload new ones, verify database
  - Test with various file types (JPG, PNG, PDF) and sizes
  - Test error scenarios: oversized files, invalid types, missing mandatory documents
  - Verify backward compatibility with legacy document fields
  - Test UI organization and visual indicators
  - Ensure all tests pass, ask the user if questions arise

## Notes

- All document validation and upload logic is reused from student/submit_registration.php
- The implementation maintains full backward compatibility with legacy document fields
- Document paths are preserved when no new file is uploaded
- Error handling includes rollback of uploaded files if any validation fails
- UI is organized into logical sections matching the registration form structure
- All file uploads use the same validation rules and storage structure as the registration system
