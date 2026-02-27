# Design Document: Admin Edit Student Document Update

## Overview

This design document specifies the technical implementation for updating the admin panel's `edit_student.php` page to support the new categorized document upload system. The feature extends the existing student editing interface to handle 6 new document categories (Aadhar card, 10th marksheet, 12th marksheet, caste certificate, graduation certificate, and other documents) while maintaining backward compatibility with legacy document fields.

The implementation reuses validated upload logic from `student/submit_registration.php` to ensure consistency across the system. The UI will be organized into logical sections matching the registration form structure, providing administrators with a clear and intuitive interface for managing student documents.

### Key Design Goals

1. Reuse existing validation and upload logic from `submit_registration.php`
2. Maintain backward compatibility with legacy document fields
3. Provide clear visual organization of document categories
4. Ensure secure file handling with validation and malicious content detection
5. Preserve existing documents when not uploading new ones
6. Display comprehensive error messages for failed uploads

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                    Admin Panel (Browser)                     │
│                   admin/edit_student.php                     │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ HTTP POST (multipart/form-data)
                         │
┌────────────────────────▼────────────────────────────────────┐
│              Document Upload Handler                         │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  validateUploadedDocument()                          │  │
│  │  - File type validation                              │  │
│  │  - File size validation                              │  │
│  │  - Malicious content detection                       │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  handleCategorizedUpload()                           │  │
│  │  - Directory creation                                │  │
│  │  - Unique filename generation                        │  │
│  │  - File storage                                      │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ File paths
                         │
┌────────────────────────▼────────────────────────────────────┐
│                   Database Layer                             │
│                   students table                             │
│  - aadhar_card_doc                                          │
│  - tenth_marksheet_doc                                      │
│  - twelfth_marksheet_doc                                    │
│  - caste_certificate_doc                                    │
│  - graduation_certificate_doc                               │
│  - other_documents_doc                                      │
│  - passport_photo (legacy)                                  │
│  - signature (legacy)                                       │
│  - documents (legacy)                                       │
│  - payment_receipt (legacy)                                 │
└─────────────────────────────────────────────────────────────┘
                         │
                         │ File paths
                         │
┌────────────────────────▼────────────────────────────────────┐
│                   File System                                │
│  student/uploads/                                           │
│    ├── aadhar/                                              │
│    ├── caste_certificates/                                  │
│    ├── marksheets/                                          │
│    │   ├── 10th/                                            │
│    │   ├── 12th/                                            │
│    │   └── graduation/                                      │
│    ├── other/                                               │
│    └── students/ (legacy)                                   │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

1. **Page Load**: Admin navigates to `edit_student.php?id={student_id}`
   - Fetch student record from database
   - Display current document paths with preview/download options
   - Render upload fields for all document categories

2. **Form Submission**: Admin submits form with new documents
   - Validate all uploaded files using `validateUploadedDocument()`
   - Process each categorized document using `handleCategorizedUpload()`
   - Update database with new file paths (or preserve existing paths)
   - Display success/error messages

3. **Error Handling**: If any upload fails
   - Collect all error messages
   - Do not update database
   - Delete any files uploaded before the error
   - Display comprehensive error information

## Components and Interfaces

### 1. Document Validation Function

**Function**: `validateUploadedDocument($file, $docCategory)`

**Location**: `student/submit_registration.php` (reused)

**Purpose**: Validates uploaded files for type, size, and malicious content

**Parameters**:
- `$file` (array): PHP `$_FILES` array element
- `$docCategory` (string): Document category identifier

**Returns**: 
```php
[
    'valid' => bool,
    'message' => string  // Error message if invalid
]
```

**Validation Rules**:
- Allowed MIME types: `image/jpeg`, `image/jpg`, `image/png`, `application/pdf`
- Allowed extensions: `jpg`, `jpeg`, `png`, `pdf`
- Max size for images: 5MB
- Max size for PDFs: 10MB
- Content check: Reject files containing `<?php` or `#!/` (shell scripts)

