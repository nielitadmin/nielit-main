# Design Document: Document Upload Enhancement

## Overview

This design document outlines the technical approach for restructuring the document upload system in the student registration form. The enhancement transforms a single generic "documents" field into six clearly categorized document upload fields with proper validation, organized storage, and backward compatibility.

### Current System Analysis

The existing system uses:
- Single `documents` column in the students table
- Generic file upload field in student/register.php
- Basic file type validation
- Flat file storage in uploads/ directory
- Single document view in admin interfaces

### Proposed Enhancement

The enhanced system will provide:
- Six categorized document fields (Aadhar, Caste Certificate, 10th, 12th, Graduation, Other)
- Dual validation (client-side and server-side)
- Organized database schema with separate columns
- Structured file storage with subdirectories
- Enhanced admin interfaces for categorized document management
- Full backward compatibility with existing records

## Architecture

### Component Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Student Registration Form                 │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Document Upload Section (6 categorized fields)      │  │
│  │  - Client-side validation (JavaScript)               │  │
│  │  - Visual indicators (mandatory/optional)            │  │
│  │  - Format hints and tooltips                         │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│              Form Submission Handler (PHP)                   │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Server-side Validation                              │  │
│  │  - File type verification (MIME + extension)         │  │
│  │  - File size limits                                  │  │
│  │  - Security checks                                   │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  File Storage Manager                                │  │
│  │  - Unique filename generation                        │  │
│  │  - Organized directory structure                     │  │
│  │  - File move operations                              │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    Database Layer (MySQL)                    │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Students Table (Enhanced Schema)                    │  │
│  │  - aadhar_card_doc                                   │  │
│  │  - caste_certificate_doc                             │  │
│  │  - tenth_marksheet_doc                               │  │
│  │  - twelfth_marksheet_doc                             │  │
│  │  - graduation_certificate_doc                        │  │
│  │  - other_documents_doc                               │  │
│  │  - documents (legacy, maintained for compatibility) │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   Admin Interfaces                           │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Students List (admin/students.php)                  │  │
│  │  - Document status indicators                        │  │
│  │  - Category completion badges                        │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Edit Student (admin/edit_student.php)               │  │
│  │  - Individual category upload fields                 │  │
│  │  - Replace/delete per category                       │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  View Documents (admin/view_student_documents.php)   │  │
│  │  - Categorized document display                      │  │
│  │  - Preview and download options                      │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

1. **Student Registration Flow**
   - Student fills registration form with personal/academic details
   - Student uploads documents in categorized fields
   - Client-side JavaScript validates file types before submission
   - Form submits to submit_registration.php
   - Server validates all files (type, size, security)
   - Files are moved to organized subdirectories
   - Database record created with categorized document paths
   - Success page displays with confirmation

2. **Admin View Flow**
   - Admin accesses students.php
   - System queries students table with document columns
   - For each student, system checks which document categories are populated
   - Admin interface displays status indicators per category
   - Admin can click to view detailed documents page
   - System retrieves and displays all categorized documents
   - Admin can download or view individual documents

3. **Admin Edit Flow**
   - Admin accesses edit_student.php with student ID
   - System loads existing student data including document paths
   - Admin interface displays current documents with replace options
   - Admin can upload new files for specific categories
   - On save, system validates new uploads
   - System updates only modified document categories
   - Existing documents remain unchanged if not replaced

## Components and Interfaces

### 1. Database Schema Enhancement

**Migration Script: `migrations/add_document_categories.sql`**

```sql
ALTER TABLE students
ADD COLUMN aadhar_card_doc VARCHAR(255) NULL AFTER documents,
ADD COLUMN caste_certificate_doc VARCHAR(255) NULL AFTER aadhar_card_doc,
ADD COLUMN tenth_marksheet_doc VARCHAR(255) NULL AFTER caste_certificate_doc,
ADD COLUMN twelfth_marksheet_doc VARCHAR(255) NULL AFTER tenth_marksheet_doc,
ADD COLUMN graduation_certificate_doc VARCHAR(255) NULL AFTER twelfth_marksheet_doc,
ADD COLUMN other_documents_doc VARCHAR(255) NULL AFTER graduation_certificate_doc;

-- Add indexes for faster queries
CREATE INDEX idx_aadhar_doc ON students(aadhar_card_doc);
CREATE INDEX idx_tenth_doc ON students(tenth_marksheet_doc);
```

**Column Specifications:**
- All new columns are VARCHAR(255) to store relative file paths
- All columns allow NULL for optional documents
- Legacy `documents` column remains unchanged
- Indexes added for frequently queried mandatory documents

