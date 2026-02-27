# Design Document: APAAR ID Field Addition

## Overview

This design document outlines the technical approach for adding an APAAR ID field to the NIELIT Bhubaneswar student registration system. The implementation follows the existing pattern established by the Aadhar field and PWD Status field additions, ensuring consistency across the codebase.

The APAAR ID (Automated Permanent Academic Account Registry) is an optional identifier that students can provide during registration. The field will be integrated into all five key components of the system:
1. Database schema (students table)
2. Registration form (student/register.php)
3. Form submission handler (submit_registration.php)
4. Admin view page (admin/view_student_documents.php)
5. Admin edit page (admin/edit_student.php)
6. PDF generation (admin/download_student_form.php)

## Architecture

### System Components

The APAAR ID feature integrates into the existing three-tier architecture:

**Presentation Layer:**
- Registration form with APAAR ID input field
- Admin view interface displaying APAAR ID
- Admin edit interface for updating APAAR ID
- PDF output with APAAR ID information

**Business Logic Layer:**
- Input sanitization using htmlspecialchars()
- NULL handling for empty values
- Data validation through prepared statements

**Data Layer:**
- MySQL database with apaar_id column
- Prepared statements for SQL injection prevention
- Consistent data type (VARCHAR(50))

### Integration Points

The APAAR ID field integrates with existing components:

1. **Registration Flow:** student/register.php → submit_registration.php → Database
2. **Admin View Flow:** admin/view_student_documents.php → Database
3. **Admin Edit Flow:** admin/edit_student.php → Database
4. **PDF Generation Flow:** admin/download_student_form.php → Database → PDF output

## Components and Interfaces

### 1. Database Schema

**Table:** students

**New Column:**
```sql
apaar_id VARCHAR(50) NULL DEFAULT NULL
```

**Rationale:**
- VARCHAR(50) accommodates various ID formats with room for future changes
- NULL allows optional field (not all students may have APAAR ID)
- DEFAULT NULL ensures consistent behavior for missing values

**Migration:**
```sql
ALTER TABLE students ADD COLUMN apaar_id VARCHAR(50) NULL DEFAULT NULL;
```

### 2. Registration Form Component

**File:** student/register.php

**Location:** Personal Information section, after Aadhar field

**HTML Structure:**
```html
<div class="col-md-6 mb-3">
    <label class="form-label">APAAR ID</label>
    <input type="text" class="form-control" name="apaar_id" 
           placeholder="Enter APAAR ID (optional)" maxlength="50">
    <small class="text-muted">Optional: Automated Permanent Academic Account Registry ID</small>
</div>
```

**Design Decisions:**
- Placed after Aadhar field for logical grouping of identification fields
- No required attribute (field is optional)
- maxlength="50" matches database column size
- Helper text explains the field purpose
- Follows existing form styling patterns

### 3. Form Submission Handler

**File:** submit_registration.php

**Input Capture:**
```php
$apaar_id = isset($_POST['apaar_id']) ? trim($_POST['apaar_id']) : NULL;

// Sanitize APAAR ID
if ($apaar_id !== NULL && $apaar_id !== '') {
    $apaar_id = htmlspecialchars($apaar_id, ENT_QUOTES, 'UTF-8');
} else {
    $apaar_id = NULL;
}
```

**SQL Update:**
```php
$stmt = $conn->prepare("INSERT INTO students (
    course, course_id, training_center, name, father_name, mother_name, 
    dob, age, mobile, aadhar, apaar_id, gender, religion, marital_status, 
    category, pwd_status, distinguishing_marks, position, nationality, email, state, city, pincode, 
    address, college_name, education_details, documents, passport_photo, 
    signature, payment_receipt, utr_number, student_id, password, 
    status, registration_date
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

$stmt->bind_param(
    "siss sssiss ssssss sssss sssss sssss ss",
    $course_name, $course_id, $training_center, $name, $father_name, 
    $mother_name, $dob, $age, $mobile, $aadhar, $apaar_id, $gender, $religion, 
    $marital_status, $category, $pwd_status, $distinguishing_marks, $position, $nationality, $email, 
    $state, $city, $pincode, $address, $college_name, $education_data,
    $documents_path, $passport_photo_path, $signature_path, 
    $payment_receipt_path, $utr_number, $student_id, $hashed_password
);
```