### 2. Document Upload Handler Function

**Function**: `handleCategorizedUpload($file, $docCategory, $student_id)`

**Location**: `student/submit_registration.php` (reused)

**Purpose**: Processes validated file uploads and stores them in categorized directories

**Parameters**:
- `$file` (array): PHP `$_FILES` array element
- `$docCategory` (string): Document category (`aadhar`, `tenth`, `twelfth`, `caste`, `graduation`, `other`)
- `$student_id` (string): Student identifier

**Returns**:
```php
[
    'success' => bool,
    'path' => string,    // Relative file path if success
    'error' => string    // Error message if failure
]
```

**Directory Mapping**:
```php
$subdirs = [
    'aadhar' => 'aadhar',
    'caste' => 'caste_certificates',
    'tenth' => 'marksheets/10th',
    'twelfth' => 'marksheets/12th',
    'graduation' => 'marksheets/graduation',
    'other' => 'other'
];
```

**Filename Pattern**: `{student_id}_{timestamp}_{category}.{extension}`

**Directory Creation**: Creates directories with `0755` permissions if they don't exist

### 3. Document Display Component

**Purpose**: Display current documents with preview and download options

**Implementation**:
```php
<?php if (!empty($student['aadhar_card_doc'])): ?>
    <div class="document-preview">
        <?php 
        $ext = pathinfo($student['aadhar_card_doc'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])): 
        ?>
            <img src="<?php echo APP_URL . '/' . $student['aadhar_card_doc']; ?>" 
                 alt="Aadhar Card" class="preview-image">
        <?php else: ?>
            <div class="pdf-preview">
                <i class="fas fa-file-pdf"></i>
                <span>PDF Document</span>
            </div>
        <?php endif; ?>
        <div class="document-actions">
            <a href="<?php echo APP_URL . '/' . $student['aadhar_card_doc']; ?>" 
               target="_blank" class="btn btn-sm btn-primary">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="<?php echo APP_URL . '/' . $student['aadhar_card_doc']; ?>" 
               download class="btn btn-sm btn-secondary">
                <i class="fas fa-download"></i> Download
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="document-not-uploaded">
        <i class="fas fa-times-circle"></i>
        <span>Not uploaded</span>
    </div>
<?php endif; ?>
```

### 4. Form Processing Logic

**Location**: `admin/edit_student.php` (to be updated)

**Pseudocode**:
```
IF form submitted THEN
    // Initialize document paths with existing values
    FOR EACH document_category IN [aadhar, tenth, twelfth, caste, graduation, other] DO
        document_path[category] = student[category + '_doc']
    END FOR
    
    // Process new uploads
    errors = []
    uploaded_files = []
    
    FOR EACH document_category IN [aadhar, tenth, twelfth, caste, graduation, other] DO
        IF file uploaded for category THEN
            result = handleCategorizedUpload(file, category, student_id)
            IF result.success THEN
                document_path[category] = result.path
                uploaded_files.append(result.path)
            ELSE
                errors.append("Category: " + result.error)
            END IF
        END IF
    END FOR
    
    // Handle errors
    IF errors not empty THEN
        // Delete any successfully uploaded files
        FOR EACH file IN uploaded_files DO
            delete_file(file)
        END FOR
        
        // Display all errors
        display_errors(errors)
        exit
    END IF
    
    // Update database
    UPDATE students SET
        aadhar_card_doc = document_path[aadhar],
        tenth_marksheet_doc = document_path[tenth],
        twelfth_marksheet_doc = document_path[twelfth],
        caste_certificate_doc = document_path[caste],
        graduation_certificate_doc = document_path[graduation],
        other_documents_doc = document_path[other],
        ... (other fields)
    WHERE student_id = student_id
    
    display_success("Student updated successfully")
END IF
```

### 5. UI Organization Structure

