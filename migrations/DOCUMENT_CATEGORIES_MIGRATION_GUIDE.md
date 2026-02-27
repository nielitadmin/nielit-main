# Document Categories Migration Guide

## Overview

This migration adds six categorized document columns to the `students` table, enabling organized document management for the student registration system. The migration is part of the **document-upload-enhancement** feature.

## What This Migration Does

### Columns Added

The migration adds the following columns to the `students` table:

1. **aadhar_card_doc** (VARCHAR 255, NULL) - Path to Aadhar card document
2. **caste_certificate_doc** (VARCHAR 255, NULL) - Path to caste certificate document
3. **tenth_marksheet_doc** (VARCHAR 255, NULL) - Path to 10th marksheet/certificate
4. **twelfth_marksheet_doc** (VARCHAR 255, NULL) - Path to 12th marksheet/diploma certificate
5. **graduation_certificate_doc** (VARCHAR 255, NULL) - Path to graduation certificate
6. **other_documents_doc** (VARCHAR 255, NULL) - Path to other supporting documents

### Indexes Created

For performance optimization, indexes are created on frequently queried mandatory document columns:

- **idx_aadhar_doc** on `aadhar_card_doc`
- **idx_tenth_doc** on `tenth_marksheet_doc`
- **idx_twelfth_doc** on `twelfth_marksheet_doc`

### Backward Compatibility

The legacy `documents` column is **preserved** to maintain backward compatibility with existing records. New registrations will use the categorized columns, while legacy records can continue to use the old column.

## Installation Methods

### Method 1: Using PHP Script (Recommended)

The PHP installation script provides automated installation with validation and rollback capability.

#### Install the Migration

```bash
cd migrations
php install_document_categories.php install
```

Or with full path (Windows XAMPP):
```bash
C:\xampp\php\php.exe migrations/install_document_categories.php install
```

**Expected Output:**
```
=== Installing Document Category Columns ===

Adding document category columns...
  ✓ Added column: aadhar_card_doc
  ✓ Added column: caste_certificate_doc
  ✓ Added column: tenth_marksheet_doc
  ✓ Added column: twelfth_marksheet_doc
  ✓ Added column: graduation_certificate_doc
  ✓ Added column: other_documents_doc

Creating indexes for frequently queried columns...
  ✓ Created index: idx_aadhar_doc on aadhar_card_doc
  ✓ Created index: idx_tenth_doc on tenth_marksheet_doc
  ✓ Created index: idx_twelfth_doc on twelfth_marksheet_doc

✓ Installation completed successfully!
```

#### Verify the Installation

```bash
php install_document_categories.php verify
```

**Expected Output:**
```
=== Verifying Document Category Installation ===

Checking document category columns...
  ✓ Column 'aadhar_card_doc' exists
  ✓ Column 'caste_certificate_doc' exists
  ✓ Column 'tenth_marksheet_doc' exists
  ✓ Column 'twelfth_marksheet_doc' exists
  ✓ Column 'graduation_certificate_doc' exists
  ✓ Column 'other_documents_doc' exists

Checking indexes...
  ✓ Index 'idx_aadhar_doc' exists
  ✓ Index 'idx_tenth_doc' exists
  ✓ Index 'idx_twelfth_doc' exists

Checking data integrity...
  ✓ Total students in database: 1029

✓ All checks passed! Installation is valid.
```

#### Rollback (Remove Migration)

```bash
php install_document_categories.php rollback
```

**WARNING:** This will permanently remove all document category columns and their data. The legacy `documents` column will be preserved.

You will be prompted to confirm:
```
Type 'yes' to confirm rollback:
```

### Method 2: Using SQL File

If you prefer to run the SQL directly:

```bash
mysql -u root -p nielit_bhubaneswar < migrations/add_document_categories.sql
```

Or import via phpMyAdmin:
1. Open phpMyAdmin
2. Select the `nielit_bhubaneswar` database
3. Go to Import tab
4. Choose `migrations/add_document_categories.sql`
5. Click Go

