# Scanned Admission Order Upload Feature

## Overview
This feature allows administrators to upload scanned copies of signed admission orders for batches and lock them to prevent further modifications.

## Features

### 📁 **File Upload**
- Upload PDF files of scanned admission orders
- File validation (PDF only, max 10MB)
- Automatic file naming with batch ID and timestamp
- Replace existing files before locking

### 🔒 **Locking Mechanism**
- Lock uploaded documents to prevent modifications
- Only Master Admin can unlock locked documents
- Visual indicators for locked/unlocked status
- Audit trail with timestamps and user information

### 📥 **Download Functionality**
- Download uploaded scanned admission orders
- Secure file access through PHP handler
- Maintains original file format (PDF)

## Database Schema

### New Columns Added to `batches` Table:
```sql
-- File storage
scanned_admission_order VARCHAR(255) NULL -- Path to uploaded file
scanned_order_uploaded_at TIMESTAMP NULL -- Upload timestamp
scanned_order_uploaded_by INT(11) NULL -- Admin who uploaded

-- Locking mechanism
scanned_order_locked TINYINT(1) DEFAULT 0 -- Lock status (0=unlocked, 1=locked)
scanned_order_locked_at TIMESTAMP NULL -- Lock timestamp
scanned_order_locked_by INT(11) NULL -- Admin who locked

-- Foreign key constraints
FOREIGN KEY (scanned_order_uploaded_by) REFERENCES admin(id)
FOREIGN KEY (scanned_order_locked_by) REFERENCES admin(id)
```

## File Structure

### New Files Created:
```
migrations/
├── add_scanned_admission_order.sql          # Database migration
├── install_scanned_admission_order.php     # Migration installer

batch_module/admin/
├── upload_scanned_admission_order.php      # Upload handler
└── batch_details.php                       # Updated with upload UI

uploads/
└── scanned_admission_orders/               # Upload directory
    ├── .htaccess                          # Security protection
    └── .gitkeep                           # Git tracking
```

## Installation

### 1. Run Database Migration
```bash
# Navigate to project root
cd /path/to/nielit-main

# Run migration (if PHP CLI available)
php migrations/install_scanned_admission_order.php install

# OR execute SQL manually in phpMyAdmin/MySQL
# Import: migrations/add_scanned_admission_order.sql
```

### 2. Create Upload Directory
```bash
# Create directory with proper permissions
mkdir -p uploads/scanned_admission_orders
chmod 755 uploads/scanned_admission_orders

# Create security files
echo "Options -Indexes" > uploads/scanned_admission_orders/.htaccess
touch uploads/scanned_admission_orders/.gitkeep
```

### 3. Update .gitignore (Already Done)
```
# Scanned admission orders
/uploads/scanned_admission_orders/*
!/uploads/scanned_admission_orders/.gitkeep
```

## Usage Guide

### For Administrators:

#### 1. **Upload Scanned Admission Order**
1. Navigate to Batch Details page
2. Locate "Scanned Admission Order" section
3. Click "Upload Scanned Admission Order (PDF)"
4. Select PDF file (max 10MB)
5. File uploads automatically

#### 2. **Lock Document**
1. After uploading, click "Lock Document"
2. Confirm the action in the dialog
3. Document becomes read-only
4. Only download is available after locking

#### 3. **Download Document**
1. Click "Download" button
2. File downloads as PDF
3. Available for both locked and unlocked documents

#### 4. **Replace Document (Before Locking)**
1. Click "Replace File" button
2. Select new PDF file
3. Old file is automatically removed
4. New file replaces the previous one

### For Master Admins:

#### **Unlock Document**
1. Master Admins can see "Unlock (Master Admin)" button
2. Click to unlock previously locked documents
3. Allows modification/replacement after unlocking

## Security Features

### 🔐 **File Security**
- Files stored outside web root when possible
- .htaccess prevents direct access
- PHP handler validates permissions
- File type validation (PDF only)

### 👤 **Access Control**
- Only authenticated admins can upload
- Master Admin override for unlocking
- Audit trail for all actions
- Session-based authentication

