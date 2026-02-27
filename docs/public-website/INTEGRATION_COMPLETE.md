# 🌐 Public Website Integration - Registration Link Publishing

## Overview

This document explains how to integrate the `link_published` flag into the public website so that only published courses show registration links.

---

## 🎯 Goal

**Control which courses show registration links on the public website:**
- ✅ Published courses (`link_published = 1`) → Show "Apply Now" button
- ❌ Unpublished courses (`link_published = 0`) → Hide "Apply Now" button

---

## 📊 Database Structure

### Admin Panel Courses Table

The admin panel uses a `courses` table with these key fields:

```sql
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_name VARCHAR(255),
    course_code VARCHAR(20),
    course_type VARCHAR(50),
    training_center VARCHAR(255),
    duration VARCHAR(100),
    fees DECIMAL(10,2),
    description TEXT,
    registration_link TEXT,
    qr_code_path VARCHAR(255),
    qr_generated_at DATETIME,
    link_published TINYINT(1) DEFAULT 0,  -- NEW FIELD
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Public Website Courses Table

The public website (`public/courses.php`) uses a DIFFERENT courses table structure:

```sql
CREATE TABLE courses (
    id INT PRIMARY KEY,
    course_name VARCHAR(255),
    eligibility TEXT,
    duration VARCHAR(50),
    training_fees DECIMAL(10,2),
    course_type ENUM('Long Term','Short Term','Non-NSQF'),
    category ENUM('Long Term NSQF','Short Term NSQF','Short-Term Non-NSQF'),
    start_date DATE,
    end_date DATE,
    description_url VARCHAR(255),
    description_pdf VARCHAR(255),
    apply_link VARCHAR(255),  -- Registration link field
    course_coordinator VARCHAR(255)
);
```

---

## ⚠️ Important Discovery

**There are TWO separate courses tables:**

1. **Admin Panel Table** - Used by `admin/manage_courses.php`
   - Fields: `registration_link`, `link_published`, `qr_code_path`
   - Purpose: Course management with QR codes

2. **Public Website Table** - Used by `public/courses.php`
   - Fields: `apply_link`, `category`, `eligibility`
   - Purpose: Display courses on public website

---

## 🔧 Solution Options

### Option 1: Add `link_published` to Public Courses Table (RECOMMENDED)

**Steps:**

1. **Update Public Courses Table:**
```sql
ALTER TABLE courses 
ADD COLUMN link_published TINYINT(1) DEFAULT 1 AFTER apply_link;

-- Set existing courses to published by default
UPDATE courses SET link_published = 1 WHERE apply_link IS NOT NULL AND apply_link != '';
```

2. **Update Public Website Queries:**

In `public/courses.php`, modify all SQL queries to include the `link_published` check:

```php
// OLD QUERY
$sql_long_term = "SELECT * FROM courses WHERE category = 'Long Term NSQF'";

// NEW QUERY
$sql_long_term = "SELECT * FROM courses 
                  WHERE category = 'Long Term NSQF' 
                  AND (link_published = 1 OR link_published IS NULL)";
```

3. **Update Display Logic:**

```php
<!-- Only show Apply button if link is published -->
<?php if (!empty($row["apply_link"]) && $row["link_published"] == 1): ?>
    <a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" 
       target="_blank" 
       class="btn-primary-modern btn-modern">
        <i class="fas fa-paper-plane"></i> Apply Now
    </a>