**Security Measures:**
- htmlspecialchars() prevents XSS attacks
- ENT_QUOTES ensures both single and double quotes are escaped
- UTF-8 encoding handles international characters
- Prepared statements prevent SQL injection
- Empty strings converted to NULL for database consistency

### 4. Admin View Component

**File:** admin/view_student_documents.php

**Display Location:** Personal Information table, after Aadhar field

**HTML Structure:**
```php
<tr>
    <td style="background: #f8fafc; font-weight: 600;">APAAR ID</td>
    <td><?php echo !empty($student['apaar_id']) ? htmlspecialchars($student['apaar_id']) : 'Not Provided'; ?></td>
    <td style="background: #f8fafc; font-weight: 600;">Position</td>
    <td><?php echo !empty($student['position']) ? htmlspecialchars($student['position']) : '-'; ?></td>
</tr>
```

**Design Decisions:**
- Displays "Not Provided" for NULL or empty values
- Uses htmlspecialchars() for output escaping
- Maintains consistent table styling
- Positioned logically with other ID fields

### 5. Admin Edit Component

**File:** admin/edit_student.php

**Form Input:**
```php
$apaar_id = isset($_POST['apaar_id']) ? trim($_POST['apaar_id']) : NULL;

// Sanitize APAAR ID
if ($apaar_id !== NULL && $apaar_id !== '') {
    $apaar_id = htmlspecialchars($apaar_id, ENT_QUOTES, 'UTF-8');
} else {
    $apaar_id = NULL;
}
```

**HTML Form Field:**
```html
<div class="form-group">
    <label class="form-label">APAAR ID</label>
    <input type="text" name="apaar_id" class="form-control" 
           value="<?php echo htmlspecialchars($student['apaar_id'] ?? ''); ?>" 
           maxlength="50" placeholder="Enter APAAR ID (optional)">
</div>
```

**SQL Update:**
```php
$update_sql = "UPDATE students SET 
    name=?, father_name=?, mother_name=?, dob=?, age=?, mobile=?, email=?, 
    course=?, status=?, address=?, city=?, state=?, pincode=?, aadhar=?, apaar_id=?,
    gender=?, religion=?, marital_status=?, category=?, pwd_status=?, distinguishing_marks=?, position=?, nationality=?, 
    college_name=?, utr_number=?, training_center=?,
    passport_photo=?, signature=?, documents=?, payment_receipt=? 
    WHERE student_id=?";

$stmt->bind_param("sssssssssssssssssssssssssssssss", 
    $name, $father_name, $mother_name, $dob, $age, $mobile, $email,
    $course, $status, $address, $city, $state, $pincode, $aadhar, $apaar_id,
    $gender, $religion, $marital_status, $category, $pwd_status, $distinguishing_marks, $position, $nationality,
    $college_name, $utr_number, $training_center,
    $passport_photo, $signature, $documents, $payment_receipt, $student_id);
```

### 6. PDF Generation Component

**File:** admin/download_student_form.php

**Location:** After Aadhar field in the Personal Information section

**PDF Code:**
```php
// APAAR ID & Position (2 columns)
$info_y += 15;
$pdf->SetFillColor(227, 242, 253);
$pdf->SetFont('freesans', 'B', 6);
$pdf->SetXY($card_x, $info_y);
$pdf->Cell($col_width, 6, 'APAAR ID / अपार आईडी', 0, 0, 'L', true);
$pdf->SetXY($card_x + $col_width, $info_y);
$pdf->Cell($col_width, 6, 'POSITION / पद', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($card_x, $info_y + 6);
$apaar_display = !empty($student['apaar_id']) ? $student['apaar_id'] : 'Not Provided';
$pdf->Cell($col_width, 6, $apaar_display, 0, 0, 'L');
$pdf->SetXY($card_x + $col_width, $info_y + 6);
$pdf->Cell($col_width, 6, $student['position'], 0, 1, 'L');
```