To rollback using SQL:
```bash
mysql -u root -p nielit_bhubaneswar < migrations/rollback_document_categories.sql
```

## Testing

### Automated Testing

Run the manual test script to verify all operations work correctly:

```bash
php migrations/manual_test_document_columns.php
```

This test script will:
1. Insert test records with document paths
2. Query records to verify data integrity
3. Update specific document columns
4. Test indexed queries
5. Test NULL values for optional fields
6. Clean up test data

**Expected Output:**
```
=== Manual Test: Document Category Columns ===

Test 1: Inserting test student with document paths...
  ✓ Test record inserted successfully

Test 2: Querying test student to verify data...
  ✓ Record found

Test 3: Updating specific document columns...
  ✓ Updated aadhar_card_doc and tenth_marksheet_doc
  ✓ Update verified successfully

Test 4: Testing indexed queries...
  ✓ Query using idx_aadhar_doc: Found X students with Aadhar documents
  ✓ Query using idx_tenth_doc: Found X students with 10th marksheets

Test 5: Testing NULL values for optional fields...
  ✓ Inserted record with only mandatory documents
  ✓ Optional fields are NULL as expected

Cleanup: Deleting test records...
  ✓ Test records deleted successfully

=== All Manual Tests Completed Successfully! ===
```

### Manual Verification

You can manually verify the migration using SQL queries:

```sql
-- Check if columns exist
SHOW COLUMNS FROM students LIKE '%_doc';

-- Check if indexes exist
SHOW INDEX FROM students WHERE Key_name LIKE 'idx_%_doc';

-- View column details
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'students' 
AND COLUMN_NAME IN ('aadhar_card_doc', 'caste_certificate_doc', 
                    'tenth_marksheet_doc', 'twelfth_marksheet_doc', 
                    'graduation_certificate_doc', 'other_documents_doc');

-- Test inserting data
INSERT INTO students (student_id, name, email, mobile, course, 
                      aadhar_card_doc, tenth_marksheet_doc, twelfth_marksheet_doc)
VALUES ('TEST123', 'Test Student', 'test@example.com', '9999999999', 'Test Course',
        'uploads/aadhar/test.pdf', 'uploads/marksheets/10th/test.pdf', 
        'uploads/marksheets/12th/test.pdf');

-- Verify the insert
SELECT student_id, aadhar_card_doc, tenth_marksheet_doc, twelfth_marksheet_doc
FROM students WHERE student_id = 'TEST123';

-- Clean up
DELETE FROM students WHERE student_id = 'TEST123';
```

## Features

### Safe to Run Multiple Times

The PHP installation script checks if each migration step has already been applied and skips it if necessary. This means you can safely run `install` multiple times without errors.

### Rollback Capability

The script provides complete rollback functionality to remove all changes. This is useful for:
- Testing
- Development
- Emergency rollback in production

### Data Validation

The script validates:
- Column existence before adding
- Index existence before creating
- Data integrity after installation

### Colored Output

When run from CLI, the script provides colored output:
- 🟢 Green: Success messages
- 🔴 Red: Error messages
- 🟡 Yellow: Warning/info messages
- 🔵 Blue: Progress messages

## Database Schema Changes

### Before Migration

```sql
students (
    ...
    documents VARCHAR(255),  -- Legacy single document field
    ...
)
```

### After Migration

```sql
students (
    ...
    documents VARCHAR(255),                    -- Legacy (preserved)
    aadhar_card_doc VARCHAR(255) NULL,         -- NEW
    caste_certificate_doc VARCHAR(255) NULL,   -- NEW
    tenth_marksheet_doc VARCHAR(255) NULL,     -- NEW
    twelfth_marksheet_doc VARCHAR(255) NULL,   -- NEW
    graduation_certificate_doc VARCHAR(255) NULL, -- NEW
    other_documents_doc VARCHAR(255) NULL,     -- NEW
    ...
)

-- Indexes
INDEX idx_aadhar_doc (aadhar_card_doc)
INDEX idx_tenth_doc (tenth_marksheet_doc)
INDEX idx_twelfth_doc (twelfth_marksheet_doc)
```

