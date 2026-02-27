# 🔗 Apply Link & QR Code System - Enhanced Features

## New Features Added ✅

### 1. **Generate Apply Link Button**
- One-click button to automatically create registration link
- No manual typing required
- Link format: `http://yoursite.com/student/register.php?course_id=123`

### 2. **Publish/Unpublish Toggle**
- Control whether registration link appears on public website
- Toggle switch with visual feedback
- "Published" = Link shows on website
- "Unpublished" = Link hidden from public

### 3. **Automatic QR Code Generation**
- QR code is generated automatically when course is saved with a registration link
- No need to click separate "Generate QR" button
- QR code updates automatically when link changes

---

## 📋 Database Changes

### New Column Added:
```sql
ALTER TABLE courses 
ADD COLUMN link_published TINYINT(1) DEFAULT 0 AFTER qr_generated_at;
```

**Field:** `link_published`
- Type: TINYINT(1)
- Values: 0 = Unpublished, 1 = Published
- Default: 0 (Unpublished)
- Purpose: Controls visibility on public website

---

## 🎨 User Interface Changes

### Add Course Modal

**Before:**
```
Apply Link: [text input field]
```

**After:**
```
Apply Link: [readonly field] [Generate Link button]
Publish Status: [toggle switch] Published/Unpublished
```

### Features:
1. **Apply Link Field** - Read-only, populated by "Generate Link" button
2. **Generate Link Button** - Green button with magic wand icon
3. **Publish Toggle** - Switch to control public visibility
4. **Status Label** - Shows "Published" (green) or "Unpublished" (gray)
5. **Preview Box** - Shows generated link before saving
6. **Info Alert** - Explains QR code will be auto-generated

---

## 🔧 How It Works

### Adding New Course:

1. **Fill Course Details**
   - Enter course name, code, type, etc.

2. **Generate Apply Link**
   - Click "Generate Link" button
   - Link is created automatically
   - Preview shows in info box

3. **Set Publish Status**
   - Toggle switch ON = Published (shows on website)
   - Toggle switch OFF = Unpublished (hidden from website)

4. **Save Course**
   - Click "Add Course" button
   - Course is saved to database
   - QR code is generated automatically
   - Success message confirms everything

### Editing Existing Course:

1. **Click Edit Button**
   - Modal opens with course details

2. **Update Apply Link (if needed)**
   - Click "Generate Link" to create new link
   - Or keep existing link

3. **Change Publish Status**
   - Toggle to publish/unpublish

4. **Save Changes**
   - Click "Update Course"
   - If link changed, QR code regenerates automatically
   - Success message confirms update

---

## 📱 Automatic QR Code Generation

### When QR Code is Generated:

**Automatically generated when:**
- ✅ New course is added with registration link
- ✅ Existing course is updated with new registration link
- ✅ Course is saved after clicking "Generate Link"

**Not generated when:**
- ❌ Course is saved without registration link
- ❌ Only publish status is changed (link stays same)

### QR Code Details:

- **Format:** PNG image
- **Location:** `assets/qr_codes/qr_[CODE]_[ID].png`
- **Size:** ~300x300 pixels
- **Content:** Registration link URL
- **Scannable:** Works with all phone cameras

---

## 🌐 Public Website Integration

### Showing Published Links:

Only courses with `link_published = 1` will show registration links on public website.

**Example Query for Public Pages:**
```php
// Get only published courses
$query = "SELECT * FROM courses 
          WHERE status = 'active' 
          AND link_published = 1 
          ORDER BY course_name";
```

**Display on Website:**
```php
<?php if ($course['link_published'] == 1 && !empty($course['registration_link'])): ?>
    <div class="registration-section">
        <h5>Register Now:</h5>
        <a href="<?php echo $course['registration_link']; ?>" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Apply Online
        </a>
        
        <?php if (!empty($course['qr_code_path'])): ?>
            <div class="qr-code">
                <p>Or scan QR code:</p>
                <img src="<?php echo $course['qr_code_path']; ?>" alt="QR Code" width="150">
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
```

---

## ✅ Setup Instructions

### Step 1: Update Database

Run the SQL update:
```bash
mysql -u root -p nielit_bhubaneswar < database_qr_system_update.sql
```

Or manually in phpMyAdmin:
```sql
ALTER TABLE courses 
ADD COLUMN IF NOT EXISTS link_published TINYINT(1) DEFAULT 0 AFTER qr_generated_at;

CREATE INDEX IF NOT EXISTS idx_link_published ON courses(link_published);
```

### Step 2: Verify Files

Ensure these files are updated:
- ✅ `admin/manage_courses.php` - Updated with new UI
- ✅ `database_qr_system_update.sql` - Includes new column
- ✅ `includes/qr_helper.php` - QR generation functions

### Step 3: Test the System