**Design Decisions:**
- Bilingual label: "APAAR ID / अपार आईडी"
- Positioned after Aadhar, before Position field
- Uses same font sizes as existing fields (6pt for labels, 8pt for values)
- Displays "Not Provided" for NULL values
- Maintains 2-page layout by using existing space efficiently

## Data Models

### Student Record

```php
[
    'student_id' => string,      // Primary identifier
    'name' => string,            // Student name
    'aadhar' => string,          // Aadhar number
    'apaar_id' => string|null,   // APAAR ID (NEW FIELD)
    'pwd_status' => string,      // PWD status
    'distinguishing_marks' => string|null,
    // ... other fields
]
```

### Form Data Structure

**POST Parameters:**
```php
$_POST = [
    'name' => string,
    'aadhar' => string,
    'apaar_id' => string|empty,  // NEW PARAMETER
    'pwd_status' => string,
    'distinguishing_marks' => string|empty,
    // ... other fields
]
```

### Database Column Specification

```
Column Name: apaar_id
Type: VARCHAR(50)
Null: YES
Default: NULL
Collation: utf8mb4_general_ci
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*


### Property Reflection

After analyzing all acceptance criteria, I identified the following testable properties and performed redundancy elimination:

**Identified Properties:**
- 3.1: Capture APAAR ID from form
- 3.2: Sanitize input with htmlspecialchars()
- 3.4: Store APAAR ID in database (round-trip)
- 4.3: Display sanitized APAAR ID
- 5.2: Pre-populate edit form (round-trip)
- 5.3: Update persists to database (round-trip)
- 5.4: Sanitize on update
- 7.3: Consistent NULL handling across components
- 8.3: SQL injection prevention

**Redundancy Analysis:**
- Properties 3.2, 5.4, and 4.3 all test sanitization → Combine into one comprehensive sanitization property
- Properties 3.4, 5.2, and 5.3 all test round-trip persistence → Combine into one comprehensive round-trip property
- Property 3.1 is subsumed by the round-trip property (if we can store and retrieve, we captured it)
- Property 7.3 (consistent NULL handling) is a valuable separate property
- Property 8.3 (SQL injection prevention) is a valuable separate property

**Final Properties:**
1. Input sanitization property (combines 3.2, 5.4, 4.3)
2. Round-trip persistence property (combines 3.1, 3.4, 5.2, 5.3)
3. Consistent NULL handling property (7.3)
4. SQL injection prevention property (8.3)

### Correctness Properties

Property 1: Input Sanitization
*For any* APAAR ID input containing special characters (such as <, >, &, ', "), the system should sanitize the input using htmlspecialchars() with ENT_QUOTES and UTF-8 encoding, and all display components (view, edit, PDF) should show the sanitized version
**Validates: Requirements 3.2, 4.3, 5.4, 8.1, 8.4**

Property 2: Round-Trip Persistence
*For any* valid APAAR ID value submitted during registration or update, storing the value to the database and then retrieving it should return an equivalent sanitized value
**Validates: Requirements 3.1, 3.4, 5.2, 5.3**

Property 3: Consistent NULL Handling
*For any* display component (admin view, admin edit, PDF generation), when the APAAR ID is NULL or empty, the component should display a consistent "not provided" indicator (either "Not Provided", "N/A", or empty string for edit forms)
**Validates: Requirements 4.2, 6.4, 7.3**

Property 4: SQL Injection Prevention
*For any* APAAR ID input containing SQL syntax (such as '; DROP TABLE students; --), the system should treat it as literal data and not execute it as SQL code, with the value being safely stored and retrieved
**Validates: Requirements 8.3**

## Error Handling

### Input Validation

**Empty Values:**
- Empty strings are converted to NULL
- NULL values are accepted (field is optional)
- No validation errors for missing APAAR ID

**Special Characters:**
- All special characters are escaped using htmlspecialchars()
- No rejection of special characters (they are sanitized, not blocked)

**Length Validation:**
- Maximum length: 50 characters (enforced by database and HTML maxlength)
- No minimum length requirement

### Database Errors

**INSERT Failures:**
- If INSERT fails, existing error handling in submit_registration.php will catch it
- Error message displayed to user via session variable
- No partial data committed (transaction integrity maintained)

**UPDATE Failures:**
- If UPDATE fails, existing error handling in edit_student.php will catch it
- Error message displayed to admin via session variable
- Original data remains unchanged

### Display Errors

**Missing Data:**
- NULL or empty APAAR ID displays as "Not Provided" or "N/A"
- No error thrown for missing data

**File Not Found (PDF):**
- If student record not found, redirect to students list
- Session message indicates error

## Testing Strategy

### Dual Testing Approach

This feature requires both unit tests and property-based tests to ensure comprehensive coverage:

**Unit Tests** will verify:
- Specific examples of APAAR ID values
- Edge cases (empty strings, NULL values, maximum length)
- Integration between components
- Error conditions (database failures, missing records)

**Property-Based Tests** will verify:
- Universal properties across all inputs
- Sanitization works for any special characters
- Round-trip persistence for any valid APAAR ID
- SQL injection prevention for any malicious input

Together, these approaches provide comprehensive coverage: unit tests catch concrete bugs in specific scenarios, while property tests verify general correctness across the input space.

### Property-Based Testing Configuration

**Testing Library:** PHPUnit with property-based testing extension (or manual randomization)

**Test Configuration:**
- Minimum 100 iterations per property test
- Each test tagged with feature name and property number
- Tag format: `@group Feature: apaar-id-field-addition, Property {number}: {property_text}`

**Property Test Implementation:**

Each correctness property must be implemented as a SINGLE property-based test:

1. **Property 1 Test:** Generate random strings with special characters, submit through registration/edit, verify sanitization in all display contexts
2. **Property 2 Test:** Generate random valid APAAR IDs, submit and retrieve, verify equivalence
3. **Property 3 Test:** Test NULL/empty values across all display components, verify consistent output
4. **Property 4 Test:** Generate SQL injection attempts, verify they're treated as literal data

### Unit Testing Focus

Unit tests should focus on:
- Specific example: Valid APAAR ID "APAAR123456789" is stored and displayed correctly
- Edge case: Empty string is converted to NULL
- Edge case: Maximum length (50 characters) is enforced
- Edge case: NULL value displays as "Not Provided"
- Integration: Registration form submission includes APAAR ID
- Integration: Admin edit form pre-populates APAAR ID
- Integration: PDF generation includes APAAR ID field
- Error condition: Database INSERT failure is handled gracefully
- Error condition: Missing student record redirects appropriately

### Test Coverage Goals

- All five integration points tested (registration, submission, view, edit, PDF)
- All four correctness properties verified with property-based tests
- All edge cases covered with unit tests
- All error conditions handled and tested

## Implementation Notes

### Code Consistency

Follow the existing pattern established by similar fields:
- Use the same sanitization approach as `distinguishing_marks`
- Follow the same NULL handling as `distinguishing_marks`
- Use the same bind_param pattern as other optional fields
- Maintain the same styling as other form fields

### Database Migration

The ALTER TABLE statement should be run before deploying code changes:
```sql
ALTER TABLE students ADD COLUMN apaar_id VARCHAR(50) NULL DEFAULT NULL;
```

### Deployment Sequence

1. Run database migration (ADD COLUMN)
2. Deploy code changes to all five files
3. Test registration flow
4. Test admin view and edit
5. Test PDF generation
6. Verify existing records display "Not Provided" for NULL APAAR ID

### Rollback Plan

If issues arise:
1. Revert code changes to all five files
2. Database column can remain (NULL values won't cause issues)
3. Or optionally: `ALTER TABLE students DROP COLUMN apaar_id;`

### Performance Considerations

- VARCHAR(50) is small and won't impact query performance
- No indexes needed (field is not used for searching/filtering)
- Sanitization overhead is negligible (htmlspecialchars is fast)
- PDF generation time unchanged (one additional field is minimal)