## Usage Examples

### Inserting a New Student with Documents

```php
$sql = "INSERT INTO students (
    student_id, name, email, mobile, course,
    aadhar_card_doc, tenth_marksheet_doc, twelfth_marksheet_doc
) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssss", 
    $studentId, $name, $email, $mobile, $course,
    $aadharPath, $tenthPath, $twelfthPath
);
$stmt->execute();
```

### Updating Specific Document Categories

```php
// Update only Aadhar card document, leave others unchanged
$sql = "UPDATE students SET aadhar_card_doc = ? WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $newAadharPath, $studentId);
$stmt->execute();
```

### Querying Students by Document Status

```php
// Find students with missing Aadhar documents
$sql = "SELECT * FROM students WHERE aadhar_card_doc IS NULL";
$result = $conn->query($sql);

// Find students with all mandatory documents
$sql = "SELECT * FROM students 
        WHERE aadhar_card_doc IS NOT NULL 
        AND tenth_marksheet_doc IS NOT NULL 
        AND twelfth_marksheet_doc IS NOT NULL";
$result = $conn->query($sql);
```

### Handling Legacy Records

```php
// Check if student has new categorized documents or legacy documents
$sql = "SELECT aadhar_card_doc, documents FROM students WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['aadhar_card_doc']) {
    // Use new categorized document
    $documentPath = $row['aadhar_card_doc'];
} else if ($row['documents']) {
    // Fall back to legacy document
    $documentPath = $row['documents'];
}
```

## Troubleshooting

### "Table 'students' doesn't exist"

Make sure you're running the script in the correct database. Check your `config/database.php` file.

### "Column already exists"

This is normal if you've run the migration before. The script will skip existing columns and continue.

### "Access denied"

Check your database credentials in `config/database.php`.

### Script hangs during rollback

The rollback command requires confirmation. Type `yes` and press Enter when prompted.

### PHP not found in PATH

Use the full path to PHP executable:
- Windows XAMPP: `C:\xampp\php\php.exe`
- Linux: `/usr/bin/php`
- Mac: `/usr/local/bin/php`

## Requirements

- PHP 7.0 or higher
- MySQL 5.7 or higher
- mysqli extension enabled
- Existing `students` table in database
- Existing `documents` column in students table

## Related Files

- `migrations/add_document_categories.sql` - SQL migration file
- `migrations/rollback_document_categories.sql` - SQL rollback file
- `migrations/install_document_categories.php` - PHP installation script
- `migrations/manual_test_document_columns.php` - Test script
- `.kiro/specs/document-upload-enhancement/requirements.md` - Feature requirements
- `.kiro/specs/document-upload-enhancement/design.md` - Feature design
- `.kiro/specs/document-upload-enhancement/tasks.md` - Implementation tasks

## Next Steps

After successfully installing this migration:

1. **Task 2**: Create file storage infrastructure (directories)
2. **Task 3**: Implement validation service
3. **Task 4**: Update registration form UI
4. **Task 5**: Update form submission handler
5. **Task 6**: Update admin interfaces

Refer to `.kiro/specs/document-upload-enhancement/tasks.md` for the complete implementation plan.

## Support

For issues or questions, refer to:
- `.kiro/specs/document-upload-enhancement/requirements.md`
- `.kiro/specs/document-upload-enhancement/design.md`
- `.kiro/specs/document-upload-enhancement/tasks.md`

## Migration History

- **Date**: 2026-02-23
- **Feature**: document-upload-enhancement
- **Requirements**: 3.1, 3.2, 3.3, 3.4, 3.5
- **Status**: ✓ Tested and verified on development database
- **Database**: nielit_bhubaneswar
- **Records Affected**: 0 (new columns, no data migration needed)
