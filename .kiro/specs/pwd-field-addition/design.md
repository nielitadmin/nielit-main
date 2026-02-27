# Design: Add PWD and Distinguishing Marks Fields

## Overview
This design document outlines the technical implementation for adding two new fields to the student registration system:
1. **PWD Status Field** - Independent of the category field, integrated across all relevant forms, views, and reports
2. **Distinguishing Marks Field** - A text input for identification purposes, integrated across all relevant forms and views

## Architecture

### Database Design

#### Schema Changes
**Table: `students`**
- Add column: `pwd_status` VARCHAR(3) DEFAULT 'No'
- Allowed values: 'Yes', 'No'
- NULL allowed for backward compatibility
- Add column: `distinguishing_marks` VARCHAR(255) DEFAULT NULL
- Allowed values: Any text up to 255 characters
- NULL allowed (optional field)

```sql
ALTER TABLE students 
ADD COLUMN pwd_status VARCHAR(3) DEFAULT 'No' 
AFTER category;

ALTER TABLE students 
ADD COLUMN distinguishing_marks VARCHAR(255) DEFAULT NULL 
AFTER pwd_status;
```

### Component Design

#### 1. Registration Form (student/register.php)
**Location:** Level 1 - Personal Information Section  
**Position:** After "Category" field, before "Position/Occupation"

**PWD Field Implementation:**
```html
<div class="col-md-3 mb-3">
    <label class="form-label">Persons with Disabilities</label>
    <select class="form-select" name="pwd_status">
        <option value="No" selected>No</option>
        <option value="Yes">Yes</option>
    </select>
    <small class="text-muted">Optional disclosure</small>
</div>
```

**Distinguishing Marks Field Implementation:**
```html
<div class="col-md-3 mb-3">
    <label class="form-label">Distinguishing Marks</label>
    <input type="text" class="form-control" name="distinguishing_marks" 
           placeholder="e.g., Birthmark on left arm" maxlength="255">
    <small class="text-muted">Optional - Any identifying marks</small>
</div>
```

**Styling:** Match existing form controls with modern styling

#### 2. Form Submission (submit_registration.php)
**Changes:**
- Capture `pwd_status` from POST data
- Default to 'No' if not provided
- Capture `distinguishing_marks` from POST data
- Sanitize distinguishing_marks input
- Add both fields to INSERT statement
- Update bind_param type string

**Implementation:**
```php
$pwd_status = isset($_POST['pwd_status']) ? $_POST['pwd_status'] : 'No';
$distinguishing_marks = isset($_POST['distinguishing_marks']) ? trim($_POST['distinguishing_marks']) : NULL;

// Sanitize distinguishing marks
if ($distinguishing_marks !== NULL && $distinguishing_marks !== '') {
    $distinguishing_marks = htmlspecialchars($distinguishing_marks, ENT_QUOTES, 'UTF-8');
} else {
    $distinguishing_marks = NULL;
}

// Update INSERT statement to include pwd_status and distinguishing_marks
// Update bind_param to include both parameters
```

#### 3. Admin Edit Form (admin/edit_student.php)
**Location:** Personal Information section  
**Position:** After "Category" field

**PWD Field Implementation:**
```html
<div class="col-md-3 mb-3">
    <label class="form-label">Persons with Disabilities</label>
    <select class="form-select" name="pwd_status">
        <option value="No" <?php echo ($student['pwd_status'] == 'No') ? 'selected' : ''; ?>>No</option>
        <option value="Yes" <?php echo ($student['pwd_status'] == 'Yes') ? 'selected' : ''; ?>>Yes</option>
    </select>
</div>
```

**Distinguishing Marks Field Implementation:**
```html
<div class="col-md-3 mb-3">
    <label class="form-label">Distinguishing Marks</label>
    <input type="text" class="form-control" name="distinguishing_marks" 
           value="<?php echo htmlspecialchars($student['distinguishing_marks'] ?? '', ENT_QUOTES); ?>" 
           maxlength="255">
</div>
```

**Update Logic:**
- Include pwd_status and distinguishing_marks in UPDATE query
- Add both to bind_param
- Sanitize distinguishing_marks input

#### 4. View Student Documents (admin/view_student_documents.php)
**Location:** Personal Information table  
**Position:** After "Category" row

**PWD Status Implementation:**
```html
<tr>
    <td style="background: #f8fafc; font-weight: 600;">PWD Status</td>
    <td>
        <?php if ($student['pwd_status'] == 'Yes'): ?>
            <span class="badge" style="background: #3b82f6; color: white;">
                <i class="fas fa-wheelchair"></i> Yes
            </span>
        <?php else: ?>
            <span class="badge" style="background: #94a3b8; color: white;">No</span>
        <?php endif; ?>
    </td>
    <td style="background: #f8fafc; font-weight: 600;">Distinguishing Marks</td>
    <td><?php echo !empty($student['distinguishing_marks']) ? htmlspecialchars($student['distinguishing_marks']) : '-'; ?></td>
</tr>
```

