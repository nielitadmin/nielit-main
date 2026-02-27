# Implementation Plan: APAAR ID Field Addition

## Overview

This implementation plan adds an APAAR ID field to the NIELIT Bhubaneswar student registration system. The implementation follows the existing pattern established by the Aadhar and PWD Status fields, ensuring consistency across all system components.

The work is organized into discrete tasks that build incrementally, with each task adding functionality that can be validated before proceeding. Testing tasks are marked as optional with "*" to allow for faster MVP delivery if needed.

## Tasks

- [ ] 1. Database schema update
  - Run ALTER TABLE statement to add apaar_id column
  - Verify column exists with correct type (VARCHAR(50), NULL, DEFAULT NULL)
  - Test that existing records have NULL for apaar_id
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [ ] 2. Update registration form (student/register.php)
  - [ ] 2.1 Add APAAR ID input field to Personal Information section
    - Add form field after Aadhar field
    - Set field as optional (no required attribute)
    - Add maxlength="50" attribute
    - Add placeholder text "Enter APAAR ID (optional)"
    - Add helper text explaining the field
    - _Requirements: 2.1, 2.3, 2.4, 2.5_
  
  - [ ]* 2.2 Write unit test for registration form rendering
    - Test that form contains input field with name="apaar_id"
    - Test that field does not have required attribute
    - Test that field has maxlength="50"
    - _Requirements: 2.1, 2.3, 2.5_

- [ ] 3. Update form submission handler (submit_registration.php)
  - [ ] 3.1 Add APAAR ID capture and sanitization logic
    - Capture apaar_id from POST data
    - Trim whitespace
    - Convert empty strings to NULL
    - Apply htmlspecialchars() with ENT_QUOTES and UTF-8
    - _Requirements: 3.1, 3.2, 3.3, 8.1, 8.2_
  
  - [ ] 3.2 Update INSERT statement and bind_param
    - Add apaar_id to column list in INSERT statement
    - Add apaar_id placeholder (?) to VALUES clause
    - Update bind_param type string (add one 's')
    - Add $apaar_id to bind_param parameters
    - Position after $aadhar in parameter list
    - _Requirements: 3.4, 3.5_
  
  - [ ]* 3.3 Write property test for input sanitization
    - **Property 1: Input Sanitization**
    - **Validates: Requirements 3.2, 4.3, 5.4, 8.1, 8.4**
    - Generate random strings with special characters (<, >, &, ', ")
    - Submit through registration
    - Verify sanitization applied correctly
    - Run 100+ iterations
  
  - [ ]* 3.4 Write property test for round-trip persistence
    - **Property 2: Round-Trip Persistence**
    - **Validates: Requirements 3.1, 3.4, 5.2, 5.3**
    - Generate random valid APAAR IDs
    - Submit and store to database
    - Retrieve and verify equivalence
    - Run 100+ iterations
  
  - [ ]* 3.5 Write unit tests for edge cases
    - Test empty string converts to NULL
    - Test NULL value is accepted
    - Test maximum length (50 characters)
    - _Requirements: 3.3_

- [ ] 4. Checkpoint - Test registration flow
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 5. Update admin view page (admin/view_student_documents.php)
  - [ ] 5.1 Add APAAR ID display to Personal Information table
    - Add table row after Aadhar field
    - Display "Not Provided" for NULL/empty values
    - Apply htmlspecialchars() to output
    - Use consistent table styling
    - _Requirements: 4.1, 4.2, 4.3, 4.4_
  
  - [ ]* 5.2 Write unit test for admin view display
    - Test APAAR ID field is displayed
    - Test NULL displays as "Not Provided"
    - Test non-empty value is displayed correctly
    - _Requirements: 4.1, 4.2, 4.3_

- [ ] 6. Update admin edit page (admin/edit_student.php)
  - [ ] 6.1 Add APAAR ID input field to edit form
    - Add form field in Personal Information section
    - Pre-populate with existing value using ?? operator
    - Set maxlength="50"
    - Add placeholder text
    - _Requirements: 5.1, 5.2_
  
  - [ ] 6.2 Add APAAR ID capture and sanitization in POST handler
    - Capture apaar_id from POST data
    - Trim whitespace
    - Convert empty strings to NULL
    - Apply htmlspecialchars() with ENT_QUOTES and UTF-8
    - _Requirements: 5.4, 8.1_
  
  - [ ] 6.3 Update UPDATE statement and bind_param
    - Add apaar_id=? to SET clause
    - Update bind_param type string (add one 's')
    - Add $apaar_id to bind_param parameters
    - Position after $aadhar in parameter list
    - _Requirements: 5.3, 5.5_
  
  - [ ]* 6.4 Write unit tests for admin edit functionality
    - Test edit form pre-populates APAAR ID
    - Test update persists to database
    - Test empty value converts to NULL
    - _Requirements: 5.2, 5.3_

- [ ] 7. Update PDF generation (admin/download_student_form.php)
  - [ ] 7.1 Add APAAR ID field to PDF Personal Information section
    - Add bilingual label "APAAR ID / अपार आईडी"
    - Position after Aadhar field, before Position field
    - Use 2-column layout (APAAR ID | Position)
    - Use font size 6pt for label, 8pt for value
    - Display "Not Provided" for NULL/empty values
    - _Requirements: 6.1, 6.2, 6.3, 6.4_
  
  - [ ]* 7.2 Write unit test for PDF generation
    - Test PDF contains APAAR ID field
    - Test bilingual label is present
    - Test NULL displays as "Not Provided"
    - Test PDF remains 2 pages
    - _Requirements: 6.1, 6.2, 6.4, 6.5, 6.6_

- [ ] 8. Integration testing and validation
  - [ ]* 8.1 Write property test for consistent NULL handling
    - **Property 3: Consistent NULL Handling**
    - **Validates: Requirements 4.2, 6.4, 7.3**
    - Test NULL/empty values across all display components
    - Verify consistent "not provided" indicators
    - Run 100+ iterations
  
  - [ ]* 8.2 Write property test for SQL injection prevention
    - **Property 4: SQL Injection Prevention**
    - **Validates: Requirements 8.3**
    - Generate SQL injection attempts
    - Verify treated as literal data
    - Verify safe storage and retrieval
    - Run 100+ iterations
  
  - [ ]* 8.3 Write integration tests
    - Test complete registration flow with APAAR ID
    - Test admin view displays APAAR ID correctly
    - Test admin edit updates APAAR ID correctly
    - Test PDF generation includes APAAR ID
    - _Requirements: 7.1, 7.2, 7.3_

- [ ] 9. Final checkpoint - Comprehensive testing
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties (100+ iterations each)
- Unit tests validate specific examples and edge cases
- Follow existing code patterns from Aadhar and PWD Status fields
- Database migration must be run before deploying code changes
- All five files must be updated together for consistency