### 2. File Storage Structure

**Directory Organization:**

```
uploads/
├── documents/              # Legacy documents (preserved)
├── aadhar/                 # Aadhar card uploads
├── caste_certificates/     # Caste certificate uploads
├── marksheets/
│   ├── 10th/              # 10th marksheet uploads
│   ├── 12th/              # 12th/Diploma uploads
│   └── graduation/        # Graduation certificate uploads
└── other/                  # Miscellaneous documents
```

**Filename Convention:**
```
{student_id}_{timestamp}_{category}.{extension}
Example: STU2024001_1704067200_aadhar.pdf
```

### 3. Validation Service

**Client-Side Validation (JavaScript)**

Location: `student/register.php` (inline script)

```javascript
function validateDocumentUpload(inputElement, allowedTypes) {
    const file = inputElement.files[0];
    if (!file) return true; // Optional fields can be empty
    
    const fileName = file.name.toLowerCase();
    const fileExtension = fileName.substring(fileName.lastIndexOf('.'));
    const allowedExtensions = ['.jpg', '.jpeg', '.pdf'];
    
    if (!allowedExtensions.includes(fileExtension)) {
        return {
            valid: false,
            message: `Invalid file type. Allowed: JPG, JPEG, PDF`
        };
    }
    
    const maxSize = fileExtension === '.pdf' ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
    if (file.size > maxSize) {
        return {
            valid: false,
            message: `File too large. Maximum: ${maxSize / (1024 * 1024)}MB`
        };
    }
    
    return { valid: true };
}
```

**Server-Side Validation (PHP)**

Location: `submit_registration.php`

```php
function validateUploadedDocument($file, $category) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'application/pdf'];
    $allowedExtensions = ['jpg', 'jpeg', 'pdf'];
    
    // Check if file was uploaded
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'File upload error'];
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['valid' => false, 'message' => 'Invalid file type'];
    }
    
    // Validate extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['valid' => false, 'message' => 'Invalid file extension'];
    }
    
    // Validate size
    $maxSize = ($extension === 'pdf') ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'message' => 'File too large'];
    }
    
    // Security: Check for executable content
    $content = file_get_contents($file['tmp_name'], false, null, 0, 1024);
    if (strpos($content, '<?php') !== false || strpos($content, '#!/') !== false) {
        return ['valid' => false, 'message' => 'Invalid file content'];
    }
    
    return ['valid' => true];
}
```

### 4. File Upload Handler

**Function: `handleCategorizedUpload()`**

Location: `submit_registration.php`

```php
function handleCategorizedUpload($file, $category, $student_id) {
    // Validate file
    $validation = validateUploadedDocument($file, $category);
    if (!$validation['valid']) {
        return ['success' => false, 'error' => $validation['message']];
    }
    
    // Determine subdirectory based on category
    $subdirs = [
        'aadhar' => 'aadhar',
        'caste' => 'caste_certificates',
        'tenth' => 'marksheets/10th',
        'twelfth' => 'marksheets/12th',
        'graduation' => 'marksheets/graduation',
        'other' => 'other'
    ];
    
    $subdir = $subdirs[$category] ?? 'other';
    $uploadDir = __DIR__ . '/uploads/' . $subdir . '/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $student_id . '_' . time() . '_' . $category . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Return relative path for database storage
        $relativePath = 'uploads/' . $subdir . '/' . $filename;
        return ['success' => true, 'path' => $relativePath];
    }
    
    return ['success' => false, 'error' => 'Failed to move uploaded file'];
}
```

### 5. Registration Form Updates

**Location: `student/register.php`**

**HTML Structure for Document Upload Section:**

