# Letterhead-Style Word Document Generator

## Overview

The letterhead-style Word document generator creates professional admission orders with government-standard formatting, institutional branding, and bilingual header support.

## Features

✅ **Professional Letterhead Layout**
- NIELIT logo positioned on the left side of header
- Bilingual institutional header (Hindi and English)
- Government-standard formatting and typography

✅ **Complete Document Structure**
- Reference number and date
- Admission order title
- Batch and course details
- Complete student list with all required fields
- Category and gender summary tables
- Signature section with proper official designation
- Copy-to list with recipients

✅ **Extension Centre Support**
- Automatic customization for Bhubaneswar or Balasore centres
- Consistent location references throughout document

✅ **Data Integration**
- Retrieves batch details from database
- Fetches student information from appropriate tables
- Calculates category and gender statistics accurately
- Handles missing or optional data gracefully

## Usage

### From Batch Details Page

1. Navigate to **Batch Module > Manage Batches**
2. Click on any batch to view details
3. Click **"Generate Letterhead (Word)"** button
4. Document will download automatically as `.doc` file

### Direct URL Access

```
batch_module/admin/generate_admission_order_word_letterhead.php?batch_id=X
```

Where `X` is the batch ID.

## File Structure

```
batch_module/admin/
├── generate_admission_order_word_letterhead.php  # Main generator file
├── test_letterhead_generator.php                 # Test file
└── LETTERHEAD_GENERATOR_README.md               # This documentation
```

## Technical Details

### LogoAssetManager Class

Handles logo image loading and base64 encoding:
- Loads logo from `assets/images/bhubaneswar_logo.png`
- Converts to base64 for HTML embedding
- Graceful degradation if logo file is missing
- Supports PNG, JPG, JPEG, GIF formats

### CSS Styling

Professional government-standard styling:
- Flexbox-based letterhead layout
- Consistent typography and spacing
- Structured table formatting
- Print-optimized margins and sizing

### Data Retrieval

Comprehensive database integration:
- Batch details with course and scheme information
- Student enrollment data from multiple table structures
- Category and gender statistics calculation
- Extension centre customization logic

## Requirements

- PHP 7.4+
- MySQL database with batch and student tables
- Admin authentication session
- Logo file: `assets/images/bhubaneswar_logo.png`

## Error Handling

- **No students found**: Displays descriptive error message
- **Missing logo**: Document generates without logo
- **Database errors**: Graceful error handling with logging
- **Invalid parameters**: Parameter validation and error messages

## Output Format

- **File Type**: Microsoft Word (.doc)
- **Compatibility**: Word 2016, 2019, 365, LibreOffice Writer
- **Layout**: Professional letterhead with government standards
- **Content**: Complete admission order with all required sections

## Integration

The letterhead generator integrates seamlessly with:
- Existing batch module UI
- Current authentication system
- Database schema (no modifications required)
- File download patterns

## Testing

Run the test file to verify functionality:
```
batch_module/admin/test_letterhead_generator.php
```

## Support

For issues or questions:
1. Check error logs for detailed error messages
2. Verify batch ID exists and has enrolled students
3. Ensure logo file is present and readable
4. Confirm admin authentication is active