### 📝 **Validation**
- File type validation (PDF only)
- File size limits (10MB max)
- Batch existence validation
- Lock status checking

## User Interface

### Visual Indicators:
- **🟢 Green Alert**: File uploaded successfully
- **🔴 Red Badge**: Document locked
- **🟡 Yellow Badge**: Document unlocked
- **ℹ️ Info Alert**: No file uploaded yet

### Action Buttons:
- **Upload**: Primary blue button for initial upload
- **Replace**: Warning orange button for file replacement
- **Lock**: Danger red button for locking
- **Download**: Primary blue button for download
- **Unlock**: Warning orange button (Master Admin only)

## Error Handling

### Common Errors:
- **File too large**: "File size must be less than 10MB"
- **Wrong file type**: "Only PDF files are allowed"
- **Already locked**: "Cannot upload: Scanned admission order is locked"
- **No file**: "No scanned admission order uploaded yet"
- **Permission denied**: "Only Master Admin can unlock scanned admission orders"

### Error Recovery:
- Clear file input on validation errors
- Show user-friendly error messages
- Maintain page state on errors
- Provide retry options

## API Endpoints

### POST `/batch_module/admin/upload_scanned_admission_order.php`

#### Actions:
1. **upload** - Upload new file
2. **lock** - Lock existing file
3. **unlock** - Unlock file (Master Admin only)
4. **download** - Download file

#### Parameters:
- `action`: Action to perform
- `batch_id`: Batch ID
- `scanned_file`: File upload (for upload action)

#### Response Format:
```json
{
    "success": true|false,
    "message": "Status message",
    "filename": "uploaded_filename.pdf" // for upload action
}
```

## Testing Checklist

### ✅ **Upload Testing**
- [ ] Upload valid PDF file
- [ ] Reject non-PDF files
- [ ] Reject files over 10MB
- [ ] Handle upload errors gracefully
- [ ] Replace existing files

### ✅ **Lock Testing**
- [ ] Lock uploaded document
- [ ] Prevent upload when locked
- [ ] Show locked status correctly
- [ ] Prevent lock when no file exists

### ✅ **Download Testing**
- [ ] Download uploaded files
- [ ] Handle missing files
- [ ] Correct file headers
- [ ] Security validation

### ✅ **Permission Testing**
- [ ] Regular admin cannot unlock
- [ ] Master admin can unlock
- [ ] Authentication required
- [ ] Session validation

## Deployment Notes

### Production Considerations:
1. **File Storage**: Consider moving uploads outside web root
2. **Backup**: Include uploaded files in backup strategy
3. **Monitoring**: Monitor upload directory size
4. **Cleanup**: Implement cleanup for orphaned files

### Performance:
- Files are served through PHP (not direct access)
- Consider CDN for large deployments
- Monitor disk space usage
- Implement file compression if needed

## Future Enhancements

### Possible Improvements:
1. **Version Control**: Keep history of replaced files
2. **Digital Signatures**: Add digital signature validation
3. **Bulk Operations**: Upload multiple files at once
4. **Email Notifications**: Notify on lock/unlock actions
5. **File Preview**: PDF preview in browser
6. **Compression**: Automatic PDF compression

## Support

### Troubleshooting:
1. Check file permissions on upload directory
2. Verify database schema is updated
3. Check PHP file upload limits
4. Validate .htaccess configuration

### Log Files:
- PHP error logs for upload issues
- Database logs for constraint violations
- Web server logs for access issues

---

## Summary

The Scanned Admission Order Upload Feature provides a complete solution for managing signed admission order documents with:

- **Secure file upload** with validation
- **Locking mechanism** to prevent unauthorized changes
- **Role-based access control** with Master Admin override
- **Audit trail** for compliance and tracking
- **User-friendly interface** with clear visual indicators
- **Comprehensive error handling** and validation

This feature enhances the batch management system by providing a secure, auditable way to handle important admission order documents while maintaining data integrity and access control.