#### 5. Students List (admin/students.php)
**Implementation:**
- Add PWD column to table (optional - can be added as filter)
- Add PWD filter dropdown
- Update query to filter by pwd_status

**Filter Implementation:**
```html
<select class="form-select" name="filter_pwd">
    <option value="">All PWD Status</option>
    <option value="Yes">PWD: Yes</option>
    <option value="No">PWD: No</option>
</select>
```

#### 6. PDF Form (admin/download_student_form.php)
**Location:** Personal Information section  
**Position:** After Category field

**Implementation:**
```php
// Add to personal information section
$pdf->Cell(45, 6, 'PWD Status / दिव्यांग स्थिति:', 0, 0, 'L');
$pdf->Cell(45, 6, $student['pwd_status'] == 'Yes' ? 'Yes / हाँ' : 'No / नहीं', 0, 1, 'L');

$pdf->Cell(45, 6, 'Distinguishing Marks / पहचान चिह्न:', 0, 0, 'L');
$distinguishing_marks_text = !empty($student['distinguishing_marks']) ? $student['distinguishing_marks'] : 'None / कोई नहीं';
$pdf->Cell(45, 6, $distinguishing_marks_text, 0, 1, 'L');
```

#### 7. Admission Order (batch_module/admin/generate_admission_order_ajax.php)
**Implementation:**
- Add PWD count section after category summary table
- Count PWD students by gender
- Display in separate summary box

**Count Logic:**
```php
$pwd_counts = ['M' => 0, 'F' => 0];
foreach ($students as $student) {
    if (isset($student['pwd_status']) && $student['pwd_status'] == 'Yes') {
        $gender = strtoupper(substr(trim($student['gender'] ?? 'M'), 0, 1));
        if ($gender == 'M' || $gender == 'F') {
            $pwd_counts[$gender]++;
        }
    }
}
$total_pwd = $pwd_counts['M'] + $pwd_counts['F'];
```

**Display:**
```html
<div style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 8px;">
    <p style="margin: 0; font-weight: 600; color: #1e40af;">
        <i class="fas fa-wheelchair"></i> Persons with Disabilities (PWD): 
        Male: <?php echo $pwd_counts['M']; ?>, 
        Female: <?php echo $pwd_counts['F']; ?>, 
        Total: <?php echo $total_pwd; ?>
    </p>
</div>
```

## Data Flow

### Registration Flow
1. Student fills registration form → pwd_status and distinguishing_marks captured
2. Form submitted to submit_registration.php
3. distinguishing_marks sanitized for security
4. pwd_status saved to database (default: 'No')
5. distinguishing_marks saved to database (NULL if empty)
6. Student record created with both fields

### Admin View Flow
1. Admin views student list → pwd_status displayed/filterable
2. Admin views student documents → pwd_status and distinguishing_marks shown in personal info
3. Admin edits student → pwd_status and distinguishing_marks editable
4. Admin downloads PDF → pwd_status and distinguishing_marks included

### Reporting Flow
1. Admin generates admission order
2. System counts PWD students from pwd_status field
3. PWD summary displayed separately from category counts

## UI/UX Considerations

### Form Design
- PWD field uses dropdown (Yes/No) for clarity
- Distinguishing marks uses text input for free-form entry
- Both positioned logically after Category field
- Both are optional fields with helpful hint text
- Consistent styling with existing form controls
- Character limit (255) for distinguishing marks

### Display Design
- Badge/icon display for PWD status for visual clarity
- Blue color scheme for PWD indicators
- Wheelchair icon for quick recognition
- Clear Yes/No text labels for PWD
- Plain text display for distinguishing marks
- Show "-" or "None" when distinguishing marks are empty
- Proper HTML escaping for distinguishing marks display

### Accessibility
- Proper label associations
- Screen reader friendly
- Keyboard navigable
- High contrast indicators

## Validation Rules

### Input Validation
- PWD: Accept only 'Yes' or 'No' values
- PWD: Default to 'No' if empty
- Distinguishing marks: Accept any text up to 255 characters
- Distinguishing marks: Trim whitespace
- Distinguishing marks: Allow empty/NULL values
- No special validation required (both optional fields)

### Database Validation
- pwd_status column accepts VARCHAR(3)
- pwd_status NULL allowed for backward compatibility
- pwd_status default value: 'No'
- distinguishing_marks column accepts VARCHAR(255)
- distinguishing_marks NULL allowed (optional field)
- distinguishing_marks no default value (NULL)

## Error Handling

### Database Errors
- If columns don't exist, show admin error message
- Graceful degradation for old records (NULL → 'No' for PWD, NULL → '-' for marks)
- Log errors for debugging

