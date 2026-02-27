# Requirements: Add PWD and Distinguishing Marks Fields

## Feature Overview
Add two new fields to the student registration system:
1. **Persons with Disabilities (PWD)** - A dedicated field to track disability status separately from the category field, enabling better reporting and compliance with accessibility requirements.
2. **Distinguishing Marks** - A text field for students to enter identifying physical marks (birthmarks, scars, etc.) for identification purposes.

## User Stories

### US-1: Student Registration with PWD Status
**As a** student registering for a course  
**I want to** indicate if I am a person with disabilities  
**So that** the institute can provide appropriate accommodations and track PWD enrollment

**Acceptance Criteria:**
- AC-1.1: Registration form displays a "Persons with Disabilities" field with Yes/No options
- AC-1.2: PWD field is clearly labeled and positioned in the Personal Information section
- AC-1.3: PWD field is optional (not required)
- AC-1.4: Selection is saved to the database when form is submitted
- AC-1.5: PWD status is independent of the Category field (student can be PWD and belong to any category)

### US-1B: Student Registration with Distinguishing Marks
**As a** student registering for a course  
**I want to** enter my distinguishing marks (birthmarks, scars, etc.)  
**So that** the institute has additional identification information on record

**Acceptance Criteria:**
- AC-1B.1: Registration form displays a "Distinguishing Marks" text input field
- AC-1B.2: Field is clearly labeled and positioned in the Personal Information section
- AC-1B.3: Field is optional (not required)
- AC-1B.4: Field accepts free-form text input (up to 255 characters)
- AC-1B.5: Input is saved to the database when form is submitted

### US-2: Admin View PWD Status and Distinguishing Marks
**As an** admin viewing student information  
**I want to** see the PWD status and distinguishing marks of each student  
**So that** I can identify students who may need special accommodations and have complete identification information

**Acceptance Criteria:**
- AC-2.1: PWD status is displayed in the student documents view page
- AC-2.2: PWD status is displayed in the edit student page
- AC-2.3: PWD status is shown in the student list/table
- AC-2.4: PWD status is included in the downloadable PDF form
- AC-2.5: PWD status is displayed with clear Yes/No or icon indicators
- AC-2.6: Distinguishing marks are displayed in the student documents view page
- AC-2.7: Distinguishing marks are displayed in the edit student page
- AC-2.8: Distinguishing marks are included in the downloadable PDF form
- AC-2.9: Distinguishing marks display shows "-" or "None" when empty

### US-3: Admin Edit PWD Status and Distinguishing Marks
**As an** admin editing student information  
**I want to** update the PWD status and distinguishing marks of a student  
**So that** I can correct any errors or update the information

**Acceptance Criteria:**
- AC-3.1: Edit student form includes PWD field with Yes/No options
- AC-3.2: Current PWD status is pre-selected when editing
- AC-3.3: Changes to PWD status are saved to the database
- AC-3.4: Updated PWD status is reflected in all views
- AC-3.5: Edit student form includes Distinguishing Marks text input field
- AC-3.6: Current distinguishing marks are pre-filled when editing
- AC-3.7: Changes to distinguishing marks are saved to the database
- AC-3.8: Updated distinguishing marks are reflected in all views

### US-4: PWD Reporting in Admission Orders
**As an** admin generating admission orders  
**I want to** see PWD count separately from category counts  
**So that** I can report accurate PWD enrollment statistics

**Acceptance Criteria:**
- AC-4.1: Admission order displays total PWD count
- AC-4.2: PWD count is calculated from the pwd_status field, not category
- AC-4.3: PWD students are still counted in their respective categories (SC/ST/OBC/GEN)
- AC-4.4: Admission order shows PWD count by gender (Male/Female)

## Technical Requirements

### TR-1: Database Schema
- TR-1.1: Add `pwd_status` column to `students` table
- TR-1.2: Column type for pwd_status: ENUM('Yes', 'No') or VARCHAR(3)
- TR-1.3: Default value for pwd_status: 'No'
- TR-1.4: pwd_status column should allow NULL for backward compatibility
- TR-1.5: Add `distinguishing_marks` column to `students` table
- TR-1.6: Column type for distinguishing_marks: VARCHAR(255) or TEXT
- TR-1.7: distinguishing_marks column should allow NULL (optional field)
- TR-1.8: Default value for distinguishing_marks: NULL

### TR-2: Form Integration
- TR-2.1: Add PWD field to `student/register.php`
- TR-2.2: Add PWD field to `admin/edit_student.php`
- TR-2.3: Update `submit_registration.php` to handle pwd_status
- TR-2.4: Update form validation to accept pwd_status
- TR-2.5: Add Distinguishing Marks field to `student/register.php`
- TR-2.6: Add Distinguishing Marks field to `admin/edit_student.php`
- TR-2.7: Update `submit_registration.php` to handle distinguishing_marks
- TR-2.8: Sanitize distinguishing_marks input to prevent XSS

### TR-3: Display Integration
- TR-3.1: Update `admin/view_student_documents.php` to show PWD status
- TR-3.2: Update `admin/students.php` to show PWD status in table
- TR-3.3: Update `admin/download_student_form.php` to include PWD status
- TR-3.4: Update admission order generation to count PWD students
- TR-3.5: Update `admin/view_student_documents.php` to show distinguishing marks
- TR-3.6: Update `admin/download_student_form.php` to include distinguishing marks
- TR-3.7: Update `batch_module/admin/generate_admission_order_ajax.php` to include distinguishing marks

## Business Rules

### BR-1: PWD and Category Independence
- PWD status is independent of category
- A student can be PWD and belong to any category (General, SC, ST, OBC, EWS)
- PWD category in the category dropdown is different from PWD status field

### BR-2: Optional Fields
- PWD status field is optional during registration
- Default value is "No" if not specified
- Students can choose not to disclose
- Distinguishing marks field is optional during registration
- Students can leave distinguishing marks blank
- Empty distinguishing marks should display as "-" or "None" in views

### BR-3: Data Privacy and Security
- PWD status is sensitive information
- Only admins can view PWD status
- PWD status should be handled with appropriate privacy measures
- Distinguishing marks are personal identification information
- Only admins can view distinguishing marks
- Distinguishing marks input must be sanitized to prevent XSS attacks
- Both fields should be stored securely in the database

## Out of Scope
- Detailed disability type classification
- Medical certificate upload for PWD verification
- PWD-specific course recommendations
- Automated accommodation assignment
- Photo upload of distinguishing marks
- Medical verification of distinguishing marks
- Biometric identification integration

## Dependencies
- Existing student registration system
- Existing admin student management system
- Existing admission order generation system

## Success Metrics
- PWD field is successfully added to all required forms
- PWD status is accurately captured and displayed
- Admission orders correctly report PWD counts
- Distinguishing marks field is successfully added to all required forms
- Distinguishing marks are accurately captured and displayed
- No data loss or corruption during implementation
- All input is properly sanitized and secure