1. Login to admin panel
2. Go to Manage Courses
3. Click "Add New Course"
4. Fill in course details
5. Click "Generate Link" button
6. Toggle "Publish Status" ON
7. Click "Add Course"
8. Verify:
   - Course is saved
   - Registration link is created
   - QR code is generated automatically
   - Success message appears

---

## 🎯 Usage Examples

### Example 1: Create Published Course

```
1. Course Name: "Web Development Bootcamp"
2. Course Code: "WDB25"
3. Click "Generate Link"
   → Link: http://localhost/public_html/student/register.php?course_id=5
4. Toggle "Publish Status" ON
5. Click "Add Course"
   → Course saved
   → QR code generated: assets/qr_codes/qr_WDB25_5.png
   → Link visible on public website
```

### Example 2: Create Unpublished Course (Draft)

```
1. Course Name: "AI & Machine Learning"
2. Course Code: "AIML26"
3. Click "Generate Link"
4. Keep "Publish Status" OFF
5. Click "Add Course"
   → Course saved
   → QR code generated
   → Link NOT visible on public website (draft mode)
```

### Example 3: Update Course and Regenerate QR

```
1. Click Edit on existing course
2. Change course name or details
3. Click "Generate Link" (creates new link)
4. Click "Update Course"
   → Old QR code deleted
   → New QR code generated automatically
   → Link updated
```

---

## 🔍 Troubleshooting

### Issue: "Generate Link" button doesn't work

**Solution:**
1. Ensure course name is filled in
2. Check browser console for errors
3. Refresh page and try again

### Issue: QR code not generated automatically

**Solution:**
1. Verify registration link exists
2. Check `assets/qr_codes/` directory permissions
3. Check PHP error logs
4. Manually click "Generate QR" button in course table

### Issue: Published courses not showing on website

**Solution:**
1. Check `link_published` column value (should be 1)
2. Verify public page query includes `link_published = 1`
3. Clear website cache
4. Check if registration link exists

---

## 📊 Database Schema

### Complete Courses Table Structure:

```sql
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_name VARCHAR(255) NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    course_type VARCHAR(50),
    training_center VARCHAR(255),
    duration VARCHAR(100),
    fees DECIMAL(10,2),
    description TEXT,
    registration_link TEXT,
    qr_code_path VARCHAR(255),
    qr_generated_at DATETIME,
    link_published TINYINT(1) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## 🎨 Visual Guide

### Add Course Modal Layout:

```
┌─────────────────────────────────────────┐
│  ➕ Add New Course                  [X] │
├─────────────────────────────────────────┤
│                                         │
│  Course Name: [________________]        │
│  Course Code: [______]                  │
│  ...                                    │
│                                         │
│  ─────────────────────────────────────  │
│  🔗 Registration Link Settings          │
│                                         │
│  Apply Link:                            │
│  [readonly field] [🪄 Generate Link]    │
│                                         │
│  Publish Status:                        │
│  [Toggle Switch] Published/Unpublished  │
│                                         │
│  ℹ️ Preview: http://...                 │
│                                         │
│  ⚠️ Note: QR code will be generated     │
│     automatically when you save.        │
│                                         │
│  [Cancel]  [Add Course]                 │
└─────────────────────────────────────────┘
```

---

## 🚀 Benefits

### For Admins:
✅ Faster course creation (one-click link generation)
✅ Control over public visibility
✅ Automatic QR code generation
✅ No manual QR generation needed
✅ Draft mode for unpublished courses

### For Students:
✅ Easy registration via QR code
✅ Direct link access
✅ Mobile-friendly process
✅ No broken links

### For Marketing:
✅ Quick QR code generation
✅ Control when courses go live
✅ Professional registration process
✅ Trackable registration links

---

## 📝 Summary

**What Changed:**
1. Added "Generate Link" button in course modals
2. Added "Publish/Unpublish" toggle switch
3. QR codes now generate automatically on save
4. Database updated with `link_published` column
5. UI improved with better visual feedback

**What Stayed Same:**
- Course management functionality
- QR code quality and format
- Registration page
- Student experience

**What's New:**
- One-click link generation
- Publish control for public visibility
- Automatic QR code creation
- Better admin workflow

---

## ✅ Verification Checklist

Before going live:

- [ ] Database column `link_published` added
- [ ] "Generate Link" button works in Add modal
- [ ] "Generate Link" button works in Edit modal
- [ ] Publish toggle changes label text
- [ ] QR code generates automatically on save
- [ ] Published courses show on public website
- [ ] Unpublished courses hidden from public
- [ ] QR codes scan correctly
- [ ] Registration links work

---

**Status:** ✅ COMPLETE
**Version:** 2.0.0
**Date:** February 11, 2026
**Feature:** Enhanced Apply Link & Auto QR Generation