```html
<div class="form-section">
    <div class="section-header">
        <div class="section-icon">
            <i class="fas fa-file-upload"></i>
        </div>
        <div>
            <h3 class="section-title">Document Uploads</h3>
            <p class="section-subtitle">Upload required documents (JPG, JPEG, or PDF format)</p>
        </div>
    </div>
    
    <!-- Mandatory Documents -->
    <div class="document-category mandatory">
        <h4 class="category-title">
            <i class="fas fa-id-card"></i> Identity Proof
            <span class="required-badge">Required</span>
        </h4>
        
        <div class="form-group">
            <label class="form-label">
                Aadhar Card <span class="required-mark">*</span>
            </label>
            <input type="file" 
                   name="aadhar_card" 
                   id="aadhar_card"
                   class="form-control" 
                   accept=".jpg,.jpeg,.pdf"
                   required
                   data-category="aadhar">
            <small class="text-muted">
                <i class="fas fa-info-circle"></i> 
                Accepted formats: JPG, JPEG, PDF (Max 5MB)
            </small>
        </div>
    </div>
    
    <!-- Educational Documents -->
    <div class="document-category mandatory">
        <h4 class="category-title">
            <i class="fas fa-graduation-cap"></i> Educational Qualifications
            <span class="required-badge">Required</span>
        </h4>
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">
                        10th Marksheet/Certificate <span class="required-mark">*</span>
                    </label>
                    <input type="file" 
                           name="tenth_marksheet" 
                           id="tenth_marksheet"
                           class="form-control" 
                           accept=".jpg,.jpeg,.pdf"
                           required
                           data-category="tenth">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Accepted formats: JPG, JPEG, PDF (Max 5MB)
                    </small>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">
                        12th Marksheet/Diploma Certificate <span class="required-mark">*</span>
                    </label>
                    <input type="file" 
                           name="twelfth_marksheet" 
                           id="twelfth_marksheet"
                           class="form-control" 
                           accept=".jpg,.jpeg,.pdf"
                           required
                           data-category="twelfth">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Accepted formats: JPG, JPEG, PDF (Max 5MB)
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Optional Documents -->
    <div class="document-category optional">
        <h4 class="category-title">
            <i class="fas fa-folder-plus"></i> Additional Documents
            <span class="optional-badge">Optional</span>
        </h4>
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Caste Certificate</label>
                    <input type="file" 
                           name="caste_certificate" 
                           id="caste_certificate"
                           class="form-control" 
                           accept=".jpg,.jpeg,.pdf"
                           data-category="caste">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        For applicable students only
                    </small>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Graduation Certificate</label>
                    <input type="file" 
                           name="graduation_certificate" 
                           id="graduation_certificate"
                           class="form-control" 
                           accept=".jpg,.jpeg,.pdf"
                           data-category="graduation">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        If applicable
                    </small>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Other Supporting Documents</label>
            <input type="file" 
                   name="other_documents" 
                   id="other_documents"
                   class="form-control" 
                   accept=".jpg,.jpeg,.pdf"
                   data-category="other">
            <small class="text-muted">
                <i class="fas fa-info-circle"></i> 
                Any additional supporting documents
            </small>
        </div>
    </div>
</div>
```

**CSS Styling:**

```css
.document-category {
    background: #f8fafc;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    border: 2px solid #e2e8f0;
}

.document-category.mandatory {
    border-left: 5px solid #dc2626;
}

.document-category.optional {
    border-left: 5px solid #3b82f6;
}

.category-title {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.required-badge {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-left: auto;
}

.optional-badge {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-left: auto;
}
```

## Data Models

### Enhanced Students Table Schema

```sql
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    course VARCHAR(255) NOT NULL,
    
    -- Personal details
    father_name VARCHAR(255),
    mother_name VARCHAR(255),
    dob DATE,
    age INT,
    gender ENUM('Male', 'Female', 'Other'),
    aadhar VARCHAR(20),
    apaar_id VARCHAR(50),
    category ENUM('General', 'OBC', 'SC', 'ST', 'EWS'),
    pwd_status ENUM('Yes', 'No') DEFAULT 'No',
    
    -- Contact details
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    pincode VARCHAR(10),
    
    -- Legacy document field (preserved for backward compatibility)
    documents VARCHAR(255),
    
    -- New categorized document fields
    aadhar_card_doc VARCHAR(255),
    caste_certificate_doc VARCHAR(255),
    tenth_marksheet_doc VARCHAR(255),
    twelfth_marksheet_doc VARCHAR(255),
    graduation_certificate_doc VARCHAR(255),
    other_documents_doc VARCHAR(255),
    
    -- Other fields
    passport_photo VARCHAR(255),
    signature VARCHAR(255),
    payment_receipt VARCHAR(255),
    status ENUM('pending', 'active', 'inactive', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_student_id (student_id),
    INDEX idx_status (status),
    INDEX idx_aadhar_doc (aadhar_card_doc),
    INDEX idx_tenth_doc (tenth_marksheet_doc)
);
```

### Document Category Mapping

