# Training Centre Not Showing - Fix Guide

## Problem

Training centres are not appearing in:
1. The courses filter dropdown on `public/courses.php`
2. The manage centres page at `admin/manage_centres.php`
3. Courses assigned to these centres are not visible when filtered

## Root Cause

The centres exist in the database but are marked as **inactive** (`is_active = 0`). Both the courses page and manage centres page only show centres where `is_active = 1`.

## Quick Fix

### Option 1: Run PHP Script (Recommended)

1. Upload `migrations/check_and_fix_centres.php` to your server
2. Access it in your browser:
   ```
   https://yourdomain.com/migrations/check_and_fix_centres.php
   ```
3. The script will:
   - Show all centres in database
   - Check NIELIT BHUBANESWAR status
   - Activate it if inactive
   - Create it if missing
   - Show all courses using this centre

### Option 2: Run SQL Directly

Execute this SQL in phpMyAdmin or MySQL command line:

```sql
-- Check current status
SELECT id, name, code, city, state, is_active 
FROM centres 
WHERE name LIKE '%NIELIT%BHUBANESWAR%';

-- Activate the centre
UPDATE centres 
SET is_active = 1 
WHERE name LIKE '%NIELIT%BHUBANESWAR%';

-- Verify it's active
SELECT id, name, is_active 
FROM centres 
WHERE name LIKE '%NIELIT%BHUBANESWAR%';
```

### Option 3: Use Admin Interface

1. Log in to admin panel
2. Go to **Training Centres** (if you can see the centre)
3. Click the **Toggle** button to activate it
4. The status should change from "Inactive" to "Active"

## How to Prevent This

### When Adding New Centres

Make sure to check the "Active" checkbox when creating a new centre in the admin panel.

### When Editing Courses

If you select a centre that doesn't appear in the dropdown:
1. Go to **Manage Centres**
2. Find the centre
3. Make sure it's marked as **Active**

## Verification Steps

After applying the fix:

1. **Check Manage Centres Page**
   - Go to `admin/manage_centres.php`
   - NIELIT BHUBANESWAR should appear with green "Active" badge

2. **Check Courses Filter**
   - Go to `public/courses.php`
   - Open the "Filter by Training Centre" dropdown
   - NIELIT BHUBANESWAR should appear in the list

3. **Test Filtering**
   - Select "NIELIT BHUBANESWAR" from dropdown
   - Page should reload showing only courses from that centre
   - Courses should display with centre name and location

## Technical Details

### Database Schema

```sql
CREATE TABLE centres (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  code VARCHAR(10) NOT NULL UNIQUE,
  address TEXT,
  city VARCHAR(100),
  state VARCHAR(100),
  pincode VARCHAR(10),
  phone VARCHAR(20),
  email VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### SQL Queries Used

**In courses.php:**
```php
$sql_centres = "SELECT id, name FROM centres WHERE is_active = 1 ORDER BY name ASC";
```

**In manage_centres.php:**
```php
function getAllCentres($conn, $active_only = false) {
    $sql = "SELECT * FROM centres";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name ASC";
    return $conn->query($sql);
}
```

## Common Issues

### Issue 1: Centre Still Not Showing After Activation

**Solution:**
- Clear browser cache (Ctrl+F5)
- Check database directly to confirm `is_active = 1`
- Verify centre name matches exactly (case-sensitive in some databases)

### Issue 2: Multiple Centres with Similar Names

**Solution:**
```sql
-- Find all similar centres
SELECT id, name, is_active FROM centres 
WHERE name LIKE '%NIELIT%' OR name LIKE '%Bhubaneswar%';

-- Activate the correct one by ID
UPDATE centres SET is_active = 1 WHERE id = [correct_id];
```

### Issue 3: Courses Not Showing Even After Centre is Active

**Solution:**
1. Check if courses have correct `centre_id`:
   ```sql
   SELECT c.id, c.course_name, c.centre_id, cen.name as centre_name
   FROM courses c
   LEFT JOIN centres cen ON c.centre_id = cen.id
   WHERE c.course_name LIKE '%your_course%';
   ```

2. Update course centre_id if needed:
   ```sql
   UPDATE courses 
   SET centre_id = [correct_centre_id] 
   WHERE id = [course_id];
   ```

## Files Involved

- `public/courses.php` - Courses listing with filter
- `admin/manage_centres.php` - Centre management interface
- `admin/edit_course.php` - Course editing (centre selection)
- `migrations/check_and_fix_centres.php` - Diagnostic and fix script
- `migrations/fix_nielit_bhubaneswar_centre.sql` - SQL fix script

## Related Documentation

- `migrations/add_centres_module.sql` - Original centres table creation
- `docs/CURRENT_SYSTEM_STRUCTURE.md` - System overview

---

**Last Updated**: March 5, 2026
**Issue**: Training centres not showing in dropdown/list
**Status**: Fixed
**Solution**: Activate centres by setting `is_active = 1`