<?php endif; ?>
```

### Option 2: Merge Both Tables (COMPLEX)

Merge the admin panel courses table with the public website courses table. This requires:
- Data migration
- Schema unification
- Updating all queries across the system

**Not recommended** due to complexity and risk of breaking existing functionality.

### Option 3: Sync Between Tables (AUTOMATED)

Create a sync mechanism that copies data from admin panel table to public table:

```php
// sync_courses.php
function syncCourseToPublic($admin_course_id) {
    global $conn;
    
    // Get course from admin table
    $stmt = $conn->prepare("SELECT * FROM admin_courses WHERE id = ?");
    $stmt->bind_param("i", $admin_course_id);
    $stmt->execute();
    $admin_course = $stmt->get_result()->fetch_assoc();
    
    // Update or insert into public courses table
    $stmt = $conn->prepare("
        INSERT INTO courses (course_name, apply_link, link_published, ...)
        VALUES (?, ?, ?, ...)
        ON DUPLICATE KEY UPDATE
        apply_link = VALUES(apply_link),
        link_published = VALUES(link_published)
    ");
    // ... execute with admin course data
}
```

---

## ✅ Recommended Implementation (Option 1)

### Step 1: Update Database

Run this SQL in phpMyAdmin or MySQL command line:

```sql
-- Add link_published column to public courses table
ALTER TABLE courses 
ADD COLUMN IF NOT EXISTS link_published TINYINT(1) DEFAULT 1 AFTER apply_link;

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_link_published ON courses(link_published);

-- Set existing courses with apply_link to published
UPDATE courses 
SET link_published = 1 
WHERE apply_link IS NOT NULL AND apply_link != '';

-- Set courses without apply_link to unpublished
UPDATE courses 
SET link_published = 0 
WHERE apply_link IS NULL OR apply_link = '';
```

### Step 2: Update public/courses.php

Replace the SQL queries at the top of the file:

```php
<?php
// Include the database connection
require_once __DIR__ . '/../config/config.php';

// Fetch courses for each category - ONLY PUBLISHED COURSES
$sql_long_term = "SELECT * FROM courses 
                  WHERE category = 'Long Term NSQF' 
                  AND link_published = 1";
                  
$sql_short_term = "SELECT * FROM courses 
                   WHERE category = 'Short Term NSQF' 
                   AND link_published = 1";
                   
$sql_non_nsqf = "SELECT * FROM courses 
                 WHERE category = 'Short-Term Non-NSQF' 
                 AND link_published = 1";
                 
$sql_internship = "SELECT * FROM courses 
                   WHERE category = 'Internship Program' 
                   AND link_published = 1";

// Execute the queries
$result_long_term = $conn->query($sql_long_term);
$result_short_term = $conn->query($sql_short_term);
$result_non_nsqf = $conn->query($sql_non_nsqf);
$result_internship = $conn->query($sql_internship);
?>
```

### Step 3: Update Display Logic (Optional Enhancement)

Add additional check in the display sections:

```php
<div class="course-card-footer">
    <?php if (!empty($row["description_url"])): ?>
        <a href="<?php echo htmlspecialchars($row["description_url"]); ?>" 
           target="_blank" 
           class="btn-outline-modern btn-modern">
            <i class="fas fa-info-circle"></i> View Details
        </a>
    <?php elseif (!empty($row["description_pdf"])): ?>
        <a href="<?php echo htmlspecialchars($row["description_pdf"]); ?>" 
           download 
           class="btn-outline-modern btn-modern">
            <i class="fas fa-file-pdf"></i> Download PDF
        </a>
    <?php endif; ?>
    
    <!-- Only show if link is published -->
    <?php if (!empty($row["apply_link"]) && $row["link_published"] == 1): ?>
        <a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" 
           target="_blank" 
           class="btn-primary-modern btn-modern">
            <i class="fas fa-paper-plane"></i> Apply Now
        </a>
    <?php endif; ?>
</div>
```

---

## 🧪 Testing Checklist

### Database Testing:

- [ ] `link_published` column added to courses table
- [ ] Index created on `link_published` column
- [ ] Existing courses with `apply_link` set to published (1)
- [ ] Existing courses without `apply_link` set to unpublished (0)

### Admin Panel Testing:

- [ ] Can add new course with publish toggle
- [ ] Can edit course and change publish status
- [ ] Publish toggle shows correct label (Published/Unpublished)
- [ ] QR code generates automatically when course saved with link

### Public Website Testing:

- [ ] Published courses show "Apply Now" button
- [ ] Unpublished courses hide "Apply Now" button
- [ ] All course categories filter correctly
- [ ] Registration links work when clicked
- [ ] No PHP errors in error logs

---

## 📋 SQL Verification Queries

### Check Current State:

```sql
-- See all courses with their publish status
SELECT id, course_name, 
       SUBSTRING(apply_link, 1, 50) as link_preview,
       link_published,
       category
FROM courses
ORDER BY category, course_name;

-- Count published vs unpublished
SELECT 
    link_published,
    COUNT(*) as count,
    CASE 
        WHEN link_published = 1 THEN 'Published'
        WHEN link_published = 0 THEN 'Unpublished'
        ELSE 'NULL'
    END as status
FROM courses
GROUP BY link_published;

-- Find courses with links but not published
SELECT id, course_name, apply_link
FROM courses
WHERE apply_link IS NOT NULL 
AND apply_link != ''
AND link_published = 0;
```

### Update Specific Course:

```sql
-- Publish a course
UPDATE courses SET link_published = 1 WHERE id = 5;

-- Unpublish a course
UPDATE courses SET link_published = 0 WHERE id = 5;

-- Publish all courses with links
UPDATE courses 
SET link_published = 1 
WHERE apply_link IS NOT NULL AND apply_link != '';
```

---

## 🔄 Workflow After Implementation

### Adding New Course (Admin Panel):

1. Admin fills course details
2. Admin clicks "Generate Link"
3. Admin toggles "Publish Status" ON
4. Admin saves course
5. QR code generated automatically
6. Course appears on public website with "Apply Now" button

### Unpublishing Course:

1. Admin edits course
2. Admin toggles "Publish Status" OFF
3. Admin saves course
4. "Apply Now" button disappears from public website
5. Course still visible but no registration link shown

### Re-publishing Course:

1. Admin edits course
2. Admin toggles "Publish Status" ON
3. Admin saves course
4. "Apply Now" button reappears on public website

---

## 🎨 Visual Indicators

### Admin Panel:

```
Published Course:
┌─────────────────────────────────┐
│ Web Development Bootcamp        │
│ [WDB25] [Bootcamp]             │
│ Link: http://...               │
│ Status: ✅ Published           │
│ [Edit] [Delete]                │
└─────────────────────────────────┘

Unpublished Course:
┌─────────────────────────────────┐
│ AI & Machine Learning           │
│ [AIML26] [Workshop]            │
│ Link: http://...               │
│ Status: ⚠️ Unpublished         │
│ [Edit] [Delete]                │
└─────────────────────────────────┘
```

### Public Website:

```
Published Course Card:
┌─────────────────────────────────┐
│ Web Development Bootcamp        │
│ Duration: 3 months              │
│ Fees: ₹15,000                   │
│                                 │
│ [View Details] [Apply Now] ✅   │
└─────────────────────────────────┘

Unpublished Course Card:
┌─────────────────────────────────┐
│ AI & Machine Learning           │
│ Duration: 2 months              │
│ Fees: ₹20,000                   │
│                                 │
│ [View Details]                  │
│ (No Apply button shown)         │
└─────────────────────────────────┘
```

---

## 🚨 Important Notes

1. **Two Separate Tables**: The admin panel and public website use different courses tables. This implementation adds `link_published` to the PUBLIC courses table.

2. **Manual Sync Required**: If you add courses through admin panel, you need to manually add them to the public courses table OR create a sync mechanism.

3. **Default Behavior**: New courses default to `link_published = 1` (published) to maintain backward compatibility.

4. **Null Handling**: Queries include `OR link_published IS NULL` to handle courses added before this feature.

5. **Index Performance**: The index on `link_published` improves query performance for large course lists.

---

## 📞 Support

If you encounter issues:

1. Check PHP error logs: `/var/log/apache2/error.log` or `C:\xampp\apache\logs\error.log`
2. Verify database column exists: `DESCRIBE courses;`
3. Test SQL queries in phpMyAdmin
4. Clear browser cache
5. Check file permissions on `public/courses.php`

---

**Status:** ✅ READY FOR IMPLEMENTATION
**Version:** 1.0.0
**Date:** February 11, 2026
**Feature:** Public Website Registration Link Publishing Control