**Section 1: Photo & Signature**
- passport_photo (legacy, mandatory)
- signature (legacy, mandatory)

**Section 2: Identity Proof**
- aadhar_card_doc (mandatory)

**Section 3: Educational Qualifications**
- tenth_marksheet_doc (mandatory)
- twelfth_marksheet_doc (optional)
- graduation_certificate_doc (optional)

**Section 4: Additional Documents**
- caste_certificate_doc (optional)
- other_documents_doc (optional)

**Section 5: Payment Information**
- payment_receipt (legacy, optional)

**HTML Structure**:
```html
<div class="form-section">
    <h5 class="section-title">
        <i class="fas fa-id-card"></i> Identity Proof
        <span class="badge badge-required">Required</span>
    </h5>
    
    <div class="form-group">
        <label class="form-label">Aadhar Card *</label>
        
        <!-- Current document display -->
        <div class="current-document">
            <!-- Preview/download UI here -->
        </div>
        
        <!-- Upload field -->
        <input type="file" name="aadhar_card_doc" 
               class="form-control" 
               accept=".jpg,.jpeg,.png,.pdf">
        <small class="file-info">
            Upload new document (JPG/PNG/PDF, max 5MB for images, 10MB for PDF)
        </small>
    </div>
</div>
```

## Data Models

### Database Schema

**Table**: `students`

**Existing Columns** (no changes):
- `student_id` VARCHAR(50) PRIMARY KEY
- `name`, `father_name`, `mother_name`, etc. (personal info)
- `passport_photo` VARCHAR(255) - Legacy document
- `signature` VARCHAR(255) - Legacy document
- `documents` VARCHAR(255) - Legacy document
- `payment_receipt` VARCHAR(255) - Legacy document

**New Columns** (already added in previous migration):
- `aadhar_card_doc` VARCHAR(255) NULL
- `tenth_marksheet_doc` VARCHAR(255) NULL
- `twelfth_marksheet_doc` VARCHAR(255) NULL
- `caste_certificate_doc` VARCHAR(255) NULL
- `graduation_certificate_doc` VARCHAR(255) NULL
- `other_documents_doc` VARCHAR(255) NULL

### File System Structure

```
student/uploads/
├── aadhar/
│   └── {student_id}_{timestamp}_aadhar.{ext}
├── caste_certificates/
│   └── {student_id}_{timestamp}_caste.{ext}
├── marksheets/
│   ├── 10th/
│   │   └── {student_id}_{timestamp}_tenth.{ext}
│   ├── 12th/
│   │   └── {student_id}_{timestamp}_twelfth.{ext}
│   └── graduation/
│       └── {student_id}_{timestamp}_graduation.{ext}
├── other/
│   └── {student_id}_{timestamp}_other.{ext}
└── students/ (legacy)
    └── {uniqid}_{original_filename}
```

### Document Category Mapping

```php
$documentCategories = [
    'aadhar_card_doc' => [
        'label' => 'Aadhar Card',
        'category' => 'aadhar',
        'required' => true,
        'section' => 'Identity Proof'
    ],
    'tenth_marksheet_doc' => [
        'label' => '10th Marksheet',
        'category' => 'tenth',
        'required' => true,
        'section' => 'Educational Qualifications'
    ],
    'twelfth_marksheet_doc' => [
        'label' => '12th Marksheet',
        'category' => 'twelfth',
        'required' => false,
        'section' => 'Educational Qualifications'
    ],
    'caste_certificate_doc' => [
        'label' => 'Caste Certificate',
        'category' => 'caste',
        'required' => false,
        'section' => 'Additional Documents'
    ],
    'graduation_certificate_doc' => [
        'label' => 'Graduation Certificate',
        'category' => 'graduation',
        'required' => false,
        'section' => 'Educational Qualifications'
    ],
    'other_documents_doc' => [
        'label' => 'Other Documents',
        'category' => 'other',
        'required' => false,
        'section' => 'Additional Documents'
    ]
];
```

