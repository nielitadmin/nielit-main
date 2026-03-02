# Passport Photo & Signature Preview Fix

## Issue Reported
When registering a candidate, the passport photo, signature, and other documents were not showing any visual confirmation after being selected. Users couldn't see what they uploaded before submitting the form.

## Root Cause
The registration form (`student/register.php`) had file upload inputs but lacked image preview functionality. While the file preview code existed, it only showed:
- File name
- File size
- Generic icon

It did NOT show actual image thumbnails for passport photos and signatures.

## Solution Implemented

### 1. Enhanced JavaScript File Preview
**Location:** `student/register.php` (lines ~2302-2370)

**Changes:**
- Added FileReader API to read image files
- Created image preview for passport_photo and signature fields
- Shows actual thumbnail (200x200px max) with styling
- Maintains standard preview for other document types (PDFs, etc.)

**Key Features:**
```javascript
// Detects if field is passport_photo or signature
const isImageField = (fieldName === 'passport_photo' || fieldName === 'signature');
const isImageFile = file.type.startsWith('image/');

if (isImageField && isImageFile) {
    // Show actual image preview
    const reader = new FileReader();
    reader.onload = function(e) {
        // Display image thumbnail with styling
    };
    reader.readAsDataURL(file);
}
```

### 2. Enhanced CSS Styling
**Location:** `student/register.php` (lines ~614-670)

**Added Styles:**
```css
.file-preview-image-container {
    width: 100%;
    text-align: center;
    margin-bottom: 10px;
    padding: 10px;
    background: white;
    border-radius: 8px;
}

.file-preview-image-container img {
    max-width: 200px;
    max-height: 200px;
    border-radius: 8px;
    border: 2px solid #0d47a1;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    object-fit: contain;
}
```

### 3. Improved Helper Text
**Location:** `student/register.php` (lines ~1865-1890)

**Added:**
- File size limits in helper text
- Accepted formats clearly stated
- Green confirmation message: "Preview will appear after selecting file"
- Info icons for better UX

## Visual Changes

### Before
```
[Passport Photo *]
[Choose File] No file chosen
Recent passport size photo
```

### After
```
[Passport Photo *]
[Choose File] photo.jpg

ℹ️ Recent passport size photo (JPG/PNG, max 5MB)
✓ Preview will appear after selecting file

[Image Preview Box]
┌─────────────────┐
│                 │
│  [Photo Image]  │
│                 │
└─────────────────┘
✓ photo.jpg
  125.5 KB
  [Remove Button]
```

## Benefits

1. **Visual Confirmation** - Users can see exactly what they uploaded
2. **Error Prevention** - Can verify correct photo before submission
3. **Better UX** - Immediate feedback on file selection
4. **Professional Look** - Matches admin edit_student.php preview style
5. **Responsive** - Works on mobile and desktop

## Files Modified

1. `student/register.php`
   - Enhanced JavaScript file preview handler
   - Added CSS for image preview container
   - Improved helper text and labels

## Testing Checklist

- [ ] Upload passport photo - verify image preview appears
- [ ] Upload signature - verify image preview appears
- [ ] Upload PDF document - verify standard file preview (no image)
- [ ] Click remove button - verify file clears and preview disappears
- [ ] Submit form - verify files are uploaded correctly
- [ ] Check on mobile - verify preview is responsive
- [ ] Test with large images - verify 200x200px max constraint works
- [ ] Test with invalid file types - verify error handling

## Comparison with Admin Edit Student

The registration form now matches the preview functionality in `admin/edit_student.php`:

**Admin Edit Student (lines 935-958):**
```php
<?php if (!empty($student['passport_photo'])): ?>
    <div class="photo-preview">
        <img src="<?php echo APP_URL . '/' . $student['passport_photo']; ?>" 
             alt="Passport Photo">
        <a href="..." download>Download</a>
    </div>
<?php endif; ?>
```

**Registration Form (now):**
- Shows preview immediately after file selection
- Uses JavaScript FileReader for instant preview
- No page reload needed
- Same visual style and user experience

## Technical Notes

### FileReader API
- Reads file as Data URL (base64)
- Displays image without server upload
- Client-side only (no network request)
- Supported in all modern browsers

### Layout Handling
```javascript
if (isImageField && isImageFile) {
    preview.style.flexDirection = 'column';  // Stack vertically
    preview.style.alignItems = 'center';     // Center image
} else {
    preview.style.flexDirection = 'row';     // Horizontal layout
}
```

## Future Enhancements

1. Add image cropping tool
2. Add compression for large images
3. Show image dimensions
4. Add zoom on click
5. Support drag-and-drop upload

## Related Files

- `student/submit_registration.php` - Handles file upload on server
- `admin/edit_student.php` - Similar preview functionality
- `admin/view_student_documents.php` - Document viewing

## Status
✅ **COMPLETE** - Ready for testing and deployment