```php
// Document category configuration
$documentCategories = [
    'aadhar_card' => [
        'db_column' => 'aadhar_card_doc',
        'label' => 'Aadhar Card',
        'required' => true,
        'subdirectory' => 'aadhar',
        'max_size' => 5 * 1024 * 1024, // 5MB
        'allowed_types' => ['image/jpeg', 'image/jpg', 'application/pdf']
    ],
    'caste_certificate' => [
        'db_column' => 'caste_certificate_doc',
        'label' => 'Caste Certificate',
        'required' => false,
        'subdirectory' => 'caste_certificates',
        'max_size' => 5 * 1024 * 1024,
        'allowed_types' => ['image/jpeg', 'image/jpg', 'application/pdf']
    ],
    'tenth_marksheet' => [
        'db_column' => 'tenth_marksheet_doc',
        'label' => '10th Marksheet/Certificate',
        'required' => true,
        'subdirectory' => 'marksheets/10th',
        'max_size' => 5 * 1024 * 1024,
        'allowed_types' => ['image/jpeg', 'image/jpg', 'application/pdf']
    ],
    'twelfth_marksheet' => [
        'db_column' => 'twelfth_marksheet_doc',
        'label' => '12th Marksheet/Diploma Certificate',
        'required' => true,
        'subdirectory' => 'marksheets/12th',
        'max_size' => 5 * 1024 * 1024,
        'allowed_types' => ['image/jpeg', 'image/jpg', 'application/pdf']
    ],
    'graduation_certificate' => [
        'db_column' => 'graduation_certificate_doc',
        'label' => 'Graduation Certificate',
        'required' => false,
        'subdirectory' => 'marksheets/graduation',
        'max_size' => 5 * 1024 * 1024,
        'allowed_types' => ['image/jpeg', 'image/jpg', 'application/pdf']
    ],
    'other_documents' => [
        'db_column' => 'other_documents_doc',
        'label' => 'Other Supporting Documents',
        'required' => false,
        'subdirectory' => 'other',
        'max_size' => 10 * 1024 * 1024, // 10MB for other docs
        'allowed_types' => ['image/jpeg', 'image/jpg', 'application/pdf']
    ]
];
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: File Type Validation Consistency

*For any* uploaded document file, if it passes client-side validation, then it must also pass server-side validation with the same criteria (file extension and MIME type matching allowed types).

**Validates: Requirements 2.1, 2.2, 2.4, 2.5**

### Property 2: Mandatory Document Enforcement

*For any* student registration submission, the system must reject the submission if any mandatory document field (Aadhar Card, 10th Marksheet, 12th Marksheet) is empty or contains an invalid file.

**Validates: Requirements 1.3, 4.6**

### Property 3: Database Column Mapping Integrity

*For any* uploaded document in a specific category, the file path stored in the database must be in the corresponding category-specific column (e.g., aadhar_card uploads go to aadhar_card_doc column).

**Validates: Requirements 3.1, 3.4, 5.4**

### Property 4: File Storage Path Consistency

*For any* uploaded document, the file path stored in the database must point to an actual file that exists in the filesystem at that exact location.

**Validates: Requirements 3.2, 5.3, 11.1**

### Property 5: Backward Compatibility Preservation

*For any* legacy student record (where new categorized columns are NULL), the system must successfully retrieve and display documents from the legacy "documents" column without errors.

**Validates: Requirements 9.1, 9.2, 9.4**

### Property 6: Unique Filename Generation

*For any* two document uploads, even if uploaded simultaneously for the same student, the generated filenames must be unique to prevent file collisions.

**Validates: Requirements 5.2, 11.2**

### Property 7: File Size Limit Enforcement

*For any* uploaded file, if the file size exceeds the maximum limit for its category (5MB for images, 10MB for PDFs), then the system must reject the upload and display an appropriate error message.

**Validates: Requirements 2.6, 5.6, 10.2**

### Property 8: Security Validation

*For any* uploaded file, if the file content contains executable code patterns (PHP tags, shell scripts), then the system must reject the upload regardless of file extension.

**Validates: Requirements 12.1, 12.4**

### Property 9: Admin Edit Preservation

*For any* student record being edited, if an administrator does not upload a new file for a specific document category, then the existing document path for that category must remain unchanged in the database.

**Validates: Requirements 7.5**

### Property 10: Error Message Specificity

*For any* validation failure during document upload, the error message displayed must specifically identify which document category failed and what the validation issue was (format, size, or security).

**Validates: Requirements 2.6, 10.1, 10.2, 10.3**

## Error Handling

### Validation Errors

**Client-Side Error Handling:**
- Display inline error messages below the affected upload field
- Prevent form submission until all errors are resolved
- Highlight invalid fields with red borders
- Show error icon next to field label

**Server-Side Error Handling:**
- Collect all validation errors before returning response
- Preserve all form data in session for re-display
- Display consolidated error summary at top of form
- Highlight specific fields that failed validation
- Log validation failures for security monitoring

### File Upload Errors

**Upload Failure Scenarios:**
1. **File too large**: Display size limit and current file size
2. **Invalid format**: List allowed formats
3. **Upload interrupted**: Suggest retry
4. **Disk space full**: Admin notification, user-friendly message
5. **Permission denied**: Log error, display generic message

**Error Recovery:**
- Transaction rollback if any file upload fails
- Cleanup of partially uploaded files
- Preserve form state for user correction
- Detailed error logging for debugging

### Database Errors

**Insert/Update Failures:**
- Rollback file uploads if database operation fails
- Delete uploaded files to prevent orphaned files
- Display user-friendly error message
- Log detailed error for admin investigation

### Backward Compatibility Errors

**Legacy Record Handling:**
- Gracefully handle NULL values in new columns
- Fall back to legacy "documents" column if new columns are empty
- Display migration prompt for legacy records
- Log records that need migration

## Testing Strategy

### Unit Testing

**File Validation Tests:**
- Test valid file types (JPG, JPEG, PDF)
- Test invalid file types (EXE, PHP, SH)
- Test file size limits (under limit, at limit, over limit)
- Test MIME type validation
- Test security content scanning

**Filename Generation Tests:**
- Test uniqueness with concurrent uploads
- Test special character handling
- Test length limits
- Test collision prevention

**Database Operation Tests:**
- Test INSERT with all document categories
- Test UPDATE with selective category updates
- Test backward compatibility queries
- Test NULL handling

### Integration Testing

**End-to-End Registration Flow:**
1. Fill registration form with all required fields
2. Upload documents in all categories
3. Submit form
4. Verify database record created correctly
5. Verify files stored in correct directories
6. Verify file paths in database match actual files

**Admin Interface Testing:**
1. View student list with document indicators
2. Click to view detailed documents page
3. Verify all categories displayed correctly
4. Test document preview/download
5. Edit student and replace specific documents
6. Verify only replaced documents updated

**Backward Compatibility Testing:**
1. Create test records with legacy document format
2. Verify legacy documents display correctly
3. Edit legacy record and add categorized documents
4. Verify migration from legacy to categorized format
5. Test mixed scenarios (some categories filled, some legacy)

### Property-Based Testing

Each correctness property should be implemented as a property-based test with minimum 100 iterations:

**Property Test Example (File Type Validation Consistency):**
```php
// Feature: document-upload-enhancement, Property 1: File Type Validation Consistency
function testFileTypeValidationConsistency() {
    for ($i = 0; $i < 100; $i++) {
        // Generate random file with valid extension
        $file = generateRandomValidFile();
        
        // Test client-side validation
        $clientValid = clientSideValidation($file);
        
        // Test server-side validation
        $serverValid = serverSideValidation($file);
        
        // Property: If client says valid, server must also say valid
        if ($clientValid) {
            assert($serverValid, "File passed client validation but failed server validation");
        }
    }
}
```

### Manual Testing Checklist

**Student Registration:**
- [ ] All document fields visible and labeled correctly
- [ ] Mandatory fields marked with asterisk
- [ ] Optional fields clearly indicated
- [ ] File format hints displayed
- [ ] Client-side validation prevents invalid uploads
- [ ] Error messages clear and specific
- [ ] Success confirmation shows uploaded documents
- [ ] Files stored in correct subdirectories

**Admin Views:**
- [ ] Students list shows document status indicators
- [ ] Document view page displays all categories
- [ ] Image previews work correctly
- [ ] PDF download works correctly
- [ ] Missing documents show appropriate indicators
- [ ] Edit page allows selective document replacement
- [ ] Legacy records display correctly

**Edge Cases:**
- [ ] Upload maximum size files
- [ ] Upload files with special characters in names
- [ ] Upload same file to multiple categories
- [ ] Edit student without changing documents
- [ ] Edit student and replace all documents
- [ ] View student with no documents uploaded
- [ ] View legacy student record

### Performance Testing

**Load Testing:**
- Test concurrent uploads from multiple users
- Test large file uploads (near size limits)
- Test database query performance with large datasets
- Test file system performance with many files

**Optimization Targets:**
- File upload: < 5 seconds for 5MB file
- Form validation: < 500ms
- Database insert: < 1 second
- Admin page load: < 2 seconds with 1000 students