### Form Errors
- No validation errors (both optional fields)
- Handle missing POST data gracefully
- Sanitize distinguishing marks to prevent XSS

## Security Considerations

### Data Privacy
- PWD status is sensitive personal information
- Distinguishing marks are personal identification information
- Only admins can view both fields
- No public display of either field
- Secure database storage

### Input Sanitization
- Validate PWD input values (Yes/No only)
- Sanitize distinguishing marks with htmlspecialchars()
- Use prepared statements for both fields
- Escape output in HTML for both fields
- Prevent XSS attacks through proper sanitization

## Testing Strategy

### Unit Tests
- Test pwd_status capture in registration
- Test pwd_status update in edit form
- Test pwd_status display in views
- Test PWD counting logic
- Test distinguishing_marks capture in registration
- Test distinguishing_marks update in edit form
- Test distinguishing_marks display in views
- Test distinguishing_marks sanitization
- Test empty/NULL handling for distinguishing_marks

### Integration Tests
- Test complete registration flow with PWD and distinguishing marks
- Test admin edit flow with both fields
- Test PDF generation with both fields
- Test admission order with PWD counts and distinguishing marks
- Test XSS prevention in distinguishing marks

### Manual Tests
- Register student with PWD: Yes and distinguishing marks filled
- Register student with PWD: No and distinguishing marks empty
- Register student with PWD: Yes and distinguishing marks empty
- Edit student PWD status and distinguishing marks
- View student documents with both fields
- Generate admission order
- Download PDF form with both fields
- Test XSS attempts in distinguishing marks field

## Rollback Plan

### Database Rollback
```sql
ALTER TABLE students DROP COLUMN pwd_status;
ALTER TABLE students DROP COLUMN distinguishing_marks;
```

### Code Rollback
- Remove pwd_status and distinguishing_marks from all forms
- Remove both fields from all queries
- Remove both fields from all displays
- Restore previous versions of modified files

## Performance Impact

### Database Impact
- Minimal: Two additional columns (VARCHAR(3) + VARCHAR(255))
- Indexed: Not required (low cardinality for PWD, text field for marks)
- Query impact: Negligible

### Application Impact
- No significant performance impact
- Simple field additions
- No complex calculations
- Sanitization adds minimal overhead

## Deployment Steps

1. **Database Migration**
   - Run ALTER TABLE statements for both columns
   - Verify columns added successfully
   - Test with sample data

2. **Code Deployment**
   - Deploy updated files
   - Test registration form with both fields
   - Test admin forms
   - Test PDF generation
   - Test admission orders
   - Test XSS prevention

3. **Verification**
   - Register test student with PWD: Yes and distinguishing marks
   - Verify data saved correctly for both fields
   - Verify display in all views
   - Verify PDF includes both fields
   - Verify admission order counts
   - Verify distinguishing marks are properly sanitized

## Maintenance

### Future Enhancements
- Add PWD type classification (optional)
- Add PWD certificate upload
- Add PWD-specific reports
- Add PWD accommodation tracking
- Add photo upload for distinguishing marks
- Add distinguishing marks verification system
- Add search/filter by distinguishing marks

### Documentation
- Update user manual
- Update admin guide
- Update API documentation (if applicable)
- Update database schema documentation

## Correctness Properties

### Property 1: PWD Status Persistence
**Description:** PWD status must be correctly saved and retrieved  
**Validation:** After registration, pwd_status in database matches form input

### Property 2: PWD Status Independence
**Description:** PWD status is independent of category field  
**Validation:** Student can have any category and any PWD status

### Property 3: PWD Count Accuracy
**Description:** Admission order PWD counts must match actual PWD students  
**Validation:** Manual count of PWD students equals system count

### Property 4: Default Value Handling
**Description:** Missing pwd_status defaults to 'No'  
**Validation:** Records without pwd_status display as 'No'

### Property 5: Display Consistency
**Description:** PWD status displays consistently across all views  
**Validation:** Same PWD status shown in all admin views and PDF

### Property 6: Distinguishing Marks Persistence
**Description:** Distinguishing marks must be correctly saved and retrieved  
**Validation:** After registration, distinguishing_marks in database matches form input

### Property 7: Distinguishing Marks Sanitization
**Description:** Distinguishing marks must be sanitized to prevent XSS  
**Validation:** HTML special characters are escaped in all displays

### Property 8: Empty Distinguishing Marks Handling
**Description:** Empty distinguishing marks display as "-" or "None"  
**Validation:** NULL or empty distinguishing_marks show appropriate placeholder

### Property 9: Distinguishing Marks Display Consistency
**Description:** Distinguishing marks display consistently across all views  
**Validation:** Same distinguishing marks shown in all admin views and PDF

### Property 10: Character Limit Enforcement
**Description:** Distinguishing marks respect 255 character limit  
**Validation:** Input field enforces maxlength, database accepts up to 255 characters
