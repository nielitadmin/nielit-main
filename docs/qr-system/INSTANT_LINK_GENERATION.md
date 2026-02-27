# Instant Link & QR Code Generation System

## Overview
The system now supports instant generation of registration links and QR codes without needing to save the course first.

## How It Works

### 🆕 For New Courses (Dashboard - Add Course)
1. Fill in Course Name and Course Code
2. Click **"Generate Link"** button
3. Link is generated instantly and shown in the field
4. When you click **"Add Course"**, the link is saved AND QR code is automatically generated

### ✏️ For Existing Courses (Edit Course Page)
1. Click **"Generate Link"** button
2. System immediately:
   - Generates the registration link
   - Saves it to the database
   - Creates the QR code
   - Updates the database with QR path
3. Page reloads to show the new QR code
4. When you click **"Update Course"**, all other changes are saved

## Technical Implementation

### Files Created/Modified

#### 1. `admin/generate_link_qr.php` (NEW)
- AJAX endpoint for instant link and QR generation
- Handles both new and existing courses
- Returns JSON response with link and QR code path

#### 2. `admin/edit_course.php` (UPDATED)
- Added AJAX call to `generate_link_qr.php`
- Shows loading spinner during generation
- Reloads page to display new QR code
- QR code appears immediately after generation

#### 3. `admin/dashboard.php` (UPDATED)
- Simple link generation for new courses
- QR code generated when course is saved
- No AJAX needed (course doesn't exist yet)

## User Experience

### Edit Course Flow:
```
1. Click "Generate Link" 
   ↓
2. [Loading spinner appears]
   ↓
3. Link saved to database
   ↓
4. QR code generated
   ↓
5. Page reloads
   ↓
6. QR code visible with download button
   ↓
7. Click "Update Course" to save other changes
```

### Add New Course Flow:
```
1. Fill in course details
   ↓
2. Click "Generate Link" (optional)
   ↓
3. Link appears in field
   ↓
4. Click "Add Course"
   ↓
5. Course saved + QR code generated automatically
   ↓
6. Redirected to dashboard
```

## Benefits

✅ **Instant Feedback** - See link and QR immediately
✅ **No Extra Clicks** - Generate once, save once
✅ **Database Updated** - Link and QR saved automatically
✅ **Error Handling** - Clear messages if something fails
✅ **Loading States** - Visual feedback during generation

## API Response Format

```json
{
  "success": true,
  "message": "Link and QR code generated successfully!",
  "apply_link": "http://localhost/public_html/student/register.php?course=...",
  "qr_code_path": "assets/qr_codes/course_123_PPI-2026.png",
  "qr_code_url": "http://localhost/public_html/assets/qr_codes/course_123_PPI-2026.png"
}
```

## Requirements

- Course Name (required)
- Course Code (required)
- Admin session (authentication)

## Error Messages

- "Course name is required"
- "Course code is required"
- "Unauthorized" (if not logged in)
- "Failed to update database"
- QR generation errors (if phpqrcode fails)

## Testing

### Test Edit Course:
1. Go to: `admin/edit_course.php?id=50`
2. Click "Generate Link"
3. Wait for page reload
4. Verify QR code appears
5. Click "Download QR Code" to test download

### Test Add Course:
1. Go to: `admin/dashboard.php`
2. Click "Add New Course"
3. Fill in required fields
4. Click "Generate Link"
5. Verify link appears
6. Click "Add Course"
7. Check dashboard for new course with QR

## Notes

- For **new courses**, QR is generated when course is saved (no course ID yet)
- For **existing courses**, QR is generated immediately via AJAX
- Page reload is necessary to display the newly generated QR image
- All changes are preserved during reload
